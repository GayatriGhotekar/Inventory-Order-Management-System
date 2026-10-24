<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get()->map(function (Permission $permission) {
            $permission->roles = DB::table('role_permissions')
                ->where('permission_id', $permission->id)->orderBy('role')->pluck('role');

            return $permission;
        });

        return response()->json(['success' => true, 'message' => 'Permissions retrieved successfully', 'data' => $permissions]);
    }
}
