# 🔌 Database Integration Blueprint
## FreeRADIUS + Laravel v13 + OpenVPN + OvenVPN

**Purpose:** Konfigurasi database MySQL agar FreeRADIUS, Laravel, dan OpenVPN dapat terintegrasi secara seamless — satu source of truth untuk user credentials, session tracking, dan billing.

**Target:** ISP Billing System dengan Hotspot (Mikrotik tanpa IP Public) + PPPoE + VPN
**Use Case:** Mikrotik di remote POP terhubung ke FreeRADIUS server via OpenVPN tunnel

---

## Step 1 — Arsitektur Database Terintegrasi

### 1.1 Diagram Arsitektur

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        SINGLE MYSQL DATABASE                             │
│                    (laravel_db)                                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────┐    ┌──────────────────────┐                  │
│  │    LARAVEL TABLES     │    │   FREERADIUS TABLES  │                  │
│  │    (billing system)   │    │   (user credentials) │                  │
│  ├──────────────────────┤    ├──────────────────────┤                  │
│  │  hotspot_users        │───►│    radcheck          │  (SYNC)        │
│  │  pppoe_users          │───►│    radreply          │  (SYNC)        │
│  │  hotspot_profiles     │───►│    radgroupreply     │  (SYNC)        │
│  │  pppoe_profiles       │───►│    radgroupcheck     │  (SYNC)        │
│  │  hotspot_sessions     │◄───│    radacct           │  (READ)        │
│  │  pppoe_sessions       │◄───│    radacct           │  (READ)        │
│  │  nas                  │───►│    radnas            │  (SYNC)        │
│  │  invoices             │    │    usergroup         │  (SYNC)        │
│  │  transactions         │    │    radpostauth       │                  │
│  │  mitras               │    │                      │                  │
│  │  pelanggans          │    │                      │                  │
│  │  deposits            │    │                      │                  │
│  │  tikets              │    │                      │                  │
│  │  cpes                │    │                      │                  │
│  └──────────────────────┘    └──────────────────────┘                  │
│           │                           │                                  │
│  ┌────────┴────────┐       ┌──────────┴──────────┐                     │
│  │  LARAVEL APP    │       │    FREERADIUS 3.x   │                     │
│  │  (PHP/Laravel)  │       │    (RADIUS Server)  │                     │
│  └────────┬────────┘       └──────────┬──────────┘                     │
│           │                           │                                  │
│  ┌────────┴────────┐       ┌──────────┴──────────┐                     │
│  │  MIKROTIK       │       │     OPENVPN /      │                     │
│  │  (Hotspot/      │◄─────│     OVENVPN         │                     │
│  │   PPPoE)        │ OpenVPN  │     (VPN Tunnel)  │                     │
│  │  [Tanpa IP Pub] │  Tunnel  │                   │                     │
│  └─────────────────┘       └────────────────────┘                     │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Alur Koneksi

```
MIKROTIK (tanpa IP Public)
    │
    │ 1. Buat VPN tunnel ke OpenVPN Server
    │    OpenVPN IP: 172.31.119.10/30
    │
    ▼
OPENVPN SERVER (172.31.119.1)
    │
    │ 2. RADIUS Access-Request (UDP 1812)
    │    Source: 172.31.119.10
    │
    ▼
FREERADIUS SERVER (172.31.119.2)
    │
    │ 3. Query MySQL (radcheck/radreply)
    │    Authentication & Authorization
    │
    ▼
MYSQL DATABASE (laravel_db)
    │
    │ 4. Same database - zero sync delay
    │    Laravel writes → FreeRADIUS reads instantly
    │
    ▼
LARAVEL APP (Dashboard Admin)
    │ 5. CRUD user, view sessions, billing
    │
    ▼
ADMIN / RESELLER / PELANGGAN
```

### 1.3 Strategi Integrasi

| Approach | Cara Kerja | Kelebihan | Kekurangan |
|----------|-----------|-----------|------------|
| **A. Shared Database** | Laravel tables + FreeRADIUS tables dalam 1 DB MySQL | Simple, realtime, zero sync delay | Schema harus disatukan |
| **B. Dual DB Sync** | Laravel di DB terpisah, sync via trigger/job ke DB FRRADIUS | Fleksibel | Sync delay, complexity tinggi |

**Rekomendasi: Approach A** (Shared Database)

---

## Step 2 — MySQL Schema: FreeRADIUS Tables

### 2.1 Buat Database dan User MySQL

```sql
-- =============================================================
-- MYSQL SETUP — Single Database Approach
-- Jalankan sebagai root MySQL
-- =============================================================

-- 1. Buat database utama
CREATE DATABASE IF NOT EXISTS laravel_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE laravel_db;

-- 2. User untuk Laravel App (full access semua tabel)
CREATE USER IF NOT EXISTS 'laravel_user'@'%'
  IDENTIFIED BY 'Jokam354313';

GRANT ALL PRIVILEGES ON laravel_db.* TO 'laravel_user'@'%';

-- 3. User untuk FreeRADIUS (baca/tulis tabel RADIUS)
CREATE USER IF NOT EXISTS 'radius_user'@'localhost'
  IDENTIFIED BY 'Jokam354313';

GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radcheck TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radreply TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radacct TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radgroupreply TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radgroupcheck TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radusergroup TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radnas TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radpostauth TO 'radius_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radippool TO 'radius_user'@'localhost';

-- Laravel juga perlu akses RADIUS tables (untuk sync via Observer)
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radcheck TO 'laravel_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radreply TO 'laravel_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radusergroup TO 'laravel_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radnas TO 'laravel_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_db.radgroupreply TO 'laravel_user'@'%';

FLUSH PRIVILEGES;
```

### 2.2 FreeRADIUS Core Tables

