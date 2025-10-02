<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Http\Responses\ApiResponse;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return ApiResponse::success('Roles fetched', $roles);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);

        $role = Role::create(['name' => $request->name]);
        return ApiResponse::success('Role created', $role);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|string|unique:roles,name,' . $role->role_id]);

        $role->update(['name' => $request->name]);
        return ApiResponse::success('Role updated', $role);
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return ApiResponse::success('Role deleted');
    }
}
