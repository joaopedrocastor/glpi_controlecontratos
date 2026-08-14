/**
 * Web Push (Browser Notifications) — CONTROLE DE CONTRATOS.
 * Registra o Service Worker, solicita permissão e inscreve o navegador
 * usando a VAPID public key vinda do servidor. Arquitetura inspirada no
 * plugin edgardmessias/browsernotification.
 *
 * @author João Pedro Castor Quirino
 */
(function () {
    'use strict';

    // Descobre a raiz do plugin a partir do caminho deste script.
    var pluginRoot = (function () {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            var idx = src.indexOf('/plugins/controlecontratos/');
            if (idx !== -1) {
                return src.substring(0, idx) + '/plugins/controlecontratos';
            }
        }
        return '';
    })();

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return; // Navegador sem suporte a Web Push.
    }

    /** Converte a chave VAPID base64-url para Uint8Array. */
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var output = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) {
            output[i] = raw.charCodeAt(i);
        }
        return output;
    }

    async function init() {
        try {
            var cfg = await fetch(pluginRoot + '/ajax/webpush_config.php', {
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); });

            if (!cfg.enabled || !cfg.publicKey) {
                return; // Web Push não configurado no plugin.
            }

            var registration = await navigator.serviceWorker.register(
                pluginRoot + '/js/service-worker.js'
            );

            var permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                return;
            }

            var subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(cfg.publicKey)
                });
            }

            // Envia a assinatura ao backend (com token CSRF no header).
            await fetch(pluginRoot + '/ajax/webpush_subscribe.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Glpi-Csrf-Token': cfg.csrf
                },
                body: JSON.stringify(subscription)
            });
        } catch (e) {
            /* Falha silenciosa — não deve quebrar a navegação do GLPI. */
            if (window.console) { console.warn('[WebPush]', e); }
        }
    }

    // Aguarda o carregamento para não competir com o boot do GLPI.
    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }
})();
