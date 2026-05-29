#!/usr/bin/env python3
# =============================================================
# verify_radius.py — OpenVPN Authentication via FreeRADIUS
# =============================================================
#
# Script untuk validasi username/password saat user koneksi ke OpenVPN.
# Dipanggil oleh OpenVPN server via auth-user-pass-verify directive.
#
# Alur:
# 1. OpenVPN terima username + password dari client
# 2. OpenVPN jalankan script ini dengan env variables:
#    - username = username VPN client
#    - password = password VPN client
#    - untrusted_ip = IP client
# 3. Script kirim Access-Request ke FreeRADIUS
# 4. FreeRADIUS cek radcheck table → authenticate
# 5. Return exit code:
#    - 0 = Allow (Access-Accept)
#    - 1 = Deny (Access-Reject)
#
# Install:
#   chmod +x /etc/openvpn/verify_radius.py
#
# Usage di server.conf:
#   auth-user-pass-verify /etc/openvpn/verify_radius.py via-env
#
# =============================================================

import sys
import os
import socket
import struct
import secrets
import time
import logging
from typing import Optional, Tuple

# =============================================================
# CONFIGURATION
# =============================================================

# FreeRADIUS Server Configuration
RADIUS_SERVER = os.getenv('RADIUS_SERVER', '127.0.0.1')
RADIUS_PORT = int(os.getenv('RADIUS_PORT', '1812'))
RADIUS_SECRET = os.getenv('RADIUS_SECRET', 'testing123')
RADIUS_TIMEOUT = int(os.getenv('RADIUS_TIMEOUT', '5'))

# NAS Configuration (untuk Access-Request)
NAS_IP = os.getenv('NAS_IP', '172.31.119.1')
NAS_IDENTIFIER = os.getenv('NAS_IDENTIFIER', 'openvpn')

# Logging
LOG_FILE = os.getenv('OPENVPN_LOG', '/var/log/openvpn/auth.log')

# Debug mode (verbose logging)
DEBUG = os.getenv('VERIFY_RADIUS_DEBUG', '0') == '1'


# =============================================================
# LOGGING SETUP
# =============================================================

def setup_logging():
    """Setup logging configuration"""
    logging.basicConfig(
        level=logging.DEBUG if DEBUG else logging.INFO,
        format='%(asctime)s [VERIFY_RADIUS] %(levelname)s: %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S',
        handlers=[
            logging.FileHandler(LOG_FILE),
            logging.StreamHandler(sys.stderr)
        ]
    )


logger = logging.getLogger(__name__)


# =============================================================
# RADIUS PROTOCOL
# =============================================================

