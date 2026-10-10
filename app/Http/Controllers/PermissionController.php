<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissionGroups = Permission::withCount('roles')->orderBy('group')->orderBy('name')->get()->groupBy('group');
        return view('permissions.index', compact('permissionGroups'));
    }
}
