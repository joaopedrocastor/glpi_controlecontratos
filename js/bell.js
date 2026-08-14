/**
 * Sino flutuante — CONTROLE DE CONTRATOS.
 * Abre/fecha o painel de contratos próximos do término.
 * (Em arquivo .js para não esbarrar na CSP de <script> inline do GLPI.)
 */
(function () {
    'use strict';

    function bind() {
        var btn = document.getElementById('cc-bell-btn');
        var panel = document.getElementById('cc-bell-panel');
        var wrap = document.getElementById('cc-bell');
        if (!btn || !panel || btn.dataset.ccBound) {
            return;
        }
        btn.dataset.ccBound = '1';

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.style.display = (panel.style.display === 'none' || !panel.style.display) ? 'block' : 'none';
        });

        // Fecha ao clicar fora do widget.
        document.addEventListener('click', function (e) {
            if (wrap && !wrap.contains(e.target)) {
                panel.style.display = 'none';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
