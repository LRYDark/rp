<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Gestion individuelle des accès aux fonctionnalités du plugin RP.
 *
 * Chaque fonctionnalité ("feature") est associée à un droit de profil GLPI qui
 * reste le comportement par défaut. Une règle stockée dans
 * `glpi_plugin_rp_accessrules` peut la surcharger par utilisateur :
 *   mode 0 : droits du profil GLPI uniquement (comportement historique)
 *   mode 1 : les utilisateurs listés ont accès MÊME sans le droit de profil,
 *            les autres suivent leur profil
 *   mode 2 : les utilisateurs listés n'ont PAS accès MÊME avec le droit de profil,
 *            les autres suivent leur profil
 * Liste vide ou règle absente => comportement profil, quel que soit le mode.
 *
 * Certaines fonctionnalités restent des droits de PROFIL uniquement
 * (`per_user => false` dans getFeatures()) : aucune règle par utilisateur
 * n'est proposée ni appliquée pour elles — canUse() n'y regarde que le profil.
 *
 * Toute vérification d'accès du plugin (boutons, pages, AJAX, POST, API,
 * pages mobiles) DOIT passer par PluginRpAccess::canUse() / checkUse().
 */
class PluginRpAccess {

   const MODE_PROFILE = 0;
   const MODE_ALLOW   = 1;
   const MODE_DENY    = 2;

   /** @var array<string,array|null> cache des règles par feature */
   private static $rules_cache = [];

   /** @var bool|null cache d'existence de la table (mise à jour plugin pas encore jouée) */
   private static $table_ok = null;

   /**
    * Fonctionnalités couvertes : feature => [libellé, droit profil de repli, niveau par défaut]
    */
   static function getFeatures(): array {
      return [
         /*
          * Fiche (type 0) et rapport d'intervention (type 1) ont longtemps
          * partagé un seul droit. Ils sont désormais séparés : deux droits de
          * profil, deux règles. La reprise des valeurs existantes est faite
          * par PluginRpProfile::migrateSplitRights(), sans migration à jouer.
          */
         'fiche'           => ['label' => __('Fiche de prise en charge', 'rp'),
                               'right' => 'plugin_rp_fiche',
                               'level' => CREATE],
         'rapport_tech'    => ['label' => __("Rapport d'intervention", 'rp'),
                               'right' => 'plugin_rp_rapport_tech',
                               'level' => CREATE],
         'rapport_hotline' => ['label' => __('Rapport hotline', 'rp'),
                               'right' => 'plugin_rp_rapport_hotline',
                               'level' => CREATE],
         'preparation'     => ['label' => __("Rapport d'atelier", 'rp'),
                               'right' => 'plugin_rp_rapport_preparation',
                               'level' => CREATE],
         /*
          * Interface mobile et partage du lien : longtemps ouverts par le
          * rapport d'intervention en création, ils ont chacun leur droit de
          * profil (une case Lecture). Sans cela, ouvrir la page mobile à
          * beaucoup de monde obligeait à lister chaque utilisateur dans les
          * règles individuelles. Reprise automatique : voir
          * PluginRpProfile::migrateSplitRights().
          */
         'mobile'          => ['label' => __('Interface mobile (QR code)', 'rp'),
                               'right' => 'plugin_rp_mobile',
                               'level' => READ],
         /*
          * Emettre le lien mobile d'un ticket depuis sa fiche.
          *
          * Distinct de 'mobile', qui gouverne l'USAGE de la page : un
          * technicien la consulte sans forcement avoir a la diffuser, et
          * l'inverse se defend aussi. Surtout, ce droit ferme la
          * fonctionnalite d'un coup — sans personne autorise, le bouton
          * n'existe nulle part.
          */
         'lien_rapide'     => ['label' => __('Partage du lien mobile depuis le ticket', 'rp'),
                               'right' => 'plugin_rp_lien_mobile',
                               'level' => READ],
         /*
          * Droit de PROFIL uniquement : l'export massif est une action
          * d'exploitation, ouverte ou fermée par profil, jamais utilisateur
          * par utilisateur. Aucune règle individuelle n'est proposée.
          */
         'massif'          => ['label' => __('Export massif Rapport PDF', 'rp'),
                               'right' => 'plugin_rp_pdf',
                               'level' => CREATE,
                               'per_user' => false],
         /*
          * Supervision : voir les rapports d'atelier restés sans rapport
          * d'intervention. Elle expose ce qui n'a PAS été fait — donc réservée,
          * et gouvernée par DEUX leviers, de profil uniquement :
          *   - le droit de profil `plugin_rp_supervision`, fermé par défaut ;
          *   - le super-administrateur, qui y accède toujours (cf. canSupervise).
          * Pas de règle par utilisateur : l'encadrement se désigne par profil.
          */
         'supervision'     => ['label' => __('Supervision des rapports en attente', 'rp'),
                               'right' => 'plugin_rp_supervision',
                               'level' => READ,
                               'per_user' => false],
      ];
   }

   /**
    * Fonctionnalités surchargeables utilisateur par utilisateur : les seules
    * proposées dans la carte « Accès individuels » de la configuration, et
    * les seules pour lesquelles une règle est enregistrée.
    */
   static function getPerUserFeatures(): array {
      return array_filter(self::getFeatures(), static function (array $data): bool {
         return ($data['per_user'] ?? true) !== false;
      });
   }

