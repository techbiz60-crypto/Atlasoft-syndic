<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lets an admin customize, per residence, which of the non-admin roles
 * (trésorier, conseil, copropriétaire) can do what — admin itself is never
 * listed here since it's always fully allowed.
 */
class RolePermissionController extends Controller
{
    private const EDITABLE_ROLES = [Role::Tresorier, Role::Conseil, Role::Coproprietaire];

    public function index(Request $request): JsonResponse
    {
        $grants = RolePermission::with('permission')
            ->where('residence_id', $request->user()->residence_id)
            ->get()
            ->groupBy(fn (RolePermission $rolePermission) => $rolePermission->role->value)
            ->map(fn ($rows) => $rows->pluck('permission.key')->values());

        return response()->json([
            'data' => [
                'permissions' => Permission::orderBy('group')->orderBy('label')->get(['key', 'label', 'group']),
                'roles' => collect(self::EDITABLE_ROLES)->map(fn (Role $role) => $role->value)->values(),
                'grants' => collect(self::EDITABLE_ROLES)->mapWithKeys(
                    fn (Role $role) => [$role->value => $grants->get($role->value, collect())->values()],
                ),
            ],
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request): JsonResponse
    {
        $residenceId = $request->user()->residence_id;
        $permissionIdsByKey = Permission::pluck('id', 'key');

        DB::transaction(function () use ($request, $residenceId, $permissionIdsByKey) {
            foreach (self::EDITABLE_ROLES as $role) {
                $keys = $request->input("grants.{$role->value}", []);

                RolePermission::where('residence_id', $residenceId)->where('role', $role->value)->delete();

                foreach ($keys as $key) {
                    RolePermission::create([
                        'residence_id' => $residenceId,
                        'role' => $role,
                        'permission_id' => $permissionIdsByKey[$key],
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Permissions mises à jour.']);
    }
}
