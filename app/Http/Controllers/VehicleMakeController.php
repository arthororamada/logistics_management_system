<?php

namespace App\Http\Controllers;

use App\Models\VehicleMake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleMakeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $vehicleMakes = VehicleMake::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('vehicle_make', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('vehicle_make')
            ->paginate(15)
            ->withQueryString();

        return view('vehicle-makes.index', compact('vehicleMakes', 'filters'));
    }

    public function create(): View
    {
        return view('vehicle-makes.create', ['vehicleMake' => new VehicleMake()]);
    }

    public function store(Request $request): RedirectResponse
    {
        VehicleMake::create($this->validateVehicleMake($request));

        return redirect()->route('vehicle-makes.index')->with('success', 'Vehicle make created successfully.');
    }

    public function edit(VehicleMake $vehicleMake): View
    {
        return view('vehicle-makes.edit', compact('vehicleMake'));
    }

    public function update(Request $request, VehicleMake $vehicleMake): RedirectResponse
    {
        $vehicleMake->update($this->validateVehicleMake($request, $vehicleMake));

        return redirect()->route('vehicle-makes.index')->with('success', 'Vehicle make updated successfully.');
    }

    public function destroy(VehicleMake $vehicleMake): RedirectResponse
    {
        $vehicleMake->delete();

        return back()->with('success', 'Vehicle make deleted successfully.');
    }

    private function validateVehicleMake(Request $request, ?VehicleMake $vehicleMake = null): array
    {
        $request->merge(['vehicle_make' => trim((string) $request->input('vehicle_make'))]);

        return $request->validate([
            'vehicle_make' => ['required', 'string', 'max:100', Rule::unique('vehicle_makes', 'vehicle_make')->ignore($vehicleMake)],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
