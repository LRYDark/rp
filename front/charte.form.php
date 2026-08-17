<?php
/**
 * Ajout, modification et suppression des chartes de rapport.
 *
 * Appelé depuis la page de configuration du plugin (onglet « Rapport »).
 * Droit requis : `config` en écriture, comme le reste de la configuration.
 */
include('../../../inc/includes.php');

Session::checkLoginUser();

$plugin = new Plugin();
if (!$plugin->isInstalled('rp') || !$plugin->isActivated('rp')) {
   Html::displayNotFoundError();
}
Session::checkRight('config', UPDATE);

/*
 * Pas de contrôle CSRF supplémentaire ici : sur GLPI 11, un écouteur du noyau
 * valide `_glpi_csrf_token` AVANT même de charger ce fichier, et consomme le
 * jeton au passage. Le revalider échouerait systématiquement.
 * Les formulaires de la liste des chartes postent donc bien ce champ-là.
 */

global $DB;

/** Retour à la page de configuration, sur l'onglet du plugin. */
$rp_charte_back = static function (): void {
   Html::back();
};

/** Couleur hexadécimale, ou la valeur de repli si la saisie est inexploitable. */
$rp_color = static function (?string $value, string $fallback): string {
   $value = trim((string)$value);
   return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $fallback;
};

// ---- Ajout ---------------------------------------------------------------
if (isset($_POST['add_charte'])) {
   $rank = 1;
   $last = $DB->request([
      'SELECT' => ['rank'],
      'FROM'   => 'glpi_plugin_rp_chartes',
      'ORDER'  => ['rank DESC'],
      'LIMIT'  => 1,
   ])->current();
   if ($last) {
      $rank = (int)$last['rank'] + 1;
   }

   $name = trim((string)($_POST['name'] ?? ''));
   if ($name === '') {
      $name = sprintf(__('Charte %d', 'rp'), $rank);
   }

   /*
    * Charte par défaut : demandée par l'utilisateur, ou imposée s'il s'agit de
    * la toute première — sans repli, les entités non rattachées n'auraient
    * aucune charte du tout.
    */
   $is_first   = countElementsInTable('glpi_plugin_rp_chartes') === 0;
   $as_default = $is_first || !empty($_POST['is_default']);

   $ok = $DB->insert('glpi_plugin_rp_chartes', [
      'name'        => $name,
      'entities_id' => (int)($_POST['entities_id'] ?? 0),
      'color_bg'    => $rp_color($_POST['color_bg'] ?? null, '#2980b9'),
      'color_text'  => $rp_color($_POST['color_text'] ?? null, '#ffffff'),
      'line1'       => trim((string)($_POST['line1'] ?? '')),
      'line2'       => trim((string)($_POST['line2'] ?? '')),
      'rank'        => $rank,
      'is_default'  => $as_default ? 1 : 0,
   ]);

   if (!$ok) {
      Session::addMessageAfterRedirect(__("Échec de l'ajout de la charte.", 'rp'), true, ERROR);
      $rp_charte_back();
   }

   $new_id = (int)$DB->insertId();

   // Rôle exclusif : la nouvelle charte le reprend aux autres.
   if ($as_default && !$is_first) {
      $DB->update('glpi_plugin_rp_chartes', ['is_default' => 0], ['NOT' => ['id' => $new_id]]);
   }

   // Logo envoyé avec le reste : la charte est créée ET habillée en une fois.
   $logo_error = PluginRpCharte::storeLogo($new_id, $_FILES['photo'] ?? []);

   Session::addMessageAfterRedirect(__('Charte ajoutée.', 'rp'), true, INFO);
   if ($logo_error !== '') {
      Session::addMessageAfterRedirect($logo_error, true, WARNING);
   }
   $rp_charte_back();
}

// ---- Modification --------------------------------------------------------
if (isset($_POST['update_charte'])) {
   $id = (int)($_POST['id'] ?? 0);
   if ($id <= 0 || PluginRpCharte::getById($id) === null) {
      Session::addMessageAfterRedirect(__('Charte introuvable.', 'rp'), true, ERROR);
      $rp_charte_back();
   }

   $name = trim((string)($_POST['name'] ?? ''));
   if ($name === '') {
      $name = sprintf(__('Charte %d', 'rp'), $id);
   }

   $ok = $DB->update('glpi_plugin_rp_chartes', [
      'name'        => $name,
      'entities_id' => (int)($_POST['entities_id'] ?? 0),
      'color_bg'    => $rp_color($_POST['color_bg'] ?? null, '#2980b9'),
      'color_text'  => $rp_color($_POST['color_text'] ?? null, '#ffffff'),
      'line1'       => trim((string)($_POST['line1'] ?? '')),
      'line2'       => trim((string)($_POST['line2'] ?? '')),
   ], ['id' => $id]);

   // Charte par défaut : exclusive, on retire la marque de toutes les autres.
   if (!empty($_POST['is_default'])) {
      $DB->update('glpi_plugin_rp_chartes', ['is_default' => 0], ['NOT' => ['id' => $id]]);
      $DB->update('glpi_plugin_rp_chartes', ['is_default' => 1], ['id' => $id]);
   }

   // Champ facultatif : sans fichier, le logo actuel est conservé.
   $logo_error = PluginRpCharte::storeLogo($id, $_FILES['photo'] ?? []);

   Session::addMessageAfterRedirect(
      $ok ? __('Charte enregistrée.', 'rp') : __("Échec de l'enregistrement de la charte.", 'rp'),
      true,
      $ok ? INFO : ERROR
   );
   if ($logo_error !== '') {
      Session::addMessageAfterRedirect($logo_error, true, WARNING);
   }
   $rp_charte_back();
}

// ---- Suppression ---------------------------------------------------------
if (isset($_POST['delete_charte'])) {
   $id     = (int)($_POST['id'] ?? 0);
   $charte = $id > 0 ? PluginRpCharte::getById($id) : null;

   if ($charte === null) {
      Session::addMessageAfterRedirect(__('Charte introuvable.', 'rp'), true, ERROR);
      $rp_charte_back();
   }

   // On ne supprime jamais la dernière : sans charte, plus aucun rapport ne
   // pourrait être généré.
   if (countElementsInTable('glpi_plugin_rp_chartes') <= 1) {
      Session::addMessageAfterRedirect(
         __('Impossible de supprimer la dernière charte : les rapports en ont besoin.', 'rp'),
         true,
         ERROR
      );
      $rp_charte_back();
   }

   $was_default = (int)($charte['is_default'] ?? 0) === 1;
   $ok = $DB->delete('glpi_plugin_rp_chartes', ['id' => $id]);

   // La charte par défaut vient de disparaître : la première reprend le rôle,
   // sinon les entités non rattachées se retrouveraient sans repli.
   if ($ok && $was_default) {
      $next = $DB->request([
         'FROM'  => 'glpi_plugin_rp_chartes',
         'ORDER' => ['rank ASC', 'id ASC'],
         'LIMIT' => 1,
      ])->current();
      if ($next) {
         $DB->update('glpi_plugin_rp_chartes', ['is_default' => 1], ['id' => (int)$next['id']]);
      }
   }

   Session::addMessageAfterRedirect(
      $ok ? __('Charte supprimée.', 'rp') : __('Échec de la suppression de la charte.', 'rp'),
      true,
      $ok ? INFO : ERROR
   );
   $rp_charte_back();
}

$rp_charte_back();