```sql
-- =============================================================
-- FREERADIUS CORE TABLES
-- Database: laravel_db
-- =============================================================

-- 1. radcheck — Stored user credentials (password)
CREATE TABLE IF NOT EXISTS radcheck (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64) NOT NULL DEFAULT '',
    attribute       VARCHAR(64) NOT NULL DEFAULT 'Crypt-Password',
    op              CHAR(2) NOT NULL DEFAULT ':=',
    value           VARCHAR(253) NOT NULL DEFAULT '',
    INDEX (username),
    INDEX (attribute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. radreply — Server response attributes (rate limit, session timeout)
CREATE TABLE IF NOT EXISTS radreply (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64) NOT NULL DEFAULT '',
    attribute       VARCHAR(64) NOT NULL DEFAULT 'Reply-Message',
    op              CHAR(2) NOT NULL DEFAULT ':=',
    value           VARCHAR(253) NOT NULL DEFAULT '',
    INDEX (username),
    INDEX (attribute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. radacct — Accounting sessions (HOTSPOT & PPPoE tracking)
CREATE TABLE IF NOT EXISTS radacct (
    radacctid       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    acctsessionid   VARCHAR(64) NOT NULL DEFAULT '',
    acctuniqueid    VARCHAR(32) NOT NULL UNIQUE,
    username        VARCHAR(64) NOT NULL DEFAULT '',
    groupname       VARCHAR(64) NOT NULL DEFAULT '',
    realm           VARCHAR(64) NOT NULL DEFAULT '',
    nasipaddress    VARCHAR(45) NOT NULL DEFAULT '',
    nasportid       VARCHAR(15) NOT NULL DEFAULT '',
    nasporttype     VARCHAR(32) NOT NULL DEFAULT '',
    acctstarttime   DATETIME NULL DEFAULT NULL,
    acctupdatetime  DATETIME NULL DEFAULT NULL,
    acctstoptime    DATETIME NULL DEFAULT NULL,
    acctinterval    INT(12) DEFAULT NULL,
    acctsessiontime INT(12) UNSIGNED DEFAULT NULL,
    acctinputoctets BIGINT(20) DEFAULT NULL,
    acctoutputoctets BIGINT(20) DEFAULT NULL,
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    acctterminatecause VARCHAR(50) NOT NULL DEFAULT '',
    servicetype     VARCHAR(32) NOT NULL DEFAULT '',
    framedprotocol  VARCHAR(32) NOT NULL DEFAULT '',
    framedipaddress VARCHAR(45) NOT NULL DEFAULT '',
    framedipv6address VARCHAR(45) NOT NULL DEFAULT '',
    framedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    framedipprefix VARCHAR(45) NOT NULL DEFAULT '',
    delegatedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    INDEX (acctuniqueid),
    INDEX (username),
    INDEX (framedipaddress),
    INDEX (nasipaddress),
    INDEX (acctstarttime),
    INDEX (acctstoptime),
    INDEX (callingstationid),
    INDEX (calledstationid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. radgroupcheck — Group check rules
CREATE TABLE IF NOT EXISTS radgroupcheck (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    groupname       VARCHAR(64) NOT NULL DEFAULT '',
    attribute       VARCHAR(64) NOT NULL DEFAULT '',
    op              CHAR(2) NOT NULL DEFAULT ':=',
    value           VARCHAR(253) NOT NULL DEFAULT '',
    INDEX (groupname),
    INDEX (attribute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. radgroupreply — Group reply attributes (paket speed limit, dll)
CREATE TABLE IF NOT EXISTS radgroupreply (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    groupname       VARCHAR(64) NOT NULL DEFAULT '',
    attribute       VARCHAR(64) NOT NULL DEFAULT '',
    op              CHAR(2) NOT NULL DEFAULT '=',
    value           VARCHAR(253) NOT NULL DEFAULT '',
    INDEX (groupname),
    INDEX (attribute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. radusergroup — User-to-group mapping
CREATE TABLE IF NOT EXISTS radusergroup (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64) NOT NULL DEFAULT '',
    groupname       VARCHAR(64) NOT NULL DEFAULT '',
    priority        INT(11) NOT NULL DEFAULT 1,
    UNIQUE KEY radusergroup_username_groupname_unique (username, groupname),
    INDEX (username),
    INDEX (groupname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. radpostauth — Login attempt logging
CREATE TABLE IF NOT EXISTS radpostauth (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64) NOT NULL DEFAULT '',
    pass            VARCHAR(64) NOT NULL DEFAULT '',
    reply           VARCHAR(32) NOT NULL DEFAULT '',
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    authdate        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (username),
    INDEX (authdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. radnas — NAS clients (Mikrotik servers via OpenVPN)
CREATE TABLE IF NOT EXISTS radnas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nasname         VARCHAR(64) NOT NULL DEFAULT '',
    shortname       VARCHAR(32) NOT NULL DEFAULT '',
    type            VARCHAR(32) NOT NULL DEFAULT 'other',
    ports           INT(5) DEFAULT NULL,
    secret          VARCHAR(60) NOT NULL DEFAULT 'secret',
    server          VARCHAR(64) NOT NULL DEFAULT '',
    community       VARCHAR(50) NOT NULL DEFAULT '',
    description     VARCHAR(200) NOT NULL DEFAULT 'RADIUS Client',
    INDEX (nasname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. radippool — IP address pool untuk PPPoE/DHCP
CREATE TABLE IF NOT EXISTS radippool (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pool_name       VARCHAR(64) NOT NULL DEFAULT '',
    framedipaddress VARCHAR(45) NOT NULL DEFAULT '',
    nasipaddress    VARCHAR(45) NOT NULL DEFAULT '',
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    expiry_time     DATETIME NULL DEFAULT NULL,
    username        VARCHAR(64) NULL DEFAULT NULL,
    pool_key        VARCHAR(30) NOT NULL DEFAULT '',
    INDEX (pool_name),
    INDEX (framedipaddress),
    INDEX (expiry_time),
    INDEX (nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default NAS (Mikrotik via OpenVPN)
INSERT INTO radnas (nasname, shortname, type, ports, secret, description)
VALUES ('172.31.119.10', 'MKT-WYH-01', 'mikrotik', 1812, 'testing123', 'Mikrotik via OpenVPN Tunnel')
ON DUPLICATE KEY UPDATE shortname = 'MKT-WYH-01';
```

