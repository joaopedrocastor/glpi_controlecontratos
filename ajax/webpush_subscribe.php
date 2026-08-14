<?php
/**
 * Endpoint AJAX — registra a assinatura Web Push do navegador do usuário.
 * CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

header('Content-Type: application/json');

Session::checkLoginUser();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Valida o token CSRF enviado no header pelo JS.
if (!isset($_SERVER['HTTP_X_GLPI_CSRF_TOKEN'])
    || !Session::validateCSRF(['_glpi_csrf_token' => $_SERVER['HTTP_X_GLPI_CSRF_TOKEN']])
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF']);
    exit;
}

if (empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_subscription']);
    exit;
}

$ok = PluginControlecontratosWebpush::saveSubscription(
    Session::getLoginUserID(),
    $data['endpoint'],
    $data['keys']['p256dh'],
    $data['keys']['auth']
);

echo json_encode(['ok' => (bool) $ok]);
