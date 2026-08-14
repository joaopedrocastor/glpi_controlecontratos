<?php
/**
 * -------------------------------------------------------------------------
 * Preferências de notificação por usuário — CONTROLE DE CONTRATOS.
 * Define em quais canais o destinatário recebe os alertas de vencimento.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosNotificationpref extends CommonDBTM
{
    public static $rightname = 'plugin_controlecontratos_contract';

    public static function getTypeName($nb = 0)
    {
        return __('Preferências de Notificação', 'controlecontratos');
    }

    /**
     * Retorna a preferência do usuário (cria com padrões se não existir).
     *
     * @param int $users_id
     * @return self
     */
    public static function getForUser($users_id)
    {
        $pref = new self();
        if (!$pref->getFromDBByCrit(['users_id' => $users_id])) {
            $id = $pref->add([
                'users_id'     => $users_id,
                'use_webpush'  => 1, // padrão: Web Push ligado.
                'date_mod'     => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ]);
            $pref->getFromDB($id);
        }
        return $pref;
    }

    /**
     * Exibe a aba de preferências dentro da ficha do contrato.
     * (A escolha de canais é por usuário logado; a aba dá acesso rápido.)
     *
     * @param PluginControlecontratosContract $contract
     * @return void
     */
    public static function showForContract(PluginControlecontratosContract $contract)
    {
        $users_id = Session::getLoginUserID();
        $pref     = self::getForUser($users_id);

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@controlecontratos/notificationpref.html.twig', [
            'pref'     => $pref,
            'contract' => $contract,
            'target'   => Plugin::getWebDir('controlecontratos') . '/front/notificationpref.form.php',
        ]);
    }

    /**
     * Lista os usuários que optaram por um canal específico e devem
     * ser notificados. Usado pela CronTask.
     *
     * @param string $channel use_whatsapp|use_telegram|use_teams|use_webpush
     * @return array Linhas de preferências (com users_id e whatsapp_number).
     */
    public static function getRecipientsForChannel($channel)
    {
        /** @var DBmysql $DB */
        global $DB;

        $allowed = ['use_whatsapp', 'use_telegram', 'use_teams', 'use_webpush'];
        if (!in_array($channel, $allowed, true)) {
            return [];
        }

        return iterator_to_array($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [$channel => 1],
        ]));
    }
}
