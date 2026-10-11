<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $deliveries = Delivery::query()->latest('id')->paginate(15);
        $draftNumbers = $request->session()->get('delivery.waybill_numbers', []);
        return view('delivery.index', compact('deliveries', 'draftNumbers'));
    }

    public function addWaybills(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'waybill_numbers' => ['required', 'string', 'max:100000'],
        ]);

        $numbers = array_values(array_unique(preg_split('/[\s,;]+/u', trim($validated['waybill_numbers']), -1, PREG_SPLIT_NO_EMPTY)));
        $draft = array_values(array_unique(array_merge($request->session()->get('delivery.waybill_numbers', []), $numbers)));
        $this->validateWaybills($draft);
        $request->session()->put('delivery.waybill_numbers', $draft);

        return redirect()->route('delivery.index')->with('success', 'Waybill numbers validated and added. '.count($draft).' number(s) ready for the document manifest.');
    }

    public function removeWaybill(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_number' => ['required', 'string', 'max:100']]);
        $numbers = $request->session()->get('delivery.waybill_numbers', []);
        $request->session()->put('delivery.waybill_numbers', array_values(array_filter($numbers, fn ($number) => $number !== $validated['waybill_number'])));

        return redirect()->route('delivery.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $numbers = $request->session()->get('delivery.waybill_numbers', []);
        $delivery = DB::transaction(function () use ($numbers, $request) {
            $this->validateWaybills($numbers, 'accepted_to_warehouse', true);
            $delivery = Delivery::create([
                'waybill_numbers' => $numbers,
                'created_by' => $request->user()->id,
            ]);
            DB::table('packages')->whereIn('waybill_number', $numbers)->where('is_deleted', 0)
                ->update(['package_status' => 'for_delivery_dispatch']);

            return $delivery;
        });
        $request->session()->forget('delivery.waybill_numbers');

        return redirect()->route('delivery.show', $delivery)->with('success', 'Document manifest created.');
    }

    public function show(Delivery $delivery): View
    {
        $details = DB::table('delivery_details')->where('dm', $delivery->id)->get()->keyBy('waybill_number');
        $packages = DB::table('packages')->whereIn('waybill_number', $delivery->waybill_numbers)->where('is_deleted', 0)->get()->groupBy('waybill_number');
        $creator = DB::table('users')->where('id', $delivery->created_by)->value('name');

        return view('delivery.show', compact('delivery', 'details', 'packages', 'creator'));
    }

    public function dispatch(Delivery $delivery): RedirectResponse
    {
        $dispatched = DB::transaction(function () use ($delivery) {
            $manifest = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (DB::table('delivery_details')->where('dm', $manifest->id)->exists()) {
                return false;
            }

            $this->validateWaybills($manifest->waybill_numbers, 'for_delivery_dispatch', true);
            DB::table('delivery_details')->insert(array_map(fn ($number) => [
                'dm' => $manifest->id,
                'waybill_number' => $number,
                'status' => 'dispatched',
                'created_at' => now(),
            ], $manifest->waybill_numbers));
            DB::table('packages')->whereIn('waybill_number', $manifest->waybill_numbers)->where('is_deleted', 0)
                ->update(['package_status' => 'delivery_dispatched']);

            return true;
        });

        return redirect()->route('delivery.show', $delivery)->with('success', $dispatched ? 'Document manifest dispatched.' : 'Document manifest has already been dispatched.');
    }

    private function validateWaybills(array $numbers, string $requiredStatus = 'accepted_to_warehouse', bool $lock = false): void
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
        $dispatchedQuery = DB::table('delivery_details')->whereIn('waybill_number', $numbers)->where('status', 'dispatched');
        if ($lock) {
            $dispatchedQuery->lockForUpdate();
        }
        $dispatched = $dispatchedQuery->distinct()->pluck('waybill_number')->all();
        $errors = array_merge(
            array_map(fn ($number) => 'Waybill '.$number.' does not exist in packages or has been deleted.', array_values($missing)),
            array_map(fn ($number) => 'Waybill '.$number.' must have package status '.$requiredStatus.' before it can be processed for delivery.', $wrongStatus),
            array_map(fn ($number) => 'Waybill '.$number.' has already been dispatched and cannot be added to another delivery document manifest.', $dispatched),
        );
        if ($errors) {
            throw ValidationException::withMessages(['waybill_numbers' => $errors]);
        }
    }
}
