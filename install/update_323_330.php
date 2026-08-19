<?php
/**
 * Migration 3.2.3 -> 3.3.0 (mise à jour unique regroupant toutes les nouveautés)
 *
 *  - table `glpi_plugin_rp_accessrules`  : règles d'accès individuelles par fonctionnalité
 *  - table `glpi_plugin_rp_preparations` : données structurées des rapports de préparation
 *  - table `glpi_plugin_rp_userprefs`    : préférences d'affichage des boutons flottants
 *  - colonnes de config : titel_prep, sign_rp_prep, qr_secret (secret HMAC des QR codes)
 *  - colonne `entities_id` sur glpi_plugin_rp_cridetails (filtre par entité du tableau
 *    des rapports) + reprise depuis glpi_tickets
 *  - dossier physique _plugins/rp/rapportsPreparation
 *  - colonnes par défaut du tableau « Rapport PDF »
 *  - droits de profil : rapport de préparation, tableau des rapports, boutons flottants
 *  - table `glpi_plugin_rp_chartes`      : chartes de rapport en nombre libre
 *    (logo, couleurs, bas de page), reprise des deux chartes figées existantes
 *
 * Idempotente : chaque étape teste l'existant avant d'agir.
 */
function update_323_330() {
   global $DB;

   // --- 1) Table des règles d'accès individuelles ---
   if (!$DB->tableExists('glpi_plugin_rp_accessrules')) {
      $query = "CREATE TABLE `glpi_plugin_rp_accessrules` (
         `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
         `feature` VARCHAR(64) NOT NULL,
         `mode` TINYINT NOT NULL DEFAULT 0,
         `users` LONGTEXT NULL,
         PRIMARY KEY (`id`),
         UNIQUE KEY `feature` (`feature`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
      if (!$DB->doQuery($query)) {
         Toolbox::logInFile('plugin-rp', "3.3.0 : échec création glpi_plugin_rp_accessrules : " . $DB->error() . "\n");
      }
   }

   // --- 2) Table des données de rapports de préparation ---
   if (!$DB->tableExists('glpi_plugin_rp_preparations')) {
      $query = "CREATE TABLE `glpi_plugin_rp_preparations` (
         `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
         `id_ticket` INT NOT NULL,
         `materiel` VARCHAR(255) NULL,
         `marque` VARCHAR(255) NULL,
         `modele` VARCHAR(255) NULL,
         `serial` VARCHAR(255) NULL,
         `probleme` TEXT NULL,
         `diagnostic` TEXT NULL,
         `travaux` TEXT NULL,
         `pieces` TEXT NULL,
         `tests` TEXT NULL,
         `remarques` TEXT NULL,
         `tests_ok` TINYINT NOT NULL DEFAULT 1,
         `users_id_tech` INT UNSIGNED NOT NULL DEFAULT 0,
         `date_prep` TIMESTAMP NULL DEFAULT NULL,
         `id_documents` INT NOT NULL DEFAULT 0,
         PRIMARY KEY (`id`),
         UNIQUE KEY `id_ticket` (`id_ticket`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
      if (!$DB->doQuery($query)) {
         Toolbox::logInFile('plugin-rp', "3.3.0 : échec création glpi_plugin_rp_preparations : " . $DB->error() . "\n");
      }
   }

   // --- 3) Colonnes de configuration (une par une : idempotent) ---
   $config_columns = [
      // Apostrophe doublée : échappement SQL à l'intérieur d'une valeur littérale.
      'titel_prep'   => "ALTER TABLE `glpi_plugin_rp_configs` ADD `titel_prep` VARCHAR(255) NULL DEFAULT 'RAPPORT D''ATELIER'",
      'sign_rp_prep' => "ALTER TABLE `glpi_plugin_rp_configs` ADD `sign_rp_prep` TINYINT(1) NOT NULL DEFAULT 1",
      'qr_secret'    => "ALTER TABLE `glpi_plugin_rp_configs` ADD `qr_secret` VARCHAR(64) NULL DEFAULT NULL",
   ];
   foreach ($config_columns as $column => $query) {
      if (!$DB->fieldExists('glpi_plugin_rp_configs', $column)) {
         if (!$DB->doQuery($query)) {
            Toolbox::logInFile('plugin-rp', "3.3.0 : échec ajout colonne $column : " . $DB->error() . "\n");
         }
      }
   }

   // seed du titre + du secret HMAC des QR codes si absents
   $row = $DB->request([
      'SELECT' => ['id', 'titel_prep', 'qr_secret'],
      'FROM'   => 'glpi_plugin_rp_configs',
      'WHERE'  => ['id' => 1],
      'LIMIT'  => 1,
   ])->current();
   if ($row) {
      $update = [];
      if (trim((string)($row['titel_prep'] ?? '')) === '') {
         $update['titel_prep'] = "RAPPORT D'ATELIER";
      }
      if (trim((string)($row['qr_secret'] ?? '')) === '') {
         $update['qr_secret'] = bin2hex(random_bytes(32));
      }
      if ($update) {
         $DB->update('glpi_plugin_rp_configs', $update, ['id' => 1]);
      }
   }

   // --- 4) entities_id sur glpi_plugin_rp_cridetails + reprise depuis les tickets ---
   if (!$DB->fieldExists('glpi_plugin_rp_cridetails', 'entities_id')) {
      if ($DB->doQuery("ALTER TABLE `glpi_plugin_rp_cridetails`
                        ADD `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                        ADD KEY `entities_id` (`entities_id`)")) {
         $DB->doQuery("UPDATE `glpi_plugin_rp_cridetails` d
                       INNER JOIN `glpi_tickets` t ON t.`id` = d.`id_ticket`
                       SET d.`entities_id` = t.`entities_id`");
      } else {
         Toolbox::logInFile('plugin-rp', "3.3.0 : échec ajout entities_id sur cridetails : " . $DB->error() . "\n");
      }
   }

   // --- 5) Dossier physique des rapports de préparation ---
   $dir = GLPI_PLUGIN_DOC_DIR . "/rp/rapportsPreparation";
   if (!is_dir($dir)) {
      mkdir($dir);
   }

   // --- 6) Seed des nouveaux droits de profil (uniquement là où ils valent 0) ---
   // Les lignes ont été créées à 0 pour tous les profils par PluginRpProfile::initProfile().
   // Rapport de préparation : mêmes droits que le rapport technicien.
   $DB->doQuery("UPDATE `glpi_profilerights` pr_new
                 INNER JOIN `glpi_profilerights` pr_tech
                    ON pr_tech.`profiles_id` = pr_new.`profiles_id`
                   AND pr_tech.`name` = 'plugin_rp_rapport_tech'
                 SET pr_new.`rights` = pr_tech.`rights`
                 WHERE pr_new.`name` = 'plugin_rp_rapport_preparation'
                   AND pr_new.`rights` = 0");
   // Tableau des rapports : lecture seule pour les profils qui lisent déjà les rapports
   // (READ = 1 ; l'admin affine ensuite UPDATE/PURGE dans la matrice de profils).
   $DB->doQuery("UPDATE `glpi_profilerights` pr_new
                 INNER JOIN `glpi_profilerights` pr_tech
                    ON pr_tech.`profiles_id` = pr_new.`profiles_id`
                   AND pr_tech.`name` = 'plugin_rp_rapport_tech'
                 SET pr_new.`rights` = 1
                 WHERE pr_new.`name` = 'plugin_rp_liste'
                   AND pr_new.`rights` = 0
                   AND (pr_tech.`rights` & 1)");

   // --- 7) Colonnes par défaut du tableau « Rapport PDF » (glpi_displaypreferences) ---
   // Sans ces lignes, GLPI n'affiche que la 1re colonne : on impose type de rapport,
   // ticket, date, signataire, nom du document et bouton Visualiser
   // (cf. rawSearchOptions de PluginRpCriDetail).
   $rank = 1;
   foreach ([2, 3, 4, 5, 9, 14] as $num) {
      $exists = countElementsInTable('glpi_displaypreferences', [
         'itemtype' => 'PluginRpCriDetail',
         'num'      => $num,
         'users_id' => 0,
      ]);
      if ($exists == 0) {
         $DB->insert('glpi_displaypreferences', [
            'itemtype' => 'PluginRpCriDetail',
            'num'      => $num,
            'rank'     => $rank,
            'users_id' => 0,
         ]);
      }
      $rank++;
   }

   // --- 8) Préférences personnelles des boutons flottants ---
   if (!$DB->tableExists('glpi_plugin_rp_userprefs')) {
      $query = "CREATE TABLE `glpi_plugin_rp_userprefs` (
         `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
         `users_id` INT UNSIGNED NOT NULL,
         `fab_home` TINYINT NOT NULL DEFAULT 1,
         `fab_ticket` TINYINT NOT NULL DEFAULT 1,
         PRIMARY KEY (`id`),
         UNIQUE KEY `users_id` (`users_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
      if (!$DB->doQuery($query)) {
         Toolbox::logInFile('plugin-rp', "3.3.0 : échec création glpi_plugin_rp_userprefs : " . $DB->error() . "\n");
      }
   }

   // --- 9) Droit des boutons flottants (READ = accueil, UPDATE = ticket) ---
   // Ouvert là où les rapports sont déjà autorisés.
   $DB->doQuery("UPDATE `glpi_profilerights` pr_new
                 INNER JOIN `glpi_profilerights` pr_tech
                    ON pr_tech.`profiles_id` = pr_new.`profiles_id`
                   AND pr_tech.`name` = 'plugin_rp_rapport_tech'
                 SET pr_new.`rights` = 3
                 WHERE pr_new.`name` = 'plugin_rp_boutons'
                   AND pr_new.`rights` = 0
                   AND pr_tech.`rights` > 0");

   /*
    * --- 10) Chartes de rapport ---
    *
    * Les deux chartes étaient figées dans des colonnes numérotées de la table
    * de configuration, avec une nomenclature qui n'aidait pas :
    *
    *   charte 1 : entity_parrent1, logo_id,  color1, color_text1, line1, line2
    *   charte 2 : entity_parrent2, logo_id2, color2, color_text2, line3, line4
    *
    * Impossible d'en ajouter une troisième sans ajouter six colonnes. On passe
    * donc à une table dédiée, une ligne par charte, en nombre libre.
    *
    * Les anciennes colonnes ne sont PAS supprimées : elles restent la source de
    * secours tant que le nouveau fonctionnement n'a pas tourné en conditions
    * réelles. Leur suppression fera l'objet d'une migration ultérieure.
    */
   if (!$DB->tableExists('glpi_plugin_rp_chartes')) {
      $query = "CREATE TABLE `glpi_plugin_rp_chartes` (
         `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
         `name` VARCHAR(255) NOT NULL DEFAULT '',
         `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
         `logo_id` INT UNSIGNED NOT NULL DEFAULT 0,
         `color_bg` VARCHAR(16) NOT NULL DEFAULT '#2980b9',
         `color_text` VARCHAR(16) NOT NULL DEFAULT '#ffffff',
         `line1` VARCHAR(255) NULL,
         `line2` VARCHAR(255) NULL,
         `rank` INT NOT NULL DEFAULT 0,
         `is_default` TINYINT NOT NULL DEFAULT 0,
         PRIMARY KEY (`id`),
         KEY `entities_id` (`entities_id`),
         KEY `rank` (`rank`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
      if (!$DB->doQuery($query)) {
         Toolbox::logInFile('plugin-rp', "3.3.0 : échec création glpi_plugin_rp_chartes : " . $DB->error() . "\n");
      }
   }

   // Reprise des deux chartes existantes, une seule fois (table vide).
   if ($DB->tableExists('glpi_plugin_rp_chartes')
       && countElementsInTable('glpi_plugin_rp_chartes') === 0) {

      $cfg = $DB->request(['FROM' => 'glpi_plugin_rp_configs', 'LIMIT' => 1])->current();
      if ($cfg) {
         // La première charte est marquée par défaut : c'est elle que recevaient
         // implicitement les entités ne correspondant à aucune des deux.
         $legacy = [
            [
               'entities_id' => (int)($cfg['entity_parrent1'] ?? 0),
               'logo_id'     => (int)($cfg['logo_id'] ?? 0),
               'color_bg'    => (string)($cfg['color1'] ?? '#2980b9'),
               'color_text'  => (string)($cfg['color_text1'] ?? '#ffffff'),
               'line1'       => (string)($cfg['line1'] ?? ''),
               'line2'       => (string)($cfg['line2'] ?? ''),
               'rank'        => 1,
               'is_default'  => 1,
            ],
            [
               'entities_id' => (int)($cfg['entity_parrent2'] ?? 0),
               'logo_id'     => (int)($cfg['logo_id2'] ?? 0),
               'color_bg'    => (string)($cfg['color2'] ?? '#2980b9'),
               'color_text'  => (string)($cfg['color_text2'] ?? '#ffffff'),
               // La charte 2 utilisait line3/line4, pas line1/line2.
               'line1'       => (string)($cfg['line3'] ?? ''),
               'line2'       => (string)($cfg['line4'] ?? ''),
               'rank'        => 2,
               'is_default'  => 0,
            ],
         ];

         foreach ($legacy as $charte) {
            // Une charte sans entité NI logo n'a jamais été configurée : on ne
            // la reprend pas, elle encombrerait la liste pour rien.
            if ($charte['entities_id'] === 0 && $charte['logo_id'] === 0) {
               continue;
            }
            /*
             * Nom COURT de l'entité, lu directement en base : `Entity` étend
             * `CommonTreeDropdown`, donc `Dropdown::getDropdownName()` renvoie
             * le chemin complet (« Root entity > EASI SUPPORT »), ce qui
             * donnerait des libellés de charte à rallonge dans les boutons
             * radio et le menu de l'action massive.
             */
            $name = '';
            if ($charte['entities_id'] > 0) {
               $entity_row = $DB->request([
                  'SELECT' => ['name'],
                  'FROM'   => 'glpi_entities',
                  'WHERE'  => ['id' => (int)$charte['entities_id']],
                  'LIMIT'  => 1,
               ])->current();
               $name = trim((string)($entity_row['name'] ?? ''));
            }
            $charte['name'] = ($name !== '' && $name !== '&nbsp;')
               ? $name
               : sprintf(__('Charte %d', 'rp'), $charte['rank']);

            if (!$DB->insert('glpi_plugin_rp_chartes', $charte)) {
               Toolbox::logInFile('plugin-rp', "3.3.0 : échec reprise d'une charte : " . $DB->error() . "\n");
            }
         }
      }

      // Aucune charte reprise (installation neuve) : une charte par défaut vide,
      // pour que la page de configuration ne s'ouvre jamais sur une liste vide.
      if (countElementsInTable('glpi_plugin_rp_chartes') === 0) {
         $DB->insert('glpi_plugin_rp_chartes', [
            'name'       => __('Charte par défaut', 'rp'),
            'rank'       => 1,
            'is_default' => 1,
         ]);
      }
   }
}