---

## Step 3 — Konfigurasi FreeRADIUS

### 3.1 /etc/freeradius/3.0/mods-available/sql

```conf
# /etc/freeradius/3.0/mods-available/sql
sql {
    driver = "rlm_sql_mysql"
    server = "127.0.0.1"
    port = 3306
    login = "radius_user"
    password = "Jokam354313"

    # Database — sama dengan Laravel (shared approach)
    database = "laravel_db"

    runtime_trace = no
    read_groups = yes
    read_clients = yes
    delete_stale_sessions = yes

    # Table names
    acct_table1 = "radacct"
    acct_table2 = "radacct"
    postauth_table = "radpostauth"
    authcheck_table = "radcheck"
    authreply_table = "radreply"
    group_membership_table = "radusergroup"
    nas_table = "radnas"
    ippool_table = "radippool"

    pool {
        start = 5
        min = 5
        max = 10
        spare = 3
        uses = 0
        retry_delay = 30
        lifetime = 86400
        idle_timeout = 600
    }

    mysql {
        warnings = 1625
        tls_required = no
        max_queries = 0
        connect_timeout = 10
        read_timeout = 10
        write_timeout = 10
    }
}
```

### 3.2 Aktifkan SQL Module

```bash
# Aktifkan module SQL
cd /etc/freeradius/3.0/mods-enabled
ln -sf ../mods-available/sql sql

# Enable site default
cd /etc/freeradius/3.0/sites-enabled
ln -sf ../sites-available/default default
```

### 3.3 /etc/freeradius/3.0/clients.conf

```conf
# /etc/freeradius/3.0/clients.conf
# Client = Mikrotik yang terhubung via OpenVPN

# OpenVPN Server (bisa juga via radnas table)
client 172.31.119.1/32 {
    secret          = shared_secret_openvpn
    shortname       = openvpn_bridge
    nas_type        = other
}

# Mikrotik via OpenVPN tunnel
client 172.31.119.10/32 {
    secret          = testing123
    shortname       = MKT-WYH-01
    nas_type        = mikrotik
}

client 172.31.119.11/32 {
    secret          = testing123
    shortname       = MKT-PTU-01
    nas_type        = mikrotik
}
```

### 3.4 /etc/freeradius/3.0/sites-available/default (authorize section)

```conf
authorize {
    # Load group membership dari SQL
    sql

    # Load reply attributes dari SQL
    sql

    # Fallback ke file jika SQL gagal
    files
}

authenticate {
    Auth-Type PAP {
        pap
        sql
    }
    Auth-Type CHAP {
        chap
        sql
    }
    Auth-Type MS-CHAP {
        mschap
        sql
    }
}

accounting {
    detail
    sql
    exec
    attr_filter.accounting_response
}

post-auth {
    sql

    if ("%{Acct-Unique-Session-Id}" != "") {
        update reply {
            &Acct-Unique-Session-Id := "%{Acct-Unique-Session-Id}"
        }
    }

    Post-Auth-Type REJECT {
        attr_filter.access_reject
    }
}
```

---

## Step 4 — Laravel Multi-Database Configuration

### 4.1 config/database.php

```php
<?php
// config/database.php

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel_db'),
            'username' => env('DB_USERNAME', 'laravel_user'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
```

> **Catatan:** Karena Laravel dan FreeRADIUS menggunakan database yang SAMA (`laravel_db`), tidak perlu multi-database connection. Laravel bisa langsung write ke `radcheck`, `radreply`, dll menggunakan Eloquent ORM.

### 4.2 Konfigurasi .env

```env
# .env — Laravel Environment

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=Jokam354313

# FreeRADIUS Server
RADIUS_SERVER=127.0.0.1
RADIUS_AUTH_PORT=1812
RADIUS_ACCT_PORT=1813
RADIUS_SECRET=testing123

# OpenVPN
OPENVPN_SUBNET=172.31.119.0/24
```

---

## Step 5 — Laravel Models untuk FreeRADIUS Tables

### 5.1 RadCheck Model

