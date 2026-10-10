<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleTypeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $vehicleTypes = VehicleType::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('vehicle_type', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('vehicle_type')
            ->paginate(15)
            ->withQueryString();

        return view('vehicle-types.index', compact('vehicleTypes', 'filters'));
    }

    public function create(): View
    {
        return view('vehicle-types.create', ['vehicleType' => new VehicleType()]);
    }

    public function store(Request $request): RedirectResponse
    {
        VehicleType::create($this->validateVehicleType($request));

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle type created successfully.');
    }

    public function edit(VehicleType $vehicleType): View
    {
        return view('vehicle-types.edit', compact('vehicleType'));
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $vehicleType->update($this->validateVehicleType($request, $vehicleType));

        return redirect()->route('vehicle-types.index')->with('success', 'Vehicle type updated successfully.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        $vehicleType->delete();

        return back()->with('success', 'Vehicle type deleted successfully.');
    }

    private function validateVehicleType(Request $request, ?VehicleType $vehicleType = null): array
    {
        $request->merge(['vehicle_type' => trim((string) $request->input('vehicle_type'))]);

        return $request->validate([
            'vehicle_type' => ['required', 'string', 'max:100', Rule::unique('vehicle_types', 'vehicle_type')->ignore($vehicleType)],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
