<?php

namespace App\Http\Controllers;

use App\Models\VehicleGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleGroupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $vehicleGroups = VehicleGroup::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder
                ->where('group_code', 'like', "%{$search}%")
                ->orWhere('vehicle_group_description', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('vehicle_group_description')
            ->paginate(15)
            ->withQueryString();

        return view('vehicle-groups.index', compact('vehicleGroups', 'filters'));
    }

    public function create(): View
    {
        return view('vehicle-groups.create', ['vehicleGroup' => new VehicleGroup()]);
    }

    public function store(Request $request): RedirectResponse
    {
        VehicleGroup::create($this->validateVehicleGroup($request));

        return redirect()->route('vehicle-groups.index')->with('success', 'Vehicle group created successfully.');
    }

    public function edit(VehicleGroup $vehicleGroup): View
    {
        return view('vehicle-groups.edit', compact('vehicleGroup'));
    }

    public function update(Request $request, VehicleGroup $vehicleGroup): RedirectResponse
    {
        $vehicleGroup->update($this->validateVehicleGroup($request, $vehicleGroup));

        return redirect()->route('vehicle-groups.index')->with('success', 'Vehicle group updated successfully.');
    }

    public function destroy(VehicleGroup $vehicleGroup): RedirectResponse
    {
        $vehicleGroup->delete();

        return back()->with('success', 'Vehicle group deleted successfully.');
    }

    private function validateVehicleGroup(Request $request, ?VehicleGroup $vehicleGroup = null): array
    {
        $request->merge([
            'group_code' => strtoupper(trim((string) $request->input('group_code'))),
            'vehicle_group_description' => trim((string) $request->input('vehicle_group_description')),
        ]);

        return $request->validate([
            'group_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vehicle_groups', 'group_code')->ignore($vehicleGroup)],
            'vehicle_group_description' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
