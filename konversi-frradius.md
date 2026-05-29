# FRRADIUS — Konversi HTML Statis ke Laravel

**Project:** SMC BY FRRADIUS — ISP Billing System
**Tanggal:** 21 Mei 2026
**Status:** Belum dimulai

---

## Ringkasan Proyek

Aplikasi FRRADIUS adalah platform billing dan manajemen pelanggan ISP berbasis RADIUS untuk PPPoE dan Hotspot. Aplikasi ini mencakup fitur tagihan, pembayaran, monitoring koneksi, dan manajemen pelanggan.

Aplikasi saat ini berupa file HTML statis beserta asset bundle (JS/CSS). Target migrasi adalah ke framework Laravel 13 agar mendapatkan manfaat: route management terstruktur, database ORM, session-based auth, CSRF protection, dan kemudahan pengembangan modular.

---

## Struktur Folder Sumber

```
/home/mrbogang/public/html/
├── auth/             → Halaman login, register, reset password
├── dashboard/         → Tampilan utama setelah login
├── users/            → Manajemen user aplikasi
├── profile/          → Data profil user
├── perusahaan/       → Pengaturan perusahaan
├── mikrotik/         → Pengaturan server Mikrotik
├── whatsapp/         → Modul notifikasi WhatsApp
├── hotspot/          → CRUD: user, session, profile, template, sold
├── pppoe/            → CRUD: user, online, offline, profile, odp
├── invoice/          → Invoice: paid, unpaid
├── transaksi/        →Riwayat transaksi keuangan
├── payment-gateway/  → Pengaturan gateway pembayaran
├── moota/            → Integrasi Moota ( BCA )
├── mitra/            → Reseller, biller, outlet, deposit
├── map/              → Mapping: user, odp
├── olt/              → Manajemen OLT, HIO SO, HSGQ
├── acs-cloud/        → Genie A CS cloud
├── acs-mandiri/      → Genie A CS lokal
├── tiket/gangguan/   → Sistem tiket gangguan
├── log/             → Log aplikasi
├── license/         → Informasi lisensi
├── build/assets/    → JS & CSS bundle (Vite output)
└── images/          → Assets gambar, icon, logo
```

**Stack frontend saat ini:** Bootstrap 5 + jQuery + DataTables + SweetAlert2 + Tabler Icons + Remix Icon (Vite-bundled)

**Total modul:** 32 halaman

---

## Stack Target (Laravel 13)

| Komponen | Technology |
|---|---|
| Framework | Laravel 13 |
| PHP | 8.3 |
| Database | MySQL/PostgreSQL (SQLite untuk development) |
| Auth | Laravel Fortify (headless session-based) |
| Frontend | Blade + reserved bundel assets |
| Bundling | Vite (biarkan bundel JS/CSS existentes) |

---

## Prinsip Konversi

| Aspek | Pendekatan |
|---|---|
| **HTML → Blade** | Konversi manual per halaman; pecah menjadi layout + partials |
| **Assets** | Copy `/build/assets/` dan `/images/` ke `public/` |
| **JS Bundled** | Tetap pakai `public/build/assets/*.js` — sudah compiled |
| **Ajax Calls** | Update `ROUTES.ajax` & `ROUTES.store` di setiap halaman ke Laravel route |
| **CSRF** | Gunakan `{{ csrf_token() }}` dan `{{ csrf_field() }}` Laravel |
| **Auth** | Session-based dengan CSRF protection (sesuai model HTML existing) |
| **DataTables** | Tetap pakai bundel DataTables; ubah ke server-side via Laravel |
| **Theme System** | Simpan preferensi tema di session (light/dark/customizer) |
| **Logo & Images** | Serve via Laravel storage atau `public/images/` |

---

## Tahapan Migrasi

---

### Fase 0 — Persiapan & Setup Database

**Tujuan:** Menyiapkan infrastruktur dasar Laravel agar siap menerima conversion.

| # | Task | Penjelasan |
|---|---|---|
| 0.1 | Review schema SQL | User memberikan schema database; saya generate migrations |
| 0.2 | Generate migrations | Buat semua tabel dari schema ke Laravel migration files |
| 0.3 | Generate models | Buat Eloquent models dengan relasi yang sesuai |
| 0.4 | Setup seeder (opsional) | Data dummy untuk development |
| 0.5 | Copy assets | Copy `build/` folder + `images/` ke `public/` |
| 0.6 | Konfigurasi `.env` | Setup database connection, app key, base URL |
| 0.7 | Setup middleware | CsrfToken, Session, Auth (standar Laravel) |

**Output:** Project Laravel siap jalan dengan database terstruktur.

---

### Fase 1 — Auth & Layout Dasar

**Tujuan:** Mendapatkan auth flow working dan base layout yang reusable.

| # | Task | Penjelasan |
|---|---|---|
| 1.1 | Buat `User` model + migration | Sesuai schema (username, password, role, nama, whatsapp) |
| 1.2 | Buat `AuthController` | Login, logout, register, reset password |
| 1.3 | Konversi `auth/index.html` | Halaman login → Blade + CSRF token |
| 1.4 | Konversi `register/index.html` | Halaman registrasi → Blade |
| 1.5 | Konversi `reset-pass/index.html` | Reset password → Blade |
| 1.6 | Setup auth routes | `routes/web.php` untuk auth flow |
| 1.7 | Buat base layout Blade | `layouts/app.blade.php` dengan slot() untuk content |

