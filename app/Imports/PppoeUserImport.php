<?php

namespace App\Imports;

use App\Models\Mikrotik;
use App\Models\Odp;
use App\Models\PopArea;
use App\Models\PppoeUser;
use App\Models\Profile;
use App\Models\Reseller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PppoeUserImport implements ToModel, WithHeadingRow, WithValidation
{
    protected int $importedCount = 0;

    protected int $failedCount = 0;

    protected array $errors = [];

    public function model(array $row): ?PppoeUser
    {
        $username = trim($row['username'] ?? '');
        $password = trim($row['password'] ?? '');

        if (empty($username)) {
            $this->failedCount++;
            $this->errors[] = 'Row missing username';

            return null;
        }

        $data = [
            'username' => $username,
            'password' => $password,
            'ip_address' => trim($row['ip_address'] ?? ''),
            'id_pelanggan' => trim($row['id_pelanggan'] ?? ''),
            'nama' => trim($row['nama_lengkap'] ?? ''),
            'whatsapp' => trim($row['nomor_wa'] ?? ''),
            'alamat' => trim($row['alamat'] ?? ''),
            'tipe_user' => trim($row['type'] ?? 'individu'),
            'jenis_tagihan' => trim($row['jenis_pembayaran'] ?? 'manual'),
            'siklus_tagihan' => trim($row['siklus_tagihan'] ?? 'bulanan'),
            'koordinat' => $this->buildKoordinat($row),
            'enable_billing' => $this->parseBool($row['billing'] ?? null),
        ];

        // Lookup id_profile by nama
        $profileName = trim($row['profile'] ?? '');
        if (! empty($profileName)) {
            $profile = Profile::where('nama', $profileName)->where('tipe', 'pppoe')->first();
            if ($profile) {
                $data['id_profile'] = $profile->id;
            }
        }

        // Lookup id_nas by nama
        $nasName = trim($row['nama_nas'] ?? '');
        if (! empty($nasName)) {
            $nas = Mikrotik::where('nama', $nasName)->first();
            if ($nas) {
                $data['id_nas'] = $nas->id;
            }
        }

        // Lookup id_pop by nama
        $popName = trim($row['pop'] ?? '');
        if (! empty($popName)) {
            $pop = PopArea::where('nama', $popName)->first();
            if ($pop) {
                $data['id_pop'] = $pop->id;
            }
        }

        // Lookup id_odp by nama
        $odpName = trim($row['odp'] ?? '');
        if (! empty($odpName)) {
            $odp = Odp::where('nama', $odpName)->first();
            if ($odp) {
                $data['id_odp'] = $odp->id;
            }
        }

        // Lookup id_reseller by username
        $resellerUsername = trim($row['username_reseller'] ?? '');
        if (! empty($resellerUsername)) {
            $reseller = Reseller::where('username', $resellerUsername)->first();
            if ($reseller) {
                $data['id_reseller'] = $reseller->id;
            }
        }

        // jatuh_tempo
        $jatuhTempo = trim($row['tgl_jatuh_tempo'] ?? '');
        if (! empty($jatuhTempo)) {
            try {
                $data['jatuh_tempo'] = Carbon::parse($jatuhTempo)->toDateString();
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        // ppn_percent
        $ppn = trim($row['ppn'] ?? '');
        if ($ppn !== '') {
            $data['ppn_percent'] = (float) $ppn;
        }

        // diskon_rp
        $discount = trim($row['discount'] ?? '');
        if ($discount !== '') {
            $data['diskon_rp'] = (float) $discount;
        }

        try {
            $user = PppoeUser::updateOrCreate(
                ['username' => $username],
                $data
            );
            $this->importedCount++;

            return $user;
        } catch (\Exception $e) {
            $this->failedCount++;
            $this->errors[] = "Row {$username}: ".$e->getMessage();
            Log::error('PppoeUserImport error', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function buildKoordinat(array $row): ?string
    {
        $lat = trim($row['latitude'] ?? '');
        $lng = trim($row['longitude'] ?? '');
        if ($lat !== '' && $lng !== '') {
            return $lat.','.$lng;
        }

        return null;
    }

    protected function parseBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'aktif', 'ya'], true);
    }
}
