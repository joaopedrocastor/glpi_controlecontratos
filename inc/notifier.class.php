<?php
/**
 * -------------------------------------------------------------------------
 * Envio de notificações multicanais — CONTROLE DE CONTRATOS.
 * Cada método resgata as credenciais da tabela de configuração (GUI)
 * e dispara a requisição HTTP correspondente via cURL.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosNotifier
{
    /**
     * Helper genérico de POST via cURL com timeout e headers.
     *
     * @param string $url
     * @param string|array $body     Corpo (string JSON ou array form-encoded).
     * @param array  $headers        Headers HTTP extras.
     * @param bool   $isJson         Se true, envia como JSON.
     * @return array{ok:bool, http_code:int, response:string, error:string}
     */
    private static function httpPost($url, $body, array $headers = [], $isJson = true)
    {
        $ch = curl_init($url);

        $payload = $isJson ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $body;

        if ($isJson) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $ok = ($error === '' && $httpCode >= 200 && $httpCode < 300);

        if (!$ok) {
            Toolbox::logInFile(
                'controlecontratos',
                sprintf("Falha no POST para %s (HTTP %d): %s\n", $url, $httpCode, $error ?: $response)
            );
        }

        return [
            'ok'        => $ok,
            'http_code' => $httpCode,
            'response'  => (string) $response,
            'error'     => $error,
        ];
    }

    // ---------------------------------------------------------------------
    // MICROSOFT TEAMS — Incoming Webhook (MessageCard JSON).
    // ---------------------------------------------------------------------
    /**
     * @param string $title
     * @param string $message
     * @param array  $facts   Pares chave/valor exibidos como "facts".
     * @return bool
     */
    public static function sendTeams($title, $message, array $facts = [])
    {
        $webhook = PluginControlecontratosConfig::getValue('teams_webhook');
        if ($webhook === '') {
            return false;
        }

        $factList = [];
        foreach ($facts as $name => $value) {
            $factList[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        $card = [
            '@type'      => 'MessageCard',
            '@context'   => 'http://schema.org/extensions',
            'themeColor' => 'D93025',
            'summary'    => $title,
            'sections'   => [[
                'activityTitle'    => $title,
                'activitySubtitle' => 'CONTROLE DE CONTRATOS',
                'text'             => $message,
                'facts'            => $factList,
                'markdown'         => true,
            ]],
        ];

        $res = self::httpPost($webhook, $card, [], true);
        return $res['ok'];
    }

    // ---------------------------------------------------------------------
    // TELEGRAM — API nativa de bots (sendMessage).
    // ---------------------------------------------------------------------
    /**
     * @param string      $message Texto (aceita HTML do Telegram).
     * @param string|null $chatId  Sobrescreve o chat_id padrão da config.
     * @return bool
     */
    public static function sendTelegram($message, $chatId = null)
    {
        $token  = PluginControlecontratosConfig::getValue('telegram_token');
        $chatId = $chatId ?: PluginControlecontratosConfig::getValue('telegram_chatid');

        if ($token === '' || $chatId === '') {
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $body = [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $res = self::httpPost($url, $body, [], true);
        return $res['ok'];
    }

    // ---------------------------------------------------------------------
    // WHATSAPP — Evolution API (padrão de disparo semelhante ao nextool).
    // Endpoint: POST {baseUrl}/message/sendText/{instance} com header apikey.
    // ---------------------------------------------------------------------
    /**
     * @param string $number  Número destino em E.164 (ex.: 5511999998888).
     * @param string $message Texto da mensagem.
     * @return bool
     */
    public static function sendWhatsapp($number, $message)
    {
        $baseUrl  = PluginControlecontratosConfig::getValue('evolution_url');
        $apiKey   = PluginControlecontratosConfig::getValue('evolution_apikey');
        $instance = PluginControlecontratosConfig::getValue('evolution_instance');

        if ($baseUrl === '' || $apiKey === '' || $instance === '' || $number === '') {
            return false;
        }

        // Normaliza o número (apenas dígitos).
        $number = preg_replace('/\D+/', '', $number);

        $url = "{$baseUrl}/message/sendText/" . rawurlencode($instance);

        // Formato de payload da Evolution API v2.
        $body = [
            'number'      => $number,
            'text'        => $message,
            'options'     => [
                'delay'    => 1200,
                'presence' => 'composing',
            ],
        ];

        $headers = ['apikey: ' . $apiKey];

        $res = self::httpPost($url, $body, $headers, true);
        return $res['ok'];
    }

    // ---------------------------------------------------------------------
    // E-MAIL — usa o mailer nativo do GLPI (respeita a config de SMTP).
    // ---------------------------------------------------------------------
    /**
     * @param int    $users_id Destinatário (usuário responsável pelo contrato).
     * @param string $subject
     * @param string $body     Texto simples.
     * @return bool
     */
    public static function sendEmail($users_id, $subject, $body)
    {
        if (!class_exists('GLPIMailer')) {
            return false;
        }

        $user = new User();
        if (!$user->getFromDB($users_id)) {
            return false;
        }
        $address = $user->getDefaultEmail();
        if (empty($address)) {
            return false;
        }

        try {
            $mailer = new GLPIMailer();
            $mailer->getEmail()
                ->to($address)
                ->subject($subject)
                ->text($body);
            return (bool) $mailer->send();
        } catch (\Throwable $e) {
            Toolbox::logInFile('controlecontratos', 'Falha no envio de e-mail: ' . $e->getMessage() . "\n");
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // WEB PUSH — entrega às assinaturas do navegador.
    // Requer a lib web-push (minishlink/web-push) via composer para a
    // assinatura VAPID. Aqui delegamos à classe dedicada.
    // ---------------------------------------------------------------------
    /**
     * @param int    $users_id
     * @param string $title
     * @param string $message
     * @param string $url      URL de destino ao clicar na notificação.
     * @return bool
     */
    public static function sendWebPush($users_id, $title, $message, $url = '')
    {
        return PluginControlecontratosWebpush::sendToUser($users_id, $title, $message, $url);
    }
}