**Output:** User bisa login, register, dan logout. Layout dasar siap dipak ai di semua halaman.

---

### Fase 2 — Dashboard & Sidebar Navigation

**Tujuan:** Halaman utama dengan sidebar navigasi yang lengkap.

| # | Task | Penjelasan |
|---|---|---|
| 2.1 | Konversi `dashboard/index.html` | Halaman dashboard admin → Blade |
| 2.2 | Buat sidebar component | Blade component `sidebar.blade.php` dengan semua menu |
| 2.3 | Buat topbar component | Blade component `topbar.blade.php` (logo, user dropdown, theme toggle) |
| 2.4 | Implement theme system | Light/dark mode toggle → simpan di session |
| 2.5 | Buat offcanvas customizer | Theme settings (skin, color, sidenav size) |
| 2.6 | Setup layout hierarchy | `layouts/app.blade.php` → topbar → sidebar → `@yield('content')` → footer |

**Output:** Layout konsisten untuk seluruh halaman internal.

---

### Fase 3 — Manajemen User & Settings

**Tujuan:** CRUD user aplikasi dan pengaturan sistem.

| # | Task | Penjelasan |
|---|---|---|
| 3.1 | Konversi `users/index.html` | Manajemen user dengan DataTable + modal |
| 3.2 | Buat `UserController` | Index, store, update, destroy |
| 3.3 | Setup AJAX route | Datatable server-side dari Laravel |
| 3.4 | Konversi `profile/index.html` | Edit profil user yang login |
| 3.5 | Konversi `perusahaan/index.html` | Pengaturan data perusahaan |
| 3.6 | Konversi `mikrotik/index.html` | Pengaturan server Mikrotik |
| 3.7 | Konversi `whatsapp/index.html` | Pengaturan notifikasi WhatsApp |
| 3.8 | Konversi `log/index.html` | Log aplikasi |

**Output:** Sistem manajemen user dan settings berfungsi.

---

### Fase 4 — ISP Core: PPPoE & Hotspot

**Tujuan:** Manajemen pelanggan dan koneksi jaringan.

| # | Task | Penjelasan |
|---|---|---|
| 4.1 | Buat PPPoE controller & views | User, online, offline, profile, ODP |
| 4.2 | Konversi `pppoe/user/index.html` | CRUD pelanggan PPPoE |
| 4.3 | Konversi `pppoe/online/index.html` | DataTable user aktif |
| 4.4 | Konversi `pppoe/offline/index.html` | DataTable user mati |
| 4.5 | Konversi `pppoe/profile/index.html` | Manajemen profile RADIUS |
| 4.6 | Konversi `pppoe/odp/index.html` | Mapping ODP |
| 4.7 | Buat Hotspot controller & views | User, session, profile, template, sold |
| 4.8 | Konversi `hotspot/user/index.html` | CRUD hotspot user |
| 4.9 | Konversi `hotspot/session/index.html` | Active sessions |
| 4.10 | Konversi `hotspot/profile/index.html` | Profile Hotspot |
| 4.11 | Konversi `hotspot/template/index.html` | Template voucher |
| 4.12 | Konversi `hotspot/sold/index.html` | Riwayat voucher terjual |

**Output:** Modul inti ISP (PPPoE + Hotspot) berfungsi.

---

### Fase 5 — Keuangan & Tagihan

**Tujuan:** Sistem invoice dan财务管理.

| # | Task | Penjelasan |
|---|---|---|
| 5.1 | Buat Invoice controller | Paid + unpaid invoice |
| 5.2 | Konversi `invoice/unpaid/index.html` | Invoice belum bayar |
| 5.3 | Konversi `invoice/paid/index.html` | Invoice sudah bayar |
| 5.4 | Konversi `transaksi/index.html` | Riwayat transaksi keuangan |
| 5.5 | Konversi `payment-gateway/index.html` | Pengaturan gateway |
| 5.6 | Konversi `moota/index.html` | Integrasi Moota BCA |

**Output:** Modul keuangan berfungsi lengkap.

---

### Fase 6 — Mitra & Reseller

**Tujuan:** Manajemen jaringan distributor dan sub-reseller.

| # | Task | Penjelasan |
|---|---|---|
| 6.1 | Buat Mitra controller | Reseller, biller, outlet, deposit |
| 6.2 | Konversi `mitra/reseller/index.html` | Manajemen reseller |
| 6.3 | Konversi `mitra/biller/index.html` | Manajemen biller |
| 6.4 | Konversi `mitra/outlet/index.html` | Manajemen outlet |
| 6.5 | Konversi `mitra/deposit/index.html` | Deposit saldo reseller |

**Output:** Sistem multi-level mitra berfungsi.

---

### Fase 7 — Monitoring &Device Management

**Tujuan:** Monitoring jaringan dan perangkat.

