<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    // Retrieve permissions for all roles
    public function getAllRoleWisePermissions()
    {
        $roles = Role::with('permissions')->get();

        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role->name] = $role->permissions;
        }

        return response()->json(['data' => $rolePermissions]);
    }

    // Retrieve permissions for a specific role
    public function getSingleRoleWisePermission($roleName)
    {
        $role = Role::findByName($roleName);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $permissions = $role->permissions;

        return response()->json(['data' => $permissions]);
    }

    // Update permissions for a specific role
    public function updateRoleWisePermission(Request $request, $roleName)
    {
        $role = Role::findByName($roleName);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $permissions = $request->input('permissions');
        $role->syncPermissions($permissions);

        return response()->json(['message' => 'Role permissions updated successfully']);
    }

    public function updateAllRolesPermissions(Request $request)
    {
        $data = $request->json()->all();

        foreach ($data['roles'] as $roleData) {
            $roleName = $roleData['name'];
            $permissions = $roleData['permissions'];

            $role = Role::findByName($roleName);

            if (!$role) {
                // Handle errors or continue with the next role
                continue;
            }

            $role->syncPermissions($permissions);
        }

        return response()->json(['message' => 'Permissions updated for all roles']);

        // body pass value for multiple
        // {
        //     "roles": [
        //         {
        //             "name": "role1",
        //             "permissions": ["permission1", "permission2"]
        //         },
        //         {
        //             "name": "role2",
        //             "permissions": ["permission3", "permission4"]
        //         }
        //     ]
        // }
        
    }

}
