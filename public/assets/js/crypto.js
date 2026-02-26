/**
 * CIVISTROM ID — Crypto (PIN + chiffrement seeds)
 *
 * Web Crypto API :
 *   - PBKDF2 600K itérations → AES-256-GCM CryptoKey
 *   - Encrypt / Decrypt des seeds TOTP
 *   - SHA-256 hash du PIN pour vérification rapide
 *
 * Fonctions exportées :
 *   CryptoPIN.deriveKey(pin, salt) → Promise<CryptoKey>
 *   CryptoPIN.encrypt(plaintext, pin, salt) → Promise<{iv, ciphertext}>
 *   CryptoPIN.decrypt(data, pin, salt) → Promise<string>
 *   CryptoPIN.hashPin(pin, salt) → Promise<string>
 *   CryptoPIN.generateSalt() → string
 */

'use strict';

const CryptoPIN = (() => {

    const PBKDF2_ITERATIONS = 600000;
    const AES_KEY_LENGTH = 256;
    const IV_LENGTH = 12; // 96 bits pour AES-GCM

    /**
     * Encode une string en Uint8Array (UTF-8)
     */
    function encode(str) {
        return new TextEncoder().encode(str);
    }

    /**
     * Décode un Uint8Array en string (UTF-8)
     */
    function decode(buffer) {
        return new TextDecoder().decode(buffer);
    }

    /**
     * Convertit un ArrayBuffer en string Base64
     */
    function bufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (const byte of bytes) {
            binary += String.fromCharCode(byte);
        }
        return btoa(binary);
    }

    /**
     * Convertit une string Base64 en Uint8Array
     */
    function base64ToBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }

    /**
     * Génère un salt aléatoire (16 bytes, Base64)
     */
    function generateSalt() {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        return bufferToBase64(salt);
    }

    /**
     * Dérive une clé AES-256-GCM depuis le PIN via PBKDF2
     *
     * @param {string} pin  - PIN 4 chiffres
     * @param {string} salt - Salt en Base64
     * @returns {Promise<CryptoKey>}
     */
    async function deriveKey(pin, salt) {
        // Importer le PIN comme clé PBKDF2
        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            encode(pin),
            'PBKDF2',
            false,
            ['deriveKey']
        );

        // Dériver la clé AES-256-GCM
        return crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt: base64ToBuffer(salt),
                iterations: PBKDF2_ITERATIONS,
                hash: 'SHA-256',
            },
            keyMaterial,
            { name: 'AES-GCM', length: AES_KEY_LENGTH },
            false,
            ['encrypt', 'decrypt']
        );
    }

    /**
     * Chiffre du texte avec AES-256-GCM (clé dérivée du PIN)
     *
     * @param {string} plaintext - Texte à chiffrer
     * @param {string} pin       - PIN 4 chiffres
     * @param {string} salt      - Salt en Base64
     * @returns {Promise<{iv: string, ciphertext: string}>} - Base64
     */
    async function encrypt(plaintext, pin, salt) {
        const key = await deriveKey(pin, salt);
        const iv = crypto.getRandomValues(new Uint8Array(IV_LENGTH));

        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv: iv },
            key,
            encode(plaintext)
        );

        return {
            iv: bufferToBase64(iv),
            ciphertext: bufferToBase64(encrypted),
        };
    }

    /**
     * Déchiffre du texte avec AES-256-GCM
     *
     * @param {{iv: string, ciphertext: string}} data - Données chiffrées
     * @param {string} pin  - PIN 4 chiffres
     * @param {string} salt - Salt en Base64
     * @returns {Promise<string>} - Texte déchiffré
     * @throws {Error} Si le PIN est incorrect
     */
    async function decrypt(data, pin, salt) {
        const key = await deriveKey(pin, salt);

        try {
            const decrypted = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: base64ToBuffer(data.iv) },
                key,
                base64ToBuffer(data.ciphertext)
            );
            return decode(decrypted);
        } catch {
            throw new Error('PIN incorrect');
        }
    }

    /**
     * Hash SHA-256 du PIN + salt pour vérification rapide
     *
     * @param {string} pin  - PIN 4 chiffres
     * @param {string} salt - Salt en Base64
     * @returns {Promise<string>} - Hash en hex
     */
    async function hashPin(pin, salt) {
        const data = encode(pin + ':' + salt);
        const hash = await crypto.subtle.digest('SHA-256', data);
        const hashArray = new Uint8Array(hash);
        return Array.from(hashArray)
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

    // API publique
    return {
        deriveKey,
        encrypt,
        decrypt,
        hashPin,
        generateSalt,
        // Utilitaires exposés pour les tests
        bufferToBase64,
        base64ToBuffer,
    };

})();

console.log('[CIVISTROM ID] crypto.js chargé');