| # | Task | Penjelasan |
|---|---|---|
| 7.1 | Konversi `map/user/index.html` | Peta lokasi pelanggan |
| 7.2 | Konversi `map/odp/index.html` | Peta ODP |
| 7.3 | Konversi `olt/index.html` | Manajemen OLT |
| 7.4 | Konversi `olt/hioso/index.html` | HIO SO module |
| 7.5 | Konversi `olt/hsgq/index.html.html` | HSGQ module |
| 7.6 | Konversi `acs-cloud/index.html`  | GenieACS cloud provisioning |
| 7.7 | Konversi `acs-mandiri/index.html` | GenieACS lokal |
| 7.8 | Konversi `tiket/gangguan/index.html` | Sistem tiket gangguan |

**Output:** Sistem monitoring dan device management berfungsi.

---

### Fase 8 — Finalisasi & Polish

**Tujuan:** Memastikan semua integrasi berjalan dan tidak ada yang terlewat.

| # | Task | Penjelasan |
|---|---|---|
| 8.1 | Konversi `license/index.html` | Halaman informasi lisensi |
| 8.2 | Setup global search (optional) | Fitur pencarian global |
| 8.3 | Error handling | custom 404, 500 pages dengan styling |
| 8.4 | SEO meta tags | Blade directive untuk meta description, keywords |
| 8.5 | Deployment checklist | Permissions, storage link, env config |
| 8.6 | Dokumentasi API (optional) | Jika ada endpoint yang perlu di-expose |

---

## Struktur Direktori Target Laravel

```
laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── UserController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── HotspotController.php
│   │   │   ├── PppoeController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── MitraController.php
│   │   │   ├── MonitoringController.php
│   │   │   └── ...
│   │   ├── Middleware/
│   │   └── Requests/         ← Form request validation per modul
│   ├── Models/
│   │   ├── User.php
│   │   ├── HotspotUser.php
│   │   ├── PppoeUser.php
│   │   ├── Invoice.php
│   │   ├── Transaction.php
│   │   └── ...
│   └── Providers/
├── database/
│   ├── migrations/          ← Generated dari schema SQL
│   └── seeders/
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php
│       │   ├── auth.blade.php
│       │   └── blank.blade.php
│       ├── components/
│       │   ├── sidebar.blade.php
│       │   ├── topbar.blade.php
│       │   ├── footer.blade.php
│       │   └── modal.blade.php
│       ├── auth/
│       │   ├── login.blade.php
│       │   ├── register.blade.php
│       │   └── reset-password.blade.php
│       ├── dashboard/
│       │   └── index.blade.php
│       ├── users/
│       │   └── index.blade.php
│       ├── hotspot/
│       │   ├── user/index.blade.php
│       │   ├── session/index.blade.php
│       │   ├── profile/index.blade.php
│       │   ├── template/index.blade.php
│       │   └── sold/index.blade.php
│       ├── pppoe/
│       ├── invoice/
│       ├── mitra/
│       ├── map/
│       ├── olt/
│       ├── acs/
│       ├── tiket/
│       └── ...
├── routes/
│   └── web.php             ← Semua route aplikasi
└── public/
    ├── build/assets/        ← Copy dari /build/assets/
    └── images/             ← Copy dari /images/
```

---

## Routing Convention

```
GET    /login           → AuthController@showLogin
POST   /auth            → AuthController@login
POST   /register        → AuthController@register
GET    /register        → AuthController@showRegister
POST   /reset-pass      → AuthController@resetPassword
GET    /reset-pass      → AuthController@showResetPassword
POST   /logout          → AuthController@logout

GET    /dashboard       → DashboardController@index

GET    /users           → UserController@index
GET    /users/ajax      → UserController@ajax      (DataTable server-side)
POST   /users           → UserController@store
PUT    /users/{id}      → UserController@update
DELETE /users/{id}      → UserController@destroy

GET    /hotspot/user    → HotspotController@users
GET    /hotspot/session → HotspotController@sessions
... (setiap modul mengikuti pattern RESTful yang sama)
```

---

## Status Per Fase

| Fase | Nama | Status | Catatan |
|---|---|---|---|
| 0 | Persiapan & Setup Database |  Pending | Menunggu schema SQL |
| 1 | Auth & Layout Dasar |  Pending | |
| 2 | Dashboard & Sidebar |  |  |
| 3 | Manajemen User & Settings | — | |
| 4 | ISP Core: PPPoE & Hotspot | — | |
| 5 | Keuangan & Tagihan | — | |
| 6 | Mitra & Reseller | — | |
| 7 | Monitoring & Device | — | |
| 8 | Finalisasi & Polish | \ | |
| 9 | \ | \ | \ |

---

## Catatan Penting

- **Schema harus provided lebih dulu** sebelum eksekusi Fase 0
- **Modul dikerjakan satu per satu** — tidak ada parallel untuk mempertahankan kualitas
- **JS bundel tetap dipakai** — tidak perlu di-rewrite karena sudah compiled via Vite
- **Ajax routes harus consistent** — setiap form HTML harus bisa submit ke route Laravel baru
- **CSRF token** di-generate via `csrf_field()` atau `{{ csrf_token() }}` di setiap form
