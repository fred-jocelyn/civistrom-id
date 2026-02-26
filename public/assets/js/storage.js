/**
 * CIVISTROM ID — Storage (IndexedDB)
 *
 * Base de données locale chiffrée :
 *   - Store "accounts" : seeds TOTP chiffrés par PIN
 *   - Store "settings" : pinHash, salt, préférences
 *
 * Fonctions exportées :
 *   Storage.init() → Promise<void>
 *   Storage.isSetup() → Promise<boolean>
 *   Storage.setupPin(pin) → Promise<void>
 *   Storage.verifyPin(pin) → Promise<boolean>
 *   Storage.addAccount(civistromId, secret, pin) → Promise<void>
 *   Storage.getAccounts(pin) → Promise<Array>
 *   Storage.removeAccount(civistromId) → Promise<void>
 *   Storage.getAccountCount() → Promise<number>
 *   Storage.clearAll() → Promise<void>
 */

'use strict';

const Storage = (() => {

    const DB_NAME = 'civistrom-id';
    const DB_VERSION = 1;

    let db = null;

    /**
     * Ouvre (ou crée) la base IndexedDB
     */
    function openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event) => {
                const database = event.target.result;

                // Store accounts : {civistromId, encryptedSeed, iv, addedAt}
                if (!database.objectStoreNames.contains('accounts')) {
                    database.createObjectStore('accounts', { keyPath: 'civistromId' });
                }

                // Store settings : {key, value}
                if (!database.objectStoreNames.contains('settings')) {
                    database.createObjectStore('settings', { keyPath: 'key' });
                }
            };

            request.onsuccess = (event) => {
                resolve(event.target.result);
            };

            request.onerror = (event) => {
                reject(new Error('Impossible d\'ouvrir IndexedDB : ' + event.target.error));
            };
        });
    }

    /**
     * Initialise la connexion à la base
     */
    async function init() {
        if (!db) {
            db = await openDB();
        }
    }

    /**
     * Helper : transaction + opération sur un store
     */
    function storeOp(storeName, mode, callback) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, mode);
            const store = tx.objectStore(storeName);
            const request = callback(store);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Lit une valeur du store settings
     */
    async function getSetting(key) {
        await init();
        const result = await storeOp('settings', 'readonly', (store) => store.get(key));
        return result ? result.value : null;
    }

    /**
     * Écrit une valeur dans le store settings
     */
    async function setSetting(key, value) {
        await init();
        return storeOp('settings', 'readwrite', (store) => store.put({ key, value }));
    }

    // ─── API publique ────────────────────────────

    /**
     * Vérifie si le PIN a déjà été configuré
     */
    async function isSetup() {
        const pinHash = await getSetting('pinHash');
        return pinHash !== null;
    }

    /**
     * Configure le PIN initial (première utilisation)
     *
     * @param {string} pin - PIN 4 chiffres
     */
    async function setupPin(pin) {
        const salt = CryptoPIN.generateSalt();
        const hash = await CryptoPIN.hashPin(pin, salt);

        await setSetting('salt', salt);
        await setSetting('pinHash', hash);
    }

    /**
     * Vérifie un PIN saisi
     *
     * @param {string} pin - PIN 4 chiffres
     * @returns {Promise<boolean>}
     */
    async function verifyPin(pin) {
        const salt = await getSetting('salt');
        const storedHash = await getSetting('pinHash');

        if (!salt || !storedHash) return false;

        const hash = await CryptoPIN.hashPin(pin, salt);
        return hash === storedHash;
    }

    /**
     * Ajoute un compte (seed TOTP chiffré par le PIN)
     *
     * @param {string} civistromId - CIV-XXXX-XXXX-X
     * @param {string} secret      - Seed TOTP en Base32
     * @param {string} pin         - PIN 4 chiffres (pour chiffrer)
     */
    async function addAccount(civistromId, secret, pin) {
        await init();
        const salt = await getSetting('salt');

        // Chiffrer le seed avec le PIN
        const encrypted = await CryptoPIN.encrypt(secret, pin, salt);

        const account = {
            civistromId: civistromId,
            encryptedSeed: encrypted.ciphertext,
            iv: encrypted.iv,
            addedAt: new Date().toISOString(),
        };

        return storeOp('accounts', 'readwrite', (store) => store.put(account));
    }

    /**
     * Récupère tous les comptes avec seeds déchiffrés
     *
     * @param {string} pin - PIN 4 chiffres (pour déchiffrer)
     * @returns {Promise<Array<{civistromId: string, secret: string, addedAt: string}>>}
     */
    async function getAccounts(pin) {
        await init();
        const salt = await getSetting('salt');

        const accounts = await new Promise((resolve, reject) => {
            const tx = db.transaction('accounts', 'readonly');
            const store = tx.objectStore('accounts');
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        // Déchiffrer chaque seed
        const decrypted = [];
        for (const account of accounts) {
            try {
                const secret = await CryptoPIN.decrypt(
                    { iv: account.iv, ciphertext: account.encryptedSeed },
                    pin,
                    salt
                );
                decrypted.push({
                    civistromId: account.civistromId,
                    secret: secret,
                    addedAt: account.addedAt,
                });
            } catch {
                // PIN incorrect ou données corrompues — skip
                console.warn(`[Storage] Impossible de déchiffrer ${account.civistromId}`);
            }
        }

        return decrypted;
    }

    /**
     * Supprime un compte par CIV-ID
     *
     * @param {string} civistromId - CIV-XXXX-XXXX-X
     */
    async function removeAccount(civistromId) {
        await init();
        return storeOp('accounts', 'readwrite', (store) => store.delete(civistromId));
    }

    /**
     * Retourne le nombre de comptes enregistrés
     */
    async function getAccountCount() {
        await init();
        return storeOp('accounts', 'readonly', (store) => store.count());
    }

    /**
     * Supprime toute la base (reset complet)
     */
    async function clearAll() {
        await init();

        return new Promise((resolve, reject) => {
            const tx = db.transaction(['accounts', 'settings'], 'readwrite');
            tx.objectStore('accounts').clear();
            tx.objectStore('settings').clear();
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    // API publique
    return {
        init,
        isSetup,
        setupPin,
        verifyPin,
        addAccount,
        getAccounts,
        removeAccount,
        getAccountCount,
        clearAll,
    };

})();

console.log('[CIVISTROM ID] storage.js chargé');
