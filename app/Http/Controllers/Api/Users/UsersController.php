<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Users\StoreUserRequest;
use App\Models\User;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends BaseApiController
{
    /**
     * GET /users/ajax
     * Paginated user list with optional role filter.
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = User::query()
            ->when($request->filled('role'), fn($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->get('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => collect($paginator->items())->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'whatsapp' => $user->whatsapp,
                'status' => $user->status,
                'created_at' => $user->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /users
     * Base view — redirect to ajax listing.
     */
    public function index(): JsonResponse
    {
        return $this->ok(null, 'Gunakan /users/ajax untuk list.');
    }

    /**
     * POST /users
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);

        Log::create([
            'module' => 'Users',
            'action' => 'create',
            'deskripsi' => "Membuat user baru: {$user->name} ({$user->role})",
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        /**
     * PUT /users/:id
     * Update user.
     */
    public function update(StoreUserRequest $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('User tidak ditemukan', 404);
        }

        $data = $request->validated();
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        Log::create([
            'module' => 'Users',
            'action' => 'update',
            'deskripsi' => "Memperbarui user: {$user->name}",
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok($user->fresh()->only(['id', 'name', 'username', 'email', 'role', 'whatsapp', 'status']), 'User berhasil diperbarui.');
    }

    /**
     * DELETE /users/:id
     * Delete user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('User tidak ditemukan', 404);
        }

        if ($user->id === $request->user()->id) {
            return $this->error('Tidak dapat menghapus akun sendiri.', 422);
        }

        $name = $user->name;
        $user->delete();

        Log::create([
            'module' => 'Users',
            'action' => 'delete',
            'deskripsi' => "Menghapus user: {$name}",
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok(null, 'User berhasil dihapus.');
    }
}