<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\WaybillPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

    public function index(Request $request): View
    {
        $rangeParts = preg_split('/\s+to\s+/', trim((string) $request->input('date_range')), 2);
        $request->merge([
            'date_from' => $rangeParts[0] ?? null,
            'date_to' => $rangeParts[1] ?? ($rangeParts[0] ?? null),
        ]);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'creator_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'package_status' => ['nullable', 'string', 'max:100'],
            'package_location' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::in(['yes', 'no'])],
            'date_reference' => ['nullable', Rule::in(['created_at', 'updated_at', 'handover_date'])],
            'date_range' => ['nullable', 'string', 'max:30', 'regex:/^\d{4}-\d{2}-\d{2}(\s+to\s+\d{4}-\d{2}-\d{2})?$/'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tracking_numbers' => ['nullable', 'string', 'max:5000'],
            'package_ids' => ['nullable', 'string', 'max:5000'],
            'references' => ['nullable', 'string', 'max:5000'],
            'order_numbers' => ['nullable', 'string', 'max:5000'],
        ]);
        $filters = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $filters);
        $request->query->remove('date_from');
        $request->query->remove('date_to');
        $hasFilters = collect(['search', 'creator_id', 'client_id', 'package_status', 'package_location', 'payment_type', 'priority', 'date_range', 'tracking_numbers', 'package_ids', 'references', 'order_numbers'])
            ->contains(fn ($key) => ($filters[$key] ?? null) !== null && ($filters[$key] ?? '') !== '');
        $dateReference = $filters['date_reference'] ?? 'created_at';
        $clients = Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_code', 'client_name']);
        $creators = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']);
        $splitValues = fn ($value) => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $value))));

        if (!$hasFilters) {
            $packages = new LengthAwarePaginator([], 0, 15, 1, ['path' => $request->url(), 'query' => $request->query()]);
        } else {
            $packages = WaybillPackage::with(['client', 'seller', 'items'])
                ->where('is_deleted', false)
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder
                    ->where('waybill_number', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('package_id', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")))
                ->when($filters['creator_id'] ?? null, fn ($query, $creatorId) => $query->where('created_by', $creatorId))
                ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
                ->when($filters['package_status'] ?? null, fn ($query, $status) => $query->where('package_status', $status))
                ->when($filters['package_location'] ?? null, fn ($query, $location) => $query->where('package_location', 'like', "%{$location}%"))
                ->when($filters['payment_type'] ?? null, fn ($query, $paymentType) => $query->where('payment_type', 'like', "%{$paymentType}%"))
                ->when(($filters['priority'] ?? null) === 'yes', fn ($query) => $query->where('is_priority', true))
                ->when(($filters['priority'] ?? null) === 'no', fn ($query) => $query->where('is_priority', false))
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
                ->latest()->paginate(15)->withQueryString();
        }

        return view('waybills.index', compact('packages', 'filters', 'hasFilters', 'clients', 'creators', 'dateReference'));
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
            $firstItem = null;
            foreach ($items as $itemData) {
                $item = $package->items()->create([...$itemData, 'created_by' => $userId, 'updated_by' => $userId]);
                $firstItem ??= $item;
            }
            $package->update(['item_id' => $firstItem?->id]);
        });

        return redirect()->route('waybills.index')->with('success', 'Waybill created successfully.');
    }

    public function upload(): View { return view('waybills.upload'); }

    public function template()
    {
        $headers = [...self::PACKAGE_FIELDS, ...self::ITEM_FIELDS];
        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            fputcsv($handle, array_fill(0, count($headers), ''));
            fclose($handle);
        }, 'waybill-upload-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:51200']]);
        $rows = $this->readCsv($request->file('file')->getRealPath());
        if (!$rows) return back()->with('error', 'The uploaded file does not contain any waybill rows.');

        $errors = [];
        $grouped = [];
        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, $this->importRules());
            if ($validator->fails()) {
                $errors[] = 'Row '.($index + 2).': '.implode(' ', $validator->errors()->all());
                continue;
            }
            $grouped[$row['waybill_number']][] = $validator->validated();
        }
        if ($errors) return back()->with('error', 'Upload failed: '.implode(' | ', array_slice($errors, 0, 10)));

        $duplicates = WaybillPackage::whereIn('waybill_number', array_keys($grouped))->pluck('waybill_number')->all();
        if ($duplicates) return back()->with('error', 'These waybills already exist: '.implode(', ', $duplicates));
        $duplicatePackageIds = collect($grouped)->map(fn ($rows) => $rows[0]['package_id'])->countBy()->filter(fn ($count) => $count > 1)->keys()->all();
        if ($duplicatePackageIds) return back()->with('error', 'Package IDs must be unique: '.implode(', ', $duplicatePackageIds));

        $userId = $request->user()->id;
        DB::transaction(function () use ($grouped, $userId) {
            foreach ($grouped as $rows) {
                $packageData = array_intersect_key($rows[0], array_flip(self::PACKAGE_FIELDS));
                $sellerData = array_intersect_key($packageData, array_flip(self::SELLER_FIELDS));
                $packageData = array_diff_key($packageData, array_flip(self::SELLER_FIELDS));
                $seller = $this->resolveSeller($sellerData);
                $package = WaybillPackage::create([...$packageData, 'seller_id' => $seller->id, 'created_by' => $userId, 'updated_by' => $userId]);
                $firstItem = null;
                foreach ($rows as $row) {
                    $itemData = array_intersect_key($row, array_flip(self::ITEM_FIELDS));
                    $item = $package->items()->create([...$itemData, 'created_by' => $userId, 'updated_by' => $userId]);
                    $firstItem ??= $item;
                }
                $package->update(['item_id' => $firstItem?->id]);
            }
        });

        return redirect()->route('waybills.index')->with('success', count($grouped).' waybill(s) uploaded successfully.');
    }

    private function rules(): array
    {
        return [...$this->packageRules(),
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_reference' => ['nullable', 'string', 'max:255'],
            'items.*.item_description' => ['required', 'string', 'max:5000'],
            'items.*.item_sku' => ['nullable', 'string', 'max:100'],
            'items.*.item_length' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_width' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_height' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function packageRules(bool $forImport = false): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            ...($forImport ? ['waybill_number' => ['required', 'string', 'max:100', Rule::unique('packages', 'waybill_number')]] : []),
            'order_number' => ['nullable', 'string', 'max:100'],
            'package_id' => ['required', 'string', 'max:100', Rule::unique('packages', 'package_id')],
            ...($forImport ? [
                'package_location' => ['nullable', 'string', 'max:255'],
                'package_status' => ['required', 'string', 'max:100'],
            ] : []),
            'package_description' => ['nullable', 'string', 'max:5000'], 'package_sku' => ['nullable', 'string', 'max:100'],
            'package_size' => ['nullable', 'string', 'max:100'], 'package_quantity' => ['required', 'integer', 'min:1'],
            'package_length' => ['nullable', 'numeric', 'min:0'], 'package_width' => ['nullable', 'numeric', 'min:0'],
            'package_height' => ['nullable', 'numeric', 'min:0'], 'package_weight' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'string', 'max:100'], 'total_price' => ['nullable', 'numeric', 'min:0'],
            'declared_value' => ['nullable', 'numeric', 'min:0'], 'package_remarks' => ['nullable', 'string', 'max:5000'],
            'package_reference_1' => ['nullable', 'string', 'max:255'], 'package_reference_2' => ['nullable', 'string', 'max:255'],
            'package_reference_3' => ['nullable', 'string', 'max:255'], 'package_reference_4' => ['nullable', 'string', 'max:255'],
            'package_reference_5' => ['nullable', 'string', 'max:255'], 'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_mobile' => ['required', 'string', 'max:50'], 'buyer_email' => ['nullable', 'email', 'max:255'],
            'buyer_full_address' => ['required', 'string', 'max:5000'], 'buyer_province' => ['nullable', 'string', 'max:100'],
            'buyer_city' => ['nullable', 'string', 'max:100'], 'buyer_barangay' => ['nullable', 'string', 'max:100'],
            'buyer_long' => ['nullable', 'numeric', 'between:-180,180'], 'buyer_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'buyer_building_type' => ['nullable', 'string', 'max:100'],
            'seller_name' => ['required', 'string', 'max:150'], 'seller_full_address' => ['required', 'string', 'max:5000'],
            'seller_province' => ['required', 'string', 'max:100'], 'seller_city' => ['required', 'string', 'max:100'],
            'seller_barangay' => ['required', 'string', 'max:100'], 'seller_contact_number' => ['nullable', 'string', 'max:50'],
            'seller_email' => ['nullable', 'email', 'max:255'], 'seller_building_type' => ['nullable', 'string', 'max:100'],
            ...($forImport ? [
                'pickup_date' => ['nullable', 'date'], 'handover_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
                'remittance_status' => ['nullable', 'string', 'max:100'],
            ] : []),
            'delivery_min_time' => ['nullable', 'date'],
            'delivery_max_time' => ['nullable', 'date', 'after_or_equal:delivery_min_time'], 'is_priority' => ['required', 'boolean'],
        ];
    }

    private function importRules(): array
    {
        $rules = $this->packageRules(true);
        foreach (self::ITEM_FIELDS as $field) $rules[$field] = str_ends_with($field, 'description') ? ['required', 'string', 'max:5000'] : (in_array($field, ['item_length', 'item_width', 'item_height', 'item_weight']) ? ['nullable', 'numeric', 'min:0'] : ['nullable', 'string', 'max:5000']);
        return $rules;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = array_map(fn ($header) => trim((string) $header), fgetcsv($handle) ?: []);
        $expected = [...self::PACKAGE_FIELDS, ...self::ITEM_FIELDS];
        if (array_diff($expected, $headers)) { fclose($handle); return []; }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (!array_filter($values, fn ($value) => trim((string) $value) !== '')) continue;
            $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
            $rows[] = array_map(fn ($value) => $value === '' ? null : $value, array_combine($headers, $values));
        }
        fclose($handle);
        return $rows;
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
