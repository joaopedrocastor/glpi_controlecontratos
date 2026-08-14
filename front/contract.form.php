<?php
/**
 * Formulário (add/update/delete) de contrato — CONTROLE DE CONTRATOS.
 * @author João Pedro Castor Quirino
 */

include('../../../inc/includes.php');

$contract = new PluginControlecontratosContract();

// Valida o token CSRF em toda operação que altera dados (defesa em profundidade).
if (isset($_POST['add'])) {
    Session::checkCSRF($_POST);
    $contract->check(-1, CREATE, $_POST);
    $newID = $contract->add($_POST);
    Html::redirect(PluginControlecontratosContract::getFormURLWithID($newID));
} elseif (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    $contract->check($_POST['id'], UPDATE);
    $contract->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    Session::checkCSRF($_POST);
    $contract->check($_POST['id'], PURGE);
    $contract->delete($_POST, true);
    $contract->redirectToList();
} elseif (isset($_POST['delete'])) {
    Session::checkCSRF($_POST);
    $contract->check($_POST['id'], DELETE);
    $contract->delete($_POST);
    Html::back();
} else {
    // Exibição do formulário.
    $ID = (int) ($_GET['id'] ?? 0);

    Html::header(
        PluginControlecontratosContract::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'management',
        'PluginControlecontratosMenu',
        'contract'
    );

    $contract->display(['id' => $ID]);

    Html::footer();
}
