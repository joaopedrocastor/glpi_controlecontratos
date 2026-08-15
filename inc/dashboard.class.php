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
        // Filtro por tipo vindo da URL (?kind=contract|license). null = todos.
        $kind = $_GET['kind'] ?? null;
        if (!in_array($kind, ['contract', 'license'], true)) {
            $kind = null;
        }

        $stats = PluginControlecontratosContract::getDashboardStats($kind);
        // Tabela lista TODOS (seguindo o filtro de tipo), não só os vencendo.
        $rows  = PluginControlecontratosContract::getContractsList($kind);

        // Anexa o badge de status a cada linha para o template.
        foreach ($rows as &$row) {
            $row['status_badge'] = PluginControlecontratosContract::getStatusBadge($row);
            $row['kind_badge']   = PluginControlecontratosContract::getKindBadge($row['kind'] ?? 'contract');
        }
        unset($row);

        $webdir   = Plugin::getWebDir('controlecontratos');
        $dashBase = $webdir . '/front/dashboard.php';

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@controlecontratos/dashboard.html.twig', [
            'stats'        => $stats,
            'rows'         => $rows,
            'active_kind'  => $kind,                 // filtro atual (null|contract|license)
            'form_url'     => $webdir . '/front/contract.form.php',
            'list_url'     => $webdir . '/front/contract.php',
            'dash_all'     => $dashBase,
            'dash_contract' => $dashBase . '?kind=contract',
            'dash_license'  => $dashBase . '?kind=license',
        ]);
    }
}
