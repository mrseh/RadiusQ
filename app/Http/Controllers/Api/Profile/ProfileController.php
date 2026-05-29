<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\CompanyLicense;
use App\Models\Perusahaan;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends BaseApiController
{
    /**
     * GET /profile/ajax/profile
     * Get current user profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        $perusahaan = Perusahaan::first();
        $companyLicense = CompanyLicense::with(['licensePackage:id,nama,paket,durasi_hari,harga'])
            ->where('is_active', true)
            ->orderByDesc('tanggal_expired')
            ->first();

        return $this->ok([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'whatsapp' => $user->whatsapp,
                'status' => $user->status,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'perusahaan' => $perusahaan ? [
                'id' => $perusahaan->id,
                'nama' => $perusahaan->nama,
                'alamat' => $perusahaan->alamat,
                'telepon' => $perusahaan->whatsapp,
                'email' => $perusahaan->email,
            ] : null,
            'company_license' => $companyLicense ? [
                'id' => $companyLicense->id,
                'status' => $companyLicense->status,
                'tanggal_mulai' => $companyLicense->tanggal_mulai?->toDateString(),
                'tanggal_expired' => $companyLicense->tanggal_expired?->toDateString(),
                'package' => $companyLicense->licensePackage?->nama,
                'days_remaining' => $companyLicense->tanggal_expired
                    ? max(0, now()->diffInDays($companyLicense->tanggal_expired, false))
                    : 0,
            ] : null,
        ]);
    }

    /**
     * GET /profile/ajax/license
     * Get license info for current company.
     */
    public function license(Request $request): JsonResponse
    {
        $license = CompanyLicense::with(['licensePackage:id,nama,paket,durasi_hari,harga', 'perusahaan:id,nama'])
            ->where('is_active', true)
            ->orderByDesc('tanggal_expired')
            ->first();

        if (!$license) {
            return $this->ok(null, 'Tidak ada license ditemukan.');
        }

        $daysRemaining = $license->tanggal_expired
            ? max(0, (int) now()->diffInDays($license->tanggal_expired, false))
            : 0;

        $isExpired = $license->tanggal_expired && $license->tanggal_expired->isPast();

        return $this->ok([
            'id' => $license->id,
            'perusahaan' => $license->perusahaan?->nama,
            'license_key' => $license->license_key,
            'status' => $license->status,
            'tanggal_mulai' => $license->tanggal_mulai?->toDateString(),
            'tanggal_expired' => $license->tanggal_expired?->toDateString(),
            'is_expired' => $isExpired,
            'days_remaining' => $daysRemaining,
            'licensed_url' => $license->licensed_url,
            'package' => $license->licensePackage ? [
                'nama' => $license->licensePackage->nama,
                'paket' => $license->licensePackage->paket,
                'durasi_hari' => $license->licensePackage->durasi_hari,
                'harga' => (float) $license->licensePackage->harga,
            ] : null,
        ]);
    }

    /**
     * PUT /profile/ajax/update
     * Update current user profile (name, email).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update($data);

        Log::create([
            'module' => 'Profile',
            'action' => 'update_profile',
            'deskripsi' => 'Memperbarui profil pengguna.',
            'id_user' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 'Profil berhasil diperbarui.');
    }

    /**
     * GET /profile/ajax/password
     * Show change password form data (just the user info — actual change handled via separate endpoint).
     */
    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'user_id' => $user->id,
            'has_password' => !empty($user->password),
        ]);
    }

    /**
     * GET /profile/ajax/license-renew
     * License renewal info / purchase link for dashboard.
     */
    public function licenseRenew(Request $request): JsonResponse
    {
        $license = CompanyLicense::with(['licensePackage:id,nama,paket,durasi_hari,harga'])
            ->where('is_active', true)
            ->orderByDesc('tanggal_expired')
            ->first();

        $daysRemaining = $license?->tanggal_expired
            ? max(0, (int) now()->diffInDays($license->tanggal_expired, false))
            : 0;

        $isExpired = $license?->tanggal_expired?->isPast() ?? true;
        $isNearExpiry = $daysRemaining <= 30 && !$isExpired;

        return $this->ok([
            'license' => $license ? [
                'id' => $license->id,
                'license_key' => $license->license_key,
                'status' => $license->status,
                'tanggal_mulai' => $license->tanggal_mulai?->toDateString(),
                'tanggal_expired' => $license->tanggal_expired?->toDateString(),
                'days_remaining' => $daysRemaining,
                'is_expired' => $isExpired,
                'is_near_expiry' => $isNearExpiry,
                'package' => $license->licensePackage?->nama,
            ] : null,
            'renewal_url' => config('app.license_renewal_url', 'https://yourlicenseprovider.com/renew'),
            'contact_whatsapp' => config('app.whatsapp_support', '+6281234567890'),
            'pricing' => [
                'monthly' => 150000,
                'quarterly' => 400000,
                'yearly' => 1400000,
            ],
        ]);
    }
}