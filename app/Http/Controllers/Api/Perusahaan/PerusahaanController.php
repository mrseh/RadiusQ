<?php

namespace App\Http\Controllers\Api\Perusahaan;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Perusahaan\SaveCompanyRequest;
use App\Http\Requests\Perusahaan\StoreBankAccountRequest;
use App\Http\Requests\Perusahaan\UpdateBankAccountRequest;
use App\Models\Perusahaan;
use App\Models\Rekening;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerusahaanController extends BaseApiController
{
    /**
     * GET /perusahaan/company/get
     * Get company info for the authenticated user's company.
     */
    public function getCompany(Request $request): JsonResponse
    {
        $user = $request->user();

        $perusahaan = Perusahaan::first();

        if (!$perusahaan) {
            return $this->ok(null, 'Data perusahaan belum ada.');
        }

        return $this->ok($perusahaan, 'Data perusahaan berhasil dimuat.');
    }

    /**
     * POST /perusahaan/company/save
     * Save / update company info.
     */
    public function saveCompany(SaveCompanyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $perusahaan = Perusahaan::first();

        if ($perusahaan) {
            $perusahaan->update($data);
            $message = 'Data perusahaan berhasil diperbarui.';
        } else {
            $perusahaan = Perusahaan::create($data);
            $message = 'Data perusahaan berhasil disimpan.';
        }

        Log::create([
            'module' => 'Perusahaan',
            'action' => $perusahaan->wasRecentlyCreated ? 'create' : 'update',
            'deskripsi' => $message,
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok($perusahaan, $message);
    }

    /**
     * GET /perusahaan/bank/list
     * List bank accounts for the company.
     */
    public function listBank(Request $request): JsonResponse
    {
        $banks = Rekening::with(['perusahaan:id,nama'])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return $this->ok($banks);
    }

    /**
     * POST /perusahaan/bank
     * Create a new bank account.
     */
    public function storeBank(StoreBankAccountRequest $request): JsonResponse
    {
        $data = $request->validated();

        $perusahaan = Perusahaan::first();
        if (!$perusahaan) {
            return $this->error('Data perusahaan belum ada. Silakan simpan data perusahaan terlebih dahulu.', 422);
        }

        $data['id_perusahaan'] = $perusahaan->id;

        // If this is the first bank or is_default is true, ensure it's the only default
        if (!empty($data['is_default'])) {
            Rekening::where('id_perusahaan', $perusahaan->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $rekening = Rekening::create($data);

        Log::create([
            'module' => 'Perusahaan',
            'action' => 'create_bank',
            'deskripsi' => 'Menambah rekening bank: ' . ($data['bank'] ?? '') . ' - ' . ($data['norek'] ?? ''),
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok($rekening, 'Rekening bank berhasil ditambahkan.', 201);
    }

    /**
     * PUT /perusahaan/bank/{id}
     * Update a bank account.
     */
    public function updateBank(UpdateBankAccountRequest $request, int $id): JsonResponse
    {
        $rekening = Rekening::find($id);
        if (!$rekening) {
            return $this->error('Rekening tidak ditemukan.', 404);
        }

        $rekening->update($request->validated());

        Log::create([
            'module' => 'Perusahaan',
            'action' => 'update_bank',
            'deskripsi' => 'Mengubah rekening bank ID: ' . $id,
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok($rekening, 'Rekening bank berhasil diperbarui.');
    }

    /**
     * DELETE /perusahaan/bank/{id}
     * Delete a bank account.
     */
    public function deleteBank(Request $request, int $id): JsonResponse
    {
        $rekening = Rekening::find($id);
        if (!$rekening) {
            return $this->error('Rekening tidak ditemukan.', 404);
        }

        $bankInfo = $rekening->bank . ' - ' . $rekening->norek;
        $rekening->delete();

        Log::create([
            'module' => 'Perusahaan',
            'action' => 'delete_bank',
            'deskripsi' => 'Menghapus rekening bank: ' . $bankInfo,
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok(null, 'Rekening bank berhasil dihapus.');
    }

    /**
     * PUT /perusahaan/bank/{id}/default
     * Set bank account as default.
     */
    public function setDefaultBank(Request $request, int $id): JsonResponse
    {
        $rekening = Rekening::find($id);
        if (!$rekening) {
            return $this->error('Rekening tidak ditemukan.', 404);
        }

        DB::transaction(function () use ($rekening) {
            Rekening::where('id_perusahaan', $rekening->id_perusahaan)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $rekening->is_default = true;
            $rekening->save();
        });

        Log::create([
            'module' => 'Perusahaan',
            'action' => 'set_default_bank',
            'deskripsi' => 'Mengubah rekening utama ke: ' . $rekening->bank . ' - ' . $rekening->norek,
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok($rekening, 'Rekening utama berhasil diubah.');
    }
}