<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTripRequest;
use App\Models\FuelSupplier;
use App\Models\OtherCost;
use App\Models\Personnel;
use App\Models\Route as Location;
use App\Models\ShippingLine;
use App\Models\ShippingRate;
use App\Models\Trip;
use App\Models\TripPayment;
use App\Models\TripRate;
use App\Models\TripType;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->accessible($request)->with('personnels');
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                foreach (['trip_id', 'trip_no', 'van_number', 'plate_number', 'trip_route_origin', 'trip_route_destination'] as $field) {
                    $q->orWhere($field, 'like', '%'.$search.'%');
                }
                $q->orWhereHas('personnels', fn ($p) => $p->whereIn('employee_id', Personnel::where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->select('id')));
            });
        }
        foreach (['current_status', 'trip_type', 'vehicle_group'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        $sort = in_array($request->input('sort'), ['trip_id', 'trip_no', 'total_distance', 'total_cost', 'current_status', 'created_at']) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $trips = $query->orderBy($sort, $direction)->orderBy('id', $direction)->paginate(min(100, max(10, (int) $request->input('per_page', 15))))->withQueryString();
        if ($request->expectsJson()) {
            return response()->json($trips);
        }

        return view('trips.index', ['trips' => $trips, 'employees' => Personnel::all()->keyBy('id'), 'tripTypes' => TripType::where('is_active', true)->get(), 'vehicleGroups' => Vehicle::distinct()->pluck('vehicle_group'), 'creationKey' => (string) Str::uuid()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['creation_key' => 'required|uuid']);
        $trip = DB::transaction(function () use ($data, $request) {
            DB::table('trips')->insertOrIgnore([
                'trip_id' => 'TRIP-'.Str::ulid(), 'creation_key' => $data['creation_key'],
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                'current_status' => 'Open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $trip = Trip::where('creation_key', $data['creation_key'])->lockForUpdate()->firstOrFail();
            abort_unless((int) $trip->created_by === $request->user()->id, 403);
            $trip->history()->firstOrCreate(['status' => 'Open'], ['created_by' => $request->user()->id]);

            return $trip;
        });

        return $this->success($request, $trip, 'Open trip saved. You can continue this draft later from Trips.');
    }

    public function show(Request $request, Trip $trip)
    {
        $this->authorizeTrip($request, $trip);
        $data = $this->documentData($trip) + [
            'vehicles' => Vehicle::orderBy('body_number')->get(),
            'tripTypes' => TripType::where('is_active', true)->get(),
            'payments' => TripPayment::where('is_active', true)->get(),
            'tripRates' => TripRate::where('is_active', true)->get(),
            'locations' => Location::where('is_active', true)->get(),
            'shippingRates' => ShippingRate::where('is_active', true)->get(),
            'shippingLines' => ShippingLine::where('is_active', true)->orderBy('shipping_line_name')->get(),
        ];
        $data['designations'] = $data['employees']->where('is_active', true)->pluck('position')->filter(fn ($position) => trim((string) $position) !== '')->unique(fn ($position) => mb_strtolower($position))->sort()->values();
        $data['availableCostCodes'] = OtherCost::availableTo($request->user())->orderBy('cost_code')->get()->keyBy('id');
        $fuelCode = $data['availableCostCodes']->first(fn ($code) => strtoupper(trim($code->cost_code)) === 'FUEL_PURCHASE');
        $data['costRows'] = $trip->costs->toArray();
        foreach ($trip->fuelPurchases as $fuel) {
            $data['costRows'][] = [
                'fuel_purchase_id' => $fuel->id, 'cost_code' => $fuelCode?->id,
                'fuel_supplier' => $fuel->fuel_supplier, 'liters_required' => $fuel->liters_required,
                'price_per_liter' => $fuel->price_per_liter, 'cost_amount' => $fuel->fuel_price,
                'remarks' => $fuel->remarks,
            ];
        }
        if ($request->expectsJson()) {
            return response()->json($trip);
        }

        return view('trips.show', $data);
    }

    public function update(SaveTripRequest $request, Trip $trip, TripService $service)
    {
        $this->authorizeTrip($request, $trip);
        DB::transaction(function () use ($request, $trip, $service) {
            $locked = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $service->save($locked, $request->validated(), $request->user()->id);
        });

        return $this->success($request, $trip, 'Trip saved. Document and totals updated.');
    }

    public function dispatch(Request $request, Trip $trip, TripService $service)
    {
        $this->authorizeTrip($request, $trip);
        $reason = $request->validate(['reason' => 'nullable|string|max:2000'])['reason'] ?? null;
        $data = null;
        if ($request->has('version')) {
            abort_unless($request->user()->hasPermission('trips.update'), 403);
            $data = $request->validate((new SaveTripRequest)->rules());
        }
        DB::transaction(function () use ($request, $trip, $service, $data, $reason) {
            $locked = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            if ($data) {
                $service->save($locked, $data, $request->user()->id);
            }
            $service->dispatch($locked, $request->user()->id, $reason);
        });

        return $this->success($request, $trip, 'Truck dispatched. Trip is now read-only.');
    }

    public function complete(Request $request, Trip $trip, TripService $service)
    {
        $this->authorizeTrip($request, $trip);
        $reason = $request->validate(['reason' => 'nullable|string|max:2000'])['reason'] ?? null;
        DB::transaction(function () use ($request, $trip, $service, $reason) {
            $locked = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $service->complete($locked, $request->user()->id, $reason);
        });

        return $this->success($request, $trip, 'Trip completed.');
    }

    public function print(Request $request, Trip $trip)
    {
        $this->authorizeTrip($request, $trip);
        abort_unless(in_array($trip->current_status, ['Dispatched', 'Completed']), 409, 'Only dispatched or completed trips can be printed.');

        return response()->view('trips.print', $this->documentData($trip))->header('Cache-Control', 'no-store, private');
    }

    private function accessible(Request $request)
    {
        return Trip::query()->when(! $request->user()->hasPermission('trips.manage-all'), fn ($q) => $q->where('created_by', $request->user()->id));
    }

    private function authorizeTrip(Request $request, Trip $trip): void
    {
        abort_unless((int) $trip->created_by === $request->user()->id || $request->user()->hasPermission('trips.manage-all'), 403);
    }

    private function documentData(Trip $trip): array
    {
        $trip->load(['personnels', 'segments', 'fuelPurchases', 'costs', 'history']);

        return ['trip' => $trip, 'employees' => Personnel::all()->keyBy('id'), 'suppliers' => FuelSupplier::all()->keyBy('id'), 'costCodes' => OtherCost::all()->keyBy('id'), 'users' => User::whereIn('id', $trip->history->pluck('created_by')->push($trip->approved_by))->get()->keyBy('id'), 'selectedTripRate' => $trip->tariff_snapshot ? (object) $trip->tariff_snapshot : $trip->tripRate, 'financialSummary' => app(TripService::class)->financialSummary($trip)];
    }

    private function success(Request $request, Trip $trip, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => route('trips.show', $trip), 'trip' => $trip->fresh()]);
        }

        return redirect()->route('trips.show', $trip)->with('success', $message);
    }
}
