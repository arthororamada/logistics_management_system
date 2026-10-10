<?php

namespace App\Http\Controllers;

use App\Models\OtherCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OtherCostController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $otherCosts = OtherCost::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder->where('cost_code', 'like', "%{$search}%")->orWhere('cost_name', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('cost_name')->paginate(15)->withQueryString();
        return view('other-costs.index', compact('otherCosts', 'filters'));
    }

    public function create(): View { return view('other-costs.create', ['otherCost' => new OtherCost()]); }

    public function store(Request $request): RedirectResponse
    {
        OtherCost::create($this->validateOtherCost($request));
        return redirect()->route('other-costs.index')->with('success', 'Other cost created successfully.');
    }

    public function edit(OtherCost $otherCost): View { return view('other-costs.edit', compact('otherCost')); }

    public function update(Request $request, OtherCost $otherCost): RedirectResponse
    {
        $otherCost->update($this->validateOtherCost($request, $otherCost));
        return redirect()->route('other-costs.index')->with('success', 'Other cost updated successfully.');
    }

    public function destroy(OtherCost $otherCost): RedirectResponse
    {
        $otherCost->delete();
        return back()->with('success', 'Other cost deleted successfully.');
    }

    private function validateOtherCost(Request $request, ?OtherCost $otherCost = null): array
    {
        $request->merge(['cost_code' => strtoupper(trim((string) $request->input('cost_code'))), 'cost_name' => trim((string) $request->input('cost_name'))]);
        return $request->validate([
            'cost_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('other_costs', 'cost_code')->ignore($otherCost)],
            'cost_name' => ['required', 'string', 'max:150'],
            'is_cash_release' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
