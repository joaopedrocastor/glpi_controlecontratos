<?php
/**
 * -------------------------------------------------------------------------
 * CronTask — verificação diária de vencimentos e disparo multicanal.
 * CONTROLE DE CONTRATOS.
 *
 * Fluxo:
 *   1) Busca contratos próximos do vencimento (janela = alert_days).
 *   2) Para cada canal, lê as preferências de quem optou por recebê-lo.
 *   3) Resgata as credenciais da tabela de configuração (GUI).
 *   4) Dispara os alertas via PluginControlecontratosNotifier.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosCron extends CommonDBTM
{
    /**
     * Descrição exibida na tela de Ações Automáticas do GLPI.
     *
     * @param string $name
     * @return array
     */
    public static function cronInfo($name)
    {
        if ($name === 'contractAlert') {
            return [
                'description' => __('Alerta de vencimento de contratos (multicanal)', 'controlecontratos'),
            ];
        }
        return [];
    }

    /**
     * Rotina executada pelo cron do GLPI.
     *
     * @param CronTask $task
     * @return int  1 = executou com ações, 0 = nada a fazer, -1 = erro.
     */
    public static function cronContractAlert(CronTask $task)
    {
        $config = PluginControlecontratosConfig::getConfig();
        if (empty($config->fields['is_active'])) {
            return 0;
        }

        // Janela ampla (60 dias) — o filtro fino por alert_days é feito por contrato.
        $contracts = PluginControlecontratosContract::getExpiringContracts(60);
        if (count($contracts) === 0) {
            return 0;
        }

        $today          = new DateTime();
        $totalDispatched = 0;

        // Pré-carrega os destinatários por canal (uma única query por canal).
        $recipients = [
            'use_whatsapp' => PluginControlecontratosNotificationpref::getRecipientsForChannel('use_whatsapp'),
            'use_telegram' => PluginControlecontratosNotificationpref::getRecipientsForChannel('use_telegram'),
            'use_teams'    => PluginControlecontratosNotificationpref::getRecipientsForChannel('use_teams'),
            'use_webpush'  => PluginControlecontratosNotificationpref::getRecipientsForChannel('use_webpush'),
        ];

        foreach ($contracts as $contract) {
            $end       = new DateTime($contract['date_end']);
            $daysLeft  = (int) $today->diff($end)->format('%r%a');
            $alertDays = (int) ($contract['alert_days'] ?: 30);

            // Só notifica quando o contrato entra na janela de antecedência configurada.
            if ($daysLeft > $alertDays) {
                continue;
            }

            // Evita reenvio no mesmo dia (idempotência simples via last_alert_date).
            if (!empty($contract['last_alert_date'])
                && date('Y-m-d', strtotime($contract['last_alert_date'])) === date('Y-m-d')
            ) {
                continue;
            }

            $dispatched = self::dispatchAlerts($contract, $daysLeft, $recipients);

            if ($dispatched > 0) {
                self::markAlerted($contract['id']);
                $task->addVolume($dispatched);
                $totalDispatched += $dispatched;
            }
        }

        return $totalDispatched > 0 ? 1 : 0;
    }

    /**
     * Monta as mensagens e dispara em cada canal habilitado.
     *
     * @param array $contract  Linha do contrato.
     * @param int   $daysLeft  Dias restantes (pode ser negativo se vencido).
     * @param array $recipients Destinatários pré-carregados por canal.
     * @return int Quantidade de envios efetuados com sucesso.
     */
    private static function dispatchAlerts(array $contract, $daysLeft, array $recipients)
    {
        $count = 0;

        $situacao = $daysLeft < 0
            ? sprintf(__('VENCIDO há %d dias', 'controlecontratos'), abs($daysLeft))
            : sprintf(__('vence em %d dias', 'controlecontratos'), $daysLeft);

        $valor = Html::formatNumber($contract['value'], false, 2);
        $fim   = Html::convDate($contract['date_end']);

        // --- Texto simples (Telegram/WhatsApp) ---
        $plain = sprintf(
            "🔔 *CONTROLE DE CONTRATOS*\n\n" .
            "Contrato: %s\n" .
            "Fornecedor/Cliente: %s\n" .
            "Término: %s (%s)\n" .
            "Valor: R$ %s",
            $contract['name'],
            $contract['partner'] ?: '-',
            $fim,
            $situacao,
            $valor
        );

        // --- Telegram usa HTML ---
        $telegramMsg = sprintf(
            "🔔 <b>CONTROLE DE CONTRATOS</b>\n\n" .
            "<b>Contrato:</b> %s\n" .
            "<b>Fornecedor/Cliente:</b> %s\n" .
            "<b>Término:</b> %s (%s)\n" .
            "<b>Valor:</b> R$ %s",
            htmlspecialchars($contract['name']),
            htmlspecialchars($contract['partner'] ?: '-'),
            $fim,
            $situacao,
            $valor
        );

        // 1) TEAMS — envia uma vez se houver ao menos um optante (canal de grupo).
        if (count($recipients['use_teams']) > 0) {
            $ok = PluginControlecontratosNotifier::sendTeams(
                sprintf(__('Contrato %s', 'controlecontratos'), $situacao),
                $contract['name'],
                [
                    __('Fornecedor/Cliente', 'controlecontratos') => $contract['partner'] ?: '-',
                    __('Término', 'controlecontratos')            => $fim,
                    __('Valor', 'controlecontratos')              => 'R$ ' . $valor,
                ]
            );
            $count += $ok ? 1 : 0;
        }

        // 2) TELEGRAM — envia ao chat padrão configurado (canal de grupo).
        if (count($recipients['use_telegram']) > 0) {
            $ok = PluginControlecontratosNotifier::sendTelegram($telegramMsg);
            $count += $ok ? 1 : 0;
        }

        // 3) WHATSAPP — envio individual a cada optante que tenha número.
        foreach ($recipients['use_whatsapp'] as $r) {
            if (empty($r['whatsapp_number'])) {
                continue;
            }
            $ok = PluginControlecontratosNotifier::sendWhatsapp($r['whatsapp_number'], $plain);
            $count += $ok ? 1 : 0;
        }

        // 4) WEB PUSH — envio individual a cada optante.
        $url = Plugin::getWebDir('controlecontratos', true) . '/front/contract.form.php?id=' . $contract['id'];
        foreach ($recipients['use_webpush'] as $r) {
            $ok = PluginControlecontratosNotifier::sendWebPush(
                (int) $r['users_id'],
                sprintf(__('Contrato %s', 'controlecontratos'), $situacao),
                $contract['name'] . ' — ' . $fim,
                $url
            );
            $count += $ok ? 1 : 0;
        }

        // 5) E-MAIL — se o contrato tiver "Avisos por e-mail" ligado, envia
        //    ao usuário responsável (respeita a configuração de SMTP do GLPI).
        if (!empty($contract['email_alert']) && !empty($contract['users_id'])) {
            $ok = PluginControlecontratosNotifier::sendEmail(
                (int) $contract['users_id'],
                sprintf(__('Contrato %s: %s', 'controlecontratos'), $situacao, $contract['name']),
                strip_tags(str_replace(['*'], [''], $plain))
            );
            $count += $ok ? 1 : 0;
        }

        return $count;
    }

    /**
     * Marca a data do último alerta enviado para o contrato.
     *
     * @param int $contracts_id
     * @return void
     */
    private static function markAlerted($contracts_id)
    {
        $contract = new PluginControlecontratosContract();
        $contract->update([
            'id'              => $contracts_id,
            'last_alert_date' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            '_disablenotif'   => true,
        ]);
    }
}
