<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Список пользователей (для администратора или ленты)
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Фильтр по роли
        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Поиск по имени или email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Сортировка по рангу
        if ($request->has('sort_by_rank')) {
            $query->orderBy('rank', 'desc');
        }

        $users = $query->with('role')->paginate(min($request->integer('per_page', 15), 100));

        return UserResource::collection($users);
    }

    /**
     * Просмотр профиля пользователя
     */
    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);

        return new UserResource($user);
    }

    /**
     * Обновление собственного профиля
     */
    public function update(Request $request)
    {
        $user = $request->user();

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

        return new UserResource($user);
    }

    /**
     * Удаление пользователя (администратор)
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeAdmin($request);

        $user = User::findOrFail($id);

        // Нельзя удалить себя
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Изменение роли пользователя (администратор)
     */
    public function updateRole(Request $request, $id)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'role_name' => 'required|string|exists:roles,name',
        ]);

        $user = User::findOrFail($id);

        // Нельзя менять свою роль
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own role',
            ], 403);
        }

        $role = Role::where('name', $validated['role_name'])->first();
        $user->role_id = $role->id;
        $user->save();

        return new UserResource($user);
    }

    /**
     * Проверка прав администратора
     */
    private function authorizeAdmin(Request $request)
    {
        if ($request->user()->role->name !== 'admin') {
            abort(403, 'Only administrators can perform this action');
        }
    }
}
