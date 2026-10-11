<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\WaybillPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaybillController extends Controller
{
    private const PACKAGE_FIELDS = [
        'client_id', 'waybill_number', 'order_number', 'package_id', 'package_location', 'package_status',
        'package_description', 'package_sku', 'package_size', 'package_quantity', 'package_length', 'package_width',
        'package_height', 'package_weight', 'payment_type', 'total_price', 'declared_value', 'package_remarks',
        'package_reference_1', 'package_reference_2', 'package_reference_3', 'package_reference_4', 'package_reference_5',
        'buyer_name', 'buyer_mobile', 'buyer_email', 'buyer_full_address', 'buyer_province', 'buyer_city',
        'buyer_barangay', 'buyer_long', 'buyer_lat', 'buyer_building_type', 'seller_name', 'seller_full_address',
        'seller_province', 'seller_city', 'seller_barangay', 'seller_contact_number', 'seller_email',
        'seller_building_type', 'pickup_date',
        'handover_date', 'remittance_status', 'delivery_min_time', 'delivery_max_time', 'is_priority',
    ];

    private const ITEM_FIELDS = [
        'item_reference', 'item_description', 'item_sku', 'item_length', 'item_width', 'item_height',
        'item_weight', 'item_remarks',
    ];

    private const SELLER_FIELDS = [
        'seller_name', 'seller_full_address', 'seller_province', 'seller_city', 'seller_barangay',
        'seller_contact_number', 'seller_email', 'seller_building_type',
    ];

    private const TEMPLATE_COLUMNS = [
        'waybill_number', 'order_number', 'package_id',
        'package_description', 'package_sku', 'package_size', 'package_quantity', 'package_length', 'package_width',
        'package_height', 'package_weight', 'payment_type', 'total_price', 'declared_value', 'package_remarks',
        'package_reference_1', 'package_reference_2', 'package_reference_3', 'package_reference_4', 'package_reference_5',
        'buyer_name', 'buyer_mobile', 'buyer_email', 'buyer_full_address', 'buyer_province', 'buyer_city',
        'buyer_barangay', 'buyer_building_type',
        'item_reference', 'item_description', 'item_sku', 'item_length', 'item_width', 'item_height', 'item_weight',
        'item_remarks', 'handover_date', 'remittance_status', 'delivery_min_time', 'delivery_max_time', 'is_priority',
        'is_deleted',
    ];

    private const REQUIRED_IMPORT_FIELDS = [
        'order_number', 'package_id', 'package_description', 'package_size', 'package_quantity',
        'package_length', 'package_width', 'package_height', 'package_weight', 'payment_type', 'total_price',
        'declared_value', 'buyer_name', 'buyer_mobile', 'buyer_full_address', 'buyer_province', 'buyer_city',
        'buyer_barangay', 'item_reference', 'item_description', 'item_sku', 'item_length', 'item_width',
        'item_height', 'item_weight',
    ];

    public function index(Request $request): View
    {
        $rangeParts = preg_split('/\s+to\s+/', trim((string) $request->input('date_range')), 2);
        $request->merge([
            'date_from' => $rangeParts[0] ?? null,
            'date_to' => $rangeParts[1] ?? ($rangeParts[0] ?? null),
        ]);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'creator_ids' => ['nullable', 'array'],
            'creator_ids.*' => ['integer', Rule::exists('users', 'id')],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', Rule::exists('clients', 'id')],
            'package_statuses' => ['nullable', 'array'],
            'package_statuses.*' => ['string', 'max:100'],
            'package_location' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'string', 'max:100'],
            'priorities' => ['nullable', 'array'],
            'priorities.*' => [Rule::in(['yes', 'no'])],
            'date_reference' => ['nullable', Rule::in(['created_at', 'updated_at', 'handover_date'])],
            'date_range' => ['nullable', 'string', 'max:30', 'regex:/^\d{4}-\d{2}-\d{2}(\s+to\s+\d{4}-\d{2}-\d{2})?$/'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tracking_numbers' => ['nullable', 'string', 'max:5000'],
            'package_ids' => ['nullable', 'string', 'max:5000'],
            'references' => ['nullable', 'string', 'max:5000'],
            'order_numbers' => ['nullable', 'string', 'max:5000'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);
        $filters = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $filters);
        $request->query->remove('date_from');
        $request->query->remove('date_to');
        $hasFilters = collect(['search', 'creator_ids', 'client_ids', 'package_statuses', 'package_location', 'payment_type', 'priorities', 'date_range', 'tracking_numbers', 'package_ids', 'references', 'order_numbers'])
            ->contains(fn ($key) => ($filters[$key] ?? null) !== null && ($filters[$key] ?? '') !== '');
        $dateReference = $filters['date_reference'] ?? 'created_at';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $clients = Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_code', 'client_name']);
        $creators = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']);
        $splitValues = fn ($value) => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $value))));

        if (!$hasFilters) {
            $packages = new LengthAwarePaginator([], 0, $perPage, 1, ['path' => $request->url(), 'query' => $request->query()]);
        } else {
            $packages = WaybillPackage::with(['client', 'seller', 'items'])
                ->whereNull('is_deleted')
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder
                    ->where('waybill_number', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('package_id', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")))
                ->when($filters['creator_ids'] ?? null, fn ($query, $creatorIds) => $query->whereIn('created_by', $creatorIds))
                ->when($filters['client_ids'] ?? null, fn ($query, $clientIds) => $query->whereIn('client_id', $clientIds))
                ->when($filters['package_statuses'] ?? null, fn ($query, $statuses) => $query->whereIn('package_status', $statuses))
                ->when($filters['package_location'] ?? null, fn ($query, $location) => $query->where('package_location', 'like', "%{$location}%"))
                ->when($filters['payment_type'] ?? null, fn ($query, $paymentType) => $query->where('payment_type', 'like', "%{$paymentType}%"))
                ->when($filters['priorities'] ?? null, function ($query, $priorities) {
                    $query->whereIn('is_priority', collect($priorities)->map(fn ($priority) => $priority === 'yes')->all());
                })
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate($dateReference, '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate($dateReference, '<=', $date))
                ->when($filters['tracking_numbers'] ?? null, fn ($query, $value) => $query->whereIn('waybill_number', $splitValues($value)))
                ->when($filters['package_ids'] ?? null, fn ($query, $value) => $query->whereIn('package_id', $splitValues($value)))
                ->when($filters['order_numbers'] ?? null, fn ($query, $value) => $query->whereIn('order_number', $splitValues($value)))
                ->when($filters['references'] ?? null, function ($query, $value) use ($splitValues) {
                    $references = $splitValues($value);
                    $query->where(function ($builder) use ($references) {
                        foreach ($references as $reference) {
                            $builder->orWhere(function ($referenceQuery) use ($reference) {
                                foreach (range(1, 5) as $number) $referenceQuery->orWhere("package_reference_{$number}", $reference);
                            });
                        }
                    });
                })
                ->latest()->paginate($perPage)->withQueryString();
        }

        return view('waybills.index', compact('packages', 'filters', 'hasFilters', 'clients', 'creators', 'dateReference'));
    }

    public function show(WaybillPackage $package): View
    {
        abort_if($package->is_deleted, 404);
        $package->load([
            'client',
            'seller',
            'items' => fn ($query) => $query->whereNull('is_deleted')->orderBy('id'),
            'statusLogs' => fn ($query) => $query
                ->with('updatedBy')
                ->whereNull('is_deleted')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return view('waybills.show', compact('package'));
    }

    public function create(): View
    {
        $clients = Client::where('is_active', true)->orderBy('client_name')->get();
        $clientOptions = $clients->map(fn (Client $client) => $client->only(['id', 'client_code', 'prefix', 'last_value']))->values();
        $sellerOptions = $clients->map(fn (Client $client) => $client->only([
            'id', 'client_name', 'full_address', 'province', 'city', 'barangay',
            'contact_number', 'email', 'building_type',
        ]))->values();

        return view('waybills.create', compact('clients', 'clientOptions', 'sellerOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $items = $validated['items'];
        $sellerData = array_intersect_key($validated, array_flip(self::SELLER_FIELDS));
        $validated = array_diff_key($validated, array_flip([...self::SELLER_FIELDS, 'items']));
        $userId = $request->user()->id;

        DB::transaction(function () use ($validated, $items, $sellerData, $userId) {
            $seller = $this->resolveSeller($sellerData);
            $waybillNumber = $this->nextWaybillNumber((int) $validated['client_id']);
            $package = WaybillPackage::create([...$validated, 'waybill_number' => $waybillNumber, 'package_status' => 'for_pickup', 'seller_id' => $seller->id, 'created_by' => $userId, 'updated_by' => $userId]);
            $this->createInitialStatusLog($package, $userId);
            $firstItem = null;
            foreach ($items as $itemData) {
                $item = $package->items()->create([...$itemData, 'created_by' => $userId, 'updated_by' => $userId]);
                $firstItem ??= $item;
            }
            $package->update(['item_id' => $firstItem?->id]);
        });

        return redirect()->route('waybills.index')->with('success', 'Waybill created successfully.');
    }

    public function upload(): View
    {
        return view('waybills.upload', $this->uploadViewData((int) old('upload_client_id')));
    }

    private function uploadViewData(?int $selectedClientId = null): array
    {
        $uploadClients = Client::where('is_active', true)->orderBy('client_name')->get()
            ->map(fn (Client $client) => $client->only(['id', 'client_code', 'client_name', 'prefix', 'last_value']))->values();
        $selectedUploadClient = $uploadClients->firstWhere('id', $selectedClientId);
        $selectedUploadClientLabel = $selectedUploadClient
            ? $selectedUploadClient['client_code'].' - '.$selectedUploadClient['client_name']
            : '';

        return compact('uploadClients', 'selectedUploadClient', 'selectedUploadClientLabel') + [
            'templateColumns' => self::TEMPLATE_COLUMNS,
            'requiredTemplateColumns' => self::REQUIRED_IMPORT_FIELDS,
            'previewPackages' => collect(),
            'previewToken' => null,
        ];
    }

    public function template()
    {
        $headers = self::REQUIRED_IMPORT_FIELDS;
        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            fputcsv($handle, array_fill(0, count($headers), ''));
            fclose($handle);
        }, 'waybill-upload-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function customTemplate(Request $request)
    {
        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', Rule::in(self::TEMPLATE_COLUMNS)],
        ]);
        $fields = array_values(array_unique($validated['fields']));

        return response()->streamDownload(function () use ($fields) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $fields);
            fputcsv($handle, array_fill(0, count($fields), ''));
            fclose($handle);
        }, 'custom-waybill-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): View|RedirectResponse
    {
        $validatedUpload = $request->validate([
            'upload_client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);
        $clientId = (int) $validatedUpload['upload_client_id'];
        $prepared = $this->prepareImport($request->file('file')->getRealPath(), $clientId);
        if ($prepared['error']) return back()->withInput()->with('error', $prepared['error']);

        foreach ((array) session('waybill_upload_previews', []) as $preview) {
            if (isset($preview['path'])) Storage::disk('local')->delete($preview['path']);
        }
        session()->forget('waybill_upload_previews');

        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs('waybill-previews', "{$token}.csv", 'local');
        session()->put("waybill_upload_previews.{$token}", ['path' => $path, 'client_id' => $clientId]);

        $client = Client::findOrFail($clientId);
        $generatedOffset = 0;
        $previewPackages = collect($prepared['grouped'])->map(function ($rows) use ($client, &$generatedOffset) {
            $providedWaybill = collect($rows)->pluck('waybill_number')->first(fn ($number) => filled($number));
            if (!$providedWaybill) {
                $generatedOffset++;
                $providedWaybill = ($client->prefix ?: $client->client_code.'-')
                    .str_pad((string) ($client->last_value + $generatedOffset), 8, '0', STR_PAD_LEFT);
            }

            return [
                'waybill_number' => $providedWaybill,
                'waybill_source' => collect($rows)->pluck('waybill_number')->filter()->isNotEmpty() ? 'Provided' : 'Generated',
                'package_id' => $rows[0]['package_id'],
                'order_number' => $rows[0]['order_number'],
                'buyer_name' => $rows[0]['buyer_name'],
                'buyer_city' => $rows[0]['buyer_city'],
                'item_count' => count($rows),
                'total_price' => $rows[0]['total_price'],
            ];
        })->values();

        $viewData = $this->uploadViewData($clientId);
        $viewData['previewPackages'] = $previewPackages;
        $viewData['previewToken'] = $token;

        return view('waybills.upload', $viewData);
    }

    public function proceedImport(Request $request): RedirectResponse|JsonResponse|StreamedResponse
    {
        $validated = $request->validate(['preview_token' => ['required', 'uuid']]);
        $token = $validated['preview_token'];
        $preview = session("waybill_upload_previews.{$token}");
        if (!$preview || !Storage::disk('local')->exists($preview['path'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The validated upload has expired. Please validate the CSV again.'], 422);
            }
            return redirect()->route('waybills.upload')->with('error', 'The validated upload has expired. Please validate the CSV again.');
        }

        $clientId = (int) $preview['client_id'];
        $prepared = $this->prepareImport(Storage::disk('local')->path($preview['path']), $clientId);
        if ($prepared['error']) {
            Storage::disk('local')->delete($preview['path']);
            session()->forget("waybill_upload_previews.{$token}");
            if ($request->expectsJson()) return response()->json(['message' => $prepared['error']], 422);
            return redirect()->route('waybills.upload')->with('error', $prepared['error']);
        }

        $exportRows = $this->createImportedPackages($prepared['grouped'], $clientId, $request->user()->id);
        Storage::disk('local')->delete($preview['path']);
        session()->forget("waybill_upload_previews.{$token}");

        $headers = array_values(array_unique([
            'waybill_number',
            ...array_values(array_intersect($prepared['headers'], [...self::PACKAGE_FIELDS, ...self::ITEM_FIELDS])),
        ]));

        return response()->streamDownload(function () use ($headers, $exportRows) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            foreach ($exportRows as $row) {
                fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
            }
            fclose($handle);
        }, 'uploaded-waybills-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
            'X-Upload-Message' => count($prepared['grouped']).' waybill(s) uploaded successfully.',
        ]);
    }

    private function prepareImport(string $path, int $clientId): array
    {
        $csv = $this->readCsv($path);
        $missingHeaders = array_values(array_diff(self::REQUIRED_IMPORT_FIELDS, $csv['headers']));
        if ($missingHeaders) return ['error' => 'Missing required CSV columns: '.implode(', ', $missingHeaders), 'grouped' => []];
        $rows = $csv['rows'];
        if (!$rows) return ['error' => 'The uploaded file does not contain any waybill rows.', 'grouped' => []];

        $errors = [];
        $grouped = [];
        foreach ($rows as $index => $row) {
            $row['client_id'] = $clientId;
            $validator = Validator::make($row, $this->importRules());
            if ($validator->fails()) {
                $errors[] = 'Row '.($index + 2).': '.implode(' ', $validator->errors()->all());
                continue;
            }
            $validatedRow = $validator->validated();
            $grouped[$validatedRow['package_id']][] = $validatedRow;
        }
        if ($errors) return ['error' => 'Validation failed: '.implode(' | ', array_slice($errors, 0, 10)), 'grouped' => []];

        foreach ($grouped as $packageId => $groupRows) {
            $waybillNumbers = collect($groupRows)
                ->pluck('waybill_number')
                ->filter(fn ($number) => filled($number))
                ->unique()
                ->values();
            if ($waybillNumbers->count() > 1) {
                $errors[] = "Package ID {$packageId} contains multiple waybill numbers.";
            }
        }
        if ($errors) return ['error' => 'Validation failed: '.implode(' | ', array_slice($errors, 0, 10)), 'grouped' => []];

        $providedWaybills = collect($grouped)->map(fn ($groupRows) => collect($groupRows)
            ->pluck('waybill_number')->first(fn ($number) => filled($number)))->filter()->values();
        $repeatedWaybills = $providedWaybills->countBy()->filter(fn ($count) => $count > 1)->keys()->all();
        if ($repeatedWaybills) return ['error' => 'Waybill numbers must belong to only one package ID: '.implode(', ', $repeatedWaybills), 'grouped' => []];
        $duplicates = WaybillPackage::whereIn('waybill_number', $providedWaybills)->pluck('waybill_number')->all();
        if ($duplicates) return ['error' => 'These waybills already exist: '.implode(', ', $duplicates), 'grouped' => []];

        return ['error' => null, 'grouped' => $grouped, 'headers' => $csv['headers']];
    }

    private function createImportedPackages(array $grouped, int $clientId, int $userId): array
    {
        $exportRows = [];
        DB::transaction(function () use ($grouped, $userId, $clientId, &$exportRows) {
            foreach ($grouped as $rows) {
                $packageData = array_intersect_key($rows[0], array_flip(self::PACKAGE_FIELDS));
                $packageData = array_diff_key($packageData, array_flip(self::SELLER_FIELDS));
                $packageData['client_id'] = $clientId;
                $providedWaybill = collect($rows)->pluck('waybill_number')->first(fn ($number) => filled($number));
                $packageData['waybill_number'] = $providedWaybill ?: $this->nextWaybillNumber($clientId);
                $packageData['package_status'] = $packageData['package_status'] ?: 'for_pickup';
                $packageData['is_priority'] = $packageData['is_priority'] ?? false;
                $package = WaybillPackage::create([...$packageData, 'seller_id' => $clientId, 'created_by' => $userId, 'updated_by' => $userId]);
                $this->createInitialStatusLog($package, $userId);
                $firstItem = null;
                foreach ($rows as $row) {
                    $itemData = array_intersect_key($row, array_flip(self::ITEM_FIELDS));
                    $item = $package->items()->create([...$itemData, 'created_by' => $userId, 'updated_by' => $userId]);
                    $firstItem ??= $item;
                    $exportRows[] = [...$row, 'waybill_number' => $package->waybill_number];
                }
                $package->update(['item_id' => $firstItem?->id]);
            }
        });

        return $exportRows;
    }

    private function createInitialStatusLog(WaybillPackage $package, int $userId): void
    {
        $package->statusLogs()->create([
            'status' => $package->package_status,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function rules(): array
    {
        return [...$this->packageRules(),
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_reference' => ['required', 'string', 'max:255'],
            'items.*.item_description' => ['required', 'string', 'max:5000'],
            'items.*.item_sku' => ['required', 'string', 'max:100'],
            'items.*.item_length' => ['required', 'numeric', 'min:0'],
            'items.*.item_width' => ['required', 'numeric', 'min:0'],
            'items.*.item_height' => ['required', 'numeric', 'min:0'],
            'items.*.item_weight' => ['required', 'numeric', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function packageRules(bool $forImport = false): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            ...($forImport ? ['waybill_number' => ['nullable', 'string', 'max:100', Rule::unique('packages', 'waybill_number')]] : []),
            'order_number' => ['required', 'string', 'max:100'],
            'package_id' => ['required', 'string', 'max:100', Rule::unique('packages', 'package_id')],
            ...($forImport ? [
                'package_location' => ['nullable', 'string', 'max:255'],
                'package_status' => ['nullable', 'string', 'max:100'],
            ] : []),
            'package_description' => ['required', 'string', 'max:5000'], 'package_sku' => ['nullable', 'string', 'max:100'],
            'package_size' => ['required', 'string', 'max:100'], 'package_quantity' => ['required', 'integer', 'min:1'],
            'package_length' => ['required', 'numeric', 'min:0'], 'package_width' => ['required', 'numeric', 'min:0'],
            'package_height' => ['required', 'numeric', 'min:0'], 'package_weight' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'string', 'max:100'], 'total_price' => ['required', 'numeric', 'min:0'],
            'declared_value' => ['required', 'numeric', 'min:0'], 'package_remarks' => ['nullable', 'string', 'max:5000'],
            'package_reference_1' => ['nullable', 'string', 'max:255'], 'package_reference_2' => ['nullable', 'string', 'max:255'],
            'package_reference_3' => ['nullable', 'string', 'max:255'], 'package_reference_4' => ['nullable', 'string', 'max:255'],
            'package_reference_5' => ['nullable', 'string', 'max:255'], 'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_mobile' => ['required', 'string', 'max:50'], 'buyer_email' => ['nullable', 'email', 'max:255'],
            'buyer_full_address' => ['required', 'string', 'max:5000'], 'buyer_province' => ['required', 'string', 'max:100'],
            'buyer_city' => ['required', 'string', 'max:100'], 'buyer_barangay' => ['required', 'string', 'max:100'],
            'buyer_long' => ['nullable', 'numeric', 'between:-180,180'], 'buyer_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'buyer_building_type' => ['nullable', 'string', 'max:100'],
            'seller_name' => [$forImport ? 'nullable' : 'required', 'string', 'max:150'],
            'seller_full_address' => [$forImport ? 'nullable' : 'required', 'string', 'max:5000'],
            'seller_province' => [$forImport ? 'nullable' : 'required', 'string', 'max:100'],
            'seller_city' => [$forImport ? 'nullable' : 'required', 'string', 'max:100'],
            'seller_barangay' => [$forImport ? 'nullable' : 'required', 'string', 'max:100'],
            'seller_contact_number' => ['nullable', 'string', 'max:50'],
            'seller_email' => ['nullable', 'email', 'max:255'], 'seller_building_type' => ['nullable', 'string', 'max:100'],
            ...($forImport ? [
                'pickup_date' => ['nullable', 'date'], 'handover_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
                'remittance_status' => ['nullable', 'string', 'max:100'],
            ] : []),
            'delivery_min_time' => ['nullable', 'date'],
            'delivery_max_time' => ['nullable', 'date', 'after_or_equal:delivery_min_time'],
            'is_priority' => [$forImport ? 'nullable' : 'required', 'boolean'],
        ];
    }

    private function importRules(): array
    {
        $rules = $this->packageRules(true);
        foreach (self::ITEM_FIELDS as $field) $rules[$field] = str_ends_with($field, 'description') ? ['required', 'string', 'max:5000'] : (in_array($field, ['item_length', 'item_width', 'item_height', 'item_weight']) ? ['nullable', 'numeric', 'min:0'] : ['nullable', 'string', 'max:5000']);
        foreach (self::REQUIRED_IMPORT_FIELDS as $field) {
            $rules[$field] = [
                'required',
                ...array_values(array_filter($rules[$field], fn ($rule) => !in_array($rule, ['required', 'nullable'], true))),
            ];
        }
        return $rules;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = array_map(fn ($header) => trim((string) $header), fgetcsv($handle) ?: []);
        if ($headers) $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $expected = [...self::PACKAGE_FIELDS, ...self::ITEM_FIELDS];
        if (!$headers || !array_intersect($headers, $expected)) {
            fclose($handle);
            return ['headers' => $headers, 'rows' => []];
        }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (!array_filter($values, fn ($value) => trim((string) $value) !== '')) continue;
            $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
            $row = array_intersect_key(array_map(fn ($value) => $value === '' ? null : $value, array_combine($headers, $values)), array_flip($expected));
            $rows[] = array_replace(array_fill_keys($expected, null), $row);
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    private function resolveSeller(array $data): Client
    {
        $name = trim((string) $data['seller_name']);
        $existing = Client::whereRaw('LOWER(client_name) = ?', [mb_strtolower($name)])->first();
        if ($existing) return $existing;

        do {
            $base = Str::upper(Str::slug($name, '')) ?: 'SELLER';
            $clientCode = 'SEL-'.Str::limit($base, 18, '').'-'.Str::upper(Str::random(6));
        } while (Client::where('client_code', $clientCode)->exists());

        return Client::create([
            'client_code' => $clientCode,
            'client_name' => $name,
            'full_address' => trim((string) $data['seller_full_address']),
            'province' => trim((string) $data['seller_province']),
            'city' => trim((string) $data['seller_city']),
            'barangay' => trim((string) $data['seller_barangay']),
            'contact_number' => trim((string) ($data['seller_contact_number'] ?? '')),
            'email' => $data['seller_email'] ?? null,
            'building_type' => $data['seller_building_type'] ?? null,
            'focal_person' => $name,
            'prefix' => $clientCode.'-',
            'is_active' => true,
        ]);
    }

    private function nextWaybillNumber(int $clientId): string
    {
        $client = Client::whereKey($clientId)->lockForUpdate()->firstOrFail();
        $nextValue = $client->last_value + 1;
        $client->update(['last_value' => $nextValue]);

        return ($client->prefix ?: $client->client_code.'-').str_pad((string) $nextValue, 8, '0', STR_PAD_LEFT);
    }
}