   /**
    * Accès à la supervision.
    *
    * Raccourci volontaire : le super-administrateur — celui qui peut modifier la
    * configuration de GLPI — y accède sans qu'on ait à lui ouvrir un droit de
    * plus. Sans cela, la fonction resterait invisible à celui-là même qui doit
    * l'attribuer aux autres.
    */
   static function canSupervise(): bool {
      return Session::haveRight('config', UPDATE) || self::canUse('supervision', READ);
   }

   private static function tableExists(): bool {
      global $DB;
      if (self::$table_ok === null) {
         self::$table_ok = $DB->tableExists('glpi_plugin_rp_accessrules');
      }
      return self::$table_ok;
   }

   /**
    * Règle d'une fonctionnalité : ['mode' => int, 'users' => int[]] ou null si absente.
    */
   static function getRule(string $feature): ?array {
      global $DB;

      if (array_key_exists($feature, self::$rules_cache)) {
         return self::$rules_cache[$feature];
      }

      $rule = null;
      if (self::tableExists()) {
         $row = $DB->request([
            'FROM'  => 'glpi_plugin_rp_accessrules',
            'WHERE' => ['feature' => $feature],
            'LIMIT' => 1,
         ])->current();
         if ($row) {
            $users = json_decode((string)($row['users'] ?? '[]'), true);
            if (!is_array($users)) {
               $users = [];
            }
            $rule = [
               'mode'  => (int)$row['mode'],
               'users' => array_values(array_map('intval', $users)),
            ];
         }
      }

      return self::$rules_cache[$feature] = $rule;
   }

   /**
    * L'utilisateur peut-il utiliser la fonctionnalité ?
    *
    * @param string   $feature  clé de self::getFeatures()
    * @param int|null $level    niveau de droit exigé côté profil (READ/CREATE/...),
    *                           défaut : niveau déclaré pour la feature
    * @param int|null $users_id défaut : utilisateur connecté (JAMAIS un id venant du POST)
    */
   static function canUse(string $feature, ?int $level = null, ?int $users_id = null): bool {
      $features = self::getFeatures();
      if (!isset($features[$feature])) {
         // feature inconnue : ne jamais ouvrir d'accès par erreur de clé
         return false;
      }

      $users_id = $users_id ?? (int)Session::getLoginUserID();
      if ($users_id <= 0) {
         return false;
      }

      $level       = $level ?? $features[$feature]['level'];
      $has_profile = (bool)Session::haveRight($features[$feature]['right'], $level);

      if (($features[$feature]['per_user'] ?? true) === false) {
         // Droit de profil pur : aucune règle individuelle ne s'applique,
         // même s'il en reste une en base d'une version antérieure.
         return $has_profile;
      }

      $rule = self::getRule($feature);
      if ($rule === null || $rule['mode'] === self::MODE_PROFILE || count($rule['users']) === 0) {
         return $has_profile;
      }

      $listed = in_array($users_id, $rule['users'], true);
      if ($rule['mode'] === self::MODE_ALLOW) {
         return $listed ? true : $has_profile;
      }
      if ($rule['mode'] === self::MODE_DENY) {
         return $listed ? false : $has_profile;
      }
      return $has_profile;
   }

   /**
    * Variante bloquante pour les pages web : 403 GLPI et arrêt.
    */
   static function checkUse(string $feature, ?int $level = null): void {
      if (!self::canUse($feature, $level)) {
         Html::displayRightError();
         exit;
      }
   }

   /**
    * Variante bloquante pour l'AJAX / POST plein-page : 403 texte et arrêt
    * (pas de page d'erreur HTML complète dans une réponse de modal).
    */
   static function checkUseAjax(string $feature, ?int $level = null): void {
      if (!self::canUse($feature, $level)) {
         http_response_code(403);
         echo __("Vous n'avez pas les droits requis pour cette action (règle d'accès du plugin RP).", 'rp');
         exit;
      }
   }

   /**
    * Enregistre les règles depuis le POST de l'écran de configuration.
    * Champs attendus : rp_access_mode_<feature> et rp_access_users_<feature>[].
    *
    * @return bool true si au moins une règle a été traitée
    */
   static function saveFromPost(array $post): bool {
      global $DB;

      if (!self::tableExists() || !isset($post['rp_access_save'])) {
         return false;
      }

      foreach (self::getFeatures() as $feature => $data) {
         if (($data['per_user'] ?? true) === false) {
            // Droit de profil pur : rien à enregistrer, et une règle laissée
            // par une version antérieure est effacée au passage.
            $DB->delete('glpi_plugin_rp_accessrules', ['feature' => $feature]);
            unset(self::$rules_cache[$feature]);
            continue;
         }
         $mode  = (int)($post['rp_access_mode_' . $feature] ?? self::MODE_PROFILE);
         if (!in_array($mode, [self::MODE_PROFILE, self::MODE_ALLOW, self::MODE_DENY], true)) {
            $mode = self::MODE_PROFILE;
         }
         $users = $post['rp_access_users_' . $feature] ?? [];
         if (!is_array($users)) {
            $users = [$users];
         }
         $users = array_values(array_unique(array_filter(array_map('intval', $users), function ($id) {
            return $id > 0;
         })));

         $json     = json_encode($users);
         $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_rp_accessrules',
            'WHERE'  => ['feature' => $feature],
            'LIMIT'  => 1,
         ])->current();

         if ($existing) {
            $DB->update('glpi_plugin_rp_accessrules',
                        ['mode' => $mode, 'users' => $json],
                        ['id' => (int)$existing['id']]);
         } else {
            $DB->insert('glpi_plugin_rp_accessrules',
                        ['feature' => $feature, 'mode' => $mode, 'users' => $json]);
         }
         unset(self::$rules_cache[$feature]);
      }

      return true;
   }
}
