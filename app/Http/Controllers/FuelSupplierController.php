<?php

namespace App\Http\Controllers;

use App\Models\FuelSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FuelSupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $fuelSuppliers = FuelSupplier::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('fuel_supplier_name', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('fuel_supplier_name')->paginate(15)->withQueryString();
        return view('fuel-suppliers.index', compact('fuelSuppliers', 'filters'));
    }

    public function create(): View { return view('fuel-suppliers.create', ['fuelSupplier' => new FuelSupplier()]); }

    public function store(Request $request): RedirectResponse
    {
        FuelSupplier::create($this->validateFuelSupplier($request));
        return redirect()->route('fuel-suppliers.index')->with('success', 'Fuel supplier created successfully.');
    }

    public function edit(FuelSupplier $fuelSupplier): View { return view('fuel-suppliers.edit', compact('fuelSupplier')); }

    public function update(Request $request, FuelSupplier $fuelSupplier): RedirectResponse
    {
        $fuelSupplier->update($this->validateFuelSupplier($request, $fuelSupplier));
        return redirect()->route('fuel-suppliers.index')->with('success', 'Fuel supplier updated successfully.');
    }

    public function destroy(FuelSupplier $fuelSupplier): RedirectResponse
    {
        $fuelSupplier->delete();
        return back()->with('success', 'Fuel supplier deleted successfully.');
    }

    private function validateFuelSupplier(Request $request, ?FuelSupplier $fuelSupplier = null): array
    {
        $request->merge(['fuel_supplier_name' => trim((string) $request->input('fuel_supplier_name'))]);
        return $request->validate([
            'fuel_supplier_name' => ['required', 'string', 'max:150', Rule::unique('fuel_suppliers', 'fuel_supplier_name')->ignore($fuelSupplier)],
            'price_per_liter' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
