<?php
/**
 * Migration 3.3.0 -> 3.3.1
 *
 *  - colonne `groups_id_livraison` sur glpi_plugin_rp_configs : groupe qui
 *    reçoit les tâches de livraison créées depuis le rapport d'atelier ;
 *  - colonne `DisplayPdfEnd` : ouvrir ou non le PDF produit après signature ;
 *  - index de lecture sur glpi_plugin_rp_cridetails, pour les vues agrégées ;
 *  - rattrapage des chartes de rapport pour les installations où 3.3.0 avait
 *    déjà été appliquée AVANT que les chartes n'y soient ajoutées ;
 *  - colonne `fab_home_tabs` sur glpi_plugin_rp_userprefs : quels onglets le
 *    modal du bouton d'accueil propose ;
 *  - table `glpi_plugin_rp_offline_queue` : garde d'idempotence de la file
 *    d'attente des signatures hors-ligne.
 *
 * Idempotente : chaque étape teste l'existant avant d'agir.
 */
function update_330_331() {
   global $DB;

   // --- 1) Groupe de livraison ---
   if ($DB->tableExists('glpi_plugin_rp_configs')
       && !$DB->fieldExists('glpi_plugin_rp_configs', 'groups_id_livraison')) {
      /*
       * doQuery() LÈVE une exception en cas d'échec sur GLPI 11, elle ne renvoie
       * jamais false : un `if (!$DB->doQuery(...))` serait du code mort. D'où le
       * try/catch, qui permet de journaliser un échec réel (droits MySQL, table
       * verrouillée) au lieu d'interrompre l'installation sans trace.
       */
      try {
         $DB->doQuery(
            "ALTER TABLE `glpi_plugin_rp_configs`
             ADD `groups_id_livraison` INT UNSIGNED NOT NULL DEFAULT 0"
         );
      } catch (\Throwable $e) {
         Toolbox::logInFile(
            'plugin-rp',
            "3.3.1 : échec ajout colonne groups_id_livraison : " . $e->getMessage() . "\n"
         );
      }
   }

   /*
    * --- 2) Affichage du PDF après signature ---
    *
    * Le plugin ouvrait toujours le document produit, sans jamais poser la
    * question. La colonne naît donc à 1 : rien ne change pour l'existant, et
    * ceux qui ne veulent pas de cet onglet peuvent enfin le dire.
    */
   if ($DB->tableExists('glpi_plugin_rp_configs')
       && !$DB->fieldExists('glpi_plugin_rp_configs', 'DisplayPdfEnd')) {
      try {
         $DB->doQuery(
            "ALTER TABLE `glpi_plugin_rp_configs`
             ADD `DisplayPdfEnd` TINYINT(1) NOT NULL DEFAULT 1"
         );
      } catch (\Throwable $e) {
         Toolbox::logInFile(
            'plugin-rp',
            "3.3.1 : échec ajout colonne DisplayPdfEnd : " . $e->getMessage() . "\n"
         );
      }
   }

   /*
    * --- 3) Index de lecture sur les rapports ---
    *
    * La table ne portait qu'une clé primaire et un index d'entité. Toute lecture
    * agrégée — les tuiles de supervision, et désormais les statistiques de
    * signature par technicien du plugin `stats` — balayait donc l'intégralité de
    * la table à chaque affichage.
    *
    * L'ordre des colonnes suit celui des filtres : le type d'abord (il réduit le
    * plus), puis le technicien, puis la date pour le tri et les bornes.
    *
    * `SHOW INDEX` plutôt que d'ajouter à l'aveugle : `ADD INDEX` échoue si
    * l'index existe déjà, et la migration doit pouvoir être rejouée.
    */
   if ($DB->tableExists('glpi_plugin_rp_cridetails')) {
      try {
         $index_existe = false;
         foreach ($DB->request(['SQL' => "SHOW INDEX FROM `glpi_plugin_rp_cridetails`"]) as $row) {
            if (($row['Key_name'] ?? '') === 'type_users_date') {
               $index_existe = true;
               break;
            }
         }
         if (!$index_existe) {
            $DB->doQuery(
               "ALTER TABLE `glpi_plugin_rp_cridetails`
                ADD INDEX `type_users_date` (`type`, `users_id`, `date`)"
            );
         }
      } catch (\Throwable $e) {
         /*
          * Un index manquant ralentit, il ne casse rien : on journalise et on
          * poursuit la migration, plutôt que d'interrompre une mise à jour pour
          * une question de performance.
          */
         Toolbox::logInFile(
            'plugin-rp',
            "3.3.1 : échec création index type_users_date : " . $e->getMessage() . "\n"
         );
      }
   }

   /*
    * --- 4) Rattrapage des chartes de rapport ---
    *
    * Les chartes ont été ajoutées à la migration 3.3.0 APRÈS que celle-ci ait
    * déjà tourné sur certaines installations : GLPI ne la rejoue pas, la table
    * n'y a donc jamais été créée. On refait ici le même travail, à l'identique
    * et sans effet sur les bases déjà pourvues.
    *
    * Tant que la table est absente, le plugin fonctionne sur ses anciennes
    * colonnes grâce au repli de PluginRpCharte : ce rattrapage est ce qui rend
    * les chartes réellement actives.
    */
   if (!$DB->tableExists('glpi_plugin_rp_chartes')) {
      include_once(PLUGIN_RP_DIR . '/install/update_323_330.php');
      update_323_330();
   }

   /*
    * --- 5) Droit de supervision ---
    *
    * Créé à ZÉRO pour tous les profils : il expose les dossiers restés sans
    * suite, on l'ouvre volontairement plutôt que de le distribuer. Le
    * super-administrateur y accède de toute façon sans ce droit
    * (PluginRpAccess::canSupervise), afin de pouvoir l'attribuer aux autres.
    */
   $existants = [];
   foreach ($DB->request([
      'SELECT' => ['profiles_id'],
      'FROM'   => 'glpi_profilerights',
      'WHERE'  => ['name' => 'plugin_rp_supervision'],
   ]) as $row) {
      $existants[] = (int)$row['profiles_id'];
   }

   foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $profile) {
      if (in_array((int)$profile['id'], $existants, true)) {
         continue;
      }
      $DB->insert('glpi_profilerights', [
         'profiles_id' => (int)$profile['id'],
         'name'        => 'plugin_rp_supervision',
         'rights'      => 0,
      ]);
   }

   /*
    * --- 6) Onglets du bouton d'accueil ---
    *
    * Quels onglets le modal « Scanner / Rechercher » propose : 1 = résolution
    * d'un identifiant, 2 = recherche par mot-clé, 3 = les deux.
    *
    * La colonne naît à 3, c'est-à-dire exactement ce que faisait le bouton
    * avant ce réglage : personne ne voit son interface changer parce qu'il a
    * mis à jour.
    *
    * Le partage des préférences avec le plugin Gestion ne demande rien ici : il
    * se joue à l'exécution (PluginRpUserpref écrit dans les deux tables et lit
    * celle du voisin quand la sienne est vide), aucune donnée n'est à déplacer.
    */
   if ($DB->tableExists('glpi_plugin_rp_userprefs')
       && !$DB->fieldExists('glpi_plugin_rp_userprefs', 'fab_home_tabs')) {
      try {
         $DB->doQuery(
            "ALTER TABLE `glpi_plugin_rp_userprefs`
             ADD `fab_home_tabs` TINYINT NOT NULL DEFAULT 3"
         );
      } catch (\Throwable $e) {
         Toolbox::logInFile(
            'plugin-rp',
            "3.3.1 : échec ajout colonne fab_home_tabs : " . $e->getMessage() . "\n"
         );
      }
   }

   /*
    * File d'attente des signatures hors-ligne.
    *
    * La table ne retient QUE les signatures déjà traitées : la file elle-même
    * vit dans le navigateur du technicien. C'est ce qui empêche un rejeu — la
    * même requête renvoyée au retour du réseau — de produire un second rapport
    * signé et un second mail au client.
    *
    * La création est déléguée à la classe, qui sait aussi la faire à la demande
    * si cette migration n'a pas été jouée. Une seule définition du schéma, donc
    * aucun risque qu'il diverge entre les deux chemins.
    */
   if (class_exists('PluginRpOfflineQueue')) {
      PluginRpOfflineQueue::createTable();
   }
}
