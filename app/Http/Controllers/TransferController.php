<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Services\PackageStatusUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $transfers = Transfer::query()->latest('id')->paginate(15);
        $draftNumbers = $request->session()->get('transfer.waybill_numbers', []);
        return view('transfer.index', compact('transfers', 'draftNumbers'));
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

    public function removeWaybill(Request $request): RedirectResponse
    {
        $validated = $request->validate(['waybill_number' => ['required', 'string', 'max:100']]);
        $numbers = $request->session()->get('transfer.waybill_numbers', []);
        $request->session()->put('transfer.waybill_numbers', array_values(array_filter($numbers, fn ($number) => $number !== $validated['waybill_number'])));

        return redirect()->route('transfer.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $numbers = $request->session()->get('transfer.waybill_numbers', []);
        $transfer = DB::transaction(function () use ($numbers, $request) {
            $this->validateWaybills($numbers, 'accepted_to_warehouse', true);
            $transfer = Transfer::create([
                'waybill_numbers' => $numbers,
                'created_by' => $request->user()->id,
            ]);
            app(PackageStatusUpdater::class)->update($numbers, 'for_transfer_dispatch', $request->user()->id);

            return $transfer;
        });
        $request->session()->forget('transfer.waybill_numbers');

        return redirect()->route('transfer.show', $transfer)->with('success', 'Document manifest created.');
    }

    public function show(Transfer $transfer): View
    {
        $details = DB::table('transfer_details')->where('dm', $transfer->id)->get()->keyBy('waybill_number');
        $packages = DB::table('packages')->whereIn('waybill_number', $transfer->waybill_numbers)->whereNull('is_deleted')->get()->groupBy('waybill_number');
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

            $this->validateWaybills($manifest->waybill_numbers, 'for_transfer_dispatch', true);
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

        $query = DB::table('packages')->whereIn('waybill_number', $numbers)->whereNull('is_deleted')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $packages = $query->get(['waybill_number', 'package_status']);
        $existing = $packages->pluck('waybill_number')->all();
        $wrongStatus = $packages->filter(fn ($package) => $package->package_status !== $requiredStatus)
            ->pluck('waybill_number')->unique()->values()->all();
        $missing = array_diff($numbers, $existing);
        $dispatchedQuery = DB::table('transfer_details')->whereIn('waybill_number', $numbers)->where('status', 'dispatched');
        if ($lock) {
            $dispatchedQuery->lockForUpdate();
        }
        $dispatched = $dispatchedQuery->distinct()->pluck('waybill_number')->all();
        $errors = array_merge(
            array_map(fn ($number) => 'Waybill '.$number.' does not exist in packages or has been deleted.', array_values($missing)),
            array_map(fn ($number) => 'Waybill '.$number.' must have package status '.$requiredStatus.' before it can be processed for transfer.', $wrongStatus),
            array_map(fn ($number) => 'Waybill '.$number.' has already been dispatched and cannot be added to another transfer document manifest.', $dispatched),
        );
        if ($errors) {
            throw ValidationException::withMessages(['waybill_numbers' => $errors]);
        }
    }
}