```php
<?php
// app/Models/RadCheck.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadCheck extends Model
{
    protected $table = 'radcheck';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['username', 'attribute', 'op', 'value'];

    // Constants
    const ATTR_CLEAR_TEXT_PASSWORD = 'Cleartext-Password';
    const ATTR_CRYPT_PASSWORD      = 'Crypt-Password';
    const ATTR_USER_PASSWORD        = 'Password';
    const ATTR_SIMULTANEOUS_USE    = 'Simultaneous-Use';

    /**
     * Sync hotspot user ke radcheck
     */
    public static function syncHotspotUser(HotspotUser $user): void
    {
        // Hapus entry lama
        self::where('username', $user->username)->delete();

        if ($user->status === HotspotUser::STATUS_DISABLED) {
            return;
        }

        // Insert password (Cleartext — untuk Mikrotik Hotspot)
        self::create([
            'username'   => $user->username,
            'attribute'  => self::ATTR_CLEAR_TEXT_PASSWORD,
            'op'         => ':=',
            'value'      => $user->password,
        ]);

        // Simultaneous-Connect (max login concurrent)
        $profile = $user->profile;
        if ($profile && $profile->shared_users > 1) {
            self::create([
                'username'   => $user->username,
                'attribute'  => self::ATTR_SIMULTANEOUS_USE,
                'op'         => ':=',
                'value'      => (string) $profile->shared_users,
            ]);
        }
    }

    /**
     * Sync PPPoE user ke radcheck
     */
    public static function syncPPPoEUser(PPPoEUser $user): void
    {
        self::where('username', $user->username)->delete();

        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            return;
        }

        // Password
        self::create([
            'username'   => $user->username,
            'attribute'  => self::ATTR_CLEAR_TEXT_PASSWORD,
            'op'         => ':=',
            'value'      => $user->password,
        ]);

        // MAC binding (Simultaneous-Use = 1)
        if ($user->mac_address) {
            self::create([
                'username'   => $user->username,
                'attribute'  => self::ATTR_SIMULTANEOUS_USE,
                'op'         => ':=',
                'value'      => '1',
            ]);
        }
    }

    /**
     * Hapus user dari radcheck
     */
    public static function removeUser(string $username): void
    {
        self::where('username', $username)->delete();
    }
}
```

### 5.2 RadReply Model

```php
<?php
// app/Models/RadReply.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadReply extends Model
{
    protected $table = 'radreply';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['username', 'attribute', 'op', 'value'];

    /**
     * Sync reply attributes untuk hotspot user
     */
    public static function syncHotspotUser(HotspotUser $user): void
    {
        self::where('username', $user->username)->delete();

        $profile = $user->profile;
        if (!$profile) return;

        // Rate Limit: Mikrotik-Queue-Max-Rate
        if ($profile->rate_limit) {
            self::create([
                'username'   => $user->username,
                'attribute'  => 'Mikrotik-Queue-Max-Rate',
                'op'         => '=',
                'value'      => $profile->rate_limit,
            ]);
        }

        // Session Timeout (dalam detik)
        if ($profile->valid_for > 0) {
            self::create([
                'username'   => $user->username,
                'attribute'  => 'Session-Timeout',
                'op'         => '=',
                'value'      => (string) ($profile->valid_for * 60),
            ]);
        }

        // Idle Timeout (5 menit default)
        self::create([
            'username'   => $user->username,
            'attribute'  => 'Idle-Timeout',
            'op'         => '=',
            'value'      => '300',
        ]);

        // Reply Message
        self::create([
            'username'   => $user->username,
            'attribute'  => 'Reply-Message',
            'op'         => '=',
            'value'      => "Selamat datang {$user->username}! Paket: {$profile->name}",
        ]);
    }

    /**
     * Sync reply attributes untuk PPPoE user
     */
    public static function syncPPPoEUser(PPPoEUser $user): void
    {
        self::where('username', $user->username)->delete();

        $profile = $user->profile;
        if (!$profile) return;

        // Rate Limit
        if ($profile->rate_limit) {
            self::create([
                'username'   => $user->username,
                'attribute'  => 'Mikrotik-Rate-Limit',
                'op'         => '=',
                'value'      => $profile->rate_limit,
            ]);
        }

        // Framed Protocol
        self::create([
            'username'   => $user->username,
            'attribute'  => 'Framed-Protocol',
            'op'         => '=',
            'value'      => 'PPP',
        ]);

        // Group
        self::create([
            'username'   => $user->username,
            'attribute'  => 'Group',
            'op'         => '=',
            'value'      => $profile->group ?? 'FRRADIUS',
        ]);

        // Static IP (jika ada)
        if ($user->ip_address) {
            self::create([
                'username'   => $user->username,
                'attribute'  => 'Framed-IP-Address',
                'op'         => '=',
                'value'      => $user->ip_address,
            ]);
        }
    }

    /**
     * Hapus reply attributes
     */
    public static function removeUser(string $username): void
    {
        self::where('username', $username)->delete();
    }
}
```

### 5.3 RadUserGroup Model

```php
<?php
// app/Models/RadUserGroup.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadUserGroup extends Model
{
    protected $table = 'radusergroup';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['username', 'groupname', 'priority'];

    /**
     * Assign user ke paket group
     */
    public static function assignUser(string $username, string $groupname): void
    {
        self::updateOrCreate(
            ['username' => $username],
            ['groupname' => $groupname, 'priority' => 1]
        );
    }

    /**
     * Hapus user dari semua group
     */
    public static function removeUser(string $username): void
    {
        self::where('username', $username)->delete();
    }
}
```

### 5.4 RadNas Model

```php
<?php
// app/Models/RadNas.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadNas extends Model
{
    protected $table = 'radnas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nasname', 'shortname', 'type', 'ports',
        'secret', 'server', 'community', 'description'
    ];

    /**
     * Sync NAS dari tabel Laravel ke FreeRADIUS
     */
    public static function syncFromNas(Nas $nas): void
    {
        self::updateOrCreate(
            ['nasname' => $nas->ip_router],
            [
                'shortname'   => $nas->name,
                'type'        => 'mikrotik',
                'ports'       => 1812,
                'secret'      => $nas->api_password ?? 'testing123',
                'community'   => $nas->snmp ?? 'public',
                'description' => "Synced from Laravel NAS #{$nas->id}",
            ]
        );
    }

    /**
     * Hapus NAS dari FreeRADIUS
     */
    public static function removeNas(string $ipRouter): void
    {
        self::where('nasname', $ipRouter)->delete();
    }
}
```

### 5.5 RadGroupReply Model

