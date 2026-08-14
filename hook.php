<?php
/**
 * -------------------------------------------------------------------------
 * Rotinas de instalação/desinstalação e hooks de exibição.
 * Plugin CONTROLE DE CONTRATOS — GLPI 10+.
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

/**
 * Rotina nativa de instalação do plugin.
 * Cria as tabelas de Contratos, Preferências de Notificação e Configuração das APIs.
 *
 * @return bool
 */
function plugin_controlecontratos_install()
{
    /** @var DBmysql $DB */
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // ---------------------------------------------------------------------
    // 1) Tabela principal de contratos.
    // ---------------------------------------------------------------------
    $table = 'glpi_plugin_controlecontratos_contracts';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id`               int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `entities_id`      int {$default_key_sign} NOT NULL DEFAULT '0',
            `is_recursive`     tinyint NOT NULL DEFAULT '0',
            `name`             varchar(255) DEFAULT NULL,
            `kind`             varchar(20) NOT NULL DEFAULT 'contract' COMMENT 'contract|license — diferencia Contrato de Licença',
            `contracttypes_id` int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'FK opcional para a dropdown nativa glpi_contracttypes (subtipo)',
            `partner`          varchar(255) DEFAULT NULL COMMENT 'Fornecedor / Cliente',
            `suppliers_id`     int {$default_key_sign} NOT NULL DEFAULT '0',
            `date_begin`       date DEFAULT NULL,
            `date_end`         date DEFAULT NULL,
            `value`            decimal(20,4) NOT NULL DEFAULT '0.0000',
            `status`           varchar(50) NOT NULL DEFAULT 'active' COMMENT 'active|expired|canceled|renewed',
            `periodicity`      int NOT NULL DEFAULT '12' COMMENT 'Periodicidade/duração do contrato em meses',
            `alert_days`       int NOT NULL DEFAULT '90' COMMENT 'Antecedência do aviso de término, em dias',
            `email_alert`      tinyint NOT NULL DEFAULT '0' COMMENT 'Enviar também aviso por e-mail',
            `last_alert_date`  timestamp NULL DEFAULT NULL,
            `comment`          text,
            `users_id`         int {$default_key_sign} NOT NULL DEFAULT '0',
            `is_deleted`       tinyint NOT NULL DEFAULT '0',
            `date_creation`    timestamp NULL DEFAULT NULL,
            `date_mod`         timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `is_deleted` (`is_deleted`),
            KEY `date_end` (`date_end`),
            KEY `status` (`status`),
            KEY `kind` (`kind`),
            KEY `contracttypes_id` (`contracttypes_id`),
            KEY `suppliers_id` (`suppliers_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());
    }

    // ---------------------------------------------------------------------
    // 1b) Migração idempotente — adiciona colunas novas em instalações antigas.
    //     Roda com segurança tanto na 1ª instalação quanto no "Atualizar".
    // ---------------------------------------------------------------------
    $migrations = [
        'periodicity' => "ALTER TABLE `$table` ADD COLUMN `periodicity` int NOT NULL DEFAULT '12' COMMENT 'Periodicidade/duração do contrato em meses' AFTER `status`",
        'email_alert' => "ALTER TABLE `$table` ADD COLUMN `email_alert` tinyint NOT NULL DEFAULT '0' COMMENT 'Enviar também aviso por e-mail' AFTER `alert_days`",
    ];
    foreach ($migrations as $field => $alter) {
        if (!$DB->fieldExists($table, $field)) {
            $DB->doQueryOrDie($alter, $DB->error());
        }
    }

    // ---------------------------------------------------------------------
    // 2) Tabela de preferências de notificação (por usuário e/ou contrato).
    //    Guarda quais canais estão habilitados para cada alvo.
    // ---------------------------------------------------------------------
    $table = 'glpi_plugin_controlecontratos_notificationprefs';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id`               int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `users_id`         int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'Usuário destinatário',
            `use_whatsapp`     tinyint NOT NULL DEFAULT '0',
            `use_telegram`     tinyint NOT NULL DEFAULT '0',
            `use_teams`        tinyint NOT NULL DEFAULT '0',
            `use_webpush`      tinyint NOT NULL DEFAULT '0',
            `whatsapp_number`  varchar(30) DEFAULT NULL COMMENT 'Número no formato E.164 do destinatário',
            `date_mod`         timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());
    }

    // ---------------------------------------------------------------------
    // 3) Tabela de configuração das APIs (uma única linha, id=1).
    //    Nenhuma credencial fica hardcoded — tudo salvo aqui pela GUI.
    // ---------------------------------------------------------------------
    $table = 'glpi_plugin_controlecontratos_configs';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id`                    int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `evolution_url`         varchar(255) DEFAULT NULL COMMENT 'URL base da Evolution API',
            `evolution_apikey`      varchar(255) DEFAULT NULL,
            `evolution_instance`    varchar(255) DEFAULT NULL COMMENT 'Nome da instância WhatsApp',
            `telegram_token`        varchar(255) DEFAULT NULL,
            `telegram_chatid`       varchar(100) DEFAULT NULL,
            `teams_webhook`         text COMMENT 'URL do Incoming Webhook do Teams',
            `vapid_public_key`      text,
            `vapid_private_key`     text,
            `vapid_subject`         varchar(255) DEFAULT NULL COMMENT 'mailto: do responsável',
            `is_active`             tinyint NOT NULL DEFAULT '1',
            `date_mod`              timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());

        // Cria a linha única de configuração (singleton id=1).
        $DB->insert($table, ['id' => 1, 'is_active' => 1, 'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')]);
    }

    // ---------------------------------------------------------------------
    // 4) Tabela de assinaturas Web Push (endpoint + chaves do navegador).
    // ---------------------------------------------------------------------
    $table = 'glpi_plugin_controlecontratos_webpushsubscriptions';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id`          int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `users_id`    int {$default_key_sign} NOT NULL DEFAULT '0',
            `endpoint`    text NOT NULL,
            `p256dh`      varchar(255) NOT NULL,
            `auth`        varchar(255) NOT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());
    }

    // ---------------------------------------------------------------------
    // 5) Direitos de perfil — sem isso o objeto fica inacessível na interface.
    //    Concede acesso total ao perfil atual (Super-Admin durante a instalação).
    // ---------------------------------------------------------------------
    $right = 'plugin_controlecontratos_contract';
    ProfileRight::addProfileRights([$right]);
    if (isset($_SESSION['glpiactiveprofile']['id'])) {
        $DB->update(
            'glpi_profilerights',
            ['rights' => ALLSTANDARDRIGHT],
            [
                'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                'name'        => $right,
            ]
        );
        // Reflete o direito na sessão atual para uso imediato pós-instalação.
        $_SESSION['glpiactiveprofile'][$right] = ALLSTANDARDRIGHT;
    }

    // ---------------------------------------------------------------------
    // 6) Colunas padrão da listagem (display preferences).
    //    Sem isso a busca só mostra a coluna "Nome". Os números são os IDs
    //    das opções de pesquisa definidas em rawSearchOptions().
    //    users_id=0 => padrão para todos os usuários sem preferência própria.
    // ---------------------------------------------------------------------
    $itemtype   = 'PluginControlecontratosContract';
    $columns     = [
        7 => 1,  // Tipo (Contrato/Licença)
        2 => 2,  // Fornecedor
        3 => 3,  // Data de início
        4 => 4,  // Data de término
        6 => 5,  // Status
    ];
    foreach ($columns as $num => $rank) {
        $already = $DB->request([
            'FROM'  => 'glpi_displaypreferences',
            'WHERE' => ['itemtype' => $itemtype, 'num' => $num, 'users_id' => 0],
        ])->count();
        if (!$already) {
            $DB->insert('glpi_displaypreferences', [
                'itemtype' => $itemtype,
                'num'      => $num,
                'rank'     => $rank,
                'users_id' => 0,
            ]);
        }
    }

    // Registra a CronTask de verificação diária de vencimentos.
    CronTask::register(
        'PluginControlecontratosCron',
        'contractAlert',
        DAY_TIMESTAMP,
        [
            'comment'  => 'Verifica contratos próximos do vencimento e dispara os alertas multicanais',
            'mode'     => CronTask::MODE_EXTERNAL,
            'state'    => CronTask::STATE_WAITING,
            'hourmin'  => 6,
            'hourmax'  => 8,
        ]
    );

    return true;
}

/**
 * Rotina nativa de desinstalação — remove tabelas e a CronTask.
 *
 * @return bool
 */
function plugin_controlecontratos_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    $tables = [
        'glpi_plugin_controlecontratos_contracts',
        'glpi_plugin_controlecontratos_notificationprefs',
        'glpi_plugin_controlecontratos_configs',
        'glpi_plugin_controlecontratos_webpushsubscriptions',
    ];
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQueryOrDie("DROP TABLE `$table`", $DB->error());
        }
    }

    // Remove os direitos de perfil criados na instalação.
    ProfileRight::deleteProfileRights(['plugin_controlecontratos_contract']);

    // Remove as preferências de exibição (colunas padrão da lista).
    $DB->delete('glpi_displaypreferences', ['itemtype' => 'PluginControlecontratosContract']);

    // Remove a CronTask registrada.
    CronTask::unregister('controlecontratos');

    return true;
}

/**
 * Hook display_central / display_header — injeta o "Sino" de notificações
 * na barra superior do GLPI com badge de contagem e dropdown de contratos.
 *
 * @return void
 */
function plugin_controlecontratos_display_header()
{
    if (!Session::getLoginUserID()) {
        return;
    }
    PluginControlecontratosContract::showNotificationBell();
}
