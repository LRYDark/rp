<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/*
 * Classe autonome, sans héritage : elle ne rend rien et ne représente aucun
 * élément GLPI, elle calcule seulement une liste d'actions. Hériter de
 * CommonDBTM réserverait des dizaines de noms de méthodes sans rien apporter.
 */

/**
 * Actions de signature disponibles pour un ticket.
 *
 * Source unique, partagée par le bouton flottant du ticket (ajax/ticket_actions.php)
 * et par les résultats du scanner (ajax/scan.php) : les deux doivent proposer
 * exactement les mêmes possibilités, filtrées par les mêmes droits.
 *
 * La liste s'adapte au contenu réel du ticket et aux plugins actifs :
 *   - tâches présentes     -> rapport d'intervention
 *   - aucune tâche         -> fiche de prise en charge
 *   - BL associé non signé -> signature du BL (plugin Gestion)
 *   - BL + tâches          -> formulaire combiné « BL + rapport »
 *
 * Fonctionne avec le plugin RP seul, le plugin Gestion seul, ou les deux.
 */
class PluginRpTicketActions {

   /**
    * @param int $ticket_id
    * @return array|null null si le ticket est inaccessible à l'utilisateur
    */
   static function build(int $ticket_id): ?array {
      global $DB;

      $ticket = new Ticket();
      if ($ticket_id <= 0 || !$ticket->getFromDB($ticket_id) || !$ticket->canViewItem()) {
         return null;
      }

      // ---- État du ticket ---------------------------------------------------
      $config     = PluginRpConfig::getInstance();
      $publiconly = (int)($config->fields['use_publictask'] ?? 0) === 1;

      $task_criteria = ['tickets_id' => $ticket_id];
      if ($publiconly) {
         $task_criteria['is_private'] = 0;
      }
      $nb_tasks = countElementsInTable('glpi_tickettasks', $task_criteria);

      // ---- Plugin Gestion : BL associé --------------------------------------
      $gestion_active = Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri');
      $gestion_webdir = $gestion_active
         ? (defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion'))
         : '';
      $can_sign_bl = $gestion_active && Session::haveRight('plugin_gestion_survey', READ);

      $bl = null;
      if ($can_sign_bl && $DB->tableExists('glpi_plugin_gestion_surveys')) {
         $row = $DB->request([
            'SELECT' => ['id', 'bl', 'signed'],
            'FROM'   => 'glpi_plugin_gestion_surveys',
            'WHERE'  => ['tickets_id' => $ticket_id],
            'ORDER'  => ['signed ASC', 'id DESC'],
            'LIMIT'  => 1,
         ])->current();
         if ($row) {
            $bl = [
               'id'     => (int)$row['id'],
               'name'   => (string)$row['bl'],
               'signed' => (int)$row['signed'],
            ];
         }
      }

      // ---- Droits RP --------------------------------------------------------
      $can_report      = PluginRpAccess::canUse('rapport_tech', CREATE);
      $can_hotline     = PluginRpAccess::canUse('rapport_hotline', CREATE);
      $can_preparation = PluginRpAccess::canUse('preparation', CREATE);

      $actions = [];

      /*
       * Les actions « modal » sont ouvertes par les fonctions JS déjà utilisées
       * par l'onglet du ticket : rp_loadCriForm() (plugin RP) et
       * gestion_loadCriForm() (plugin Gestion). Aucune logique de signature
       * n'est réécrite ici.
       */

      // BL non signé + tâches : formulaire combiné « Rapport + BL »
      if ($bl !== null && $bl['signed'] === 0 && $nb_tasks > 0 && $can_report) {
         $actions[] = [
            'key'     => 'combined',
            'label'   => __('Signer le BL + le rapport', 'rp'),
            'hint'    => $bl['name'],
            'icon'    => 'ti ti-file-signature',
            'mode'    => 'gestion',
            'bl_id'   => $bl['id'],
            'primary' => true,
         ];
      }

      // BL non signé : toujours proposé seul, y compris quand le combiné existe
      if ($bl !== null && $bl['signed'] === 0) {
         $actions[] = [
            'key'     => 'bl',
            'label'   => __('Signer le bon de livraison', 'rp'),
            'hint'    => $bl['name'],
            'icon'    => 'ti ti-signature',
            'mode'    => 'gestion',
            'bl_only' => true,
            'bl_id'   => $bl['id'],
            'primary' => ($nb_tasks === 0 || !$can_report),
         ];
      }

      // Rapport d'intervention (nécessite au moins une tâche)
      if ($can_report && $nb_tasks > 0) {
         $actions[] = [
            'key'     => 'rapport',
            'label'   => __("Signer le rapport d'intervention", 'rp'),
            'hint'    => sprintf(_n('%d tâche', '%d tâches', $nb_tasks, 'rp'), $nb_tasks),
            'icon'    => 'ti ti-file-check',
            'mode'    => 'rp',
            'modal'   => 'form_rapport',
            'primary' => ($bl === null || $bl['signed'] === 1),
         ];
      }

      // Fiche de prise en charge (pas de tâche : le rapport n'est pas générable)
      if ($can_report && $nb_tasks === 0) {
         $actions[] = [
            'key'     => 'prise_en_charge',
            'label'   => __('Signer la fiche de prise en charge', 'rp'),
            'hint'    => __('Aucune tâche sur ce ticket', 'rp'),
            'icon'    => 'ti ti-file-text',
            'mode'    => 'rp',
            'modal'   => 'form_client',
            'primary' => ($bl === null),
         ];
      }

      // Rapport hotline
      if ($can_hotline && $nb_tasks > 0) {
         $actions[] = [
            'key'     => 'hotline',
            'label'   => __('Signer le rapport hotline', 'rp'),
            'icon'    => 'ti ti-headset',
            'mode'    => 'rp',
            'modal'   => 'form_rapport_hotline',
            'primary' => false,
         ];
      }

      // Rapport d'atelier
      if ($can_preparation) {
         $actions[] = [
            'key'     => 'preparation',
            'label'   => __("Rapport d'atelier", 'rp'),
            'icon'    => 'ti ti-tools',
            'mode'    => 'rp',
            'modal'   => 'form_preparation',
            'primary' => false,
         ];
      }

      // BL déjà signé : simple information
      $notice = '';
      if ($bl !== null && $bl['signed'] === 1) {
         $notice = sprintf(__('Bon de livraison %s déjà signé.', 'rp'), $bl['name']);
      }

      return [
         'ok'             => true,
         'ticket_id'      => $ticket_id,
         'ticket_name'    => (string)$ticket->fields['name'],
         'has_tasks'      => $nb_tasks > 0,
         'notice'         => $notice,
         'gestion_webdir' => $gestion_webdir,
         'rp_webdir'      => PLUGIN_RP_WEBDIR,
         'actions'        => $actions,
      ];
   }
}
