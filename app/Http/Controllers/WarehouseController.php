<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $warehouses = Warehouse::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($builder) => $builder
                    ->where('warehouse_code', 'like', "%{$search}%")
                    ->orWhere('warehouse_description', 'like', "%{$search}%")
                    ->orWhere('complete_address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%"));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('warehouse_description')
            ->paginate(15)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'filters'));
    }

    public function create(): View
    {
        return view('warehouses.create', ['warehouse' => new Warehouse()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Warehouse::create($this->validateWarehouse($request));

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created successfully.');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validateWarehouse($request, $warehouse));

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->delete();

        return back()->with('success', 'Warehouse deleted successfully.');
    }

    private function validateWarehouse(Request $request, ?Warehouse $warehouse = null): array
    {
        $request->merge([
            'warehouse_code' => strtoupper(trim((string) $request->input('warehouse_code'))),
            'warehouse_description' => trim((string) $request->input('warehouse_description')),
            'complete_address' => trim((string) $request->input('complete_address')),
            'city' => trim((string) $request->input('city')),
            'province' => trim((string) $request->input('province')),
            'country' => trim((string) $request->input('country')),
        ]);

        return $request->validate([
            'warehouse_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('warehouses', 'warehouse_code')->ignore($warehouse)],
            'warehouse_description' => ['required', 'string', 'max:150'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'complete_address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
