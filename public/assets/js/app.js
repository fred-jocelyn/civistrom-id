/**
 * CIVISTROM ID — Application principale
 *
 * State machine : LOADING → SETUP → LOCKED → EMPTY/ACCOUNTS → SCANNING → CONFIRMING
 * Auto-lock après 5 min en background (visibilitychange).
 * Refresh codes TOTP chaque seconde, régénère à chaque période 30s.
 */

'use strict';

const App = (() => {

    // ─── State ───────────────────────────────
    let currentScreen = 'loading';
    let currentPin = null;        // PIN en mémoire pendant la session
    let accounts = [];            // [{civistromId, secret, code, addedAt}]
    let timerInterval = null;
    let autoLockTimeout = null;
    const AUTO_LOCK_MS = 5 * 60 * 1000; // 5 minutes

    // Setup flow
    let setupStep = 'create';     // 'create' | 'confirm'
    let setupFirstPin = null;

    // Pending scan data
    let pendingScan = null;       // {civistromId, secret, issuer}

    // Delete
    let deleteTarget = null;      // civistromId to delete

    // ─── DOM refs ────────────────────────────
    const $ = (id) => document.getElementById(id);

    // ─── Screen management ───────────────────
    function showScreen(name) {
        // Hide all screens
        document.querySelectorAll('.screen').forEach(s => s.hidden = true);
        // Show target
        const screen = $('screen-' + name);
        if (screen) {
            screen.hidden = false;
            currentScreen = name;
        }

        // Focus first PIN input when showing PIN screens
        if (name === 'setup' || name === 'pin') {
            setTimeout(() => {
                const container = name === 'setup' ? 'setup-pins' : 'pin-inputs';
                const first = $(container)?.querySelector('.pin-input');
                if (first) first.focus();
            }, 100);
        }
    }

    // ─── PIN input helpers ───────────────────
    function setupPinInputs(containerId, onComplete) {
        const container = $(containerId);
        if (!container) return;

        const inputs = container.querySelectorAll('.pin-input');

        inputs.forEach((input, index) => {
            // Input handler
            input.addEventListener('input', (e) => {
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val.substring(0, 1);

                if (val && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                // Check if all 4 digits entered
                const pin = getPinValue(inputs);
                if (pin.length === 4) {
                    onComplete(pin);
                }
            });

            // Backspace handler
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                }
            });

            // Paste handler
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').substring(0, 4);
                for (let i = 0; i < pasted.length && i < inputs.length; i++) {
                    inputs[i].value = pasted[i];
                }
                if (pasted.length === 4) {
                    onComplete(pasted);
                } else if (pasted.length > 0) {
                    inputs[Math.min(pasted.length, inputs.length - 1)].focus();
                }
            });
        });
    }

    function getPinValue(inputs) {
        return Array.from(inputs).map(i => i.value).join('');
    }

    function clearPinInputs(containerId) {
        const container = $(containerId);
        if (!container) return;
        container.querySelectorAll('.pin-input').forEach(i => {
            i.value = '';
            i.classList.remove('error', 'success');
        });
    }

    function setPinState(containerId, state) {
        const container = $(containerId);
        if (!container) return;
        container.querySelectorAll('.pin-input').forEach(i => {
            i.classList.remove('error', 'success');
            if (state) i.classList.add(state);
        });
    }

    // ─── Setup flow ──────────────────────────
    async function handleSetupPin(pin) {
        if (setupStep === 'create') {
            // First entry — store and ask confirmation
            setupFirstPin = pin;
            setupStep = 'confirm';
            $('setup-label').textContent = 'Confirmez votre PIN';
            $('setup-error').textContent = '';
            clearPinInputs('setup-pins');
        } else {
            // Confirmation
            if (pin === setupFirstPin) {
                // Match! Save the PIN
                setPinState('setup-pins', 'success');
                await Storage.setupPin(pin);
                currentPin = pin;

                setTimeout(() => {
                    showAccountsOrEmpty();
                }, 300);
            } else {
                // Mismatch — restart
                setPinState('setup-pins', 'error');
                $('setup-error').textContent = 'Les PIN ne correspondent pas';

                setTimeout(() => {
                    setupStep = 'create';
                    setupFirstPin = null;
                    $('setup-label').textContent = 'Entrez votre PIN';
                    $('setup-error').textContent = '';
                    clearPinInputs('setup-pins');
                }, 1000);
            }
        }
    }

    // ─── PIN verify flow ─────────────────────
    async function handleVerifyPin(pin) {
        const valid = await Storage.verifyPin(pin);

        if (valid) {
            setPinState('pin-inputs', 'success');
            currentPin = pin;

            setTimeout(async () => {
                await loadAccounts();
                showAccountsOrEmpty();
                startAutoLock();
            }, 300);
        } else {
            setPinState('pin-inputs', 'error');
            $('pin-error').textContent = 'PIN incorrect';

            setTimeout(() => {
                clearPinInputs('pin-inputs');
                $('pin-error').textContent = '';
            }, 1000);
        }
    }

    // ─── Show accounts or empty ──────────────
    function showAccountsOrEmpty() {
        if (accounts.length === 0) {
            showScreen('empty');
        } else {
            showScreen('accounts');
            renderAccounts();
            startTimer();
        }
    }

    // ─── Load accounts ───────────────────────
    async function loadAccounts() {
        if (!currentPin) return;
        try {
            const raw = await Storage.getAccounts(currentPin);
            accounts = raw.map(a => ({
                ...a,
                code: null,  // will be generated by timer
            }));
        } catch (e) {
            console.error('[App] Erreur chargement comptes:', e);
            accounts = [];
        }
    }

    // ─── Render account cards ────────────────
    function renderAccounts() {
        const list = $('accounts-list');
        if (!list) return;

        $('accounts-count').textContent = accounts.length;

        list.innerHTML = accounts.map(account => {
            const code = account.code || '------';
            const first3 = code.substring(0, 3);
            const last3 = code.substring(3);

            return `
                <div class="account-card" data-cid="${account.civistromId}">
                    <div class="account-id">${account.civistromId}</div>
                    <div class="account-code-row">
                        <div class="account-code">
                            <span class="code-first">${first3}</span>
                            <span class="code-separator">&nbsp;</span>
                            <span class="code-last">${last3}</span>
                        </div>
                        <div class="timer-container">
                            <svg class="timer-svg" viewBox="0 0 36 36">
                                <circle class="timer-bg" cx="18" cy="18" r="16"/>
                                <circle class="timer-progress" cx="18" cy="18" r="16"
                                    stroke-dasharray="100.53"
                                    stroke-dashoffset="0"/>
                            </svg>
                            <span class="timer-text">--</span>
                        </div>
                    </div>
                    <span class="account-copied">Copié !</span>
                </div>
            `;
        }).join('');

        // Attach tap-to-copy and long-press-to-delete
        list.querySelectorAll('.account-card').forEach(card => {
            const cid = card.dataset.cid;
            let pressTimer = null;

            card.addEventListener('click', () => {
                const account = accounts.find(a => a.civistromId === cid);
                if (account?.code) {
                    copyCode(account.code, card);
                }
            });

            // Long press for delete
            card.addEventListener('touchstart', (e) => {
                pressTimer = setTimeout(() => {
                    showDeleteModal(cid);
                }, 800);
            }, { passive: true });

            card.addEventListener('touchend', () => clearTimeout(pressTimer));
            card.addEventListener('touchmove', () => clearTimeout(pressTimer));
        });

        // Initial update
        updateCodes();
    }

    // ─── Copy code to clipboard ──────────────
    async function copyCode(code, card) {
        try {
            await navigator.clipboard.writeText(code);
            card.classList.add('copied');
            const copiedEl = card.querySelector('.account-copied');
            if (copiedEl) copiedEl.classList.add('visible');

            setTimeout(() => {
                card.classList.remove('copied');
                if (copiedEl) copiedEl.classList.remove('visible');
            }, 1500);
        } catch {
            console.warn('[App] Clipboard non disponible');
        }
    }

    // ─── Timer + code updates ────────────────
    function startTimer() {
        stopTimer();
        updateCodes();
        timerInterval = setInterval(updateCodes, 1000);
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    let lastPeriod = 0;

    async function updateCodes() {
        const remaining = getRemainingSeconds();
        const currentPeriod = Math.floor(Date.now() / 1000 / 30);
        const circumference = 2 * Math.PI * 16; // r=16

        // Regenerate codes at each new period
        if (currentPeriod !== lastPeriod) {
            lastPeriod = currentPeriod;
            for (const account of accounts) {
                try {
                    account.code = await generateTOTP(account.secret);
                } catch (e) {
                    account.code = '------';
                    console.error(`[App] Erreur TOTP ${account.civistromId}:`, e);
                }
            }
        }

        // Update DOM
        const cards = document.querySelectorAll('.account-card');
        cards.forEach(card => {
            const cid = card.dataset.cid;
            const account = accounts.find(a => a.civistromId === cid);
            if (!account) return;

            // Code digits
            if (account.code && account.code !== '------') {
                card.querySelector('.code-first').textContent = account.code.substring(0, 3);
                card.querySelector('.code-last').textContent = account.code.substring(3);
            }

            // Timer
            const progress = card.querySelector('.timer-progress');
            const timerText = card.querySelector('.timer-text');

            if (progress) {
                const offset = circumference * (1 - remaining / 30);
                progress.style.strokeDashoffset = offset;

                // Color based on remaining time
                progress.classList.remove('warning', 'danger');
                if (remaining <= 5) {
                    progress.classList.add('danger');
                } else if (remaining <= 10) {
                    progress.classList.add('warning');
                }
            }

            if (timerText) {
                timerText.textContent = remaining;
            }
        });
    }

    // ─── Delete modal ────────────────────────
    function showDeleteModal(civistromId) {
        deleteTarget = civistromId;
        $('modal-delete-id').textContent = civistromId;
        $('modal-delete').hidden = false;
    }

    function hideDeleteModal() {
        deleteTarget = null;
        $('modal-delete').hidden = true;
    }

    async function confirmDelete() {
        if (!deleteTarget) return;

        await Storage.removeAccount(deleteTarget);
        accounts = accounts.filter(a => a.civistromId !== deleteTarget);
        hideDeleteModal();

        if (accounts.length === 0) {
            stopTimer();
            showScreen('empty');
        } else {
            renderAccounts();
        }
    }

    // ─── Auto-lock ───────────────────────────
    function startAutoLock() {
        stopAutoLock();

        document.addEventListener('visibilitychange', handleVisibility);
    }

    function stopAutoLock() {
        if (autoLockTimeout) {
            clearTimeout(autoLockTimeout);
            autoLockTimeout = null;
        }
        document.removeEventListener('visibilitychange', handleVisibility);
    }

    function handleVisibility() {
        if (document.hidden) {
            // App goes background — start countdown
            autoLockTimeout = setTimeout(() => {
                lockApp();
            }, AUTO_LOCK_MS);
        } else {
            // App comes back — cancel lock if within time
            if (autoLockTimeout) {
                clearTimeout(autoLockTimeout);
                autoLockTimeout = null;
            }
        }
    }

    function lockApp() {
        currentPin = null;
        accounts = [];
        stopTimer();
        stopAutoLock();
        clearPinInputs('pin-inputs');
        $('pin-error').textContent = '';
        showScreen('pin');
    }

    // ─── Scanner ─────────────────────────────
    function openScanner() {
        showScreen('scanner');
        Scanner.start((data) => {
            // QR CIVISTROM valide détecté
            showConfirm(data);
        });
    }

    function closeScanner() {
        Scanner.stop();
        showAccountsOrEmpty();
    }

    // ─── Confirm add (Sprint 3 stubs) ────────
    function showConfirm(data) {
        pendingScan = data;
        $('confirm-id').textContent = data.civistromId;
        $('confirm-issuer').textContent = data.issuer || 'CIVISTROM';
        showScreen('confirm');
    }

    async function confirmAdd() {
        if (!pendingScan || !currentPin) return;

        // Check duplicate
        const existing = accounts.find(a => a.civistromId === pendingScan.civistromId);
        if (existing) {
            alert('Ce compte est déjà enregistré.');
            pendingScan = null;
            showAccountsOrEmpty();
            return;
        }

        await Storage.addAccount(pendingScan.civistromId, pendingScan.secret, currentPin);
        await loadAccounts();
        pendingScan = null;
        lastPeriod = 0; // Force code regen
        showScreen('accounts');
        renderAccounts();
        startTimer();
    }

    function cancelConfirm() {
        pendingScan = null;
        showAccountsOrEmpty();
    }

    // ─── Event bindings ──────────────────────
    function bindEvents() {
        // Setup PIN
        setupPinInputs('setup-pins', handleSetupPin);

        // Verify PIN
        setupPinInputs('pin-inputs', handleVerifyPin);

        // Add buttons → open scanner
        $('btn-add-first')?.addEventListener('click', openScanner);
        $('btn-add')?.addEventListener('click', openScanner);

        // Scanner
        $('btn-scanner-back')?.addEventListener('click', closeScanner);

        // Confirm add
        $('btn-confirm-add')?.addEventListener('click', confirmAdd);
        $('btn-confirm-cancel')?.addEventListener('click', cancelConfirm);

        // Delete modal
        $('btn-delete-confirm')?.addEventListener('click', confirmDelete);
        $('btn-delete-cancel')?.addEventListener('click', hideDeleteModal);

        // Click outside modal to close
        $('modal-delete')?.addEventListener('click', (e) => {
            if (e.target === $('modal-delete')) hideDeleteModal();
        });
    }

    // ─── Init ────────────────────────────────
    async function init() {
        try {
            await Storage.init();
            bindEvents();

            // Short splash delay
            await new Promise(r => setTimeout(r, 600));

            const isSetup = await Storage.isSetup();

            if (!isSetup) {
                // First time — show PIN setup
                showScreen('setup');
            } else {
                // Already configured — show PIN lock
                showScreen('pin');
            }
        } catch (e) {
            console.error('[App] Erreur initialisation:', e);
            // Stay on loading screen with error
        }
    }

    // Public API (for console testing)
    return { init, lockApp, accounts: () => accounts };

})();

// ─── Bootstrap ───────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    console.log('[CIVISTROM ID] app.js chargé — v1.0.0');
    App.init();
});
