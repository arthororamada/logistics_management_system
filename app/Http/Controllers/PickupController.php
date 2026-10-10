<?php

namespace App\Http\Controllers;

use App\Models\Pickup;
use App\Models\Personnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PickupController extends Controller
{
    public function index(Request $request): View
    {
        $pickups = Pickup::query()->latest('id')->paginate(15);
        $draftNumbers = $request->session()->get('pickup.waybill_numbers', []);
        $drivers = Personnel::query()->where('is_active', 1)
            ->whereRaw('LOWER(TRIM(position)) = ?', ['driver'])
            ->orderBy('last_name')->orderBy('first_name')->get();

        return view('pickup.index', compact('pickups', 'draftNumbers', 'drivers'));
    }

    public function addWaybills(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'waybill_numbers' => ['required', 'string', 'max:100000'],
        ]);

        $numbers = array_values(array_unique(preg_split('/[\s,;]+/u', trim($validated['waybill_numbers']), -1, PREG_SPLIT_NO_EMPTY)));
        $draft = array_values(array_unique(array_merge($request->session()->get('pickup.waybill_numbers', []), $numbers)));
        $this->validateWaybills($draft);
        $request->session()->put('pickup.waybill_numbers', $draft);

        return redirect()->route('pickup.index')->with('success', 'Waybill numbers validated and added. '.count($draft).' number(s) ready for the document manifest.');
    }

    public function removeWaybill(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_number' => ['required', 'string', 'max:100']]);
        $numbers = $request->session()->get('pickup.waybill_numbers', []);
        $request->session()->put('pickup.waybill_numbers', array_values(array_filter($numbers, fn ($number) => $number !== $validated['waybill_number'])));

        return redirect()->route('pickup.index');
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
        $numbers = $request->session()->get('pickup.waybill_numbers', []);
        $pickup = DB::transaction(function () use ($numbers, $request, $validated) {
            $this->validateWaybills($numbers);
            $pickup = Pickup::create([
                'waybill_numbers' => $numbers,
                'created_by' => $request->user()->id,
                'driver_id' => $validated['driver_id'],
            ]);
            DB::table('packages')->whereIn('waybill_number', $numbers)->where('is_deleted', 0)
                ->update(['package_status' => 'for_pickup_dispatch']);

            return $pickup;
        });
        $request->session()->forget('pickup.waybill_numbers');

        return redirect()->route('pickup.show', $pickup)->with('success', 'Document manifest created.');
    }

    public function show(Pickup $pickup): View
    {
        $pickup->load('driver');
        $details = DB::table('pickup_details')->where('dm', $pickup->id)->get()->keyBy('waybill_number');
        $packages = DB::table('packages')->whereIn('waybill_number', $pickup->waybill_numbers)->where('is_deleted', 0)->get()->groupBy('waybill_number');
        $creator = DB::table('users')->where('id', $pickup->created_by)->value('name');

        return view('pickup.show', compact('pickup', 'details', 'packages', 'creator'));
    }

    public function dispatch(Pickup $pickup): RedirectResponse
    {
        $dispatched = DB::transaction(function () use ($pickup) {
            $manifest = Pickup::query()->lockForUpdate()->findOrFail($pickup->id);
            if (DB::table('pickup_details')->where('dm', $manifest->id)->exists()) {
                return false;
            }

            $this->validateWaybills($manifest->waybill_numbers);
            DB::table('pickup_details')->insert(array_map(fn ($number) => [
                'dm' => $manifest->id,
                'waybill_number' => $number,
                'status' => 'dispatched',
                'created_at' => now(),
            ], $manifest->waybill_numbers));
            DB::table('packages')->whereIn('waybill_number', $manifest->waybill_numbers)->where('is_deleted', 0)
                ->update(['package_status' => 'pickup_dispatched']);

            return true;
        });

        return redirect()->route('pickup.show', $pickup)->with('success', $dispatched ? 'Document manifest dispatched.' : 'Document manifest has already been dispatched.');
    }

    private function validateWaybills(array $numbers): void
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

        $existing = DB::table('packages')->whereIn('waybill_number', $numbers)->where('is_deleted', 0)->pluck('waybill_number')->all();
        $missing = array_diff($numbers, $existing);
        $dispatched = DB::table('pickup_details')->whereIn('waybill_number', $numbers)
            ->where('status', 'dispatched')->distinct()->pluck('waybill_number')->all();
        $errors = array_merge(
            array_map(fn ($number) => 'Waybill '.$number.' does not exist in packages or has been deleted.', array_values($missing)),
            array_map(fn ($number) => 'Waybill '.$number.' has already been dispatched and cannot be added to another pickup document manifest.', $dispatched),
        );
        if ($errors) {
            throw ValidationException::withMessages(['waybill_numbers' => $errors]);
        }
    }
}