```php
<?php
// app/Models/RadGroupReply.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadGroupReply extends Model
{
    protected $table = 'radgroupreply';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['groupname', 'attribute', 'op', 'value'];

    /**
     * Sync hotspot profile → radgroupreply
     */
    public static function syncHotspotProfile(HotspotProfile $profile): void
    {
        // Hapus group lama
        self::where('groupname', $profile->name)->delete();

        if ($profile->status !== 'active') return;

        // Rate Limit
        if ($profile->rate_limit) {
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => 'Mikrotik-Queue-Max-Rate',
                'op'         => ':=',
                'value'      => $profile->rate_limit,
            ]);
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => 'Mikrotik-Queue-Limit',
                'op'         => ':=',
                'value'      => "{$profile->shared_users}M/{$profile->shared_users}M",
            ]);
        }

        // Session Timeout
        if ($profile->valid_for > 0) {
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => 'Session-Timeout',
                'op'         => ':=',
                'value'      => (string) ($profile->valid_for * 60),
            ]);
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => 'Max-All-Session',
                'op'         => ':=',
                'value'      => (string) ($profile->valid_for * 60),
            ]);
        }

        // Idle Timeout
        self::create([
            'groupname'  => $profile->name,
            'attribute'  => 'Idle-Timeout',
            'op'         => ':=',
            'value'      => '300',
        ]);

        // Reply Message
        self::create([
            'groupname'  => $profile->name,
            'attribute'  => 'Reply-Message',
            'op'         => ':=',
            'value'      => "Paket: {$profile->name}",
        ]);
    }
}
```

---

## Step 6 — RadiusSyncService

```php
<?php
// app/Services/RadiusSyncService.php
namespace App\Services;

use App\Models\HotspotUser;
use App\Models\PPPoEUser;
use App\Models\HotspotProfile;
use App\Models\Nas;
use App\Models\RadCheck;
use App\Models\RadReply;
use App\Models\RadUserGroup;
use App\Models\RadNas;
use App\Models\RadGroupReply;
use Illuminate\Support\Facades\Log;

class RadiusSyncService
{
    /**
     * Sync semua data dari Laravel ke FreeRADIUS tables
     */
    public function syncAll(): void
    {
        $this->syncHotspotProfiles();
        $this->syncNasDevices();
        $this->syncHotspotUsers();
        $this->syncPPPoEUsers();

        Log::info('RADIUS: Full sync completed');
    }

    /**
     * Sync semua hotspot profile → radgroupreply
     */
    public function syncHotspotProfiles(): void
    {
        HotspotProfile::where('status', 'active')->each(function ($profile) {
            RadGroupReply::syncHotspotProfile($profile);
        });
    }

    /**
     * Sync semua NAS → radnas
     */
    public function syncNasDevices(): void
    {
        Nas::whereNotNull('ip_router')->each(function ($nas) {
            RadNas::syncFromNas($nas);
        });
    }

    /**
     * Sync hotspot user → radcheck + radreply + radusergroup
     */
    public function syncHotspotUser(HotspotUser $user): void
    {
        RadCheck::syncHotspotUser($user);
        RadReply::syncHotspotUser($user);

        if ($user->status !== HotspotUser::STATUS_DISABLED && $user->profile) {
            RadUserGroup::assignUser($user->username, $user->profile->name);
        } else {
            RadUserGroup::removeUser($user->username);
        }

        Log::info("RADIUS: Synced hotspot user {$user->username}");
    }

    /**
     * Sync PPPoE user → radcheck + radreply + radusergroup
     */
    public function syncPPPoEUser(PPPoEUser $user): void
    {
        RadCheck::syncPPPoEUser($user);
        RadReply::syncPPPoEUser($user);

        if ($user->status !== PPPoEUser::STATUS_INACTIVE) {
            $group = $user->profile?->group ?? 'FRRADIUS';
            RadUserGroup::assignUser($user->username, $group);
        } else {
            RadUserGroup::removeUser($user->username);
        }

        Log::info("RADIUS: Synced PPPoE user {$user->username}");
    }

    /**
     * Hapus user dari FreeRADIUS
     */
    public function removeUser(string $username): void
    {
        RadCheck::removeUser($username);
        RadReply::removeUser($username);
        RadUserGroup::removeUser($username);

        Log::info("RADIUS: Removed user {$username}");
    }

    /**
     * Sync NAS ke FreeRADIUS
     */
    public function syncNas(Nas $nas): void
    {
        RadNas::syncFromNas($nas);
        Log::info("RADIUS: Synced NAS {$nas->name} ({$nas->ip_router})");
    }
}
```

---

## Step 7 — Model Observers (Auto-Sync)

### 7.1 HotspotUserObserver

```php
<?php
// app/Observers/HotspotUserObserver.php
namespace App\Observers;

use App\Models\HotspotUser;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

class HotspotUserObserver
{
    public function __construct(private RadiusSyncService $radiusSync) {}

    public function created(HotspotUser $user): void
    {
        try {
            $this->radiusSync->syncHotspotUser($user);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to create hotspot user {$user->username}: {$e->getMessage()}");
        }
    }

    public function updated(HotspotUser $user): void
    {
        // Jika status berubah ke disabled, hapus dari RADIUS
        if ($user->status === HotspotUser::STATUS_DISABLED) {
            $this->radiusSync->removeUser($user->username);
            return;
        }

        try {
            $this->radiusSync->syncHotspotUser($user);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to update hotspot user {$user->username}: {$e->getMessage()}");
        }
    }

    public function deleted(HotspotUser $user): void
    {
        try {
            $this->radiusSync->removeUser($user->username);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to delete hotspot user {$user->username}: {$e->getMessage()}");
        }
    }
}
```

### 7.2 PPPoEUserObserver

