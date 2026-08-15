/**
 * Cálculos do formulário de contrato — CONTROLE DE CONTRATOS.
 *  - Periodicidade: Data de término = Data de início + duração (meses).
 *    "Indeterminada" (0) não altera a data.
 *  - Aviso: data = Data de término − antecedência (dias); fica vermelha quando
 *    já se está dentro do período de aviso (como o módulo nativo).
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

    // Data de término = início + N meses (só quando a periodicidade > 0).
    function setEndDate() {
        var db = q('date_begin');
        var per = q('periodicity');
        var de = q('date_end');
        if (!db || !per || !de) {
            return;
        }
        var months = parseInt(per.value, 10);
        if (!db.value || isNaN(months) || months <= 0) {
            return; // Indeterminada → não mexe na data de término.
        }
        var d = new Date(db.value + 'T00:00:00');
        if (isNaN(d.getTime())) {
            return;
        }
        d.setMonth(d.getMonth() + months);

        if (de._flatpickr) {
            de._flatpickr.setDate(d, true); // true = dispara 'change' → recalcula o aviso
        } else {
            de.value = d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
            updateAlert();
        }
    }

    // Data do aviso = término − antecedência (dias).
    function updateAlert() {
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

    // Mostra o campo "Quantidade de licenças" só quando o Tipo for Licença.
    function toggleLicenseQty() {
        var kind = q('kind');
        var row = document.getElementById('cc-license-qty-row');
        if (!row) {
            return;
        }
        row.style.display = (kind && kind.value === 'license') ? '' : 'none';
    }

    document.addEventListener('change', function (e) {
        if (e.target.name === 'periodicity' || e.target.name === 'date_begin') {
            setEndDate();
            updateAlert();
        } else if (e.target.name === 'date_end' || e.target.name === 'alert_days') {
            updateAlert();
        } else if (e.target.name === 'kind') {
            toggleLicenseQty();
        }
    });

    function init() {
        updateAlert();
        toggleLicenseQty();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
