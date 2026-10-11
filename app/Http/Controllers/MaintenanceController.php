<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use App\Models\MaintenanceRenewal;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([Maintenance::STATUS_NORMAL, Maintenance::STATUS_WARNING, Maintenance::STATUS_VIOLATION])],
            'policy_type' => ['nullable', Rule::in([VehicleMaintenance::DISTANCE_POLICY, VehicleMaintenance::DATE_POLICY])],
        ]);

        $records = Maintenance::query()
            ->with(['vehicle:id,plate_number,body_number,vehicle_group,vehicle_type,odometer', 'policy:id,maintenance_code,maintenance_description'])
            ->where('is_active', true)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->whereHas('vehicle', fn ($vehicle) => $vehicle
                        ->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('body_number', 'like', "%{$search}%"))
                        ->orWhereHas('policy', fn ($policy) => $policy
                            ->where('maintenance_code', 'like', "%{$search}%")
                            ->orWhere('maintenance_description', 'like', "%{$search}%"));
                });
            })
            ->when($filters['policy_type'] ?? null, fn ($query, $value) => $query->where('policy_type', $value))
            ->orderByDesc('updated_at')
            ->get();

        $records->each(fn (Maintenance $maintenance) => $maintenance->setAttribute('current_status', $maintenance->status()));
        $statusCounts = [
            Maintenance::STATUS_NORMAL => $records->where('current_status', Maintenance::STATUS_NORMAL)->count(),
            Maintenance::STATUS_WARNING => $records->where('current_status', Maintenance::STATUS_WARNING)->count(),
            Maintenance::STATUS_VIOLATION => $records->where('current_status', Maintenance::STATUS_VIOLATION)->count(),
        ];
        if ($filters['status'] ?? null) $records = $records->where('current_status', $filters['status'])->values();

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $maintenances = new LengthAwarePaginator(
            $records->slice(($page - 1) * $perPage, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('maintenances.index', compact('maintenances', 'filters', 'statusCounts'));
    }

    public function create(Request $request): View
    {
        $selectedVehicleId = $request->validate(['vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')]])['vehicle_id'] ?? null;
        $vehicles = Vehicle::orderBy('plate_number')->get(['id', 'plate_number', 'body_number', 'vehicle_group', 'vehicle_type', 'odometer']);
        $policies = VehicleMaintenance::where('is_active', true)
            ->orderBy('maintenance_code')
            ->get(['id', 'maintenance_code', 'maintenance_description', 'vehicle_group', 'vehicle_type', 'maintenance_type', 'threshold', 'threshold_warning']);

        return view('maintenances.create', compact('vehicles', 'policies', 'selectedVehicleId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->serviceRules());
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $policy = VehicleMaintenance::where('is_active', true)->findOrFail($data['vehicle_maintenance_id']);
        $this->ensurePolicyMatchesVehicle($vehicle, $policy);

        if (Maintenance::where('vehicle_id', $vehicle->id)->where('vehicle_maintenance_id', $policy->id)->exists()) {
            throw ValidationException::withMessages(['vehicle_maintenance_id' => 'This maintenance policy is already assigned to the selected vehicle. Renew the existing maintenance instead.']);
        }

        $userId = $request->user()->id;
        $maintenance = DB::transaction(function () use ($data, $vehicle, $policy, $userId) {
            $schedule = $this->schedule($policy, $data['service_date'], (float) $data['service_odometer']);
            $maintenance = Maintenance::create([
                'vehicle_id' => $vehicle->id,
                'vehicle_maintenance_id' => $policy->id,
                'policy_type' => $policy->maintenance_type,
                'threshold' => $policy->threshold,
                'threshold_warning' => $policy->threshold_warning,
                'last_service_date' => $data['service_date'],
                'last_service_odometer' => $data['service_odometer'],
                ...$schedule,
                'is_active' => true,
                'created_by' => $userId,
                'update_by' => $userId,
            ]);
            MaintenanceRenewal::create([
                'maintenance_id' => $maintenance->id,
                'service_date' => $data['service_date'],
                'service_odometer' => $data['service_odometer'],
                'notes' => $data['notes'] ?? 'Initial maintenance cycle',
                'created_by' => $userId,
            ]);
            if ((float) $data['service_odometer'] > (float) $vehicle->odometer) $vehicle->update(['odometer' => $data['service_odometer']]);
            return $maintenance;
        });

        return redirect()->route('maintenances.show', $maintenance)->with('success', 'Vehicle maintenance cycle created successfully.');
    }

    public function show(Maintenance $maintenance): View
    {
        $maintenance->load(['vehicle', 'policy', 'creator:id,name', 'updater:id,name', 'renewals.creator:id,name']);
        $currentStatus = $maintenance->status();

        return view('maintenances.show', compact('maintenance', 'currentStatus'));
    }

    public function renew(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $maintenance->load(['vehicle', 'policy']);
        $data = $request->validate($this->serviceRules(false));

        if (Carbon::parse($data['service_date'])->lt($maintenance->last_service_date)) {
            throw ValidationException::withMessages(['service_date' => 'The renewal date cannot be earlier than the last service date.']);
        }
        if ((float) $data['service_odometer'] < (float) $maintenance->last_service_odometer) {
            throw ValidationException::withMessages(['service_odometer' => 'The renewal odometer cannot be lower than the last service odometer.']);
        }

        $userId = $request->user()->id;
        DB::transaction(function () use ($data, $maintenance, $userId) {
            $schedule = $this->schedule($maintenance->policy, $data['service_date'], (float) $data['service_odometer']);
            $maintenance->update([
                'policy_type' => $maintenance->policy->maintenance_type,
                'threshold' => $maintenance->policy->threshold,
                'threshold_warning' => $maintenance->policy->threshold_warning,
                'last_service_date' => $data['service_date'],
                'last_service_odometer' => $data['service_odometer'],
                ...$schedule,
                'update_by' => $userId,
            ]);
            MaintenanceRenewal::create([
                'maintenance_id' => $maintenance->id,
                'service_date' => $data['service_date'],
                'service_odometer' => $data['service_odometer'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            if ((float) $data['service_odometer'] > (float) $maintenance->vehicle->odometer) {
                $maintenance->vehicle->update(['odometer' => $data['service_odometer']]);
            }
        });

        return back()->with('success', 'Maintenance renewed and the next threshold was calculated successfully.');
    }

    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();
        return redirect()->route('maintenances.index')->with('success', 'Vehicle maintenance assignment deleted successfully.');
    }

    private function serviceRules(bool $includeAssignment = true): array
    {
        $rules = [
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'service_odometer' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        if ($includeAssignment) {
            $rules = [
                'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
                'vehicle_maintenance_id' => ['required', 'integer', Rule::exists('vehicle_maintenances', 'id')],
                ...$rules,
            ];
        }
        return $rules;
    }

    private function ensurePolicyMatchesVehicle(Vehicle $vehicle, VehicleMaintenance $policy): void
    {
        if ($vehicle->vehicle_group !== $policy->vehicle_group || $vehicle->vehicle_type !== $policy->vehicle_type) {
            throw ValidationException::withMessages([
                'vehicle_maintenance_id' => 'The selected maintenance policy does not match the vehicle group and vehicle type.',
            ]);
        }
    }

    private function schedule(VehicleMaintenance $policy, string $serviceDate, float $serviceOdometer): array
    {
        if ($policy->maintenance_type === VehicleMaintenance::DISTANCE_POLICY) {
            $due = $serviceOdometer + $policy->threshold;
            return [
                'warning_date' => null,
                'next_due_date' => null,
                'warning_odometer' => max($serviceOdometer, $due - $policy->threshold_warning),
                'next_due_odometer' => $due,
            ];
        }

        $due = Carbon::parse($serviceDate)->startOfDay()->addDays($policy->threshold);
        return [
            'warning_date' => $due->copy()->subDays($policy->threshold_warning),
            'next_due_date' => $due,
            'warning_odometer' => null,
            'next_due_odometer' => null,
        ];
    }
}
