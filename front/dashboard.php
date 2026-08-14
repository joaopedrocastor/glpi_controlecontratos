<?php
/**
 * Página de Dashboard — CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_controlecontratos_contract', READ);

Html::header(
    __('Dashboard — CONTROLE DE CONTRATOS', 'controlecontratos'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginControlecontratosMenu',
    'dashboard'
);

PluginControlecontratosDashboard::show();

Html::footer();
