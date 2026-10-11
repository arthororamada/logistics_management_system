<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\Personnel;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $transfers = Transfer::query()->latest('id')->paginate(15);
        $draftNumbers = $request->session()->get('transfer.waybill_numbers', []);
        $drivers = Personnel::query()->where('is_active', 1)
            ->whereRaw('LOWER(TRIM(position)) = ?', ['driver'])
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(20)->get();
        if ($request->old('driver_id') && !$drivers->contains('id', $request->old('driver_id'))) {
            $selectedDriver = Personnel::query()->where('is_active', 1)
                ->whereRaw('LOWER(TRIM(position)) = ?', ['driver'])->find($request->old('driver_id'));
            if ($selectedDriver) {
                $drivers->prepend($selectedDriver);
            }
        }

        $warehouses = Warehouse::query()->where('is_active', 1)->orderBy('warehouse_description')->orderBy('id')->limit(20)->get();
        if ($request->old('warehouse_destination') && !$warehouses->contains('warehouse_code', $request->old('warehouse_destination'))) {
            $selectedWarehouse = Warehouse::query()->where('is_active', 1)->where('warehouse_code', $request->old('warehouse_destination'))->first();
            if ($selectedWarehouse) {
                $warehouses->prepend($selectedWarehouse);
            }
        }

        return view('transfer.index', compact('transfers', 'draftNumbers', 'drivers', 'warehouses'));
    }

    public function searchDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');
        $drivers = Personnel::query()->where('is_active', 1)
            ->whereRaw('LOWER(TRIM(position)) = ?', ['driver']);
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $drivers->where(function ($query) use ($term) {
                foreach (['first_name', 'last_name', 'middle_name', 'employee_id'] as $column) {
                    $query->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }

        return response()->json(['drivers' => $drivers->orderBy('last_name')->orderBy('first_name')
            ->orderBy('id')->limit(20)->get(['id', 'first_name', 'last_name', 'middle_name', 'employee_id'])
            ->map(fn ($driver) => ['id' => $driver->id, 'label' => trim($driver->last_name.', '.$driver->first_name.' '.$driver->middle_name).' ('.$driver->employee_id.')'])])
            ->header('Cache-Control', 'no-store');
    }

    public function addWaybills(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'waybill_numbers' => ['required', 'string', 'max:100000'],
        ]);

        $numbers = array_values(array_unique(preg_split('/[\s,;]+/u', trim($validated['waybill_numbers']), -1, PREG_SPLIT_NO_EMPTY)));
        $draft = array_values(array_unique(array_merge($request->session()->get('transfer.waybill_numbers', []), $numbers)));
        $this->validateWaybills($draft);
        $request->session()->put('transfer.waybill_numbers', $draft);

        return redirect()->route('transfer.index')->with('success', 'Waybill numbers validated and added. '.count($draft).' number(s) ready for the document manifest.');
    }

    public function searchWarehouses(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $warehouses = Warehouse::query()->where('is_active', 1);
        foreach (preg_split('/\s+/u', trim($validated['search'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $warehouses->where(function ($query) use ($term) {
                foreach (['warehouse_code', 'warehouse_description', 'city', 'province'] as $column) {
                    $query->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }

        return response()->json(['warehouses' => $warehouses->orderBy('warehouse_description')->orderBy('id')
            ->limit(20)->get(['warehouse_code', 'warehouse_description'])
            ->map(fn ($warehouse) => ['id' => $warehouse->warehouse_code, 'label' => $warehouse->warehouse_description.' ('.$warehouse->warehouse_code.')'])])
            ->header('Cache-Control', 'no-store');
    }

    public function removeWaybill(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_number' => ['required', 'string', 'max:100']]);
        $numbers = $request->session()->get('transfer.waybill_numbers', []);
        $request->session()->put('transfer.waybill_numbers', array_values(array_filter($numbers, fn ($number) => $number !== $validated['waybill_number'])));

        return redirect()->route('transfer.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_destination' => ['required', 'string', 'max:255', Rule::exists('warehouses', 'warehouse_code')->where('is_active', 1)],
            'driver_id' => ['required', 'integer', Rule::exists('personnel', 'id')->where(fn ($query) => $query
                ->where('is_active', 1)->whereRaw('LOWER(TRIM(position)) = ?', ['driver']))],
        ], [
            'driver_id.required' => 'Select a driver before creating the document manifest.',
            'driver_id.exists' => 'Select an active driver from personnel.',
            'warehouse_destination.required' => 'Select a destination warehouse before creating the document manifest.',
            'warehouse_destination.exists' => 'Select an active destination warehouse.',
        ]);
        $numbers = $request->session()->get('transfer.waybill_numbers', []);
        $transfer = DB::transaction(function () use ($numbers, $request, $validated) {
            $this->validateWaybills($numbers, true);
            return Transfer::create([
                'waybill_numbers' => $numbers,
                'created_by' => $request->user()->id,
                'driver_id' => $validated['driver_id'],
                'warehouse_destination' => $validated['warehouse_destination'],
            ]);

        });
        $request->session()->forget('transfer.waybill_numbers');

        return redirect()->route('transfer.show', $transfer)->with('success', 'Document manifest created.');
    }

    public function show(Transfer $transfer): View
    {
        $transfer->load('driver', 'destination');
        $details = DB::table('transfer_details')->where('dm', $transfer->id)->get()->keyBy('waybill_number');
        $packages = DB::table('packages')->whereIn('waybill_number', $transfer->waybill_numbers)->where('is_deleted', 0)->get()->groupBy('waybill_number');
        $creator = DB::table('users')->where('id', $transfer->created_by)->value('name');

        return view('transfer.show', compact('transfer', 'details', 'packages', 'creator'));
    }

    public function dispatch(Transfer $transfer): RedirectResponse
    {
        $dispatched = DB::transaction(function () use ($transfer) {
            $manifest = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (DB::table('transfer_details')->where('dm', $manifest->id)->exists()) {
                return false;
            }

            Validator::make($manifest->only(['driver_id', 'warehouse_destination']), [
                'driver_id' => ['required', Rule::exists('personnel', 'id')->where(fn ($query) => $query->where('is_active', 1)->whereRaw('LOWER(TRIM(position)) = ?', ['driver']))],
                'warehouse_destination' => ['required', Rule::exists('warehouses', 'warehouse_code')->where('is_active', 1)],
            ], [
                'driver_id.exists' => 'The selected driver is no longer active or available.',
                'warehouse_destination.exists' => 'The destination warehouse is no longer active or available.',
            ])->validate();
            $this->validateWaybills($manifest->waybill_numbers, true);
            DB::table('transfer_details')->insert(array_map(fn ($number) => [
                'dm' => $manifest->id,
                'waybill_number' => $number,
                'status' => 'dispatched',
                'created_at' => now(),
            ], $manifest->waybill_numbers));

            return true;
        });

        return redirect()->route('transfer.show', $transfer)->with('success', $dispatched ? 'Document manifest dispatched.' : 'Document manifest has already been dispatched.');
    }

    private function validateWaybills(array $numbers, bool $lock = false): void
    {
        $validator = Validator::make(['numbers' => $numbers], [
            'numbers' => ['required', 'array', 'min:1', 'max:1000'],
            'numbers.*' => ['required', 'string', 'max:100'],
        ], [
            'numbers.required' => 'Add at least one waybill number before creating the document manifest.',
            'numbers.max' => 'A document manifest can contain up to 1,000 waybill numbers.',
            'numbers.*.max' => 'Each waybill number must be no longer than 100 characters.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['waybill_numbers' => $validator->errors()->all()]);
        }

        $query = DB::table('packages')->whereIn('waybill_number', $numbers)->where('is_deleted', 0)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $packages = $query->get(['waybill_number', 'package_status']);
        $existing = $packages->pluck('waybill_number')->all();
        $wrongStatus = $packages->filter(fn ($package) => $package->package_status !== 'accepted_to_warehouse')
            ->pluck('waybill_number')->unique()->values()->all();
        $missing = array_diff($numbers, $existing);
        $dispatchedQuery = DB::table('transfer_details')->whereIn('waybill_number', $numbers)->where('status', 'dispatched');
        if ($lock) {
            $dispatchedQuery->lockForUpdate();
        }
        $dispatched = $dispatchedQuery->distinct()->pluck('waybill_number')->all();
        $errors = array_merge(
            array_map(fn ($number) => 'Waybill '.$number.' does not exist in packages or has been deleted.', array_values($missing)),
            array_map(fn ($number) => 'Waybill '.$number.' must have package status accepted_to_warehouse before it can be added to a transfer document manifest.', $wrongStatus),
            array_map(fn ($number) => 'Waybill '.$number.' has already been dispatched and cannot be added to another transfer document manifest.', $dispatched),
        );
        if ($errors) {
            throw ValidationException::withMessages(['waybill_numbers' => $errors]);
        }
    }
}
