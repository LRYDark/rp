<?php
/**
 * Fiche d'une ligne du tableau des rapports (front/report.php).
 * Lecture : droit `plugin_rp_liste` READ ; modification : UPDATE ; purge : PURGE.
 */
include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('plugin_rp_liste', READ);

$detail = new PluginRpCriDetail();

if (isset($_POST['update'])) {
   $detail->check((int)($_POST['id'] ?? 0), UPDATE);
   // seuls ces champs sont modifiables depuis la fiche : le document signé
   // et les liens ticket/document ne sont jamais altérés
   $detail->update([
      'id'         => (int)$_POST['id'],
      'nameclient' => (string)($_POST['nameclient'] ?? ''),
      'email'      => (string)($_POST['email'] ?? ''),
      'send_mail'  => (int)($_POST['send_mail'] ?? 0),
   ]);
   Html::back();
} else if (isset($_POST['purge'])) {
   $detail->check((int)($_POST['id'] ?? 0), PURGE);
   $detail->delete(['id' => (int)$_POST['id']], 1);
   $detail->redirectToList();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
   Html::redirect(PLUGIN_RP_WEBDIR . '/front/report.php');
}

Html::header(
   __('Rapport PDF', 'rp'),
   $_SERVER['PHP_SELF'],
   'management',
   'PluginRpCriDetail'
);

$detail->display(['id' => $id]);

Html::footer();
