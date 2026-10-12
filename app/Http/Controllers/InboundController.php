<?php

namespace App\Http\Controllers;

use App\Services\PackageStatusUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InboundController extends Controller
{
    public function index(Request $request): View
    {
        $draftNumbers = $request->session()->get('inbound.waybill_numbers', []);

        return view('inbound.index', compact('draftNumbers'));
    }

    public function addWaybills(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_numbers' => ['required', 'string', 'max:100000']]);
        $numbers = array_values(array_unique(preg_split('/[\s,;]+/u', trim($validated['waybill_numbers']), -1, PREG_SPLIT_NO_EMPTY)));
        $draft = array_values(array_unique(array_merge($request->session()->get('inbound.waybill_numbers', []), $numbers)));
        $this->validateWaybills($draft);
        $request->session()->put('inbound.waybill_numbers', $draft);

        return redirect()->route('inbound.index')->with('success', 'Waybill numbers validated and added. '.count($draft).' number(s) ready to be accepted to the warehouse.');
    }

    public function removeWaybill(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_number' => ['required', 'string', 'max:100']]);
        $numbers = $request->session()->get('inbound.waybill_numbers', []);
        $request->session()->put('inbound.waybill_numbers', array_values(array_filter($numbers, fn ($number) => $number !== $validated['waybill_number'])));

        return redirect()->route('inbound.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $numbers = $request->session()->get('inbound.waybill_numbers', []);
        DB::transaction(function () use ($numbers, $request) {
            $this->validateWaybills($numbers, true);

            app(PackageStatusUpdater::class)->update($numbers, 'accepted_to_warehouse', $request->user()->id);
        });
        $request->session()->forget('inbound.waybill_numbers');

        return redirect()->route('inbound.index')->with('success', 'Packages accepted to the warehouse.');
    }

    private function validateWaybills(array $numbers, bool $lock = false): void
    {
        $validator = Validator::make(['numbers' => $numbers], [
            'numbers' => ['required', 'array', 'min:1', 'max:1000'],
            'numbers.*' => ['required', 'string', 'max:100'],
        ], [
            'numbers.required' => 'Add at least one waybill number before accepting packages to the warehouse.',
            'numbers.max' => 'An inbound batch can contain up to 1,000 waybill numbers.',
            'numbers.*.max' => 'Each waybill number must be no longer than 100 characters.',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['waybill_numbers' => $validator->errors()->all()]);
        }

        $query = DB::table('packages')->whereIn('waybill_number', $numbers)->whereNull('is_deleted');
        if ($lock) {
            $query->lockForUpdate();
        }
        $packages = $query->get(['waybill_number', 'package_status'])->groupBy('waybill_number');
        $errors = [];
        foreach ($numbers as $number) {
            $matches = $packages->get($number, collect());
            if ($matches->isEmpty()) {
                $errors[] = 'Waybill '.$number.' does not exist in packages or has been deleted.';
            } elseif ($matches->contains(fn ($package) => $package->package_status !== 'accepted_by_courier')) {
                $errors[] = 'Waybill '.$number.' must have package status accepted_by_courier before it can be accepted to the warehouse.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['waybill_numbers' => $errors]);
        }
    }
}
