#!/bin/bash
# =============================================================
# verify_radius.sh — OpenVPN Authentication via FreeRADIUS
# =============================================================
#
# Bash alternative untuk verify_radius.py
# Menggunakan radclient dari FreeRADIUS-utils
#
# Install:
#   chmod +x /etc/openvpn/verify_radius.sh
#
# Usage di server.conf:
#   auth-user-pass-verify /etc/openvpn/verify_radius.sh via-env
#
# Install radclient:
#   apt-get install freeradius-utils  (Debian/Ubuntu)
#   yum install freeradius-utils       (CentOS/RHEL)
#
# =============================================================

# Configuration
RADIUS_SERVER="${RADIUS_SERVER:-127.0.0.1}"
RADIUS_PORT="${RADIUS_PORT:-1812}"
RADIUS_SECRET="${RADIUS_SECRET:-testing123}"
NAS_IP="${NAS_IP:-172.31.119.1}"

# Get credentials from environment (set by OpenVPN)
USERNAME="${username}"
PASSWORD="${password}"
CLIENT_IP="${untrusted_ip:-unknown}"

# Log function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [VERIFY_RADIUS] $1" >> /var/log/openvpn/auth.log
    echo "$1" >&2
}

# Check if radclient exists
if ! command -v radclient &> /dev/null; then
    log "ERROR: radclient not found. Install freeradius-utils."
    exit 1
fi

# Validate credentials
if [ -z "$USERNAME" ]; then
    log "WARNING: Missing username from $CLIENT_IP"
    exit 1
fi

if [ -z "$PASSWORD" ]; then
    log "WARNING: Missing password for user '$USERNAME' from $CLIENT_IP"
    exit 1
fi

# Send RADIUS Access-Request
# Format: User-Name=xxx, User-Password=xxx, NAS-IP-Address=xxx
RESPONSE=$(echo "User-Name=$USERNAME, User-Password=$PASSWORD, NAS-IP-Address=$NAS_IP, Service-Type=Login-TCP" \
    | radclient -x -r 3 "$RADIUS_SERVER:$RADIUS_PORT" auth "$RADIUS_SECRET" 2>&1)

RADCLIENT_EXIT=$?

# Check response
if echo "$RESPONSE" | grep -q "Access-Accept"; then
    log "INFO: Auth SUCCESS for user '$USERNAME' from $CLIENT_IP"
    exit 0  # Allow
elif echo "$RESPONSE" | grep -q "Access-Reject"; then
    log "WARNING: Auth FAILED for user '$USERNAME' from $CLIENT_IP"
    exit 1  # Deny
else
    # radclient error atau timeout
    log "ERROR: RADIUS error for user '$USERNAME' from $CLIENT_IP: $RESPONSE"
    # Default deny on error
    exit 1
fi