```php
<?php
// app/Observers/PPPoEUserObserver.php
namespace App\Observers;

use App\Models\PPPoEUser;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

class PPPoEUserObserver
{
    public function __construct(private RadiusSyncService $radiusSync) {}

    public function created(PPPoEUser $user): void
    {
        try {
            $this->radiusSync->syncPPPoEUser($user);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to create PPPoE user {$user->username}: {$e->getMessage()}");
        }
    }

    public function updated(PPPoEUser $user): void
    {
        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            $this->radiusSync->removeUser($user->username);
            return;
        }

        try {
            $this->radiusSync->syncPPPoEUser($user);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to update PPPoE user {$user->username}: {$e->getMessage()}");
        }
    }

    public function deleted(PPPoEUser $user): void
    {
        try {
            $this->radiusSync->removeUser($user->username);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to delete PPPoE user {$user->username}: {$e->getMessage()}");
        }
    }
}
```

### 7.3 NasObserver

```php
<?php
// app/Observers/NasObserver.php
namespace App\Observers;

use App\Models\Nas;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

class NasObserver
{
    public function __construct(private RadiusSyncService $radiusSync) {}

    public function created(Nas $nas): void
    {
        try {
            $this->radiusSync->syncNas($nas);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to sync NAS {$nas->name}: {$e->getMessage()}");
        }
    }

    public function updated(Nas $nas): void
    {
        if ($nas->wasChanged(['ip_router', 'name', 'api_password'])) {
            try {
                $this->radiusSync->syncNas($nas);
            } catch (\Exception $e) {
                Log::error("RADIUS: Failed to update NAS {$nas->name}: {$e->getMessage()}");
            }
        }
    }

    public function deleted(Nas $nas): void
    {
        try {
            \App\Models\RadNas::removeNas($nas->ip_router);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to remove NAS {$nas->name}: {$e->getMessage()}");
        }
    }
}
```

### 7.4 Register Observers di AppServiceProvider

```php
<?php
// app/Providers/AppServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HotspotUser;
use App\Models\PPPoEUser;
use App\Models\Nas;
use App\Models\HotspotProfile;
use App\Observers\HotspotUserObserver;
use App\Observers\PPPoEUserObserver;
use App\Observers\NasObserver;
use App\Services\RadiusSyncService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RadiusSyncService::class, function ($app) {
            return new RadiusSyncService();
        });
    }

    public function boot(): void
    {
        // Auto-sync ke FreeRADIUS saat user dibuat/diubah/dihapus
        HotspotUser::observe(HotspotUserObserver::class);
        PPPoEUser::observe(PPPoEUserObserver::class);
        Nas::observe(NasObserver::class);

        // Sync hotspot profile saat diubah
        HotspotProfile::updated(function ($profile) {
            if ($profile->wasChanged(['rate_limit', 'valid_for', 'shared_users', 'status'])) {
                app(RadiusSyncService::class)->syncHotspotProfiles();
            }
        });
    }
}
```

---

## Step 8 — Session Sync Command (Cron)

```php
<?php
// app/Console/Commands/SyncRadiusSessions.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Nas;
use App\Models\HotspotSession;
use App\Models\PPPoESession;
use App\Models\HotspotUser;
use App\Models\PPPoEUser;

class SyncRadiusSessions extends Command
{
    protected $signature = 'radius:sync-sessions';
    protected $description = 'Sync active sessions dari radacct ke Laravel tables';

    public function handle(): int
    {
        $this->info('Syncing RADIUS sessions...');

        // Ambil sessions yang masih aktif (acctstoptime = NULL)
        $sessions = DB::table('radacct')
            ->whereNull('acctstoptime')
            ->where('acctstarttime', '>', now()->subHours(24))
            ->orderBy('acctstarttime', 'desc')
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            // Cek apakah PPPoE atau Hotspot user
            $pppoeUser = PPPoEUser::where('username', $session->username)->first();

            if ($pppoeUser) {
                $this->syncPPPoESession($session);
                $count++;
            } else {
                $hotspotUser = HotspotUser::where('username', $session->username)->first();
                if ($hotspotUser) {
                    $this->syncHotspotSession($session);
                    $count++;
                }
            }
        }

        $this->info("Synced {$count} active sessions.");
        return Command::SUCCESS;
    }

    private function syncPPPoESession($session): void
    {
        $nas = Nas::where('ip_router', $session->nasipaddress)->first();

        PPPoESession::updateOrCreate(
            ['username' => $session->username, 'nas_name' => $session->nasipaddress],
            [
                'ip_address'     => $session->framedipaddress,
                'mac_address'    => $session->callingstationid,
                'nas_id'        => $nas?->id,
                'nas_name'      => $session->nasipaddress,
                'input_octets'  => $session->acctinputoctets ?? 0,
                'output_octets' => $session->acctoutputoctets ?? 0,
                'session_time'  => $session->acctsessiontime ?? 0,
                'status'        => 'online',
                'start_time'    => $session->acctstarttime,
                'last_update'   => now(),
            ]
        );
    }

    private function syncHotspotSession($session): void
    {
        HotspotSession::updateOrCreate(
            ['username' => $session->username],
            [
                'ip_address'     => $session->framedipaddress,
                'mac_address'    => $session->callingstationid,
                'nas'           => $session->nasipaddress,
                'input_octets'  => $session->acctinputoctets ?? 0,
                'output_octets' => $session->acctoutputoctets ?? 0,
                'session_time'  => $session->acctsessiontime ?? 0,
                'start_time'    => $session->acctstarttime,
            ]
        );
    }
}
```

### Register di Kernel

```php
<?php
// app/Console/Kernel.php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Sync sessions setiap 1 menit
        $schedule->command('radius:sync-sessions')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Full sync profiles & NAS setiap 5 menit
        $schedule->call(function () {
            app(\App\Services\RadiusSyncService::class)->syncAll();
        })->everyFiveMinutes()->withoutOverlapping();

        // Cleanup expired sessions setiap 1 jam
        $schedule->command('radius:cleanup-sessions')->hourly();
    }
}
```

