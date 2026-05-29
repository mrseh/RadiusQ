<?php

/**
 * RADIUS Configuration
 *
 * Konfigurasi untuk FreeRADIUS integration.
 * Semua setting RADIUS  ada di sini.
 *
 * Usage:
 *   config('radius.auth_port')       → 1812
 *   config('radius.default_nas')     → 'testing123'
 */

return [
    /*
    |--------------------------------------------------------------------------
    | FreeRADIUS Server Configuration
    |--------------------------------------------------------------------------
    */
    'server' => [
        'host' => env('RADIUS_SERVER', '127.0.0.1'),
        'auth_port' => env('RADIUS_AUTH_PORT', 1812),
        'acct_port' => env('RADIUS_ACCT_PORT', 1813),
        'secret' => env('RADIUS_SECRET', 'testing123'),
        'timeout' => env('RADIUS_TIMEOUT', 5),
        'retries' => env('RADIUS_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default NAS Configuration
    |--------------------------------------------------------------------------
    */
    'default_nas' => [
        'shortname' => env('RADIUS_NAS_SHORTNAME', 'default'),
        'type' => env('RADIUS_NAS_TYPE', 'mikrotik'),
        'secret' => env('RADIUS_NAS_SECRET', 'testing123'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenVPN Configuration
    |--------------------------------------------------------------------------
    */
    'openvpn' => [
        'enabled' => env('OPENVPN_ENABLED', false),
        'server_ip' => env('OPENVPN_SERVER_IP', '172.31.119.1'),
        'subnet' => env('OPENVPN_SUBNET', '172.31.119.0/24'),
        'auth_script' => env('OPENVPN_AUTH_SCRIPT', '/etc/openvpn/verify_radius.py'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Sync Configuration
    |--------------------------------------------------------------------------
    */
    'session_sync' => [
        // Interval sync session (dalam detik)
        'interval' => env('RADIUS_SESSION_SYNC_INTERVAL', 60),

        // Cleanup expired sessions setelah X jam
        'cleanup_after_hours' => env('RADIUS_SESSION_CLEANUP_HOURS', 24),

        // Maksimum records per sync
        'max_records_per_sync' => env('RADIUS_MAX_RECORDS_PER_SYNC', 1000),

        // Enable/disable session sync
        'enabled' => env('RADIUS_SESSION_SYNC_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Attribute Defaults
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        // Password type: Cleartext-Password, Crypt-Password, MD5-Password
        'password_type' => env('RADIUS_PASSWORD_TYPE', 'Cleartext-Password'),

        // Default session timeout (dalam detik)
        'default_session_timeout' => env('RADIUS_DEFAULT_SESSION_TIMEOUT', 3600),

        // Default idle timeout (dalam detik)
        'default_idle_timeout' => env('RADIUS_DEFAULT_IDLE_TIMEOUT', 300),

        // Default rate limit (format: upload/download, e.g., "5M/5M")
        'default_rate_limit' => env('RADIUS_DEFAULT_RATE_LIMIT', '10M/10M'),

        // Simultaneous-Use default
        'default_simultaneous_use' => env('RADIUS_DEFAULT_SIMULTANEOUS_USE', 1),

        // Acct-Interim-Interval (dalam detik)
        'acct_interim_interval' => env('RADIUS_ACCT_INTERIM_INTERVAL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hotspot Configuration
    |--------------------------------------------------------------------------
    */
    'hotspot' => [
        // Prefix untuk username hotspot (opsional)
        'username_prefix' => env('RADIUS_HOTSPOT_USERNAME_PREFIX', ''),

        // Default NAS untuk hotspot
        'default_nas' => env('RADIUS_HOTSPOT_DEFAULT_NAS', 'all'),

        // Enable MAC binding
        'mac_binding_enabled' => env('RADIUS_HOTSPOT_MAC_BINDING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | PPPoE Configuration
    |--------------------------------------------------------------------------
    */
    'pppoe' => [
        // Default group name
        'default_group' => env('RADIUS_PPPOE_DEFAULT_GROUP', 'FRRADIUS'),

        // Default framed protocol
        'framed_protocol' => env('RADIUS_PPPOE_FRAMED_PROTOCOL', 'PPP'),

        // Default framed IP netmask
        'framed_netmask' => env('RADIUS_PPPOE_FRAMED_NETMASK', '255.255.255.0'),

        // Enable static IP
        'static_ip_enabled' => env('RADIUS_PPPOE_STATIC_IP_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Pool Configuration
    |--------------------------------------------------------------------------
    */
    'ippool' => [
        // Enable IP pool
        'enabled' => env('RADIUS_IPPOOL_ENABLED', true),

        // Default pool name
        'default_pool' => env('RADIUS_IPPOOL_DEFAULT', 'default-pool'),

        // Pool subnet
        'subnet' => env('RADIUS_IPPOOL_SUBNET', '10.0.0.0/24'),

        // IP expiry time (dalam detik)
        'expiry_time' => env('RADIUS_IPPOOL_EXPIRY', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // Enable logging
        'enabled' => env('RADIUS_LOGGING_ENABLED', true),

        // Log level: debug, info, warning, error
        'level' => env('RADIUS_LOG_LEVEL', 'info'),

        // Log channel
        'channel' => env('RADIUS_LOG_CHANNEL', 'single'),

        // Log file path
        'log_file' => env('RADIUS_LOG_FILE', storage_path('logs/radius.log')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    */
    'sync' => [
        // Enable auto-sync via observers
        'observers_enabled' => env('RADIUS_SYNC_OBSERVERS_ENABLED', true),

        // Enable auto-sync saat app boot
        'boot_sync_enabled' => env('RADIUS_SYNC_BOOT_ENABLED', false),

        // Batch size untuk bulk sync
        'batch_size' => env('RADIUS_SYNC_BATCH_SIZE', 100),

        // Retry attempts jika sync gagal
        'retry_attempts' => env('RADIUS_SYNC_RETRY_ATTEMPTS', 3),

        // Retry delay (dalam detik)
        'retry_delay' => env('RADIUS_SYNC_RETRY_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | FreeRADIUS Attribute Names
    |--------------------------------------------------------------------------
    | Standard RADIUS attribute names (RFC 2865, RFC 2869)
    */
    'attribute_names' => [
        // Password
        'cleartext_password' => 'Cleartext-Password',
        'crypt_password' => 'Crypt-Password',
        'md5_password' => 'MD5-Password',

        // Session
        'session_timeout' => 'Session-Timeout',
        'idle_timeout' => 'Idle-Timeout',
        'max_all_session' => 'Max-All-Session',
        'simultaneous_use' => 'Simultaneous-Use',

        // Mikrotik specific
        'mikrotik_rate_limit' => 'Mikrotik-Rate-Limit',
        'mikrotik_queue_max' => 'Mikrotik-Queue-Max-Rate',
        'mikrotik_queue_limit' => 'Mikrotik-Queue-Limit',

        // Framed
        'framed_ip_address' => 'Framed-IP-Address',
        'framed_ip_netmask' => 'Framed-IP-Netmask',
        'framed_protocol' => 'Framed-Protocol',
        'framed_route' => 'Framed-Route',

        // Other
        'reply_message' => 'Reply-Message',
        'acct_interim_interval' => 'Acct-Interim-Interval',
        'service_type' => 'Service-Type',
        'port_limit' => 'Port-Limit',
    ],

    /*
    |--------------------------------------------------------------------------
    | FreeRADIUS Operator Names
    |--------------------------------------------------------------------------
    | Standard RADIUS operators for SQL queries
    */
    'operators' => [
        'assign' => '=',
        'set' => ':=',
        'equals' => '==',
        'not_equals' => '!=',
        'greater' => '>',
        'less' => '<',
    ],
];
