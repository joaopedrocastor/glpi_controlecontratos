<?php
/**
 * Endpoint AJAX — expõe ao JS a VAPID public key e o token CSRF.
 * (A private key NUNCA é exposta ao navegador.)
 * CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

header('Content-Type: application/json');

Session::checkLoginUser();

echo json_encode([
    'publicKey' => PluginControlecontratosConfig::getValue('vapid_public_key'),
    'csrf'      => Session::getNewCSRFToken(),
    'enabled'   => PluginControlecontratosConfig::getValue('vapid_public_key') !== '',
]);