---

## Step 9 — OpenVPN + OvenVPN Integration

### 9.1 Arsitektur OpenVPN

```
MIKROTIK (Tanpa IP Public)
    │
    │ 1. OpenVPN Client → OpenVPN Server
    │    Protocol: UDP
    │    Port: 1194
    │    Auth: username/password
    │
    ▼
OPENVPN SERVER (Public IP)
    │
    │ 2. auth-user-pass-verify
    │    Script: /etc/openvpn/verify_radius.py
    │
    ▼
FREERADIUS (Localhost)
    │
    │ 3. radclient → MySQL (radcheck)
    │
    ▼
MYSQL / LARAVEL
```

### 9.2 OpenVPN Server Config

```conf
# /etc/openvpn/server.conf
port 1194
proto udp
dev tun0

# Server network — pakai subnet yang tidak bentrok
server 172.31.119.0 255.255.255.0

# OpenVPN Server IP di tunnel
ifconfig 172.31.119.1 172.31.119.2

# Enable compression
compress lz4-v2
push "compress lz4-v2"

# DNS
push "dhcp-option DNS 8.8.8.8"
push "dhcp-option DNS 8.8.4.4"

# === RADIUS AUTHENTICATION ===
# Verifikasi username/password via FreeRADIUS
auth-user-pass-verify /etc/openvpn/verify_radius.py via-env

# Gunakan username sebagai common name
username-as-common-name

# === SECURITY ===
keepalive 10 120
cipher AES-256-GCM
auth SHA256
persist-key
persist-tun

# === ROUTING ===
# Route ke jaringan FreeRADIUS/Mikrotik
route 172.31.119.0 255.255.255.0

# === LOGGING ===
status /var/log/openvpn/status.log
log-append /var/log/openvpn/server.log
verb 3
```

### 9.3 Python Verification Script

```python
#!/usr/bin/env python3
# /etc/openvpn/verify_radius.py
"""
OpenVPN Authentication via FreeRADIUS
Dipanggil oleh OpenVPN setiap user login

Usage di openvpn.conf:
    auth-user-pass-verify /etc/openvpn/verify_radius.py via-env
"""

import sys
import os
import socket
import struct
import secrets

# Konfigurasi
RADIUS_SERVER = os.getenv('RADIUS_SERVER', '127.0.0.1')
RADIUS_PORT = int(os.getenv('RADIUS_PORT', '1812'))
RADIUS_SECRET = os.getenv('RADIUS_SECRET', 'testing123')


def send_radius_request(username: str, password: str, nas_ip: str) -> bool:
    """
    Kirim Access-Request ke FreeRADIUS via raw socket
    Returns True = Access-Accept, False = Access-Reject
    """
    import random

    # Build Access-Request packet
    identifier = random.randint(0, 255)
    authenticator = bytes([random.randint(0, 255) for _ in range(16)])

    def add_tlv(t: int, v: str) -> bytes:
        b = v.encode('utf-8')
        return struct.pack('!BB', t, 2 + len(b)) + b

    # Build attributes
    attrs = b''
    attrs += add_tlv(1, username)           # User-Name
    attrs += add_tlv(2, password)           # User-Password (will be encrypted by RADIUS)
    attrs += add_tlv(4, nas_ip)             # NAS-IP-Address
    attrs += add_tlv(6, 'Login-TCP')        # Service-Type

    # Message-Authenticator
    msg_auth = bytes(16)  # Simplified
    attrs += struct.pack('!BB', 80, 18) + msg_auth

    # Packet header: Code(1) + ID(1) + Length(2) + Authenticator(16)
    packet = struct.pack('!BBH', 1, identifier, 20 + len(attrs))
    packet += authenticator + attrs

    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.settimeout(5)
        sock.sendto(packet, (RADIUS_SERVER, RADIUS_PORT))
        response, _ = sock.recvfrom(1024)
        sock.close()
    except socket.timeout:
        print("RADIUS timeout", file=sys.stderr)
        return False
    except Exception as e:
        print(f"RADIUS error: {e}", file=sys.stderr)
        return False

    if len(response) < 20:
        return False

    code = response[0]
    return code == 2  # Access-Accept = 2, Access-Reject = 3


def main():
    username = os.getenv('username', '').strip()
    password = os.getenv('password', '').strip()
    nas_ip = os.getenv('untrusted_ip', '127.0.0.1')

    if not username or not password:
        print("Missing credentials", file=sys.stderr)
        sys.exit(1)

    try:
        success = send_radius_request(username, password, nas_ip)

        if success:
            print(f"Auth OK: {username}", file=sys.stderr)
            sys.exit(0)  # OpenVPN: allow
        else:
            print(f"Auth FAILED: {username}", file=sys.stderr)
            sys.exit(1)  # OpenVPN: deny

    except Exception as e:
        print(f"Verify error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
```

### 9.4 Bash Alternative (radclient)

```bash
#!/bin/bash
# /etc/openvpn/verify_radius.sh
# OpenVPN auth script menggunakan radclient

USERNAME="${username}"
PASSWORD="${password}"
RADIUS_SERVER="127.0.0.1"
RADIUS_SECRET="testing123"
NAS_IP="${untrusted_ip}"

echo "User-Name=${USERNAME}, User-Password=${PASSWORD}, NAS-IP-Address=${NAS_IP}" \
    | radclient -x -r 3 ${RADIUS_SERVER}:1812 auth ${RADIUS_SECRET} \
    | grep -q "Access-Accept"

if [ $? -eq 0 ]; then
    exit 0  # Allow
else
    exit 1  # Deny
fi
```

### 9.5 Mikrotik OpenVPN Client Config

