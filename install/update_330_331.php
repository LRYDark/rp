<?php
/**
 * Migration 3.3.0 -> 3.3.1
 *
 *  - colonne `groups_id_livraison` sur glpi_plugin_rp_configs : groupe qui
 *    reçoit les tâches de livraison créées depuis le rapport d'atelier ;
 *  - rattrapage des chartes de rapport pour les installations où 3.3.0 avait
 *    déjà été appliquée AVANT que les chartes n'y soient ajoutées.
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
    * --- 2) Rattrapage des chartes de rapport ---
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
}
