<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $clients = Client::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($builder) => $builder
                    ->where('client_code', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('full_address', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('barangay', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('building_type', 'like', "%{$search}%")
                    ->orWhere('focal_person', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%"));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('client_name')
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients', 'filters'));
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::create($this->validateClient($request));

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validateClient($request, $client));

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return back()->with('success', 'Client deleted successfully.');
    }

    private function validateClient(Request $request, ?Client $client = null): array
    {
        $request->merge([
            'client_code' => strtoupper(trim((string) $request->input('client_code'))),
            'client_name' => trim((string) $request->input('client_name')),
            'prefix' => $this->trimToNull($request->input('prefix')),
            'full_address' => trim((string) $request->input('full_address')),
            'province' => $this->trimToNull($request->input('province')),
            'city' => $this->trimToNull($request->input('city')),
            'barangay' => $this->trimToNull($request->input('barangay')),
            'email' => $this->trimToNull($request->input('email')),
            'building_type' => $this->trimToNull($request->input('building_type')),
            'focal_person' => trim((string) $request->input('focal_person')),
            'contact_number' => trim((string) $request->input('contact_number')),
            'api_key' => $this->trimToNull($request->input('api_key')),
            'token' => $this->trimToNull($request->input('token')),
            'endpoint' => $this->trimToNull($request->input('endpoint')),
        ]);

        return $request->validate([
            'client_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('clients', 'client_code')->ignore($client)],
            'client_name' => ['required', 'string', 'max:150'],
            'prefix' => ['nullable', 'string', 'max:30', Rule::unique('clients', 'prefix')->ignore($client)],
            'full_address' => ['required', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'building_type' => ['nullable', 'string', 'max:100'],
            'focal_person' => ['required', 'string', 'max:150'],
            'contact_number' => ['required', 'string', 'max:50'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'token' => ['nullable', 'string', 'max:2000'],
            'endpoint' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function trimToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
