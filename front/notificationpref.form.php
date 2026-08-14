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

    $current = (int) Session::getLoginUserID();
    $isAdmin = Session::haveRight('plugin_controlecontratos_contract', UPDATE);

    // Um usuário comum só edita a própria preferência; admin pode editar de outro.
    $target = (int) ($_POST['users_id'] ?? $current);
    if ($target !== $current && !$isAdmin) {
        Html::displayRightError();
        exit;
    }

    // Resolve o registro pelo usuário-alvo (evita IDOR via campo 'id' forjado).
    $pref = PluginControlecontratosNotificationpref::getForUser($target);

    // Só os campos permitidos são gravados (allowlist) — ignora 'id'/'users_id' forjados.
    $input = [
        'id'       => $pref->getID(),
        'users_id' => $target,
        'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
    ];
    foreach (['use_whatsapp', 'use_telegram', 'use_teams', 'use_webpush'] as $ch) {
        $input[$ch] = isset($_POST[$ch]) ? (int) $_POST[$ch] : 0;
    }
    if (isset($_POST['whatsapp_number'])) {
        $input['whatsapp_number'] = preg_replace('/\D+/', '', (string) $_POST['whatsapp_number']);
    }

    $pref->update($input);
    Session::addMessageAfterRedirect(__('Preferências salvas.', 'controlecontratos'));
}

Html::back();
