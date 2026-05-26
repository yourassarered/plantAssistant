<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminUpdateUserRequest;
use App\Http\Requests\Api\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\ModeratorAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly ModeratorAuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('sort_by_rank')) {
            $query->orderBy('rank', 'desc');
        }

        $users = $query->with('role')->paginate(min($request->integer('per_page', 15), 100));

        return UserResource::collection($users);
    }

    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorize('updateProfile', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return new UserResource($user->fresh('role'));
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('manage', User::class);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        $this->audit->log(
            actor: $request->user(),
            action: 'user.delete',
            targetType: User::class,
            targetId: (int) $id,
            payload: null,
            request: $request
        );

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function adminUpdate(AdminUpdateUserRequest $request, $id)
    {
        $this->authorize('manage', User::class);

        $user = User::findOrFail($id);
        $validated = $request->validated();

        if ($user->id === $request->user()->id && $validated['role_name'] !== $user->role?->name) {
            return response()->json([
                'message' => 'You cannot change your own role',
            ], 403);
        }

        $user = DB::transaction(function () use ($validated, $user) {
            $role = Role::where('name', $validated['role_name'])->firstOrFail();

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->rank = $validated['rank'];
            $user->role_id = $role->id;

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            return $user;
        });

        $this->audit->log(
            actor: $request->user(),
            action: 'user.update',
            targetType: User::class,
            targetId: $user->id,
            payload: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'rank' => $validated['rank'],
                'role_name' => $validated['role_name'],
                'password_changed' => ! empty($validated['password']),
            ],
            request: $request
        );

        return new UserResource($user->fresh('role'));
    }

    public function updateRole(UpdateUserRoleRequest $request, $id)
    {
        $this->authorize('manage', User::class);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own role',
            ], 403);
        }

        $user = DB::transaction(function () use ($request, $user) {
            $role = Role::where('name', $request->string('role_name')->value())->firstOrFail();
            $user->role_id = $role->id;
            $user->save();

            return $user;
        });

        $this->audit->log(
            actor: $request->user(),
            action: 'user.role_update',
            targetType: User::class,
            targetId: $user->id,
            payload: ['role_name' => $request->string('role_name')->value()],
            request: $request
        );

        return new UserResource($user->fresh('role'));
    }
}
