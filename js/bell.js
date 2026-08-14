/**
 * Sino flutuante — CONTROLE DE CONTRATOS.
 * Abre/fecha o painel de contratos próximos do término.
 * Usa delegação de evento no document, então funciona mesmo que o sino
 * seja injetado na página depois deste script carregar.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var panel = document.getElementById('cc-bell-panel');
        if (!panel) {
            return;
        }

        // Clique no botão do sino → alterna o painel.
        if (e.target.closest && e.target.closest('#cc-bell-btn')) {
            e.preventDefault();
            e.stopPropagation();
            panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
            return;
        }

        // Clique fora do widget → fecha.
        var wrap = document.getElementById('cc-bell');
        if (wrap && !wrap.contains(e.target)) {
            panel.style.display = 'none';
        }
    });
})();
