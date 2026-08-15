<?php
/**
 * Listagem/pesquisa de contratos — CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_controlecontratos_contract', READ);

Html::header(
    PluginControlecontratosContract::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginControlecontratosMenu',
    'contract'
);

// -----------------------------------------------------------------------------
// Botões de filtro rápido por Tipo (opção de busca id 7 = kind).
// Monta URLs de busca já com o critério pronto.
// -----------------------------------------------------------------------------
$base = Plugin::getWebDir('controlecontratos') . '/front/contract.php';

$buildUrl = function ($kind = null) use ($base) {
    if ($kind === null) {
        return $base . '?' . http_build_query(['reset' => 'reset']);
    }
    return $base . '?' . http_build_query([
        'reset'    => 'reset',
        'criteria' => [[
            'field'      => 7,          // id da opção de busca "Tipo"
            'searchtype' => 'equals',
            'value'      => $kind,      // 'contract' ou 'license'
        ]],
    ]);
};

echo "<div class='mb-2'>";
echo "<a class='btn btn-sm btn-outline-secondary me-1' href='" . htmlspecialchars($buildUrl()) . "'>"
    . "<i class='ti ti-list me-1'></i>" . __('Todos', 'controlecontratos') . "</a>";
echo "<a class='btn btn-sm btn-primary me-1' href='" . htmlspecialchars($buildUrl('contract')) . "'>"
    . "<i class='ti ti-file-certificate me-1'></i>" . __('Contratos', 'controlecontratos') . "</a>";
echo "<a class='btn btn-sm text-white me-1' style='background-color:#ae3ec9' href='" . htmlspecialchars($buildUrl('license')) . "'>"
    . "<i class='ti ti-license me-1'></i>" . __('Licenças', 'controlecontratos') . "</a>";
echo "<a class='btn btn-sm btn-success me-1' href='" . htmlspecialchars($buildUrl('certificate')) . "'>"
    . "<i class='ti ti-certificate me-1'></i>" . __('Certificados', 'controlecontratos') . "</a>";
echo "<a class='btn btn-sm text-white' style='background-color:#17a2b8' href='" . htmlspecialchars($buildUrl('domain')) . "'>"
    . "<i class='ti ti-world me-1'></i>" . __('Domínios', 'controlecontratos') . "</a>";
echo "</div>";

// Motor de busca nativo do GLPI (Search) — herda filtros, exportação e paginação.
Search::show('PluginControlecontratosContract');

Html::footer();
