# PHASE 00 — Project Overview & Strategy

## Project Identity
- **Project:** ISP Billing System (Laravel 13 + FreeRADIUS + Mikrotik + OpenVPN)
- **Base URL:** `https://my.mitraserayu.net`
- **Total API Endpoints:** 185
- **Laravel Version:** 13.x | PHP: 8.3

---

## Current State Audit

### Infrastructure
| Component | Status | Notes |
|---|---|---|
| `bootstrap/app.php` API routing | ✅ Active | `api: __DIR__.'/../routes/api.php'` |
| `routes/api.php` | ✅ Written | All 16 sub-require statements present |
| Route sub-files (`routes/api/*.php`) | ❌ Empty (0 byte) | All 16 files empty — **main blocker** |
| Middleware | ✅ `auth:sanctum` | Applied globally on all API routes |
| Base Controllers | ✅ `BaseApiController`, `ApiController` | 44 lines each |

### Controllers & Models — Summary

| Module | Controller(s) | LOC | Implementation |
|---|---|---|---|
| PPPoE User | `PppoeUserController` | 578 | ✅ Full — 21 methods |
| PPPoE Online | `PppoeOnlineController` | ~170 | ✅ Full — 6 methods |
| PPPoE Offline | `PppoeOfflineController` | ~160 | ✅ Full — 6 methods |
| PPPoE Profile | `PppoeProfileController` | ~130 | ✅ Full — 5 methods |
| Hotspot User | `HotspotUserController` | 491 | ✅ Full |
| Hotspot Profile | `HotspotProfileController` | 98 | ✅ Full |
| Hotspot Session | `HotspotSessionController` | 134 | ✅ Full |
| Hotspot Voucher | `HotspotVoucherController` | 246 | ✅ Full |
| Hotspot Template | `HotspotTemplateController` | 67 | ✅ Full |
| Invoice Unpaid | `UnpaidController` | 421 | ✅ Full — 16 methods |
| Invoice Paid | `PaidController` | 183 | ✅ Full — 8 methods |
| Mikrotik | `MikrotikController` | 413 | ✅ Full |
| WhatsApp | `WhatsAppController` | 412 | ✅ Full |
| OLT | `OltController` | 297 | ✅ Full |
| Transaksi | `TransaksiController` | 250 | ✅ Full |
| Tiket Gangguan | `TiketGangguanController` | 311 | ✅ Full |
| Payment Gateway | ❌ Missing | — | Need controller |
| Perusahaan | `PerusahaanController` | 201 | ✅ Full |
| Users | `UsersController` | 153 | ✅ Full |
| Log | `LogController` | 84 | ✅ Full |
| Profile | `ProfileController` | 181 | ✅ Full |
| Map | `MapController` | 208 | ✅ Full |
| Dashboard | ❌ Missing | — | Need controller |

### Models (21 available)
`PppoeUser`, `PppoeSession`, `HotspotUser`, `HotspotProfile`, `HotspotSession`, `HotspotTemplate`, `Invoice`, `TiketGangguan`, `Mikrotik`, `Olt`, `Transaksi`, `PaymentGateway`, `PaymentGatewayTransaction`, `PaymentGatewayWithdraw`, `Perusahaan`, `Reseller`, `Deposit`, `Billers`, `Outlet`, `RadCheck`, `RadReply`

### Form Requests (33 available)

| Module | Requests |
|---|---|
| Hotspot | 10 files |
| Invoice | 5 files |
| Mikrotik | 3 files |
| Mitra | 4 files |
| Payment | 5 files |
| Perusahaan | 2 files |
| Profile | 1 file |
| Users | 1 file |
| Api | 4 files (Olt, Pppoe, Tiket, Transaksi, Users, WhatsApp) |

---

## Root Cause: Why 0 Routes Active

All `routes/api/*.php` files exist but are **empty (0 bytes)**. The main `routes/api.php` uses `require __DIR__ . '/api/xxx.php'` which loads empty files → no routes registered.

**Fix:** Fill each `routes/api/xxx.php` with route definitions pointing to existing controllers.

---

## Implementation Phases

| Phase | Document | Endpoints | Controllers |
|---|---|---|---|
| 0 | PHASE-00-OVERVIEW.md | — | — |
| 1 | PHASE-01-PPPOE.md | 38 | 4 controllers (complete) |
| 2 | PHASE-02-HOTSPOT.md | TBD | 5 controllers (complete) |
| 3 | PHASE-03-INVOICE.md | 23 | 2 controllers (complete) |
| 4 | PHASE-04-TIKET.md | 14 | 1 controller (complete) |
| 5 | PHASE-05-MIKROTIK-WHATSAPP-OLT.md | 32 | 3 controllers (complete) |
| 6 | PHASE-06-REMAINING.md | 78 | 7 modules |

---

## Missing Components Checklist

### Route Files (16 to fill)
- [ ] `routes/api/pppoe.php`
- [ ] `routes/api/hotspot.php`
- [ ] `routes/api/invoice.php`
- [ ] `routes/api/mitra.php`
- [ ] `routes/api/map.php`
- [ ] `routes/api/mikrotik.php`
- [ ] `routes/api/whatsapp.php`
- [ ] `routes/api/olt.php`
- [ ] `routes/api/transaksi.php`
- [ ] `routes/api/tiket.php`
- [ ] `routes/api/payment-gateway.php`
- [ ] `routes/api/perusahaan.php`
- [ ] `routes/api/users.php`
- [ ] `routes/api/log.php`
- [ ] `routes/api/profile.php`
- [ ] `routes/api/dashboard.php`

### Missing Controllers
- [ ] `App\Http\Controllers\Api\PaymentGateway\PaymentGatewayController`
- [ ] `App\Http\Controllers\Api\Dashboard\DashboardController`

### Missing Form Requests
- [ ] `App\Http\Requests\Api\Pppoe\StorePppoeUserRequest`
- [ ] `App\Http\Requests\Api\Pppoe\BulkActionRequest`
- [ ] `App\Http\Requests\Api\Pppoe\StorePppoeProfileRequest`
- [ ] `App\Http\Requests\Api\Tiket\StoreTiketGangguanRequest`
- [ ] `App\Http\Requests\Api\Users\StoreUserRequest`
- [ ] `App\Http\Requests\Api\Olt\StoreOltRequest`

---

## Execution Order

1. **Fill route files** for modules with complete controllers (PPPoE, Invoice, Hotspot, Tiket, Mikrotik, WhatsApp, OLT)
2. **Implement missing controllers** (PaymentGateway, Dashboard)
3. **Create missing form requests** needed by controllers
4. **Fill remaining route files** (Map, Transaksi, Mitra, Perusahaan, Users, Log, Profile)
5. **Run** `php artisan route:list --path=api` to verify all 185 routes
6. **Run tests** per module

---

## Notes
- All route files use `auth:sanctum` (inherited from parent group in `routes/api.php`)
- DataTable pagination pattern: `length`, `start`, `page` params
- Bulk action pattern: `$ids = $request->input('ids', [])`
- Response pattern: `$this->ok()`, `$this->paginate()`, `$this->error()`