<?php
/**
 * Salvamento das preferências de notificação — CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

$pref = new PluginControlecontratosNotificationpref();

if (isset($_POST['update'])) {
    Session::checkCSRF($_POST);

    // Um usuário só edita a própria preferência (a menos que seja admin).
    $users_id = (int) ($_POST['users_id'] ?? Session::getLoginUserID());
    if ($users_id !== (int) Session::getLoginUserID()
        && !Session::haveRight('plugin_controlecontratos_contract', UPDATE)
    ) {
        Html::displayRightError();
    }

    // Normaliza os toggles ausentes como 0.
    foreach (['use_whatsapp', 'use_telegram', 'use_teams', 'use_webpush'] as $ch) {
        $_POST[$ch] = isset($_POST[$ch]) ? (int) $_POST[$ch] : 0;
    }
    $_POST['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

    $pref->update($_POST);
    Session::addMessageAfterRedirect(__('Preferências salvas.', 'controlecontratos'));
}

Html::back();