```routeros
# Mikrotik sebagai OpenVPN Client
# Menu: System > OpenVPN Client

/add
name=ovpn-out1
connect-to=YOUR_OPENVPN_SERVER_PUBLIC_IP
port=1194
mode=ip
protocol=udp
user=openvpn_user
password=openvpn_password
profile=default
certificate=none
```

---

## Step 10 — Alur Data Lengkap

```
┌────────────────────────────────────────────────────────────────────┐
│ 1. ADMIN BUAT USER HOTSPOT DI LARAVEL DASHBOARD                    │
│                                                                    │
│    POST /api/hotspot-users                                         │
│    hotspot_users table ditulis (status=2 aktif)                     │
└─────────────────────────────┬──────────────────────────────────────┘
                              │ HotspotUserObserver::created()
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│ 2. LARAVEL SYNC KE FREERADIUS TABLES                               │
│                                                                    │
│    radcheck      ← username + password (Cleartext-Password)          │
│    radreply      ← rate_limit, session_timeout, reply_message       │
│    radusergroup  ← username + groupname (nama paket)               │
└─────────────────────────────┬──────────────────────────────────────┘
                              │ FreeRADIUS auto-read dari MySQL
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│ 3. MIKROTIK HOTSPOT USER KONEKSI                                   │
│                                                                    │
│    User → Login ke Mikrotik Hotspot                                 │
│    Mikrotik → RADIUS Access-Request (UDP 1812)                     │
│    Mikrotik → OpenVPN Tunnel → FreeRADIUS Server                   │
│    FreeRADIUS → Query radcheck → Verify password                   │
│    FreeRADIUS → Query radgroupreply → Get rate_limit, timeout     │
│    FreeRADIUS → Insert radacct → Start session tracking            │
│    User → Access Granted!                                           │
└─────────────────────────────┬──────────────────────────────────────┘
                              │ Cron: radius:sync-sessions (every 1 min)
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│ 4. LARAVEL SYNC SESSION DARI RADACCT                               │
│                                                                    │
│    hotspot_sessions di-update (denormalized)                        │
│    Dashboard menampilkan online users + usage stats                 │
└────────────────────────────────────────────────────────────────────┘
```

---

## Step 11 — MySQL Performance Tuning

```ini
[mysqld]
# InnoDB Buffer Pool — sesuaikan dengan RAM server
innodb_buffer_pool_size = 1G

# Log
innodb_log_file_size = 256M
innodb_log_files_in_group = 2

# ACID untuk accounting records
innodb_flush_log_at_trx_commit = 1
sync_binlog = 1

# Connection
max_connections = 200

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Max packet (accounting packets bisa besar)
max_allowed_packet = 16M

# Table cache
table_open_cache = 4000
table_definition_cache = 2000

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

---

## Step 12 — Verification & Testing

### 12.1 Test RADIUS Authentication

```bash
# Test manual FreeRADIUS
echo "User-Name=test_user, User-Password=test123" \
    | radclient -x 127.0.0.1:1812 auth testing123

# Expected: Received response ID: X, code: 2, Access-Accept
```

### 12.2 Test Laravel → FreeRADIUS Sync

```bash
php artisan tinker

# Buat user
$user = App\Models\HotspotUser::create([
    'username' => 'test_voucher',
    'password' => 'test123',
    'profile_id' => 1,
    'status' => 2,
    'reseller_id' => 1,
]);

# Verifikasi
DB::table('radcheck')->where('username', 'test_voucher')->first();
DB::table('radreply')->where('username', 'test_voucher')->first();
DB::table('radusergroup')->where('username', 'test_voucher')->first();
```

### 12.3 Test OpenVPN Auth

```bash
USERNAME=test_voucher PASSWORD=test123 \
    python3 /etc/openvpn/verify_radius.py
echo $?
# 0 = Auth success
# 1 = Auth failed
```

---

## Step 13 — Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| User tidak bisa login hotspot | radcheck kosong | Cek HotspotUserObserver, cek RadiusSyncService |
| Rate limit tidak berjalan | radreply kosong | Cek profile rate_limit di radgroupreply |
| Accounting tidak tersimpan | Port 1813 terblokir firewall | `iptables -A INPUT -p udp --dport 1813 -j ACCEPT` |
| OpenVPN auth selalu gagal | Script permissions | `chmod +x /etc/openvpn/verify_radius.py` |
| Session tidak sync | Cron job tidak jalan | Cek `php artisan schedule:work` |

---

## Step 14 — Task List

| # | Task | Status | Estimasi |
|---|------|--------|----------|
| 1 | Buat MySQL database + user permissions | ⬜ Pending | 15 menit |
| 2 | Buat 9 tabel FreeRADIUS (radcheck, radreply, dll) | ⬜ Pending | 15 menit |
| 3 | Install FreeRADIUS + konfigurasi sql module | ⬜ Pending | 30 menit |
| 4 | Buat 5 Model FreeRADIUS (RadCheck, RadReply, dll) | ⬜ Pending | 1 jam |
| 5 | Buat RadiusSyncService | ⬜ Pending | 1 jam |
| 6 | Buat 3 Observer + register di AppServiceProvider | ⬜ Pending | 1 jam |
| 7 | Buat Artisan command sync-sessions | ⬜ Pending | 30 menit |
| 8 | Setup OpenVPN server + verify script | ⬜ Pending | 1 jam |
| 9 | Konfigurasi Mikrotik sebagai OpenVPN client | ⬜ Pending | 30 menit |
| 10 | End-to-end test | ⬜ Pending | 1 jam |
| **Total** | | | **7.5 jam** |

---

*Document Version: 2.0 — Enhanced Edition*
*Stack: FreeRADIUS 3.x + Laravel 13 + MySQL 8.x + OpenVPN + OvenVPN*
*Generated: April 2026*
