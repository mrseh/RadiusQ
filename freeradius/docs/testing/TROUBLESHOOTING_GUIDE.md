# 🔧 Troubleshooting Guide

## FreeRADIUS + Laravel + OpenVPN Integration

Panduan pemecahan masalah untuk semua komponen integrasi.

## 📋 Daftar Isi

1. [FreeRADIUS Issues](#1-freeradius-issues)
2. [Laravel/Model Issues](#2-laravelmodel-issues)
3. [OpenVPN Issues](#3-openvpn-issues)
4. [Network/Connectivity Issues](#4-networkconnectivity-issues)
5. [Performance Issues](#5-performance-issues)
6. [Quick Diagnostic Commands](#6-quick-diagnostic-commands)

---

## 1. FreeRADIUS Issues

### Issue: FreeRADIUS tidak bisa start

**Symptoms:**
```
Job for freeradius.service failed because the control process exited
```

**Causes & Solutions:**

```bash
# 1. Check syntax errors in config
sudo radiusd -XC

# 2. Check specific error
sudo journalctl -u freeradius -n 50

# 3. Common fixes:
# - Missing SQL module
sudo ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# - Wrong permissions
sudo chown -R freeradius:freeradius /etc/freeradius
sudo chmod -R 640 /etc/freeradius/3.0/mods-enabled/sql

# - Port already in use
sudo lsof -i :1812
sudo lsof -i :1813
```

---

### Issue: Authentication selalu gagal (Access-Reject)

**Symptoms:**
```
Access-Reject dari radclient atau Mikrotik tidak bisa login
```

**Diagnostic Steps:**

```bash
# Step 1: Enable verbose logging
sudo nano /etc/freeradius/3.0/radiusd.conf
# Set: auth = yes, auth_badpass = yes, auth_goodpass = yes

sudo systemctl restart freeradius

# Step 2: Test dengan debug mode
sudo radiusd -X

# Step 3: Check radcheck table
mysql -u frradius_app -p frradius_db -e "
SELECT * FROM radcheck WHERE username='test_user';
"
# Pastikan ada entry dengan attribute='Cleartext-Password'

# Step 4: Test dengan plain password
# Pastikan password di radcheck match dengan yang dikirim client
```

**Common Causes:**

| Cause | Solution |
|-------|----------|
| Password stored as bcrypt | Ganti dengan plain text |
| Wrong attribute type | Gunakan `Cleartext-Password`, bukan `Crypt-Password` |
| radcheck empty | Observer tidak jalan, cek AppServiceProvider |
| Wrong secret | Cek `/etc/freeradius/3.0/clients.conf` |

---

### Issue: radcheck tidak terisi saat user dibuat

**Symptoms:**
User dibuat di Laravel tapi tidak ada di tabel `radcheck`

**Diagnostic Steps:**

```bash
# Step 1: Cek apakah observer jalan
php artisan tinker

>>> $user = App\Models\HotspotUser::first();
>>> Log::channel('single')->info('Testing observer', ['user' => $user->username]);

# Step 2: Cek RadiusSyncService
>>> $service = app(\App\Services\RadiusSyncService::class);
>>> $service->syncHotspotUser($user);

# Step 3: Cek error log
tail -f storage/logs/laravel.log | grep -i radius
tail -f storage/logs/laravel.log | grep -i error

# Step 4: Cek apakah model observably registered
php artisan tinker
>>> echo (HotspotUser::hasGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)) ? 'yes' : 'no';
```

**Solutions:**

```php
// Solution 1: Cek AppServiceProvider
// Pastikan ini ada di boot():
HotspotUser::observe(HotspotUserObserver::class);

// Solution 2: Manual sync
php artisan tinker
>>> $service = app(\App\Services\RadiusSyncService::class);
>>> $service->syncAll();

// Solution 3: Clear cache
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
```

---

### Issue: Rate limit tidak berjalan

**Symptoms:**
User bisa login tapi speed tidak sesuai paket

**Diagnostic Steps:**

```sql
-- Cek radreply
SELECT * FROM radreply WHERE username = 'test_user';
-- Pastikan ada Mikrotik-Queue-Max-Rate atau Mikrotik-Rate-Limit

-- Cek radgroupreply
SELECT * FROM radgroupreply WHERE groupname = 'Test-Paket';
-- Pastikan ada attribute Mikrotik-Queue-Max-Rate
```

**Solutions:**

```sql
-- Solution 1: Insert manual rate limit ke radreply
INSERT INTO radreply (username, attribute, op, value)
VALUES ('test_user', 'Mikrotik-Queue-Max-Rate', '=', '10M/10M');

-- Solution 2: Sync via model
-- Pastikan HotspotProfile observer jalan
```

---

### Issue: Accounting tidak tersimpan

**Symptoms:**
Login berhasil tapi `radacct` kosong

**Diagnostic Steps:**

```bash
# Step 1: Cek Mikrotik RADIUS config
# Pastikan Accounting enabled:
/radius print
/radius monitor 0

# Step 2: Cek firewall
sudo iptables -L -n | grep 1813
# Pastikan port 1813/UDP terbuka

# Step 3: Cek FreeRADIUS accounting enabled
sudo nano /etc/freeradius/3.0/sites-enabled/default
# Pastikan ada:
# accounting {
#     sql
#     ...
# }

# Step 4: Test accounting packet manually
# Di Mikrotik:
/tool sniffer quick ip-protocol=udp dst-port=1813

# Step 5: Cek radacct di database
mysql -u frradius_app -p frradius_db -e "
SELECT * FROM radacct ORDER BY radacctid DESC LIMIT 5;
"
```

**Solutions:**

```bash
# Solution 1: Enable accounting di Mikrotik
/radius
set 0 address=172.31.119.2:1813 service=accounting

# Atau via API:
/ip hotspot profile
set <profile> use-radius=yes radius-accounting=yes

# Solution 2: Restart FreeRADIUS
sudo systemctl restart freeradius

# Solution 3: Check radacct table exists
mysql -u frradius_app -p frradius_db -e "
DESCRIBE radacct;
"
```

---

### Issue: User tidak di-kick saat expired

**Symptoms:**
User sudah expired tapi masih bisa login

**Diagnostic Steps:**

```bash
# Step 1: Cek apakah Session-Timeout ada
mysql -u frradius_app -p frradius_db -e "
SELECT * FROM radreply WHERE username='test_user' AND attribute='Session-Timeout';
"

# Step 2: Cek apakah Mikrotik respect Session-Timeout
# Di Mikrotik:
/ip hotspot active print
# Lihat kolom uptime vs session-timeout

# Step 3: Cek cron job
php artisan schedule:list
# Pastikan radius:sync-sessions ada
```

**Solutions:**

```bash
# Solution 1: Disable user via radcheck removal
# Observer seharusnya handle ini otomatis

# Solution 2: Manual disable
php artisan tinker
>>> $user = App\Models\HotspotUser::where('username', 'expired_user')->first();
>>> $user->status = 0; // STATUS_DISABLED
>>> $user->save();

# Solution 3: Setup scheduled auto-disable
# Di Kernel.php, tambahkan:
$schedule->call(function () {
    // Disable users yang sudah expired
})->daily();
```

---

## 2. Laravel/Model Issues

### Issue: Model tidak bisa di-autoload

**Symptoms:**
```
Class 'App\Models\RadCheck' not found
```

**Solutions:**

```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
composer dump-autoload

# Check namespace
# Pastikan file ada di app/Models/RadCheck.php
# Pastikan namespace: namespace App\Models;
```

---

### Issue: Mass assignment error

**Symptoms:**
```
Add [attribute] to fillable property to allow mass assignment
```

**Solutions:**

```php
// Di RadCheck model, pastikan:
protected $fillable = [
    'username',
    'attribute',
    'op',
    'value',
];
```

---

### Issue: Foreign key constraint error

**Symptoms:**
```
SQLSTATE[23000]: Integrity constraint violation
```

**Solutions:**

```bash
# Cek apakah tabel utama ada
mysql -u frradius_app -p frradius_db -e "SHOW TABLES LIKE 'hotspot%';"

# Disable foreign key check sementara
mysql -u frradius_app -p frradius_db -e "SET FOREIGN_KEY_CHECKS=0;"

# Jalankan migration
php artisan migrate:fresh --seed

# Enable lagi
mysql -u frradius_app -p frradius_db -e "SET FOREIGN_KEY_CHECKS=1;"
```

---

### Issue: Observer tidak ter-trigger

**Symptoms:**
User dibuat tapi tidak sync ke RADIUS

**Diagnostic Steps:**

```bash
# Step 1: Cek observer registered
grep -r "HotspotUser::observe" app/Providers/

# Step 2: Enable debug logging
# Di .env:
APP_DEBUG=true

# Di AppServiceProvider, cek:
if ($this->app->environment('local')) {
    $this->enableObserverLogging();
}

# Step 3: Cek model events
php artisan tinker
>>> event(new \Illuminate\Database\Events\ModelsPrunable());
```

**Solutions:**

```php
// Solution 1: Explicitly call observer method
$service = app(\App\Services\RadiusSyncService::class);
$service->syncHotspotUser($user);

// Solution 2: Register observer manually
// Di AppServiceProvider::boot():
HotspotUser::observe(new HotspotUserObserver(
    app(\App\Services\RadiusSyncService::class)
));
```

---

## 3. OpenVPN Issues

### Issue: OpenVPN auth selalu gagal

**Symptoms:**
Semua user gagal auth via OpenVPN

**Diagnostic Steps:**

```bash
# Step 1: Cek script permissions
ls -la /etc/openvpn/scripts/verify_radius.py
# Pastikan -rwxr-xr-x

# Step 2: Test script manually
USERNAME=test_user PASSWORD=test123 \
    python3 /etc/openvpn/scripts/verify_radius.py
echo $?

# Step 3: Check RADIUS secret match
grep "secret" /etc/freeradius/3.0/clients.conf
grep "RADIUS_SECRET" /etc/openvpn/scripts/verify_radius.py

# Step 4: Check Python version
python3 --version

# Step 5: Check radclient
radclient -v
```

**Solutions:**

```bash
# Solution 1: Fix script permissions
chmod +x /etc/openvpn/scripts/verify_radius.py
chmod +x /etc/openvpn/scripts/verify_radius.sh

# Solution 2: Use bash version (simpler)
# Edit server.conf:
# auth-user-pass-verify /etc/openvpn/scripts/verify_radius.sh via-env

# Solution 3: Install dependencies
apt-get install python3

# Solution 4: Test with bash version
USERNAME=test_user PASSWORD=test123 \
    /etc/openvpn/scripts/verify_radius.sh
echo $?
```

---

### Issue: Mikrotik tidak bisa konek ke OpenVPN

**Symptoms:**
OpenVPN client di Mikrotik tidak bisa connect

**Diagnostic Steps:**

```bash
# Step 1: Cek OpenVPN status
systemctl status openvpn@server

# Step 2: Cek port listening
sudo lsof -i :1194

# Step 3: Cek firewall
sudo iptables -L -n | grep 1194
# Pastikan OUTPUT/INPUT ACCEPT

# Step 4: Cek client config
cat /etc/openvpn/ccd/MKT-WYH-01

# Step 5: Test connectivity dari Mikrotik
# Di Mikrotik:
/ping 172.31.119.1
```

**Solutions:**

```bash
# Solution 1: Open firewall
sudo iptables -A INPUT -p udp --dport 1194 -j ACCEPT
sudo iptables -A OUTPUT -p udp --dport 1194 -j ACCEPT

# Solution 2: Start OpenVPN manually untuk debug
sudo openvpn --config /etc/openvpn/server.conf

# Solution 3: Check client credentials di Mikrotik
# Pastikan username/password ada di radcheck
echo "User-Name=mikrotik_vpn, User-Password=vpn_pass" | \
    radclient -x 127.0.0.1:1812 auth testing123
```

---

### Issue: VPN tunnel up tapi Mikrotik tidak bisa reach FreeRADIUS

**Symptoms:**
OpenVPN connected tapi RADIUS request tidak sampai

**Diagnostic Steps:**

```bash
# Step 1: Cek IP di Mikrotik
/ip address print
# Pastikan dapat IP di subnet 172.31.119.x

# Step 2: Ping FreeRADIUS dari Mikrotik
/ping 172.31.119.2

# Step 3: Cek routing
/ip route print

# Step 4: Cek firewall NAT
/ip firewall nat print
# Pastikan ada rule untuk route traffic
```

**Solutions:**

```bash
# Solution 1: Add route di Mikrotik
/ip route
add dst-address=172.31.119.0/24 gateway=ovpn-out1

# Solution 2: Add NAT rule
/ip firewall nat
add chain=srcnat out-interface=ovpn-out1 action=masquerade

# Solution 3: Check OpenVPN server.conf
# Pastikan:
# push "route 172.31.119.0 255.255.255.0"
```

---

## 4. Network/Connectivity Issues

### Issue: UDP port 1812/1813 terblokir firewall

**Symptoms:**
RADIUS request timeout

**Solutions:**

```bash
# Linux (iptables)
sudo iptables -A INPUT -p udp --dport 1812 -j ACCEPT
sudo iptables -A INPUT -p udp --dport 1813 -j ACCEPT

# Linux (ufw)
sudo ufw allow 1812/udp
sudo ufw allow 1813/udp

# Verifikasi
sudo iptables -L -n | grep 181
```

---

### Issue: MySQL connection refused

**Symptoms:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solutions:**

```bash
# Solution 1: Start MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# Solution 2: Check MySQL listening
sudo lsof -i :3306

# Solution 3: Check credentials
mysql -u frradius_app -p -h 127.0.0.1

# Solution 4: Bind address
# Di /etc/mysql/mysql.conf.d/mysqld.cnf:
# bind-address = 0.0.0.0  # untuk allow remote
# bind-address = 127.0.0.1  # untuk local only
```

---

### Issue: Cross-database query gagal

**Symptoms:**
```
Table 'frradius_db.radcheck' doesn't exist
```

**Solutions:**

```sql
-- Solution 1: Cek database exist
SHOW DATABASES;
USE frradius_db;
SHOW TABLES LIKE 'rad%';

-- Solution 2: Cek user permissions
SHOW GRANTS FOR 'frradius_app'@'%';
-- Pastikan ada GRANT ALL ON frradius_db.*

-- Solution 3: Recreate user
CREATE USER 'frradius_app'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON frradius_db.* TO 'frradius_app'@'%';
FLUSH PRIVILEGES;
```

---

## 5. Performance Issues

### Issue: radacct table sangat besar

**Symptoms:**
Query lambat, disk space penuh

**Solutions:**

```sql
-- Solution 1: Partitioning (MySQL 5.7+)
ALTER TABLE radacct
PARTITION BY RANGE (TO_DAYS(acctstarttime)) (
    PARTITION p_old VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION p_2026_q1 VALUES LESS THAN (TO_DAYS('2026-04-01')),
    PARTITION p_2026_q2 VALUES LESS THAN (TO_DAYS('2026-07-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- Solution 2: Archive old records
CREATE TABLE radacct_archive LIKE radacct;
INSERT INTO radacct_archive SELECT * FROM radacct
WHERE acctstarttime < DATE_SUB(NOW(), INTERVAL 3 MONTH);
DELETE FROM radacct WHERE acctstarttime < DATE_SUB(NOW(), INTERVAL 3 MONTH);

-- Solution 3: Add indexes
ALTER TABLE radacct ADD INDEX (username, acctstarttime);
ALTER TABLE radacct ADD INDEX (nasipaddress, acctstoptime);
```

---

### Issue: Sync sangat lambat dengan banyak user

**Symptoms:**
`php artisan radius:sync-all` lama sekali

**Solutions:**

```php
// Solution 1: Chunk processing
// Di RadiusSyncService, gunakan chunk:

HotspotUser::where('status', '!=', HotspotUser::STATUS_DISABLED)
    ->with('profile')
    ->chunk(100, function ($users) {
        foreach ($users as $user) {
            $this->syncHotspotUser($user);
        }
    });

// Solution 2: Queue processing
// Gunakan Laravel Queue untuk background sync

// Solution 3: Batch update
// Gunakan UPDATE langsung ke DB untuk bulk operations
DB::table('radcheck')->insertOrIgnore($records);
```

---

### Issue: FreeRADIUS timeout saat banyak request

**Symptoms:**
```
RADIUS timeout
```

**Solutions:**

```bash
# Solution 1: Increase pool size
# Di /etc/freeradius/3.0/mods-enabled/sql:
sql {
    ...
    pool {
        start = 10
        min = 5
        max = 100
        ...
    }
}

# Solution 2: Increase threads
# Di /etc/freeradius/3.0/radiusd.conf:
thread pool {
    start_servers = 5
    max_servers = 50
    max_requests_per_server = 0
}

# Solution 3: Restart FreeRADIUS
sudo systemctl restart freeradius
```

---

## 6. Quick Diagnostic Commands

### FreeRADIUS Diagnostics

```bash
# Check status
systemctl status freeradius

# Start in debug mode
sudo radiusd -X

# Test auth
echo "User-Name=test, User-Password=test" | radclient -x 127.0.0.1:1812 auth testing123

# Check logs
tail -f /var/log/freeradius/radius.log

# Check radcheck
mysql -u frradius_app -p frradius_db -e "SELECT * FROM radcheck LIMIT 5;"

# Check radreply
mysql -u frradius_app -p frradius_db -e "SELECT * FROM radreply LIMIT 5;"

# Check radacct
mysql -u frradius_app -p frradius_db -e "SELECT COUNT(*) as total FROM radacct;"
```

### Laravel Diagnostics

```bash
# Clear all cache
php artisan optimize:clear

# Check migrations
php artisan migrate:status

# Check services
php artisan tinker
>>> app(\App\Services\RadiusSyncService::class)->getStats();

# Check observers
php artisan tinker
>>> $user = App\Models\HotspotUser::first();
>>> $user->password = 'test123';
>>> $user->save();

# Check logs
tail -f storage/logs/laravel.log
```

### OpenVPN Diagnostics

```bash
# Check status
systemctl status openvpn@server

# Check logs
tail -f /var/log/openvpn/server.log
tail -f /var/log/openvpn/auth.log

# Test auth script
USERNAME=test PASSWORD=test python3 /etc/openvpn/scripts/verify_radius.py

# Check connected clients
cat /var/log/openvpn/status.log

# Check port
sudo lsof -i :1194
```

### Database Diagnostics

```bash
# Check tables
mysql -u frradius_app -p frradius_db -e "SHOW TABLES LIKE 'rad%';"

# Check table sizes
mysql -u frradius_app -p frradius_db -e "
SELECT 
    table_name AS 'Table',
    ROUND(data_length / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'frradius_db'
ORDER BY data_length DESC;";

# Check slow queries
mysql -u frradius_app -p frradius_db -e "
SELECT * FROM mysql.slow_log
ORDER BY start_time DESC LIMIT 10;"
```

---

## 📞 Need More Help?

Jika masih ada masalah:

1. **Check logs first** — logs selalu bilang apa yang salah
2. **Isolate the problem** — test komponen satu per satu
3. **Google error message** — error message spesifik hampir selalu ada solusinya
4. **Check Firewall** — masalah network biasanya firewall

---

*Document Version: 1.0*
*Last Updated: April 2026*
