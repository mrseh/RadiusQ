# 🧪 FreeRADIUS + Laravel + OpenVPN Integration Testing Guide

Panduan lengkap untuk testing semua komponen integrasi FreeRADIUS.

## 📋 Daftar Isi

1. [Prerequisites](#1-prerequisites)
2. [Testing MySQL Connection](#2-testing-mysql-connection)
3. [Testing FreeRADIUS](#3-testing-freeradius)
4. [Testing Laravel Migrations](#4-testing-laravel-migrations)
5. [Testing Models & Services](#5-testing-models--services)
6. [Testing Observers](#6-testing-observers)
7. [Testing Artisan Commands](#7-testing-artisan-commands)
8. [Testing OpenVPN + RADIUS Auth](#8-testing-openvpn--radius-auth)
9. [Testing End-to-End](#9-testing-end-to-end)
10. [Test Results Template](#10-test-results-template)

---

## 1. Prerequisites

### 1.1 Checklist Setup

Pastikan semua komponen sudah terinstall:

```bash
# Check MySQL
mysql --version

# Check PHP
php --version  # Should be 8.2+

# Check FreeRADIUS
radiusd -v

# Check radclient
radclient -v

# Check OpenVPN
openvpn --version

# Check Laravel
php artisan --version  # Should be 11.x (Laravel 13 = Laravel 11)
```

### 1.2 Environment Variables

Pastikan `.env` sudah terkonfigurasi:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=frradius_db
DB_USERNAME=frradius_app
DB_PASSWORD=your_password

# FreeRADIUS
RADIUS_SERVER=127.0.0.1
RADIUS_AUTH_PORT=1812
RADIUS_ACCT_PORT=1813
RADIUS_SECRET=testing123

# OpenVPN
OPENVPN_ENABLED=true
OPENVPN_SERVER_IP=172.31.119.1
```

---

## 2. Testing MySQL Connection

### 2.1 Test Database Connection

```bash
# Test MySQL CLI
mysql -u frradius_app -p -h 127.0.0.1 frradius_db -e "SELECT 1;"

# Expected: No error output
```

### 2.2 Test FreeRADIUS Tables Exist

```sql
-- Login ke MySQL
mysql -u frradius_app -p frradius_db

-- Check semua tabel FreeRADIUS ada
SHOW TABLES LIKE 'rad%';

-- Expected output:
-- +------------------------+
-- | Tables_in_frradius (rad%) |
-- +------------------------+
-- | radacct               |
-- | radcheck              |
-- | radgroupcheck         |
-- | radgroupreply         |
-- | radnas                |
-- | radpostauth           |
-- | radreply              |
-- | radusergroup          |
-- | radippool             |
-- +------------------------+
```

### 2.3 Test Laravel DB Connection

```bash
php artisan db

# Atau test specific query
php artisan tinker --execute="DB::select('SELECT 1');"
# Expected: [[{"1":1}]]
```

---

## 3. Testing FreeRADIUS

### 3.1 Test FreeRADIUS Service

```bash
# Check status
systemctl status freeradius

# Start if not running
sudo systemctl start freeradius
sudo systemctl enable freeradius
```

### 3.2 Test RADIUS Authentication (radclient)

```bash
# Test Access-Request manual
echo "User-Name=test_user, User-Password=test123" | \
    radclient -x 127.0.0.1:1812 auth testing123

# Expected responses:
# 
# SUCCESS (Access-Accept):
# Received response ID: X, code: 2, length: XX
# Reply-Message = "Welcome test_user"
#
# FAILURE (Access-Reject):
# Received response ID: X, code: 3, length: XX
```

### 3.3 Test radcheck Table

```sql
-- Insert test user
INSERT INTO radcheck (username, attribute, op, value)
VALUES ('test_user', 'Cleartext-Password', ':=', 'test123');

-- Verify
SELECT * FROM radcheck WHERE username = 'test_user';

-- Test auth
echo "User-Name=test_user, User-Password=test123" | \
    radclient -x 127.0.0.1:1812 auth testing123
-- Expected: Access-Accept
```

### 3.4 Test radreply Table

```sql
-- Insert reply attributes
INSERT INTO radreply (username, attribute, op, value)
VALUES
    ('test_user', 'Reply-Message', '=', 'Welcome!'),
    ('test_user', 'Session-Timeout', '=', '3600'),
    ('test_user', 'Idle-Timeout', '=', '300');

-- Verify
SELECT * FROM radreply WHERE username = 'test_user';
```

### 3.5 Test radgroupreply Table

```sql
-- Insert group attributes
INSERT INTO radgroupreply (groupname, attribute, op, value)
VALUES
    ('Test-Paket', 'Mikrotik-Rate-Limit', ':=', '10M/10M'),
    ('Test-Paket', 'Session-Timeout', ':=', '86400');

-- Verify
SELECT * FROM radgroupreply WHERE groupname = 'Test-Paket';
```

### 3.6 Test radnas Table

```sql
-- Insert NAS
INSERT INTO radnas (nasname, shortname, type, ports, secret, description)
VALUES ('172.31.119.10', 'MKT-WYH-01', 'mikrotik', 1812, 'testing123', 'Test NAS');

-- Verify
SELECT * FROM radnas;
```

### 3.7 Test radacct (Accounting)

```sql
-- Simulate accounting start
INSERT INTO radacct (
    acctsessionid, acctuniqueid, username, nasipaddress,
    framedipaddress, callingstationid, acctstarttime,
    acctsessiontime, acctinputoctets, acctoutputoctets
) VALUES (
    'test-session-001', 'unique-001', 'test_user', '172.31.119.10',
    '192.168.1.100', 'AA:BB:CC:DD:EE:FF', NOW(),
    0, 0, 0
);

-- Simulate accounting stop
UPDATE radacct SET
    acctstoptime = NOW(),
    acctsessiontime = 3600,
    acctinputoctets = 1024000,
    acctoutputoctets = 2048000,
    acctterminatecause = 'User-Request'
WHERE acctsessionid = 'test-session-001';

-- Verify
SELECT * FROM radacct WHERE username = 'test_user';
```

### 3.8 Check FreeRADIUS Logs

```bash
# Watch live logs
sudo tail -f /var/log/freeradius/radius.log

# Filter for test user
sudo tail -f /var/log/freeradius/radius.log | grep test_user
```

---

## 4. Testing Laravel Migrations

### 4.1 Run Migrations

```bash
# Fresh install
php artisan migrate:fresh

# Or incremental
php artisan migrate
```

### 4.2 Verify Migrations

```bash
# List status
php artisan migrate:status

# Expected output:
# Migration name                    | Batch   | Status
# --------------------------------------------------------
# 2026_04_29_000001_create_radcheck_table | 1      | Ran
# 2026_04_29_000002_create_radreply_table  | 1      | Ran
# ... (9 tables total)
```

### 4.3 Test Seeder

```bash
php artisan db:seed --class=FreeradiusSeeder

# Verify
mysql -u frradius_app -p frradius_db -e "SELECT * FROM radnas;"
```

---

## 5. Testing Models & Services

### 5.1 Test RadCheck Model

```bash
php artisan tinker

# Test create
>>> App\Models\RadCheck::create([
...     'username' => 'test_radcheck',
...     'attribute' => 'Cleartext-Password',
...     'op' => ':=',
...     'value' => 'test123'
... ]);

# Test query
>>> App\Models\RadCheck::where('username', 'test_radcheck')->get();

# Test userExists
>>> App\Models\RadCheck::userExists('test_radcheck');
// Expected: true
```

### 5.2 Test RadReply Model

```bash
php artisan tinker

# Test create
>>> App\Models\RadReply::create([
...     'username' => 'test_radreply',
...     'attribute' => 'Reply-Message',
...     'op' => '=',
...     'value' => 'Welcome!'
... ]);

# Test query
>>> App\Models\RadReply::where('username', 'test_radreply')->get();
```

### 5.3 Test RadUserGroup Model

```bash
php artisan tinker

# Test assign
>>> App\Models\RadUserGroup::assignUser('test_user', 'Test-Paket');

# Test query
>>> App\Models\RadUserGroup::where('username', 'test_user')->first();

# Test isInGroup
>>> App\Models\RadUserGroup::isInGroup('test_user', 'Test-Paket');
// Expected: true
```

### 5.4 Test RadNas Model

```bash
php artisan tinker

# Create test NAS first in Laravel (if Nas model exists)
# Then test sync
>>> $nas = App\Models\Nas::first();
>>> App\Models\RadNas::syncFromNas($nas);

# Test query
>>> App\Models\RadNas::getByIp('172.31.119.10');
```

### 5.5 Test RadGroupReply Model

```bash
php artisan tinker

# Test sync hotspot profile
>>> $profile = App\Models\HotspotProfile::first();
>>> App\Models\RadGroupReply::syncHotspotProfile($profile);

# Test query
>>> App\Models\RadGroupReply::where('groupname', $profile->name)->get();
```

### 5.6 Test RadiusSyncService

```bash
php artisan tinker

# Instantiate service
>>> $service = app(\App\Services\RadiusSyncService::class);

# Get stats
>>> $service->getStats();
// Expected: ['radcheck' => X, 'radreply' => X, ...]

# Verify user
>>> $service->verifyUser('test_user');
// Expected: ['radcheck' => bool, 'radreply' => bool, 'radusergroup' => bool]
```

### 5.7 Test RadiusSessionService

```bash
php artisan tinker

# Instantiate service
>>> $service = app(\App\Services\RadiusSessionService::class);

# Get online stats
>>> $service->getOnlineStats();
// Expected: ['pppoe_online' => X, 'hotspot_online' => X, ...]

# Sync sessions
>>> $service->syncActiveSessions();
// Returns: int (number of sessions synced)
```

---

## 6. Testing Observers

### 6.1 Test HotspotUserObserver

```bash
php artisan tinker

# Create hotspot user (should trigger observer)
>>> $user = App\Models\HotspotUser::create([
...     'username' => 'test_hotspot_observer',
...     'password' => 'test123',
...     'profile_id' => 1,
...     'reseller_id' => 1,
...     'status' => 2,  // STATUS_PAID (Active)
... ]);

# Verify in radcheck
>>> App\Models\RadCheck::userExists('test_hotspot_observer');
// Expected: true

# Verify in radreply
>>> App\Models\RadReply::where('username', 'test_hotspot_observer')->count();
// Expected: > 0

# Test update password
>>> $user->password = 'newpassword123';
>>> $user->save();

# Verify new password in radcheck
>>> App\Models\RadCheck::getPassword('test_hotspot_observer');
// Expected: 'newpassword123'

# Test disable user
>>> $user->status = 0;  // STATUS_DISABLED
>>> $user->save();

# Verify removed from radcheck
>>> App\Models\RadCheck::userExists('test_hotspot_observer');
// Expected: false
```

### 6.2 Test PPPoEUserObserver

```bash
php artisan tinker

# Create PPPoE user
>>> $user = App\Models\PPPoEUser::create([
...     'username' => 'test_pppoe_observer',
...     'password' => 'test123',
...     'fullname' => 'Test User',
...     'package' => 1,
...     'status' => 1,  // STATUS_ACTIVE
... ]);

# Verify in radcheck
>>> App\Models\RadCheck::userExists('test_pppoe_observer');
// Expected: true

# Test disable
>>> $user->status = 0;  // STATUS_INACTIVE
>>> $user->save();

>>> App\Models\RadCheck::userExists('test_pppoe_observer');
// Expected: false
```

### 6.3 Test NasObserver

```bash
php artisan tinker

# Create NAS
>>> $nas = App\Models\Nas::create([
...     'name' => 'Test NAS',
...     'type' => 'vpn',
...     'ip_router' => '172.31.119.100',
...     'api_password' => 'secret123'
... ]);

# Verify in radnas
>>> App\Models\RadNas::getByIp('172.31.119.100');
// Expected: RadNas object
```

### 6.4 Test HotspotProfileObserver

```bash
php artisan tinker

# Create profile
>>> $profile = App\Models\HotspotProfile::create([
...     'name' => 'Test-Paket-Observer',
...     'rate_limit' => '10M/10M',
...     'price' => 50000,
...     'valid_for' => 1440,  // 1 day in minutes
...     'status' => 'active'
... ]);

# Verify in radgroupreply
>>> App\Models\RadGroupReply::groupHasAttributes('Test-Paket-Observer');
// Expected: true

# Test deactivation
>>> $profile->status = 'nonaktif';
>>> $profile->save();

>>> App\Models\RadGroupReply::groupHasAttributes('Test-Paket-Observer');
// Expected: false
```

---

## 7. Testing Artisan Commands

### 7.1 Test radius:sync-sessions

```bash
# Sync active sessions
php artisan radius:sync-sessions

# Expected output:
# ════════════════════════════════════════════════════════
#   RADIUS Session Sync
# ═════════════════════════════════════════════��══════════
# 📡 Syncing active sessions from radacct...
# ✅ Successfully synced X active sessions

# With --stats flag
php artisan radius:sync-sessions --stats

# With --full flag
php artisan radius:sync-sessions --full
```

### 7.2 Test radius:sync-all

```bash
# Full sync
php artisan radius:sync-all

# Expected output:
# ════════════════════════════════════════════════════════
#   RADIUS Full Sync
# ════════════════════════════════════════════════════════
# 🔄 Starting full RADIUS sync...
# ✅ Full sync completed successfully!

# Selective sync
php artisan radius:sync-all --profiles
php artisan radius:sync-all --users
php artisan radius:sync-all --nas

# Verify mode
php artisan radius:sync-all --verify

# Stats mode
php artisan radius:sync-all --stats

# Cleanup mode
php artisan radius:sync-all --cleanup
```

### 7.3 Test Scheduler (Cron)

```bash
# Run scheduler manually to test
php artisan schedule:work

# Or run specific scheduled task
php artisan schedule:run

# List scheduled tasks
php artisan schedule:list
```

---

## 8. Testing OpenVPN + RADIUS Auth

### 8.1 Test verify_radius.py Script

```bash
# Test with valid credentials
USERNAME=test_user PASSWORD=test123 \
    python3 /etc/openvpn/scripts/verify_radius.py
echo "Exit code: $?"
# Expected: 0 (success)

# Test with invalid credentials
USERNAME=test_user PASSWORD=wrong_password \
    python3 /etc/openvpn/scripts/verify_radius.py
echo "Exit code: $?"
# Expected: 1 (failure)
```

### 8.2 Test verify_radius.sh Script

```bash
# Make executable
chmod +x /etc/openvpn/scripts/verify_radius.sh

# Test with valid credentials
USERNAME=test_user PASSWORD=test123 \
    /etc/openvpn/scripts/verify_radius.sh
echo "Exit code: $?"
# Expected: 0 (success)

# Test with invalid credentials
USERNAME=test_user PASSWORD=wrong \
    /etc/openvpn/scripts/verify_radius.sh
echo "Exit code: $?"
# Expected: 1 (failure)
```

### 8.3 Test OpenVPN Server

```bash
# Check OpenVPN status
systemctl status openvpn@server

# Start if not running
sudo systemctl start openvpn@server

# Check logs
sudo tail -f /var/log/openvpn/server.log

# Check auth logs
sudo tail -f /var/log/openvpn/auth.log
```

### 8.4 Test OpenVPN Connection (if you have a test client)

```bash
# Create test client config
cat > /tmp/test-client.ovpn << EOF
client
dev tun
proto udp
remote YOUR_SERVER_IP 1194
resolv-retry infinite
nobind
persist-key
persist-tun
cipher AES-256-CBC
auth SHA256
auth-user-pass
EOF

# Connect
sudo openvpn --config /tmp/test-client.ovpn

# Test auth when prompted
# Username: test_user
# Password: test123
```

---

## 9. Testing End-to-End

### 9.1 Complete User Creation Flow

```bash
# Step 1: Create user di Laravel
php artisan tinker

>>> $user = App\Models\HotspotUser::create([
...     'username' => 'e2e_test_user',
...     'password' => 'e2e_test_pass',
...     'profile_id' => 1,
...     'reseller_id' => 1,
...     'status' => 2,
... ]);

# Step 2: Verify radcheck
>>> App\Models\RadCheck::userExists('e2e_test_user');
// Expected: true

# Step 3: Verify radreply
>>> App\Models\RadReply::where('username', 'e2e_test_user')->count();
// Expected: > 0

# Step 4: Verify radusergroup
>>> App\Models\RadUserGroup::isInGroup('e2e_test_user', '1 Jam - 5 Mbps');
// Expected: true (depends on profile_id)

# Step 5: Test RADIUS auth
# Exit tinker first, then:
echo "User-Name=e2e_test_user, User-Password=e2e_test_pass" | \
    radclient -x 127.0.0.1:1812 auth testing123

# Expected: Access-Accept
```

### 9.2 Complete User Login Flow (Mikrotik Hotspot)

```bash
# Assumptions:
# - Mikrotik sudah dikonfigurasi dengan RADIUS
# - OpenVPN tunnel sudah aktif
# - FreeRADIUS sudah receive accounting

# Step 1: User login ke Mikrotik Hotspot
# (manual test via browser/login page)

# Step 2: Check radacct for accounting
mysql -u frradius_app -p frradius_db -e "
SELECT username, nasipaddress, framedipaddress,
       acctstarttime, acctsessiontime
FROM radacct
WHERE username = 'e2e_test_user'
ORDER BY acctstarttime DESC LIMIT 1;
"

# Expected: Session record exists

# Step 3: Run session sync
php artisan radius:sync-sessions

# Step 4: Verify hotspot_sessions
mysql -u frradius_app -p frradius_db -e "
SELECT * FROM hotspot_sessions
WHERE username = 'e2e_test_user';
"

# Step 5: User logout
# (disconnect from hotspot)

# Step 6: Check radacct for stop record
mysql -u frradius_app -p frradius_db -e "
SELECT username, acctstoptime, acctsessiontime,
       acctterminatecause
FROM radacct
WHERE username = 'e2e_test_user'
ORDER BY acctstarttime DESC LIMIT 1;
"

# Expected: acctstoptime not null
```

### 9.3 Complete Profile Change Flow

```bash
# Step 1: Update hotspot profile
php artisan tinker

>>> $profile = App\Models\HotspotProfile::find(1);
>>> $profile->rate_limit = '20M/20M';
>>> $profile->save();

# Step 2: Verify radgroupreply updated
>>> App\Models\RadGroupReply::getGroupRateLimit($profile->name);
// Expected: '20M/20M'

# Step 3: Resync all users in this profile
>>> $service = app(\App\Services\RadiusSyncService::class);
>>> $service->resyncUsersInProfile($profile->name, 'hotspot');
// Returns: int (count of users resynced)

# Step 4: Verify one user's radreply updated
>>> App\Models\RadReply::getAttributeValue('some_user', 'Mikrotik-Queue-Max-Rate');
// Expected: '20M/20M'
```

---

## 10. Test Results Template

Gunakan template ini untuk dokumentasi testing:

```markdown
## Test Report: [Tanggal]

### Environment
- OS: [Ubuntu 22.04]
- PHP: [8.2.x]
- MySQL: [8.0.x]
- FreeRADIUS: [3.0.x]
- Laravel: [11.x]

### Test Results

| # | Test Case | Expected | Actual | Status |
|---|----------|---------|--------|--------|
| 1 | MySQL Connection | Connected | [PASS/FAIL] | ✅/❌ |
| 2 | FreeRADIUS radcheck insert | Row created | [PASS/FAIL] | ✅/❌ |
| 3 | radclient auth (valid) | Access-Accept | [PASS/FAIL] | ✅/❌ |
| 4 | radclient auth (invalid) | Access-Reject | [PASS/FAIL] | ✅/❌ |
| 5 | Laravel migrate | All tables created | [PASS/FAIL] | ✅/❌ |
| 6 | RadCheck model create | Record inserted | [PASS/FAIL] | ✅/❌ |
| 7 | HotspotUserObserver::created | Synced to RADIUS | [PASS/FAIL] | ✅/❌ |
| 8 | radius:sync-sessions | Sessions synced | [PASS/FAIL] | ✅/❌ |
| 9 | verify_radius.py (valid) | Exit 0 | [PASS/FAIL] | ✅/❌ |
| 10 | verify_radius.py (invalid) | Exit 1 | [PASS/FAIL] | ✅/❌ |
| 11 | E2E user creation | User can auth | [PASS/FAIL] | ✅/❌ |

### Issues Found

1. [Issue description]
   - Severity: [High/Medium/Low]
   - Workaround: [If any]

### Sign-off

- Tester: [Name]
- Date: [Date]
- Status: [APPROVED/NEEDS FIXING]
```

---

## 🚨 Common Issues & Quick Fixes

### Issue: radclient command not found

```bash
# Install freeradius-utils
sudo apt-get install freeradius-utils
```

### Issue: FreeRADIUS not responding

```bash
# Check if running
systemctl status freeradius

# Restart
sudo systemctl restart freeradius

# Check logs
sudo tail -f /var/log/freeradius/radius.log
```

### Issue: Permission denied on scripts

```bash
# Fix permissions
chmod +x /etc/openvpn/scripts/verify_radius.py
chmod +x /etc/openvpn/scripts/verify_radius.sh
```

### Issue: MySQL connection failed

```bash
# Test connection
mysql -u frradius_app -p -h 127.0.0.1 frradius_db

# Check credentials in .env
cat .env | grep DB_
```

### Issue: Observer not working

```bash
# Check if observer is registered
php artisan tinker
>>> dd(\App\Models\HotspotUser::class);
```

---

*Document Version: 1.0*
*Last Updated: April 2026*
