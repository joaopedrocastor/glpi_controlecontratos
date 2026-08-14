<?php
/**
 * -------------------------------------------------------------------------
 * Classe de configuração das APIs (GUI) — CONTROLE DE CONTRATOS.
 * Salva no banco todas as credenciais (Evolution/WhatsApp, Telegram,
 * Teams, VAPID/Web Push). Nenhuma chave fica hardcoded no código.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosConfig extends CommonDBTM
{
    /** @var string Somente perfis com direito de configuração acessam. */
    public static $rightname = 'config';

    /** @var self|null Cache do singleton de configuração. */
    private static $instance = null;

    public static function getTypeName($nb = 0)
    {
        return __('Configuração — CONTROLE DE CONTRATOS', 'controlecontratos');
    }

    public static function getIcon()
    {
        return 'ti ti-settings';
    }

    /**
     * Retorna (e cacheia) a linha única de configuração (id=1).
     *
     * @return self
     */
    public static function getConfig()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            if (!self::$instance->getFromDB(1)) {
                // Garante que a linha singleton exista.
                self::$instance->add(['id' => 1, 'is_active' => 1]);
                self::$instance->getFromDB(1);
            }
        }
        return self::$instance;
    }

    /**
     * Atalho para ler um campo de configuração já com fallback vazio.
     *
     * @param string $field
     * @return string
     */
    public static function getValue($field)
    {
        $cfg = self::getConfig();
        return (string) ($cfg->fields[$field] ?? '');
    }

    /**
     * Renderiza a tela de configuração das APIs via Twig (layout Tabler).
     * Inclui token CSRF nativo do formulário GLPI.
     *
     * @return bool
     */
    public function showConfigForm()
    {
        if (!Session::haveRight(self::$rightname, UPDATE)) {
            return false;
        }

        $config = self::getConfig();

        TemplateRenderer::getInstance()->display('@controlecontratos/config.html.twig', [
            'config'      => $config,
            'target'      => Plugin::getWebDir('controlecontratos') . '/front/config.form.php',
            // CSRF token é adicionado automaticamente pelo template base do GLPI (Html::hidden('_glpi_csrf_token')).
        ]);

        return true;
    }

    /**
     * Higieniza e valida a entrada antes de gravar as credenciais.
     */
    public function prepareInputForUpdate($input)
    {
        // Normaliza URLs (remove barra final) para evitar duplicidade em concatenações.
        foreach (['evolution_url', 'teams_webhook'] as $urlField) {
            if (isset($input[$urlField]) && $input[$urlField] !== '') {
                $input[$urlField] = rtrim(trim($input[$urlField]), '/');
                if (!filter_var($input[$urlField], FILTER_VALIDATE_URL)) {
                    Session::addMessageAfterRedirect(
                        sprintf(__('URL inválida no campo %s.', 'controlecontratos'), $urlField),
                        false,
                        ERROR
                    );
                    return false;
                }
            }
        }

        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }
}
