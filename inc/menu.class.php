<?php
/**
 * -------------------------------------------------------------------------
 * Entrada de menu do plugin — CONTROLE DE CONTRATOS.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Controle Contratos', 'controlecontratos');
    }

    public static function getIcon()
    {
        return 'ti ti-file-certificate';
    }

    /**
     * Define o menu principal e seus submenus (padrão menu_toadd do GLPI 10).
     *
     * @return array
     */
    public static function getMenuContent()
    {
        $webdir = Plugin::getWebDir('controlecontratos', false);

        // Página principal do menu = Dashboard (ao clicar em "Controle Contratos").
        $menu = [
            'title' => self::getTypeName(2),
            'page'  => $webdir . '/front/dashboard.php',
            'icon'  => self::getIcon(),
        ];

        // Submenus (o Dashboard aparece primeiro, depois a lista e a configuração).
        $menu['options']['dashboard'] = [
            'title' => __('Dashboard', 'controlecontratos'),
            'page'  => $webdir . '/front/dashboard.php',
            'icon'  => 'ti ti-dashboard',
        ];
        $menu['options']['contract'] = [
            'title' => PluginControlecontratosContract::getTypeName(2),
            'page'  => $webdir . '/front/contract.php',
            'icon'  => 'ti ti-file-certificate',
            'links' => [
                'search' => $webdir . '/front/contract.php',
                'add'    => $webdir . '/front/contract.form.php',
            ],
        ];
        $menu['options']['config'] = [
            'title' => __('Configuração das APIs', 'controlecontratos'),
            'page'  => Plugin::getWebDir('controlecontratos', false) . '/front/config.form.php',
            'icon'  => 'ti ti-settings',
        ];

        return $menu;
    }

    /**
     * Ícones de ação rápida exibidos no cabeçalho do menu.
     */
    public static function getAdditionalMenuLinks()
    {
        $links = [];
        $links['<i class="ti ti-dashboard"></i>'] =
            Plugin::getWebDir('controlecontratos', false) . '/front/dashboard.php';
        return $links;
    }
}
