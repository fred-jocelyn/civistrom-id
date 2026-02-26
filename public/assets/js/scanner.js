/**
 * CIVISTROM ID — QR Scanner
 *
 * Utilise jsQR (vendored) + getUserMedia pour scanner les QR codes.
 * Chargement dynamique de jsQR (lazy load).
 *
 * Fonctions exportées :
 *   Scanner.start(onDetect) → Promise<void>
 *   Scanner.stop() → void
 *   Scanner.isRunning() → boolean
 */

'use strict';

const Scanner = (() => {

    let video = null;
    let canvas = null;
    let ctx = null;
    let stream = null;
    let animFrame = null;
    let running = false;
    let jsQRLoaded = false;
    let onDetectCallback = null;

    /**
     * Charge jsQR dynamiquement (une seule fois)
     */
    async function loadJsQR() {
        if (jsQRLoaded || typeof jsQR !== 'undefined') {
            jsQRLoaded = true;
            return;
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            // Detect MAMP (public prefix) vs prod
            const prefix = document.querySelector('link[href*="/public/assets/"]') ? '/public' : '';
            script.src = prefix + '/assets/js/vendor/jsqr.min.js';
            script.onload = () => {
                jsQRLoaded = true;
                resolve();
            };
            script.onerror = () => reject(new Error('Impossible de charger jsQR'));
            document.head.appendChild(script);
        });
    }

    /**
     * Démarre le scanner QR
     *
     * @param {Function} onDetect - Callback appelé quand un QR CIVISTROM valide est détecté
     *                              Reçoit : { civistromId, secret, issuer, label }
     */
    async function start(onDetect) {
        if (running) return;

        onDetectCallback = onDetect;
        video = document.getElementById('scanner-video');
        canvas = document.getElementById('scanner-canvas');

        if (!video || !canvas) {
            console.error('[Scanner] Éléments video/canvas introuvables');
            return;
        }

        ctx = canvas.getContext('2d', { willReadFrequently: true });

        // Charger jsQR
        try {
            await loadJsQR();
        } catch (e) {
            console.error('[Scanner] Erreur chargement jsQR:', e);
            return;
        }

        // Demander l'accès caméra arrière
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 640 },
                    height: { ideal: 640 },
                }
            });

            video.srcObject = stream;
            await video.play();
            running = true;

            // Lancer la boucle de scan
            scanLoop();

        } catch (e) {
            console.error('[Scanner] Accès caméra refusé:', e);
            running = false;
        }
    }

    /**
     * Boucle de scan : capture frame → jsQR → vérifie format CIVISTROM
     */
    function scanLoop() {
        if (!running) return;

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            // Adapter le canvas à la vidéo
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Dessiner la frame
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Analyser avec jsQR
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert',
            });

            if (code && code.data) {
                // Tenter de parser comme URI otpauth CIVISTROM
                const parsed = parseOtpauthUri(code.data);

                if (parsed) {
                    // QR CIVISTROM valide détecté !
                    stop();
                    if (onDetectCallback) {
                        onDetectCallback(parsed);
                    }
                    return;
                }
                // Sinon : QR non-CIVISTROM, on continue le scan
            }
        }

        animFrame = requestAnimationFrame(scanLoop);
    }

    /**
     * Arrête le scanner (caméra + boucle)
     */
    function stop() {
        running = false;

        if (animFrame) {
            cancelAnimationFrame(animFrame);
            animFrame = null;
        }

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        if (video) {
            video.srcObject = null;
        }

        onDetectCallback = null;
    }

    /**
     * Vérifie si le scanner est actif
     */
    function isRunning() {
        return running;
    }

    return { start, stop, isRunning };

})();

console.log('[CIVISTROM ID] scanner.js chargé');
