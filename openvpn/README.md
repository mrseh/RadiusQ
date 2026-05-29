# OpenVPN + FreeRADIUS Integration

Panduan setup OpenVPN server untuk menghubungkan Mikrotik (tanpa IP public) ke FreeRADIUS server.

## 📁 Directory Structure

```
openvpn/
├── server.conf              # OpenVPN server configuration
├── ccd/
│   ├── MKT-WYH-01          # Client config untuk POP WYH
│   └── MKT-PTU-01          # Client config untuk POP PTU
├── scripts/
│   ├── verify_radius.py    # Python RADIUS verify script
│   ├── verify_radius.sh    # Bash (radclient) verify script
│   └── setup_openvpn.sh    # Setup script
├── mikrotik_openvpn_client.txt  # Panduan Mikrotik client
└── README.md               # This file
```

## 🚀 Quick Setup

### 1. Setup OpenVPN Server

```bash
# Jalankan setup script
cd openvpn
chmod +x scripts/setup_openvpn.sh
sudo ./scripts/setup_openvpn.sh
```

### 2. Configure FreeRADIUS Secret

Edit `/etc/openvpn/server.conf`:
```
# Ganti dengan secret Anda
RADIUS_SECRET=your_secret_here
```

### 3. Start OpenVPN

```bash
systemctl enable openvpn@server
systemctl start openvpn@server
```

### 4. Test Authentication

```bash
USERNAME=testuser PASSWORD=testpass \
    /etc/openvpn/scripts/verify_radius.sh
```

### 5. Configure Mikrotik

Lihat `mikrotik_openvpn_client.txt` untuk panduan lengkap.

## 🔧 Configuration Files

### server.conf

Konfigurasi utama OpenVPN server. Parameter penting:

| Parameter | Default | Deskripsi |
|-----------|---------|-----------|
| `port` | 1194 | OpenVPN port |
| `proto` | udp | Protocol (udp/tcp) |
| `server` | 172.31.119.0/24 | VPN subnet |
| `auth-user-pass-verify` | verify_radius.py | RADIUS auth script |

### ccd/* (Client Config Directory)

Konfigurasi static IP untuk setiap Mikrotik client.

Format: `ifconfig-push <IP> <NETMASK>`

### verify_radius.py / verify_radius.sh

Script untuk validasi username/password via FreeRADIUS.

- Python version: Lebih lengkap, implementasi RADIUS protocol manual
- Bash version: Simpler, gunakan `radclient`

## 📡 Network Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     INTERNET (Public)                        │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   OPENVPN SERVER                              │
│                 (Public IP: x.x.x.x)                         │
│                   Port: 1194/UDP                             │
└──────────────────────────────┬──────────────────────────────┘
                               │
                    ┌──────────┴──────────┐
                    │    OpenVPN Tunnel    │
                    │  172.31.119.0/24     │
                    └──────────┬──────────┘
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                      ▼
┌─────────────────────────────┐    ┌─────────────────────────────┐
│   MIKROTIK POP WYH        │    │   MIKROTIK POP PTU         │
│   172.31.119.10           │    │   172.31.119.11            │
│   OpenVPN Client          │    │   OpenVPN Client          │
│                           │    │                           │
│   ┌─────────────────┐     │    │   ┌─────────────────┐    │
│   │  Hotspot/PPPoE  │     │    │   │  Hotspot/PPPoE  │    │
│   │  Users connect  │     │    │   │  Users connect  │    │
│   └─────────────────┘     │    │   └─────────────────┘    │
└─────────────────────────────┘    └─────────────────────────────┘
            │                                      │
            │ RADIUS Access-Request               │ RADIUS Access-Request
            │ UDP 1812                             │ UDP 1812
            ▼                                      ▼
┌─────────────────────────────────────────────────────────────┐
│                    FREERADIUS SERVER                         │
│                  (172.31.119.2:1812)                         │
│                                                             │
│   ┌────────────────┐    ┌────────────────┐                 │
│   │    radcheck    │    │    radreply     │                 │
│   │   (auth DB)    │    │  (attributes)  │                 │
│   └────────────────┘    └────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

## 🔐 Security Notes

1. **Shared Secret**: Ganti `testing123` dengan secret unik di production
2. **Firewall**: Pastikan hanya port 1194/UDP yang terbuka
3. **Logging**: Monitor `/var/log/openvpn/auth.log` untuk failed attempts
4. **IP Forwarding**: Pastikan `net.ipv4.ip_forward=1`
5. **Script Permissions**: Verify scripts harus executable

## 📝 Troubleshooting

### OpenVPN won't start

```bash
# Check config
openvpn --config /etc/openvpn/server.conf

# Check log
journalctl -u openvpn@server -f
```

### Authentication always fails

```bash
# Test radclient manually
echo "User-Name=test, User-Password=test123" | \
    radclient -x 127.0.0.1:1812 auth testing123

# Check script permissions
ls -la /etc/openvpn/scripts/verify_radius.py
```

### Mikrotik can't connect

```bash
# Check firewall
iptables -L -n | grep 1194

# Check OpenVPN status
systemctl status openvpn@server
```

### RADIUS timeout

```bash
# Test connectivity
ping 172.31.119.2

# Check FreeRADIUS is running
systemctl status freeradius
```

## 📋 Requirements

### Server Side
- OpenVPN server (2.5+)
- FreeRADIUS 3.x
- Python 3.6+ (untuk verify_radius.py)
- MySQL/MariaDB

### Mikrotik Side
- RouterOS 6.x atau 7.x
- Package "openvpn" terinstall
- Koneksi internet (untuk establish VPN tunnel)

## 📞 Support

Jika ada pertanyaan:
1. Check OpenVPN log: `/var/log/openvpn/server.log`
2. Check RADIUS log: `/var/log/freeradius/radius.log`
3. Check auth log: `/var/log/openvpn/auth.log`
