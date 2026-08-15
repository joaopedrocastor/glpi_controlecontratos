/**
 * Datas calculadas no formulário de contrato — CONTROLE DE CONTRATOS.
 * - Periodicidade: data de início + duração (meses)  → data de término prevista
 * - Aviso:         data de término − antecedência (dias) → data do aviso
 * Recalcula ao vivo; usa delegação de evento (robusto a select2/flatpickr).
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
        var db = q('date_begin');
        var per = q('periodicity');
        var de = q('date_end');
        var ad = q('alert_days');
        var perOut = document.getElementById('cc-period-date');
        var alOut = document.getElementById('cc-alert-date');

        // Periodicidade: início + N meses.
        if (perOut) {
            var months = parseInt(per && per.value, 10);
            if (db && db.value && months > 0) {
                var d1 = new Date(db.value + 'T00:00:00');
                if (!isNaN(d1.getTime())) {
                    d1.setMonth(d1.getMonth() + months);
                    perOut.textContent = '→ ' + fmt(d1);
                }
            } else {
                perOut.textContent = '';
            }
        }

        // Aviso: término − N dias.
        if (alOut) {
            var days = parseInt(ad && ad.value, 10);
            if (de && de.value && days > 0) {
                var d2 = new Date(de.value + 'T00:00:00');
                if (!isNaN(d2.getTime())) {
                    d2.setDate(d2.getDate() - days);
                    alOut.textContent = '→ ' + fmt(d2);
                }
            } else {
                alOut.textContent = '';
            }
        }
    }

    document.addEventListener('change', function (e) {
        if (['date_begin', 'periodicity', 'date_end', 'alert_days'].indexOf(e.target.name) >= 0) {
            update();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', update);
    } else {
        update();
    }
})();
