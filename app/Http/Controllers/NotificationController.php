<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use App\Services\MaintenanceAlertService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, MaintenanceAlertService $alerts): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([Maintenance::STATUS_WARNING, Maintenance::STATUS_VIOLATION])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $records = $alerts->all();
        $counts = $alerts->counts();
        if ($filters['status'] ?? null) {
            $records = $records->where('current_status', $filters['status'])->values();
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = mb_strtolower($search);
            $records = $records->filter(fn (Maintenance $maintenance) => str_contains(mb_strtolower(implode(' ', [
                $maintenance->vehicle->plate_number,
                $maintenance->vehicle->body_number,
                $maintenance->policy->maintenance_code,
                $maintenance->policy->maintenance_description,
            ])), $needle))->values();
        }

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $notifications = new LengthAwarePaginator(
            $records->slice(($page - 1) * $perPage, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('notifications.index', compact('notifications', 'counts', 'filters'));
    }
}
