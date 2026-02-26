<?php
/**
 * CIVISTROM ID — Vue principale SPA
 *
 * Tous les écrans dans le DOM, gérés par show/hide JS.
 * State machine : LOADING → SETUP → LOCKED → EMPTY/ACCOUNTS → SCANNING → CONFIRMING
 */
?>

<div id="app" class="app">

    <!-- ═══ Loading / Splash ═══ -->
    <div id="screen-loading" class="screen screen--center">
        <div class="splash">
            <div class="splash-logo">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                    <rect width="48" height="48" rx="12" fill="#6366F1" fill-opacity="0.15"/>
                    <path d="M16 24L22 30L32 18" stroke="#6366F1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="splash-title">CIVISTROM ID</h1>
            <p class="splash-subtitle">Authentificateur</p>
        </div>
    </div>

    <!-- ═══ Setup PIN (première utilisation) ═══ -->
    <div id="screen-setup" class="screen screen--center" hidden>
        <div class="header">
            <div class="header-icon">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect width="40" height="40" rx="10" fill="#6366F1" fill-opacity="0.15"/>
                    <rect x="12" y="10" width="16" height="20" rx="3" stroke="#6366F1" stroke-width="2"/>
                    <circle cx="20" cy="22" r="2" fill="#6366F1"/>
                    <line x1="20" y1="24" x2="20" y2="27" stroke="#6366F1" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h1 class="header-title">Choisir un PIN</h1>
            <p class="header-subtitle">4 chiffres pour protéger vos codes</p>
        </div>

        <p class="pin-label" id="setup-label">Entrez votre PIN</p>
        <div class="pin-container" id="setup-pins">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="0">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="1">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="2">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="3">
        </div>
        <p class="pin-error" id="setup-error"></p>
    </div>

    <!-- ═══ PIN Lock (déverrouillage) ═══ -->
    <div id="screen-pin" class="screen screen--center" hidden>
        <div class="header">
            <div class="header-icon">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect width="40" height="40" rx="10" fill="#6366F1" fill-opacity="0.15"/>
                    <path d="M16 24L22 30L32 18" stroke="#6366F1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="header-title">CIVISTROM ID</h1>
            <p class="header-subtitle">Entrez votre PIN</p>
        </div>

        <div class="pin-container" id="pin-inputs">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="0">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="1">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="2">
            <input type="tel" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" data-index="3">
        </div>
        <p class="pin-error" id="pin-error"></p>
    </div>

    <!-- ═══ Empty State (pas de comptes) ═══ -->
    <div id="screen-empty" class="screen" hidden>
        <div class="empty-state">
            <div class="empty-icon">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <rect x="8" y="8" width="48" height="48" rx="12" stroke="#6366F1" stroke-width="2" stroke-dasharray="4 4"/>
                    <path d="M32 24V40M24 32H40" stroke="#6366F1" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h2 class="empty-title">Aucun compte</h2>
            <p class="empty-text">Scannez le QR code CIVISTROM pour ajouter votre premier compte.</p>
            <button class="btn btn-primary" id="btn-add-first">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <rect x="2" y="2" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M6 10H14M10 6V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Scanner un QR code
            </button>
        </div>
    </div>

    <!-- ═══ Accounts (liste + codes live) ═══ -->
    <div id="screen-accounts" class="screen" hidden>
        <div class="accounts-header">
            <h1 class="accounts-title">Mes comptes</h1>
            <span class="accounts-count" id="accounts-count">0</span>
        </div>

        <div class="accounts-list" id="accounts-list">
            <!-- Cards dynamiques insérées par JS -->
        </div>

        <!-- FAB Ajouter -->
        <button class="fab" id="btn-add" title="Ajouter un compte">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M12 5V19M5 12H19"/>
            </svg>
        </button>
    </div>

    <!-- ═══ Scanner QR (Sprint 3) ═══ -->
    <div id="screen-scanner" class="screen screen--center" hidden>
        <div class="header">
            <h1 class="header-title">Scanner le QR code</h1>
            <p class="header-subtitle">Placez le QR CIVISTROM dans le cadre</p>
        </div>

        <div class="scanner-container">
            <video id="scanner-video" class="scanner-video" playsinline autoplay muted></video>
            <canvas id="scanner-canvas" class="scanner-canvas"></canvas>
        </div>

        <p class="scanner-text">Positionnez le QR code dans le cadre</p>

        <button class="btn btn-ghost" id="btn-scanner-back" style="margin-top: 1.5rem;">
            Annuler
        </button>
    </div>

    <!-- ═══ Confirm Ajout (Sprint 3) ═══ -->
    <div id="screen-confirm" class="screen screen--center" hidden>
        <div class="header">
            <h1 class="header-title">Ajouter ce compte ?</h1>
        </div>

        <div class="confirm-card">
            <p class="confirm-id" id="confirm-id">CIV-XXXX-XXXX-X</p>
            <p class="confirm-issuer" id="confirm-issuer">CIVISTROM</p>
        </div>

        <div class="confirm-actions">
            <button class="btn btn-ghost" id="btn-confirm-cancel">Annuler</button>
            <button class="btn btn-primary" id="btn-confirm-add">Ajouter</button>
        </div>
    </div>

    <!-- ═══ Delete Modal ═══ -->
    <div id="modal-delete" class="modal-overlay" hidden>
        <div class="modal">
            <h2 class="modal-title">Supprimer ce compte ?</h2>
            <p class="modal-text">
                Le compte <span class="modal-id" id="modal-delete-id">CIV-XXXX-XXXX-X</span>
                sera supprimé. Vous ne pourrez plus vous connecter avec ce compte.
            </p>
            <div class="modal-actions">
                <button class="btn btn-ghost" id="btn-delete-cancel">Annuler</button>
                <button class="btn btn-danger" id="btn-delete-confirm">Supprimer</button>
            </div>
        </div>
    </div>

</div>
