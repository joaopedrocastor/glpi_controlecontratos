<?php
/**
 * Dispensa o sino de notificações até o próximo login.
 * Marca uma flag na sessão; showNotificationBell() passa a não renderizar.
 * CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

// Flag vive apenas nesta sessão — no próximo login some, e o sino volta.
$_SESSION['plugin_controlecontratos_bell_dismissed'] = 1;

// Volta para a página de onde o usuário clicou.
Html::back();
