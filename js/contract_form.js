/**
 * Data do aviso no formulário de contrato — CONTROLE DE CONTRATOS.
 * Aviso = data de término − antecedência (dias). Mostra embaixo do campo e
 * fica vermelha quando já se está dentro do período de aviso (como o nativo).
 * Recalcula ao vivo; delegação de evento (robusto a select2/flatpickr).
 */
(function () {
    'use strict';

    function q(name) {
        return document.querySelector('[name="' + name + '"]');
    }

    function fmt(d) {
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        return dd + '-' + mm + '-' + d.getFullYear();
    }

    function update() {
        var de = q('date_end');
        var ad = q('alert_days');
        var out = document.getElementById('cc-alert-date');
        if (!out) {
            return;
        }

        var days = parseInt(ad && ad.value, 10);
        if (de && de.value && days > 0) {
            var d = new Date(de.value + 'T00:00:00');
            if (isNaN(d.getTime())) {
                out.textContent = '';
                return;
            }
            d.setDate(d.getDate() - days);
            out.textContent = '→ ' + fmt(d);

            var today = new Date();
            today.setHours(0, 0, 0, 0);
            if (d <= today) {
                out.classList.add('text-danger');
                out.classList.remove('text-secondary');
            } else {
                out.classList.remove('text-danger');
                out.classList.add('text-secondary');
            }
        } else {
            out.textContent = '';
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.name === 'date_end' || e.target.name === 'alert_days') {
            update();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', update);
    } else {
        update();
    }
})();
