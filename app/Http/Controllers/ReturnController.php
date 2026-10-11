<?php

namespace App\Http\Controllers;

use App\Models\ReturnManifest;
use App\Models\Personnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $returns = ReturnManifest::query()->latest('id')->paginate(15);
        $draftNumbers = $request->session()->get('return.waybill_numbers', []);
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

        return view('return.index', compact('returns', 'draftNumbers', 'drivers'));
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
        $draft = array_values(array_unique(array_merge($request->session()->get('return.waybill_numbers', []), $numbers)));
        $this->validateWaybills($draft);
        $request->session()->put('return.waybill_numbers', $draft);

        return redirect()->route('return.index')->with('success', 'Waybill numbers validated and added. '.count($draft).' number(s) ready for the document manifest.');
    }

    public function removeWaybill(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_number' => ['required', 'string', 'max:100']]);
        $numbers = $request->session()->get('return.waybill_numbers', []);
        $request->session()->put('return.waybill_numbers', array_values(array_filter($numbers, fn ($number) => $number !== $validated['waybill_number'])));

        return redirect()->route('return.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', Rule::exists('personnel', 'id')->where(fn ($query) => $query
                ->where('is_active', 1)->whereRaw('LOWER(TRIM(position)) = ?', ['driver']))],
        ], [
            'driver_id.required' => 'Select a driver before creating the document manifest.',
            'driver_id.exists' => 'Select an active driver from personnel.',
        ]);
        $numbers = $request->session()->get('return.waybill_numbers', []);
        $returnManifest = DB::transaction(function () use ($numbers, $request, $validated) {
            $this->validateWaybills($numbers, 'for_return', true);
            $returnManifest = ReturnManifest::create([
                'waybill_numbers' => $numbers,
                'created_by' => $request->user()->id,
                'driver_id' => $validated['driver_id'],
            ]);
            DB::table('packages')->whereIn('waybill_number', $numbers)->where('is_deleted', 0)
                ->update(['package_status' => 'for_return_dispatch']);

            return $returnManifest;
        });
        $request->session()->forget('return.waybill_numbers');

        return redirect()->route('return.show', $returnManifest)->with('success', 'Document manifest created.');
    }

    public function show(ReturnManifest $returnManifest): View
    {
        $returnManifest->load('driver');
        $details = DB::table('return_details')->where('dm', $returnManifest->id)->get()->keyBy('waybill_number');
        $packages = DB::table('packages')->whereIn('waybill_number', $returnManifest->waybill_numbers)->where('is_deleted', 0)->get()->groupBy('waybill_number');
        $creator = DB::table('users')->where('id', $returnManifest->created_by)->value('name');

        return view('return.show', compact('returnManifest', 'details', 'packages', 'creator'));
    }

    public function dispatch(ReturnManifest $returnManifest): RedirectResponse
    {
        $dispatched = DB::transaction(function () use ($returnManifest) {
            $manifest = ReturnManifest::query()->lockForUpdate()->findOrFail($returnManifest->id);
            if (DB::table('return_details')->where('dm', $manifest->id)->exists()) {
                return false;
            }

            Validator::make($manifest->only('driver_id'), [
                'driver_id' => ['required', Rule::exists('personnel', 'id')->where(fn ($query) => $query
                    ->where('is_active', 1)->whereRaw('LOWER(TRIM(position)) = ?', ['driver']))],
            ], ['driver_id.exists' => 'The selected driver is no longer active or available.'])->validate();
            $this->validateWaybills($manifest->waybill_numbers, 'for_return_dispatch', true);
            DB::table('return_details')->insert(array_map(fn ($number) => [
                'dm' => $manifest->id,
                'waybill_number' => $number,
                'status' => 'dispatched',
                'created_at' => now(),
            ], $manifest->waybill_numbers));
            DB::table('packages')->whereIn('waybill_number', $manifest->waybill_numbers)->where('is_deleted', 0)
                ->update(['package_status' => 'return_dispatched']);

            return true;
        });

        return redirect()->route('return.show', $returnManifest)->with('success', $dispatched ? 'Document manifest dispatched.' : 'Document manifest has already been dispatched.');
    }

    private function validateWaybills(array $numbers, string $requiredStatus = 'for_return', bool $lock = false): void
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
        $wrongStatus = $packages->filter(fn ($package) => $package->package_status !== $requiredStatus)
            ->pluck('waybill_number')->unique()->values()->all();
        $missing = array_diff($numbers, $existing);
        $dispatchedQuery = DB::table('return_details')->whereIn('waybill_number', $numbers)->where('status', 'dispatched');
        if ($lock) {
            $dispatchedQuery->lockForUpdate();
        }
        $dispatched = $dispatchedQuery->distinct()->pluck('waybill_number')->all();
        $errors = array_merge(
            array_map(fn ($number) => 'Waybill '.$number.' does not exist in packages or has been deleted.', array_values($missing)),
            array_map(fn ($number) => 'Waybill '.$number.' must have package status '.$requiredStatus.' before it can be processed for return.', $wrongStatus),
            array_map(fn ($number) => 'Waybill '.$number.' has already been dispatched and cannot be added to another return document manifest.', $dispatched),
        );
        if ($errors) {
            throw ValidationException::withMessages(['waybill_numbers' => $errors]);
        }
    }
}
