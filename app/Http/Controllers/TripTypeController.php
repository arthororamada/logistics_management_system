<?php

namespace App\Http\Controllers;

use App\Models\TripType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripTypeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $tripTypes = TripType::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('trip_type', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('trip_type')->paginate(15)->withQueryString();
        return view('trip-types.index', compact('tripTypes', 'filters'));
    }

    public function create(): View { return view('trip-types.create', ['tripType' => new TripType()]); }

    public function store(Request $request): RedirectResponse
    {
        TripType::create($this->validateTripType($request));
        return redirect()->route('trip-types.index')->with('success', 'Trip type created successfully.');
    }

    public function edit(TripType $tripType): View { return view('trip-types.edit', compact('tripType')); }

    public function update(Request $request, TripType $tripType): RedirectResponse
    {
        $tripType->update($this->validateTripType($request, $tripType));
        return redirect()->route('trip-types.index')->with('success', 'Trip type updated successfully.');
    }

    public function destroy(TripType $tripType): RedirectResponse
    {
        $tripType->delete();
        return back()->with('success', 'Trip type deleted successfully.');
    }

    private function validateTripType(Request $request, ?TripType $tripType = null): array
    {
        $request->merge(['trip_type' => trim((string) $request->input('trip_type'))]);
        return $request->validate([
            'trip_type' => ['required', 'string', 'max:100', Rule::unique('trip_types', 'trip_type')->ignore($tripType)],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
