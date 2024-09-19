<?php

namespace App\Http\Controllers;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // Create a new role
public function storeRole(Request $request)
{
    $role = Role::create(['name' => $request->name]);
    return response()->json(['message' => 'Role created successfully', 'data' => $role]);
}

// Update a role
public function updateRole(Request $request, $id)
{
    $role = Role::find($id);
    $role->update(['name' => $request->name]);
    return response()->json(['message' => 'Role updated successfully', 'data' => $role]);
}

// Delete a role
public function destroyRole($id)
{
    $role = Role::find($id);
    $role->delete();
    return response()->json(['message' => 'Role deleted successfully']);
}

// Get a list of roles
public function getAllRole()
{
    $roles = Role::all();
    return response()->json(['data' => $roles]);
}






}
