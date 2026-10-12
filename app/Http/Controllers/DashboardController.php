<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardAnalytics $analytics): View
    {
        $filters = $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m'],
            'year' => ['sometimes', 'required', 'integer', 'between:2000,2100'],
        ]);

        return view('dashboard', $analytics->data(
            $filters['month'] ?? now()->format('Y-m'),
            (int) ($filters['year'] ?? now()->year),
        ));
    }
}
