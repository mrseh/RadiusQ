<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PppoeUserExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        public Collection $users,
    ) {}

    public function collection(): Collection
    {
        return $this->users;
    }

    public function headings(): array
    {
        return [
            'no',
            'type',
            'username',
            'password',
            'profile',
            'nama_nas',
            'pop',
            'odp',
            'ip_address',
            'id_pelanggan',
            'nama_lengkap',
            'nomor_wa',
            'alamat',
            'billing',
            'jenis_pembayaran',
            'siklus_tagihan',
            'tgl_jatuh_tempo',
            'ppn',
            'discount',
            'latitude',
            'longitude',
            'id_pelanggan_reseller',
            'username_reseller',
        ];
    }

    public function map($user): array
    {
        $koordinat = $user->koordinat ? explode(',', $user->koordinat) : ['', ''];

        return [
            '',
            $user->tipe_user ?? '',
            $user->username,
            $user->password,
            $user->profile?->nama ?? '',
            $user->nas?->nama ?? '',
            $user->pop?->nama ?? '',
            $user->odp?->nama ?? '',
            $user->ip_address ?? '',
            $user->id_pelanggan ?? '',
            $user->nama ?? '',
            $user->whatsapp ?? '',
            $user->alamat ?? '',
            $user->enable_billing ? '1' : '0',
            $user->jenis_tagihan ?? '',
            $user->siklus_tagihan ?? '',
            $user->jatuh_tempo?->format('Y-m-d') ?? '',
            $user->ppn_percent ?? '',
            $user->diskon_rp ?? '',
            $koordinat[0] ?? '',
            $koordinat[1] ?? '',
            $user->id_pelanggan ?? '',
            $user->reseller?->username ?? '',
        ];
    }
}
