<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Préférences personnelles du plugin RP (onglet des Préférences GLPI).
 *
 * Chaque utilisateur peut choisir où s'affichent les boutons flottants :
 *   MODE_MOBILE (défaut) : sur téléphone uniquement
 *   MODE_ALWAYS          : partout (mobile et ordinateur)
 *   MODE_NEVER           : nulle part
 *
 * L'absence de ligne en base vaut MODE_MOBILE : aucune donnée à migrer pour
 * les comptes existants. Les droits de profil (`plugin_rp_boutons`) restent
 * prioritaires : une préférence ne peut pas donner un accès non autorisé.
 */
class PluginRpUserpref extends CommonDBTM {

   const MODE_NEVER  = 0;
   const MODE_MOBILE = 1;
   const MODE_ALWAYS = 2;

   static $rightname = "plugin_rp_boutons";

   /** @var array<int,array> cache par utilisateur */
   private static $cache = [];

   static function getTypeName($nb = 0) {
      return __('Rapport', 'rp');
   }

   static function getIcon() {
      return "fa-solid fa-file";
   }

   static function getModes(): array {
      return [
         self::MODE_MOBILE => __('Sur mobile uniquement (recommandé)', 'rp'),
         self::MODE_ALWAYS => __('Toujours (mobile et ordinateur)', 'rp'),
         self::MODE_NEVER  => __('Jamais', 'rp'),
      ];
   }

   /**
    * Préférences d'un utilisateur, valeurs par défaut comprises.
    *
    * @return array{fab_home:int,fab_ticket:int}
    */
   static function getForUser(?int $users_id = null): array {
      global $DB;

      $users_id = $users_id ?? (int)Session::getLoginUserID();
      if (isset(self::$cache[$users_id])) {
         return self::$cache[$users_id];
      }

      $prefs = ['fab_home' => self::MODE_MOBILE, 'fab_ticket' => self::MODE_MOBILE];

      if ($users_id > 0 && $DB->tableExists('glpi_plugin_rp_userprefs')) {
         $row = $DB->request([
            'FROM'  => 'glpi_plugin_rp_userprefs',
            'WHERE' => ['users_id' => $users_id],
            'LIMIT' => 1,
         ])->current();
         if ($row) {
            foreach (['fab_home', 'fab_ticket'] as $field) {
               $value = (int)($row[$field] ?? self::MODE_MOBILE);
               if (in_array($value, [self::MODE_NEVER, self::MODE_MOBILE, self::MODE_ALWAYS], true)) {
                  $prefs[$field] = $value;
               }
            }
         }
      }

      return self::$cache[$users_id] = $prefs;
   }

   /**
    * Enregistre les préférences de l'utilisateur connecté.
    */
   static function saveForUser(array $input): bool {
      global $DB;

      $users_id = (int)Session::getLoginUserID();
      if ($users_id <= 0 || !$DB->tableExists('glpi_plugin_rp_userprefs')) {
         return false;
      }

      $data = [];
      foreach (['fab_home', 'fab_ticket'] as $field) {
         $value = (int)($input[$field] ?? self::MODE_MOBILE);
         if (!in_array($value, [self::MODE_NEVER, self::MODE_MOBILE, self::MODE_ALWAYS], true)) {
            $value = self::MODE_MOBILE;
         }
         $data[$field] = $value;
      }

      $existing = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => 'glpi_plugin_rp_userprefs',
         'WHERE'  => ['users_id' => $users_id],
         'LIMIT'  => 1,
      ])->current();

      unset(self::$cache[$users_id]);

      if ($existing) {
         return (bool)$DB->update('glpi_plugin_rp_userprefs', $data, ['id' => (int)$existing['id']]);
      }
      $data['users_id'] = $users_id;
      return (bool)$DB->insert('glpi_plugin_rp_userprefs', $data);
   }

   /**
    * Mode effectif d'un bouton : croise le droit de profil et la préférence.
    * Renvoie MODE_NEVER si le droit n'est pas accordé.
    */
   static function getEffectiveMode(string $button): int {
      $right = ($button === 'fab_home') ? READ : UPDATE;
      if (!Session::haveRight('plugin_rp_boutons', $right)) {
         return self::MODE_NEVER;
      }
      $prefs = self::getForUser();
      return (int)($prefs[$button] ?? self::MODE_MOBILE);
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() === 'Preference'
          && Session::haveRightsOr('plugin_rp_boutons', [READ, UPDATE])) {
         return self::createTabEntry(__('Rapport', 'rp'), 0, null, self::getIcon());
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() === 'Preference') {
         self::showPreferencesForm();
      }
      return true;
   }

   /**
    * Formulaire des préférences (composants natifs GLPI).
    */
   static function showPreferencesForm(): void {
      $prefs   = self::getForUser();
      $modes   = self::getModes();
      $can_home   = Session::haveRight('plugin_rp_boutons', READ);
      $can_ticket = Session::haveRight('plugin_rp_boutons', UPDATE);

      echo "<form method='post' action='" . PLUGIN_RP_WEBDIR . "/front/userpref.form.php'>";
      // Jeton autonome dédié, comme l'écran de configuration du plugin : le
      // formulaire est rendu dans un onglet chargé en AJAX, et Html::closeForm()
      // ajoute déjà son propre `_glpi_csrf_token` (pas de doublon de champ).
      echo Html::hidden('plugin_rp_userpref_csrf_token', ['value' => Session::getNewCSRFToken(true)]);

      echo "<div class='card mb-3'>";
      echo "<div class='card-header'><h3 class='card-title'>" . __('Boutons flottants', 'rp') . "</h3></div>";
      echo "<div class='card-body'>";
      echo "<p class='text-muted'>"
         . __("Ces boutons donnent un accès rapide au scan et aux signatures. Ils sont pensés pour le téléphone : par défaut ils n'apparaissent pas sur ordinateur.", 'rp')
         . "</p>";

      echo "<div class='row'>";

      if ($can_home) {
         echo "<div class='col-md-6 mb-3'>";
         echo "<label class='form-label'><i class='ti ti-scan me-1'></i>"
            . __("Bouton de scan sur la page d'accueil", 'rp') . "</label>";
         Dropdown::showFromArray('fab_home', $modes, [
            'value' => $prefs['fab_home'],
            'width' => '100%',
         ]);
         echo "</div>";
      }

      if ($can_ticket) {
         echo "<div class='col-md-6 mb-3'>";
         echo "<label class='form-label'><i class='ti ti-signature me-1'></i>"
            . __('Bouton de signature sur les tickets', 'rp') . "</label>";
         Dropdown::showFromArray('fab_ticket', $modes, [
            'value' => $prefs['fab_ticket'],
            'width' => '100%',
         ]);
         echo "</div>";
      }

      echo "</div>"; // row

      if (!$can_home && !$can_ticket) {
         echo "<div class='alert alert-info mb-0'>"
            . __("Aucun bouton flottant n'est autorisé par votre profil.", 'rp')
            . "</div>";
      }

      echo "</div>"; // card-body

      if ($can_home || $can_ticket) {
         echo "<div class='card-footer text-end'>";
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update_rp_prefs', 'class' => 'btn btn-primary']);
         echo "</div>";
      }

      echo "</div>"; // card
      Html::closeForm();
   }
}
