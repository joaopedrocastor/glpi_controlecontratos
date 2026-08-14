<?php
/**
 * -------------------------------------------------------------------------
 * Dashboard do plugin — CONTROLE DE CONTRATOS.
 * Renderiza indicadores (stat cards Tabler) e a lista de vencimentos.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosDashboard extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Dashboard — CONTROLE DE CONTRATOS', 'controlecontratos');
    }

    /**
     * Renderiza a página de dashboard via Twig (componentes Tabler).
     *
     * @return void
     */
    public static function show()
    {
        $stats     = PluginControlecontratosContract::getDashboardStats();
        $expiring  = PluginControlecontratosContract::getExpiringContracts(60);

        // Anexa o badge de status a cada linha para o template.
        foreach ($expiring as &$row) {
            $row['status_badge'] = PluginControlecontratosContract::getStatusBadge($row);
            $row['kind_badge']   = PluginControlecontratosContract::getKindBadge($row['kind'] ?? 'contract');
        }
        unset($row);

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@controlecontratos/dashboard.html.twig', [
            'stats'    => $stats,
            'expiring' => $expiring,
            'form_url' => Plugin::getWebDir('controlecontratos') . '/front/contract.form.php',
        ]);
    }
}
