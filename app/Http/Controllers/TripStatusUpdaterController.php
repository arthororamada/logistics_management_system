<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTripStatusRequest;
use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripStatusUpdaterController extends Controller
{
    public function index()
    {
        return view('trip-status-updaters.index');
    }

    public function search(Request $request)
    {
        if (is_string($request->input('trip_id'))) {
            $request->merge(['trip_id' => trim($request->input('trip_id'))]);
        }
        $data = $request->validate(['trip_id' => 'required|string|max:50']);
        try {
            $trip = $this->accessible($request)->where('trip_id', $data['trip_id'])->first();
        } catch (QueryException $error) {
            report($error);

            return response()->json(['message' => 'Unable to search trips. Please try again.'], 500);
        }
        if (! $trip) {
            return response()->json(['message' => 'Trip does not exist.'], 404);
        }

        return response()->json(['trip' => $this->tripData($trip)]);
    }

    public function update(UpdateTripStatusRequest $request, TripService $service)
    {
        $data = $request->validated();
        try {
            $trip = DB::transaction(function () use ($request, $service, $data) {
                $trip = $this->accessible($request)->where('trip_id', $data['trip_id'])->lockForUpdate()->first();
                abort_unless($trip, 404, 'Trip does not exist.');
                $service->updateStatus($trip, $data['new_status'], $data['reason'], (int) $data['version'], $request->user()->id);

                return $trip;
            });
        } catch (QueryException $error) {
            report($error);

            return response()->json(['message' => 'Unable to update trip status. No changes were saved. Please try again.'], 500);
        }

        return response()->json(['message' => 'Trip status updated successfully.', 'trip' => $this->tripData($trip)]);
    }

    private function accessible(Request $request)
    {
        return Trip::query()->when(! $request->user()->hasPermission('trips.manage-all'), fn ($query) => $query->where('created_by', $request->user()->id));
    }

    private function tripData(Trip $trip): array
    {
        return $trip->only(['trip_id', 'current_status', 'van_number', 'version']) + ['created_at' => $trip->created_at->format('M d, Y H:i')];
    }
}