class RadiusPacket:
    """RADIUS Access-Request/Response packet builder and parser"""

    # Packet codes
    ACCESS_REQUEST = 1
    ACCESS_ACCEPT = 2
    ACCESS_REJECT = 3
    ACCESS_CHALLENGE = 11

    # Attribute types
    ATTR_USER_NAME = 1
    ATTR_USER_PASSWORD = 2
    ATTR_NAS_IP_ADDRESS = 4
    ATTR_NAS_IDENTIFIER = 32
    ATTR_SERVICE_TYPE = 6
    ATTR_MESSAGE_AUTHENTICATOR = 80

    def __init__(self, code: int, authenticator: bytes = None):
        self.code = code
        self.authenticator = authenticator or secrets.token_bytes(16)
        self.attributes: list[tuple[int, bytes]] = []

    def add_attribute(self, attr_type: int, value: str):
        """Add attribute to packet"""
        value_bytes = value.encode('utf-8')
        self.attributes.append((attr_type, value_bytes))
        return self

    def build(self, secret: str) -> bytes:
        """Build complete RADIUS packet"""
        # Calculate Message-Authenticator
        # MA = HMAC-MD5(Authenticator + Attributes, Secret)
        attrs_bytes = self._pack_attributes()

        # For Access-Request, MA = 16 zero bytes initially
        msg_auth = self._calculate_message_authenticator(bytes(16), secret, attrs_bytes)

        # Re-build with correct Message-Authenticator
        attrs_bytes = self._pack_attributes_with_ma(msg_auth)

        # Build packet body
        packet_body = self.authenticator + attrs_bytes

        # Packet header: Code + ID + Length + Authenticator
        packet_length = 20 + len(packet_body)
        header = struct.pack('!BBH', self.code, self._identifier, packet_length)

        return header + packet_body

    def _pack_attributes(self) -> bytes:
        """Pack all attributes to bytes"""
        result = b''
        for attr_type, value in self.attributes:
            result += struct.pack('!BB', attr_type, 2 + len(value))
            result += value
        return result

    def _pack_attributes_with_ma(self, msg_auth: bytes) -> bytes:
        """Pack attributes with Message-Authenticator"""
        result = b''
        for attr_type, value in self.attributes:
            result += struct.pack('!BB', attr_type, 2 + len(value))
            result += value

        # Add Message-Authenticator at the end
        result += struct.pack('!BB', self.ATTR_MESSAGE_AUTHENTICATOR, 18)
        result += msg_auth

        return result

    def _calculate_message_authenticator(
        self, authenticator: bytes, secret: str, attrs_without_ma: bytes
    ) -> bytes:
        """Calculate Message-Authenticator per RFC 2869"""
        import hashlib
        import hmac

        # MA = HMAC-MD5(Request Auth + Attributes + Padding + Secret)
        # For Access-Request: MA is calculated with 16 zero bytes as Request Authenticator
        request_auth = bytes(16)  # Initially 16 zero bytes

        # Create attribute list without MA for calculation
        attrs_bytes = b''
        for attr_type, value in self.attributes:
            attrs_bytes += struct.pack('!BB', attr_type, 2 + len(value))
            attrs_bytes += value

        # Pad to multiple of 16 bytes
        padding = b'\x00' * (16 - (len(attrs_bytes) % 16)) if len(attrs_bytes) % 16 != 0 else b''

        # Calculate
        data = request_auth + attrs_bytes + padding
        secret_bytes = secret.encode('utf-8')
        ma = hmac.new(secret_bytes, data, hashlib.md5).digest()

        return ma

    def _identifier(self) -> int:
        """Get packet identifier"""
        return int.from_bytes(self.authenticator[:1], 'big') % 256


def send_radius_access_request(
    username: str,
    password: str,
    nas_ip: str,
    secret: str,
    server: str,
    port: int,
    timeout: int = 5
) -> Tuple[bool, Optional[str]]:
    """
    Kirim Access-Request ke FreeRADIUS server.

    Args:
        username: Username untuk auth
        password: Password untuk auth
        nas_ip: IP address NAS (OpenVPN server)
        secret: RADIUS shared secret
        server: RADIUS server hostname/IP
        port: RADIUS auth port
        timeout: Timeout dalam detik

    Returns:
        (success: bool, message: str)
    """
    import random

    # Generate authenticator
    authenticator = bytes([random.randint(0, 255) for _ in range(16)])

    # Build Access-Request packet
    packet = RadiusPacket(RadiusPacket.ACCESS_REQUEST, authenticator)

    # Add User-Name
    packet.add_attribute(RadiusPacket.ATTR_USER_NAME, username)

    # Add User-Password (encrypted with shared secret)
    encrypted_password = _encrypt_password(password, authenticator, secret)
    packet.add_attribute(RadiusPacket.ATTR_USER_PASSWORD, encrypted_password)

    # Add NAS-IP-Address
    packet.add_attribute(RadiusPacket.ATTR_NAS_IP_ADDRESS, nas_ip)

    # Add NAS-Identifier
    packet.add_attribute(RadiusPacket.ATTR_NAS_IDENTIFIER, NAS_IDENTIFIER)

    # Add Service-Type (Login-TCP untuk VPN)
    packet.add_attribute(RadiusPacket.ATTR_SERVICE_TYPE, 'Login-TCP')

    # Build packet
    raw_packet = packet.build(secret)

    # Send to RADIUS server
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.settimeout(timeout)
        sock.sendto(raw_packet, (server, port))
        response, _ = sock.recvfrom(1024)
        sock.close()
    except socket.timeout:
        return False, "RADIUS timeout"
    except socket.error as e:
        return False, f"Socket error: {e}"

    # Parse response
    return parse_radius_response(response, authenticator, secret)


