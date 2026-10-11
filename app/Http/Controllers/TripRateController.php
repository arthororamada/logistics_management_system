<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Route as TripRoute;
use App\Models\TripPayment;
use App\Models\TripRate;
use App\Models\TripType;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripRateController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) return $this->dataTable($request);

        return view('trip-rates.index', [
            'vehicleGroups' => VehicleGroup::where('is_active', true)->orderBy('vehicle_group_description')->get(),
            'clients' => Client::where('is_active', true)->orderBy('client_code')->get(),
            'tripTypes' => TripType::where('is_active', true)->orderBy('trip_type')->get(),
            'payments' => TripPayment::where('is_active', true)->orderBy('payment_type')->get(),
            'vehicleTypes' => VehicleType::where('is_active', true)->orderBy('vehicle_type')->get(),
        ]);
    }

    private function dataTable(Request $request): JsonResponse
    {
        $query = TripRate::query();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('trip_number', 'like', "%{$search}%")
                ->orWhere('client_code', 'like', "%{$search}%")
                ->orWhere('trip_type', 'like', "%{$search}%")
                ->orWhere('payment_type', 'like', "%{$search}%")
                ->orWhere('vehicle_type', 'like', "%{$search}%"));
        }
        foreach (['vehicle_group', 'client_code', 'payment_type', 'vehicle_type'] as $field) {
            if ($request->filled($field)) $query->where($field, $request->input($field));
        }
        if ($request->filled('delivery_type')) $query->where('trip_type', $request->input('delivery_type'));
        if ($request->input('status') === 'active') $query->where('is_active', true);
        if ($request->input('status') === 'inactive') $query->where('is_active', false);

        $filtered = (clone $query)->count();
        $columns = ['trip_number', 'trip_type', 'payment_type', 'client_code', 'route_origin', 'vehicle_group', 'driver_rate', 'is_active'];
        $orderColumn = $columns[(int) $request->input('order.0.column', 0)] ?? 'trip_number';
        $orderDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $rows = $query->orderBy($orderColumn, $orderDirection)
            ->offset(max((int) $request->input('start', 0), 0))
            ->limit(min(max((int) $request->input('length', 15), 1), 100))
            ->get();
        $canEdit = $request->user()?->hasPermission('trip-rates.update');

        $data = $rows->map(fn (TripRate $rate) => [
            'trip_number' => e($rate->trip_number),
            'trip_type' => e($rate->trip_type),
            'payment_type' => e($rate->payment_type),
            'client_code' => e($rate->client_code),
            'route' => e($rate->route_origin).' to '.e($rate->farthest_destination),
            'vehicle' => e($rate->vehicle_group).' / '.e($rate->vehicle_type),
            'driver_rate' => number_format((float) $rate->driver_rate, 2),
            'status' => '<span class="status-badge '.($rate->is_active ? 'status-active' : 'status-inactive').'">'.($rate->is_active ? 'Active' : 'Inactive').'</span>',
            'actions' => $canEdit ? '<a class="table-action" href="'.route('trip-rates.edit', $rate).'">Edit</a>' : '',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function create(): View
    {
        return view('trip-rates.create', $this->formData(new TripRate()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRate($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        TripRate::create($data);

        return redirect()->route('trip-rates.index')->with('success', 'Trip rate created successfully.');
    }

    public function edit(TripRate $tripRate): View
    {
        return view('trip-rates.edit', $this->formData($tripRate));
    }

    public function update(Request $request, TripRate $tripRate): RedirectResponse
    {
        $data = $this->validateRate($request);
        $data['updated_by'] = $request->user()->id;
        $tripRate->update($data);

        return redirect()->route('trip-rates.index')->with('success', 'Trip rate updated successfully.');
    }

    public function destroy(TripRate $tripRate): RedirectResponse
    {
        $tripRate->delete();
        return back()->with('success', 'Trip rate deleted successfully.');
    }

    private function formData(TripRate $tripRate): array
    {
        $activeOrSelected = fn ($query, $column, $value) => $query->where(fn ($builder) => $builder
            ->where('is_active', true)
            ->orWhere($column, $value));

        return [
            'tripRate' => $tripRate,
            'warehouses' => $activeOrSelected(Warehouse::query(), 'warehouse_code', $tripRate->warehouse_code)->orderBy('warehouse_code')->get(),
            'tripTypes' => $activeOrSelected(TripType::query(), 'trip_type', $tripRate->trip_type)->orderBy('trip_type')->get(),
            'vehicleGroups' => $activeOrSelected(VehicleGroup::query(), 'vehicle_group_description', $tripRate->vehicle_group)->orderBy('vehicle_group_description')->get(),
            'vehicleTypes' => $activeOrSelected(VehicleType::query(), 'vehicle_type', $tripRate->vehicle_type)->orderBy('vehicle_type')->get(),
            'payments' => $activeOrSelected(TripPayment::query(), 'payment_type', $tripRate->payment_type)->orderBy('payment_type')->get(),
            'clients' => $activeOrSelected(Client::query(), 'client_code', $tripRate->client_code)->orderBy('client_code')->get(),
            'routes' => TripRoute::where(fn ($query) => $query->where('is_active', true)
                ->when($tripRate->exists, function ($routeQuery) use ($tripRate) {
                    $routeQuery->orWhereRaw("CONCAT_WS(', ', NULLIF(province, ''), NULLIF(city, ''), NULLIF(brgy, '')) IN (?, ?)", [$tripRate->route_origin, $tripRate->farthest_destination]);
                }))
                ->orderBy('province')->orderBy('city')->get(),
        ];
    }

    private function validateRate(Request $request): array
    {
        return $request->validate([
            'warehouse_code' => ['required', Rule::exists('warehouses', 'warehouse_code')],
            'trip_type' => ['required', Rule::exists('trip_types', 'trip_type')],
            'vehicle_group' => ['required', Rule::exists('vehicle_groups', 'vehicle_group_description')],
            'vehicle_type' => ['required', Rule::exists('vehicle_types', 'vehicle_type')],
            'trip_number' => ['required', 'string', 'max:50'],
            'payment_type' => ['required', Rule::exists('trip_payments', 'payment_type')],
            'client_code' => ['required', Rule::exists('clients', 'client_code')],
            'route_origin' => ['required', 'string', 'max:150'],
            'farthest_destination' => ['required', 'string', 'max:150'],
            'cargo_capacity' => ['required', 'numeric', 'min:0'],
            'minimum_drop' => ['required', 'numeric', 'min:0'],
            'driver_rate' => ['required', 'numeric', 'min:0'],
            'helper_rate' => ['required', 'numeric', 'min:0'],
            'driver_cash_released_percentage' => ['required', 'numeric', 'between:0,100'],
            'helper_cash_released_percentage' => ['required', 'numeric', 'between:0,100'],
            'additional_driver_rate' => ['required', 'numeric', 'min:0'],
            'additional_helper_rate' => ['required', 'numeric', 'min:0'],
            'bypass_fuel_liters' => ['required', 'numeric', 'min:0'],
            'is_interisland' => ['required', 'boolean'],
            'is_with_tollfee' => ['required', 'boolean'],
            'is_bypass_fuel_usage' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
