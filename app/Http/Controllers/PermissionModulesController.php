<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PermissionModule;

class PermissionModulesController extends Controller
{
    public function getGroupedPermissions()
    {
        $modules = PermissionModule::with('permissions')->get();

        return response()->json(['modules' => $modules]);
    }
}
