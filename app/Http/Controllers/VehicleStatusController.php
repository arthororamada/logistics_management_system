<?php

namespace App\Http\Controllers;

use App\Models\VehicleStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleStatusController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $vehicleStatuses = VehicleStatus::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('vehicle_status', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('vehicle_status')->paginate(15)->withQueryString();
        return view('vehicle-statuses.index', compact('vehicleStatuses', 'filters'));
    }

    public function create(): View { return view('vehicle-statuses.create', ['vehicleStatus' => new VehicleStatus()]); }

    public function store(Request $request): RedirectResponse
    {
        VehicleStatus::create($this->validateVehicleStatus($request));
        return redirect()->route('vehicle-statuses.index')->with('success', 'Vehicle status created successfully.');
    }

    public function edit(VehicleStatus $vehicleStatus): View { return view('vehicle-statuses.edit', compact('vehicleStatus')); }

    public function update(Request $request, VehicleStatus $vehicleStatus): RedirectResponse
    {
        $vehicleStatus->update($this->validateVehicleStatus($request, $vehicleStatus));
        return redirect()->route('vehicle-statuses.index')->with('success', 'Vehicle status updated successfully.');
    }

    public function destroy(VehicleStatus $vehicleStatus): RedirectResponse
    {
        $vehicleStatus->delete();
        return back()->with('success', 'Vehicle status deleted successfully.');
    }

    private function validateVehicleStatus(Request $request, ?VehicleStatus $vehicleStatus = null): array
    {
        $request->merge(['vehicle_status' => trim((string) $request->input('vehicle_status'))]);
        return $request->validate([
            'vehicle_status' => ['required', 'string', 'max:100', Rule::unique('vehicle_statuses', 'vehicle_status')->ignore($vehicleStatus)],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