def _encrypt_password(password: str, authenticator: bytes, secret: str) -> str:
    """
    Encrypt password per RFC 2865
    Password XORed with MD5(secret + authenticator)
    """
    import hashlib

    password_bytes = password.encode('utf-8')
    secret_bytes = secret.encode('utf-8')

    # Pad password to 16 bytes (MD5 block size)
    padded_password = password_bytes + b'\x00' * (16 - len(password_bytes) % 16)

    # Calculate MD5(secret + authenticator)
    md5_input = secret_bytes + authenticator
    md5_hash = hashlib.md5(md5_input).digest()

    # XOR first 16 bytes
    encrypted = b''
    for i in range(16):
        encrypted += bytes([padded_password[i] ^ md5_hash[i]])

    return encrypted.decode('latin-1', errors='replace')


def parse_radius_response(
    response: bytes,
    request_authenticator: bytes,
    secret: str
) -> Tuple[bool, Optional[str]]:
    """
    Parse RADIUS Access-Accept/Reject response.

    Returns:
        (success: bool, message: str)
    """
    if len(response) < 20:
        return False, "Invalid response length"

    code = response[0]

    if code == RadiusPacket.ACCESS_ACCEPT:
        return True, "Access-Accept"
    elif code == RadiusPacket.ACCESS_REJECT:
        return False, "Access-Reject"
    elif code == RadiusPacket.ACCESS_CHALLENGE:
        return False, "Access-Challenge (not supported)"
    else:
        return False, f"Unknown response code: {code}"


# =============================================================
# MAIN ENTRY POINT
# =============================================================

def main():
    """
    Entry point — dipanggil oleh OpenVPN.
    Reads credentials from environment variables and validates via RADIUS.
    """
    # Setup logging
    setup_logging()

    # Get credentials from environment (set by OpenVPN)
    username = os.getenv('username', '').strip()
    password = os.getenv('password', '').strip()
    client_ip = os.getenv('untrusted_ip', 'unknown')

    # Debug logging
    if DEBUG:
        logger.debug(f"Received auth request from {client_ip}")
        logger.debug(f"Username: {username}")
        logger.debug(f"RADIUS Server: {RADIUS_SERVER}:{RADIUS_PORT}")

    # Validate credentials
    if not username:
        logger.warning(f"Missing username from {client_ip}")
        sys.exit(1)

    if not password:
        logger.warning(f"Missing password for user '{username}' from {client_ip}")
        sys.exit(1)

    # Send RADIUS Access-Request
    try:
        success, message = send_radius_access_request(
            username=username,
            password=password,
            nas_ip=NAS_IP,
            secret=RADIUS_SECRET,
            server=RADIUS_SERVER,
            port=RADIUS_PORT,
            timeout=RADIUS_TIMEOUT
        )

        if success:
            logger.info(f"Auth SUCCESS for user '{username}' from {client_ip}")
            sys.exit(0)  # OpenVPN: allow connection
        else:
            logger.warning(f"Auth FAILED for user '{username}' from {client_ip}: {message}")
            sys.exit(1)  # OpenVPN: deny connection

    except Exception as e:
        logger.error(f"Verify error for user '{username}' from {client_ip}: {e}")
        # Default: deny on error (lebih aman)
        sys.exit(1)


if __name__ == '__main__':
    main()
