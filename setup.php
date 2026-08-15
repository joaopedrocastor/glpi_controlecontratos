<?php
/**
 * -------------------------------------------------------------------------
 * Plugin CONTROLE DE CONTRATOS
 * Gestão e controle de contratos com alertas multicanais para o GLPI 10+.
 *
 * @author    João Pedro Castor Quirino
 * @copyright Copyright (C) 2026 by João Pedro Castor Quirino
 * @license   GPLv2+
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_CONTROLECONTRATOS_VERSION', '1.3.0');

// Versão mínima e máxima do GLPI suportada (foco exclusivo no 10.0.11+).
define('PLUGIN_CONTROLECONTRATOS_MIN_GLPI', '10.0.11');
define('PLUGIN_CONTROLECONTRATOS_MAX_GLPI', '11.0.99');

/**
 * Inicializa o plugin e registra todos os hooks utilizados.
 *
 * Chamado automaticamente pelo GLPI durante o bootstrap de plugins ativos.
 *
 * @return void
 */
function plugin_init_controlecontratos()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Declara compatibilidade com o padrão de segurança CSRF do GLPI 10.
    $PLUGIN_HOOKS['csrf_compliant']['controlecontratos'] = true;

    // Carrega os assets (JS/CSS) do plugin em todas as páginas autenticadas.
    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['controlecontratos'] = [
            'js/webpush.js',
        ];
        $PLUGIN_HOOKS[Hooks::ADD_CSS]['controlecontratos'] = [
            'css/controlecontratos.css',
        ];

        // Injeta o "Sino" de notificações (renderizado na página central).
        $PLUGIN_HOOKS[Hooks::DISPLAY_CENTRAL]['controlecontratos'] = 'plugin_controlecontratos_display_header';

        // Menu principal do plugin (Gerência > Controle Contratos).
        $PLUGIN_HOOKS['menu_toadd']['controlecontratos'] = [
            'management' => 'PluginControlecontratosMenu',
        ];

        // Página de configuração acessível via Configurar > Plugins.
        // ('config_page' é uma string de hook — não existe constante Hooks::CONFIG_PAGE.)
        $PLUGIN_HOOKS['config_page']['controlecontratos'] = 'front/config.form.php';
    }

    // Registra abas nativas (Anexos e Preferências) na ficha do contrato.
    Plugin::registerClass('PluginControlecontratosContract', [
        'addtabon' => [],
    ]);
    Plugin::registerClass('PluginControlecontratosConfig');
    Plugin::registerClass('PluginControlecontratosNotificationpref');
}

/**
 * Retorna os metadados do plugin exibidos no gerenciador de plugins do GLPI.
 *
 * @return array
 */
function plugin_version_controlecontratos()
{
    return [
        'name'           => 'Controle Contratos',
        'version'        => PLUGIN_CONTROLECONTRATOS_VERSION,
        'author'         => 'João Pedro Castor Quirino',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_CONTROLECONTRATOS_MIN_GLPI,
                'max' => PLUGIN_CONTROLECONTRATOS_MAX_GLPI,
            ],
            'php' => [
                'min' => '7.4',
            ],
        ],
    ];
}

/**
 * Verificação de pré-requisitos antes da ativação do plugin.
 *
 * @return bool
 */
function plugin_controlecontratos_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_CONTROLECONTRATOS_MIN_GLPI, 'lt')) {
        echo sprintf(
            __('Este plugin requer o GLPI >= %s', 'controlecontratos'),
            PLUGIN_CONTROLECONTRATOS_MIN_GLPI
        );
        return false;
    }
    return true;
}

/**
 * Verifica se a configuração é válida (chamado antes da instalação).
 *
 * @param bool $verbose
 * @return bool
 */
function plugin_controlecontratos_check_config($verbose = false)
{
    return true;
}
