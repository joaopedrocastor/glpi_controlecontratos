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

// Motor de busca nativo do GLPI (Search) — herda filtros, exportação e paginação.
Search::show('PluginControlecontratosContract');

Html::footer();
