<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required_without:username|email',
            'username' => 'required_without:email|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $field = $request->filled('email') ? 'email' : 'username';
        $credential = [$field => $request->input($field), 'password' => $request->password];

        $user = User::where($field, $request->input($field))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ], 403);
        }

        $deviceName = $request->input('device_name', $request->userAgent() ?? 'unknown');

        DB::table('personal_access_tokens')->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'whatsapp' => $user->whatsapp,
                    'status' => $user->status,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'whatsapp' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'whatsapp' => $validated['whatsapp'] ?? null,
            'role' => 'reseller',
            'status' => 'active',
        ]);

        $deviceName = $request->input('device_name', $request->userAgent() ?? 'unknown');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'whatsapp' => $user->whatsapp,
                    'status' => $user->status,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            Password::sendResetLink(['email' => $request->email]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email tersebut terdaftar, link reset password akan dikirim.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $status = Password::reset(
            $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            ]),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['success' => true, 'message' => 'Password berhasil direset.'], 200);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 400);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout dari semua device lain berhasil.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'whatsapp' => $user->whatsapp,
                'status' => $user->status,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:100', 'unique:users,email,' . $user->id],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        if (isset($validated['current_password']) && isset($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password saat ini salah.',
                ], 400);
            }
            $user->password = Hash::make($validated['password']);
            unset($validated['current_password'], $validated['password']);
        }

        $user->fill(array_filter($validated, fn($v) => $v !== null))->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'whatsapp' => $user->whatsapp,
                'status' => $user->status,
            ],
        ]);
    }

    public function emailStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->email), $hash)) {
            return response()->json(['success' => false, 'message' => 'Link tidak valid.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => true, 'message' => 'Email sudah terverifikasi sebelumnya.'], 200);
        }

        $user->markEmailAsVerified();

        return response()->json(['success' => true, 'message' => 'Email berhasil diverifikasi.'], 200);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => false, 'message' => 'Email sudah terverifikasi.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['success' => true, 'message' => 'Email verifikasi telah dikirim ulang.']);
    }
}