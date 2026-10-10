<?php

namespace App\Http\Controllers;

use App\Models\ShippingLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingLineController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $shippingLines = ShippingLine::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder->where('shipping_line_name', 'like', "%{$search}%")->orWhere('billing_address', 'like', "%{$search}%")->orWhere('focal_person', 'like', "%{$search}%")->orWhere('contact_number', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('shipping_line_name')->paginate(15)->withQueryString();
        return view('shipping-lines.index', compact('shippingLines', 'filters'));
    }

    public function create(): View { return view('shipping-lines.create', ['shippingLine' => new ShippingLine()]); }

    public function store(Request $request): RedirectResponse
    {
        ShippingLine::create($this->validateShippingLine($request));
        return redirect()->route('shipping-lines.index')->with('success', 'Shipping line created successfully.');
    }

    public function edit(ShippingLine $shippingLine): View { return view('shipping-lines.edit', compact('shippingLine')); }

    public function update(Request $request, ShippingLine $shippingLine): RedirectResponse
    {
        $shippingLine->update($this->validateShippingLine($request, $shippingLine));
        return redirect()->route('shipping-lines.index')->with('success', 'Shipping line updated successfully.');
    }

    public function destroy(ShippingLine $shippingLine): RedirectResponse
    {
        $shippingLine->delete();
        return back()->with('success', 'Shipping line deleted successfully.');
    }

    private function validateShippingLine(Request $request, ?ShippingLine $shippingLine = null): array
    {
        $request->merge([
            'shipping_line_name' => trim((string) $request->input('shipping_line_name')),
            'billing_address' => trim((string) $request->input('billing_address')),
            'focal_person' => trim((string) $request->input('focal_person')),
            'contact_number' => trim((string) $request->input('contact_number')),
        ]);
        return $request->validate([
            'shipping_line_name' => ['required', 'string', 'max:150', Rule::unique('shipping_lines', 'shipping_line_name')->ignore($shippingLine)],
            'billing_address' => ['required', 'string', 'max:1000'],
            'focal_person' => ['required', 'string', 'max:150'],
            'contact_number' => ['required', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
