<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $locations = Location::with(['createdBy', 'updatedBy'])
            ->whereNull('is_deleted')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder
                ->where('location_code', 'like', "%{$search}%")
                ->orWhere('location_name', 'like', "%{$search}%")))
            ->orderBy('location_name')
            ->paginate(15)
            ->withQueryString();

        return view('locations.index', compact('locations', 'filters'));
    }

    public function create(): View
    {
        return view('locations.create', ['location' => new Location()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;
        Location::create([...$this->validateLocation($request), 'created_by' => $userId, 'updated_by' => $userId]);

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    public function edit(Location $location): View
    {
        abort_if($location->is_deleted, 404);

        return view('locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        abort_if($location->is_deleted, 404);
        $location->update([...$this->validateLocation($request, $location), 'updated_by' => $request->user()->id]);

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        abort_if($location->is_deleted, 404);
        $location->update(['is_deleted' => now(), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Location deleted successfully.');
    }

    private function validateLocation(Request $request, ?Location $location = null): array
    {
        $request->merge([
            'location_code' => strtoupper(trim((string) $request->input('location_code'))),
            'location_name' => trim((string) $request->input('location_name')),
        ]);

        return $request->validate([
            'location_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('locations', 'location_code')->ignore($location)],
            'location_name' => ['required', 'string', 'max:150'],
            'location_long' => ['required', 'numeric', 'between:-180,180'],
            'location_lat' => ['required', 'numeric', 'between:-90,90'],
        ]);
    }
}
