<?php

namespace App\Http\Controllers;

use App\Models\ShippingRate;
use App\Models\ShippingLine;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingRateController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) return $this->dataTable($request);
        return view('shipping-rates.index');
    }

    public function create(): View
    {
        return view('shipping-rates.create', $this->formData(new ShippingRate()));
    }

    public function store(Request $request): RedirectResponse
    {
        ShippingRate::create($this->validateShippingRate($request));
        return redirect()->route('shipping-rates.index')->with('success', 'Shipping rate created successfully.');
    }

    public function edit(ShippingRate $shippingRate): View
    {
        return view('shipping-rates.edit', $this->formData($shippingRate));
    }

    public function update(Request $request, ShippingRate $shippingRate): RedirectResponse
    {
        $shippingRate->update($this->validateShippingRate($request, $shippingRate));
        return redirect()->route('shipping-rates.index')->with('success', 'Shipping rate updated successfully.');
    }

    public function destroy(ShippingRate $shippingRate): RedirectResponse
    {
        $shippingRate->delete();
        return back()->with('success', 'Shipping rate deleted successfully.');
    }

    private function dataTable(Request $request): JsonResponse
    {
        $query = ShippingRate::query();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('shipping_line_name', 'like', "%{$search}%")
                ->orWhere('port_origin', 'like', "%{$search}%")
                ->orWhere('port_destination', 'like', "%{$search}%")
                ->orWhere('vehicle_type', 'like', "%{$search}%"));
        }
        if ($request->input('status') === 'active') $query->where('is_active', true);
        if ($request->input('status') === 'inactive') $query->where('is_active', false);

        $filtered = (clone $query)->count();
        $columns = ['shipping_line_name', 'port_origin', 'port_destination', 'vehicle_type', 'shippine_rate', 'is_active'];
        $orderColumn = $columns[(int) $request->input('order.0.column', 0)] ?? 'shipping_line_name';
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $length = min(max((int) $request->input('length', 25), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $rates = $query->orderBy($orderColumn, $orderDirection)->offset($start)->limit($length)->get();
        $canEdit = $request->user()?->hasPermission('shipping-rates.update');
        $canDelete = $request->user()?->hasPermission('shipping-rates.delete');

        $data = $rates->map(function (ShippingRate $rate) use ($canEdit, $canDelete) {
            return [
                'shipping_line_name' => e($rate->shipping_line_name),
                'port_origin' => e($rate->port_origin),
                'port_destination' => e($rate->port_destination),
                'vehicle_type' => e($rate->vehicle_type),
                'shippine_rate' => number_format((float) $rate->shippine_rate, 2),
                'status' => '<span class="status-badge '.($rate->is_active ? 'status-active' : 'status-inactive').'">'.($rate->is_active ? 'Active' : 'Inactive').'</span>',
                'actions' => view('shipping-rates.partials.actions', compact('rate', 'canEdit', 'canDelete'))->render(),
            ];
        });

        return response()->json(['draw' => (int) $request->input('draw', 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    private function validateShippingRate(Request $request, ?ShippingRate $shippingRate = null): array
    {
        $request->merge([
            'shipping_line_name' => trim((string) $request->input('shipping_line_name')),
            'port_origin' => trim((string) $request->input('port_origin')),
            'port_destination' => trim((string) $request->input('port_destination')),
            'vehicle_type' => trim((string) $request->input('vehicle_type')),
        ]);
        return $request->validate([
            'shipping_line_name' => ['required', 'string', 'max:150', Rule::exists('shipping_lines', 'shipping_line_name')],
            'port_origin' => ['required', 'string', 'max:150'],
            'port_destination' => ['required', 'string', 'max:150'],
            'vehicle_type' => ['required', 'string', 'max:100', Rule::exists('vehicle_types', 'vehicle_type')],
            'shippine_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function formData(ShippingRate $shippingRate): array
    {
        $shippingLines = ShippingLine::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('shipping_line_name', $shippingRate->shipping_line_name))
            ->orderBy('shipping_line_name')->get();
        $vehicleTypes = VehicleType::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('vehicle_type', $shippingRate->vehicle_type))
            ->orderBy('vehicle_type')->get();

        return compact('shippingRate', 'shippingLines', 'vehicleTypes');
    }
}
