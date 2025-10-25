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
        return ApiResponse::success(__('messages.roles_fetched'), $roles);
    }

    public function store(Request $request)
    {
        try {
            $request->validate(['name' => 'required|string|unique:roles,name']);
            $role = Role::create(['name' => $request->name]);
            return ApiResponse::success(__('messages.role_created'), $role);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return ApiResponse::error(__('messages.validation_failed'), $ve->errors(), 422);
        }
    }

    public function update(Request $request, Role $role)
    {
        try {
            $request->validate(['name' => 'required|string|unique:roles,name,' . $role->role_id]);
            $role->update(['name' => $request->name]);
            return ApiResponse::success(__('messages.role_updated'), $role);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return ApiResponse::error(__('messages.validation_failed'), $ve->errors(), 422);
        }
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return ApiResponse::success(__('messages.role_deleted'));
    }
}
