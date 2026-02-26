/**
 * CIVISTROM ID — TOTP Engine (RFC 6238)
 *
 * Port JavaScript de SENTINEL TotpService.php + Base32.php.
 * Utilise Web Crypto API (crypto.subtle.sign) pour HMAC-SHA1.
 *
 * Fonctions exportées :
 *   base32Decode(str) → Uint8Array
 *   generateTOTP(secretBase32, timestamp?) → Promise<string> (6 digits)
 *   getRemainingSeconds() → number
 *   parseOtpauthUri(uri) → object|null
 */

'use strict';

// ═══════════════════════════════════════════
// Base32 Decode (RFC 4648)
// ═══════════════════════════════════════════

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/**
 * Décode une chaîne Base32 en Uint8Array
 * Port exact de SENTINEL Base32::decode()
 */
function base32Decode(str) {
    if (!str) return new Uint8Array(0);

    // Retirer le padding et passer en majuscules
    str = str.toUpperCase().replace(/=+$/, '');

    let binary = '';
    for (const char of str) {
        const index = BASE32_ALPHABET.indexOf(char);
        if (index === -1) {
            throw new Error(`Caractère Base32 invalide : ${char}`);
        }
        binary += index.toString(2).padStart(5, '0');
    }

    // Convertir les bits en bytes
    const bytes = [];
    for (let i = 0; i + 8 <= binary.length; i += 8) {
        bytes.push(parseInt(binary.substring(i, i + 8), 2));
    }

    return new Uint8Array(bytes);
}

// ═══════════════════════════════════════════
// TOTP Generation (RFC 6238)
// ═══════════════════════════════════════════

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;

/**
 * Génère un code TOTP 6 chiffres via Web Crypto API
 *
 * Port exact de SENTINEL TotpService::computeCode()
 *
 * @param {string} secretBase32 - Secret encodé en Base32
 * @param {number} [timestamp]  - Unix timestamp (null = now)
 * @returns {Promise<string>}   - Code 6 chiffres (zero-padded)
 */
async function generateTOTP(secretBase32, timestamp) {
    if (timestamp === undefined || timestamp === null) {
        timestamp = Math.floor(Date.now() / 1000);
    }

    const counter = Math.floor(timestamp / TOTP_PERIOD);

    // Décoder le secret Base32 → bytes
    const keyBytes = base32Decode(secretBase32);

    // Counter en 8 bytes big-endian
    const counterBytes = new ArrayBuffer(8);
    const view = new DataView(counterBytes);
    view.setUint32(0, 0);                      // 4 high bytes = 0
    view.setUint32(4, counter);                 // 4 low bytes = counter

    // Importer la clé pour HMAC-SHA1
    const cryptoKey = await crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'HMAC', hash: 'SHA-1' },
        false,
        ['sign']
    );

    // HMAC-SHA1
    const signature = await crypto.subtle.sign('HMAC', cryptoKey, counterBytes);
    const hash = new Uint8Array(signature);

    // Dynamic truncation (RFC 4226)
    const offset = hash[hash.length - 1] & 0x0F;
    const code = (
        ((hash[offset] & 0x7F) << 24) |
        ((hash[offset + 1] & 0xFF) << 16) |
        ((hash[offset + 2] & 0xFF) << 8) |
        (hash[offset + 3] & 0xFF)
    ) % (10 ** TOTP_DIGITS);

    return code.toString().padStart(TOTP_DIGITS, '0');
}

// ═══════════════════════════════════════════
// Timer
// ═══════════════════════════════════════════

/**
 * Retourne le nombre de secondes restantes dans la période TOTP courante
 * @returns {number}
 */
function getRemainingSeconds() {
    return TOTP_PERIOD - (Math.floor(Date.now() / 1000) % TOTP_PERIOD);
}

// ═══════════════════════════════════════════
// OTPAuth URI Parser
// ═══════════════════════════════════════════

/**
 * Parse une URI otpauth://totp/...
 *
 * Valide uniquement le format CIVISTROM :
 * - issuer contient "CIVISTROM"
 * - label contient un CIV-ID (CIV-XXXX-XXXX-X)
 *
 * @param {string} uri - URI otpauth://
 * @returns {object|null} - { issuer, label, secret, civistromId } ou null
 */
function parseOtpauthUri(uri) {
    if (!uri || !uri.startsWith('otpauth://totp/')) {
        return null;
    }

    try {
        // Extraire le label (entre / et ?)
        const withoutScheme = uri.substring('otpauth://totp/'.length);
        const qIndex = withoutScheme.indexOf('?');
        if (qIndex === -1) return null;

        const label = decodeURIComponent(withoutScheme.substring(0, qIndex));
        const queryString = withoutScheme.substring(qIndex + 1);

        // Parser les paramètres
        const params = {};
        for (const pair of queryString.split('&')) {
            const [key, value] = pair.split('=');
            params[decodeURIComponent(key)] = decodeURIComponent(value || '');
        }

        const secret = params.secret || '';
        const issuer = params.issuer || '';

        // Valider format CIVISTROM
        if (!issuer.toUpperCase().includes('CIVISTROM')) {
            return null;
        }

        // Extraire le CIV-ID (format CIV-XXXX-XXXX-X)
        const civIdMatch = label.match(/CIV-\d{4}-\d{4}-\d/);
        if (!civIdMatch) {
            return null;
        }

        return {
            issuer: issuer,
            label: label,
            secret: secret.toUpperCase(),
            civistromId: civIdMatch[0],
        };
    } catch {
        return null;
    }
}

console.log('[CIVISTROM ID] totp.js chargé');
