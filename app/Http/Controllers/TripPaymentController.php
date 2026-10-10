<?php

namespace App\Http\Controllers;

use App\Models\TripPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $tripPayments = TripPayment::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('payment_type', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('payment_type')->paginate(15)->withQueryString();
        return view('trip-payments.index', compact('tripPayments', 'filters'));
    }

    public function create(): View { return view('trip-payments.create', ['tripPayment' => new TripPayment()]); }

    public function store(Request $request): RedirectResponse
    {
        TripPayment::create($this->validateTripPayment($request));
        return redirect()->route('trip-payments.index')->with('success', 'Trip payment created successfully.');
    }

    public function edit(TripPayment $tripPayment): View { return view('trip-payments.edit', compact('tripPayment')); }

    public function update(Request $request, TripPayment $tripPayment): RedirectResponse
    {
        $tripPayment->update($this->validateTripPayment($request, $tripPayment));
        return redirect()->route('trip-payments.index')->with('success', 'Trip payment updated successfully.');
    }

    public function destroy(TripPayment $tripPayment): RedirectResponse
    {
        $tripPayment->delete();
        return back()->with('success', 'Trip payment deleted successfully.');
    }

    private function validateTripPayment(Request $request, ?TripPayment $tripPayment = null): array
    {
        $request->merge(['payment_type' => trim((string) $request->input('payment_type'))]);
        return $request->validate([
            'payment_type' => ['required', 'string', 'max:100', Rule::unique('trip_payments', 'payment_type')->ignore($tripPayment)],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
