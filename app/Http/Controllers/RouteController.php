<?php

namespace App\Http\Controllers;

use App\Models\Route as RouteRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RouteController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->dataTable($request);
        }

        return view('routes.index');
    }

    public function create(): View
    {
        return view('routes.create', ['routeRecord' => new RouteRecord()]);
    }

    public function store(Request $request): RedirectResponse
    {
        RouteRecord::create($this->validateRoute($request));

        return redirect()->route('routes.index')->with('success', 'Route created successfully.');
    }

    public function edit(RouteRecord $route): View
    {
        return view('routes.edit', ['routeRecord' => $route]);
    }

    public function update(Request $request, RouteRecord $route): RedirectResponse
    {
        $route->update($this->validateRoute($request, $route));

        return redirect()->route('routes.index')->with('success', 'Route updated successfully.');
    }

    public function destroy(RouteRecord $route): RedirectResponse
    {
        $route->delete();

        return back()->with('success', 'Route deleted successfully.');
    }

    private function dataTable(Request $request): JsonResponse
    {
        $query = RouteRecord::query();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', $request->input('search', '')));

        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('province', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('brgy', 'like', "%{$search}%"));
        }
        if ($request->input('status') === 'active') $query->where('is_active', true);
        if ($request->input('status') === 'inactive') $query->where('is_active', false);

        $filtered = (clone $query)->count();
        $columns = ['province', 'city', 'brgy', 'longitued', 'latitude', 'is_active'];
        $orderColumn = $columns[(int) $request->input('order.0.column', 0)] ?? 'province';
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $length = min(max((int) $request->input('length', 25), 1), 100);
        $start = max((int) $request->input('start', 0), 0);

        $routes = $query->orderBy($orderColumn, $orderDirection)->offset($start)->limit($length)->get();
        $canEdit = $request->user()?->hasPermission('routes.update');
        $canDelete = $request->user()?->hasPermission('routes.delete');

        $data = $routes->map(function (RouteRecord $route) use ($canEdit, $canDelete) {
            return [
            'province' => e($route->province),
            'city' => e($route->city),
            'brgy' => e($route->brgy),
            'longitued' => $route->longitued,
            'latitude' => $route->latitude,
            'status' => '<span class="status-badge '.($route->is_active ? 'status-active' : 'status-inactive').'">'.($route->is_active ? 'Active' : 'Inactive').'</span>',
            'actions' => view('routes.partials.actions', compact('route', 'canEdit', 'canDelete'))->render(),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    private function validateRoute(Request $request, ?RouteRecord $route = null): array
    {
        $request->merge([
            'province' => trim((string) $request->input('province')),
            'city' => trim((string) $request->input('city')),
            'brgy' => trim((string) $request->input('brgy')),
        ]);

        return $request->validate([
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'brgy' => ['required', 'string', 'max:100'],
            'longitued' => ['required', 'numeric', 'between:-180,180'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
