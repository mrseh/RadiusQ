#!/bin/bash
# =============================================================
# setup_openvpn.sh — OpenVPN + FreeRADIUS Setup Script
# =============================================================
#
# Script untuk setup OpenVPN server dengan FreeRADIUS authentication.
# Jalankan sebagai root di server.
#
# Usage:
#   chmod +x setup_openvpn.sh
#   ./setup_openvpn.sh
#
# Tested on: Ubuntu 22.04, Debian 11
#
# =============================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
OPENVPN_DIR="/etc/openvpn"
OPENVPN_SCRIPT_DIR="/etc/openvpn/scripts"
OPENVPN_CCD_DIR="/etc/openvpn/ccd"
LOG_DIR="/var/log/openvpn"

echo "=========================================="
echo "  OpenVPN + FreeRADIUS Setup"
echo "=========================================="
echo ""

# Check if root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Please run as root${NC}"
    exit 1
fi

# 1. Install OpenVPN
echo -e "${YELLOW}[1/8] Installing OpenVPN...${NC}"
if command -v apt-get &> /dev/null; then
    apt-get update
    apt-get install -y openvpn
elif command -v yum &> /dev/null; then
    yum install -y openvpn
else
    echo -e "${RED}ERROR: Unknown package manager${NC}"
    exit 1
fi
echo -e "${GREEN}✓ OpenVPN installed${NC}"
echo ""

# 2. Install FreeRADIUS utilities
echo -e "${YELLOW}[2/8] Installing FreeRADIUS utilities (radclient)...${NC}"
if command -v apt-get &> /dev/null; then
    apt-get install -y freeradius-utils
elif command -v yum &> /dev/null; then
    yum install -y freeradius-utils
fi
echo -e "${GREEN}✓ FreeRADIUS utils installed${NC}"
echo ""

# 3. Create directories
echo -e "${YELLOW}[3/8] Creating directories...${NC}"
mkdir -p "$OPENVPN_SCRIPT_DIR"
mkdir -p "$OPENVPN_CCD_DIR"
mkdir -p "$LOG_DIR"
echo -e "${GREEN}✓ Directories created${NC}"
echo ""

# 4. Copy server config
echo -e "${YELLOW}[4/8] Installing server configuration...${NC}"
cp server.conf "$OPENVPN_DIR/server.conf"
chmod 644 "$OPENVPN_DIR/server.conf"
echo -e "${GREEN}✓ Server config installed${NC}"
echo ""

# 5. Copy verification scripts
echo -e "${YELLOW}[5/8] Installing verification scripts...${NC}"
cp scripts/verify_radius.py "$OPENVPN_SCRIPT_DIR/verify_radius.py"
cp scripts/verify_radius.sh "$OPENVPN_SCRIPT_DIR/verify_radius.sh"
cp ccd/* "$OPENVPN_CCD_DIR/"
chmod +x "$OPENVPN_SCRIPT_DIR/verify_radius.py"
chmod +x "$OPENVPN_SCRIPT_DIR/verify_radius.sh"
echo -e "${GREEN}✓ Scripts installed${NC}"
echo ""

# 6. Set permissions
echo -e "${YELLOW}[6/8] Setting permissions...${NC}"
chown root:root "$OPENVPN_DIR/server.conf"
chown root:root "$OPENVPN_SCRIPT_DIR"/*.py "$OPENVPN_SCRIPT_DIR"/*.sh
chown -R root:root "$OPENVPN_CCD_DIR"
touch "$LOG_DIR/auth.log"
chown root:adm "$LOG_DIR/auth.log"
chmod 640 "$LOG_DIR/auth.log"
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# 7. Enable IP forwarding
echo -e "${YELLOW}[7/8] Enabling IP forwarding...${NC}"
echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
sysctl -p
echo -e "${GREEN}✓ IP forwarding enabled${NC}"
echo ""

# 8. Configure firewall (UFW)
echo -e "${YELLOW}[8/8] Configuring firewall...${NC}"
if command -v ufw &> /dev/null; then
    ufw allow 1194/udp
    ufw allow 7505/tcp  # Management interface
    ufw --force enable
fi
echo -e "${GREEN}✓ Firewall configured${NC}"
echo ""

# Final instructions
echo "=========================================="
echo -e "${GREEN}  Setup Complete!${NC}"
echo "=========================================="
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo ""
echo "1. Edit /etc/openvpn/server.conf and configure:"
echo "   - Certificate paths (ca, cert, key, dh)"
echo "   - RADIUS_SECRET (change 'testing123' to your secret)"
echo ""
echo "2. Start OpenVPN:"
echo "   systemctl enable openvpn@server"
echo "   systemctl start openvpn@server"
echo ""
echo "3. Test authentication:"
echo "   USERNAME=test PASSWORD=test123 /etc/openvpn/verify_radius.sh"
echo ""
echo "4. Configure Mikrotik as OpenVPN client:"
echo "   - See: openvpn/mikrotik_openvpn_client.txt"
echo ""
echo "=========================================="
