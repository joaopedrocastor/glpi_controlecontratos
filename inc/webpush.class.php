<?php
/**
 * -------------------------------------------------------------------------
 * Web Push (Browser Notifications) — CONTROLE DE CONTRATOS.
 * Arquitetura inspirada no plugin edgardmessias/browsernotification:
 * injeta JS + Service Worker e entrega notificações VAPID às assinaturas.
 *
 * A assinatura VAPID requer a lib minishlink/web-push (via composer).
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosWebpush extends CommonDBTM
{
    public static $rightname = 'plugin_controlecontratos_contract';

    public static function getTypeName($nb = 0)
    {
        return __('Assinatura Web Push', 'controlecontratos');
    }

    /**
     * Persiste (ou atualiza) a assinatura enviada pelo navegador do usuário.
     *
     * @param int    $users_id
     * @param string $endpoint
     * @param string $p256dh
     * @param string $auth
     * @return bool
     */
    public static function saveSubscription($users_id, $endpoint, $p256dh, $auth)
    {
        $sub = new self();
        // Deduplica por endpoint (um navegador = um endpoint).
        if ($sub->getFromDBByCrit(['endpoint' => $endpoint])) {
            return (bool) $sub->update([
                'id'       => $sub->getID(),
                'users_id' => $users_id,
                'p256dh'   => $p256dh,
                'auth'     => $auth,
            ]);
        }

        return (bool) $sub->add([
            'users_id'      => $users_id,
            'endpoint'      => $endpoint,
            'p256dh'        => $p256dh,
            'auth'          => $auth,
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Envia uma notificação Web Push para todas as assinaturas de um usuário.
     *
     * @param int    $users_id
     * @param string $title
     * @param string $body
     * @param string $url
     * @return bool  true se ao menos uma entrega teve sucesso.
     */
    public static function sendToUser($users_id, $title, $body, $url = '')
    {
        /** @var DBmysql $DB */
        global $DB;

        // A biblioteca de assinatura VAPID é opcional — degrada com log se ausente.
        $autoload = Plugin::getPhpDir('controlecontratos') . '/vendor/autoload.php';
        if (!is_file($autoload) || !class_exists(\Minishlink\WebPush\WebPush::class)) {
            Toolbox::logInFile(
                'controlecontratos',
                "Web Push ignorado: instale minishlink/web-push via composer no diretório do plugin.\n"
            );
            return false;
        }

        $publicKey  = PluginControlecontratosConfig::getValue('vapid_public_key');
        $privateKey = PluginControlecontratosConfig::getValue('vapid_private_key');
        $subject    = PluginControlecontratosConfig::getValue('vapid_subject') ?: 'mailto:admin@localhost';

        if ($publicKey === '' || $privateKey === '') {
            return false;
        }

        require_once $autoload;

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => Plugin::getWebDir('controlecontratos', true) . '/pics/icon.png',
        ], JSON_UNESCAPED_UNICODE);

        $subs = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['users_id' => $users_id],
        ]);

        $anyQueued = false;
        foreach ($subs as $row) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'keys'     => [
                    'p256dh' => $row['p256dh'],
                    'auth'   => $row['auth'],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
            $anyQueued = true;
        }

        if (!$anyQueued) {
            return false;
        }

        $ok = false;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $ok = true;
            } elseif ($report->isSubscriptionExpired()) {
                // Remove assinaturas expiradas (endpoint 404/410).
                $DB->delete(self::getTable(), ['endpoint' => $report->getEndpoint()]);
            }
        }

        return $ok;
    }
}
