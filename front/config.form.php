<?php
/**
 * Salvamento da configuração das APIs — CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$config = new PluginControlecontratosConfig();

if (isset($_POST['update'])) {
    // Valida o token CSRF explicitamente (defesa em profundidade).
    Session::checkCSRF($_POST);

    $_POST['id'] = $_POST['id'] ?: 1;
    $config->update($_POST);

    Session::addMessageAfterRedirect(__('Configuração salva com sucesso.', 'controlecontratos'));
    Html::back();
} elseif (isset($_POST['test_channels'])) {
    Session::checkCSRF($_POST);

    // Dispara uma mensagem de teste em todos os canais configurados.
    $ok = [];
    $ok['teams']    = PluginControlecontratosNotifier::sendTeams(
        __('Teste de integração', 'controlecontratos'),
        __('Mensagem de teste do CONTROLE DE CONTRATOS.', 'controlecontratos')
    );
    $ok['telegram'] = PluginControlecontratosNotifier::sendTelegram(
        '✅ ' . __('Teste de integração — CONTROLE DE CONTRATOS', 'controlecontratos')
    );

    foreach ($ok as $canal => $result) {
        Session::addMessageAfterRedirect(
            sprintf('%s: %s', ucfirst($canal), $result ? __('OK') : __('Falhou')),
            false,
            $result ? INFO : WARNING
        );
    }
    Html::back();
} else {
    Html::header(
        PluginControlecontratosConfig::getTypeName(),
        $_SERVER['PHP_SELF'],
        'config',
        'plugins'
    );

    $config->showConfigForm();

    Html::footer();
}
