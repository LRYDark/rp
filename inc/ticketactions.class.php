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
    * Marqueur des tâches de livraison créées par le rapport d'atelier.
    *
    * Sert à les reconnaître pour ne pas en créer deux sur le même ticket. Il
    * fait partie du texte visible : le repérer sur l'état « à faire » échouait
    * dès que la livraison était terminée, et régénérer le rapport relançait
    * alors une livraison déjà effectuée.
    */
   const LIVRAISON_MARQUEUR = 'Matériel à livrer au client.';

   /**
    * Nombre de tâches d'un ticket, AU SENS DES RAPPORTS.
    *
    * Point de comptage unique. Il en existait quatre, dont deux seulement
    * appliquaient le filtre `use_publictask` : sur un ticket ne portant que des
    * tâches privées, le formulaire d'atelier voyait « aucune tâche » et
    * proposait la saisie libre, tandis que le générateur en voyait et ne créait
    * donc pas la tâche attendue — la branche restait sans issue, sans message.
    */
   static function countTasks(int $ticket_id): int {
      if ($ticket_id <= 0) {
         return 0;
      }
      $config   = PluginRpConfig::getInstance();
      $criteria = ['tickets_id' => $ticket_id];
      if ((int)($config->fields['use_publictask'] ?? 0) === 1) {
         $criteria['is_private'] = 0;
      }
      return countElementsInTable('glpi_tickettasks', $criteria);
   }

   /**
    * Bon de livraison rattaché à un ticket, plugin Gestion.
    *
    * Point de lecture unique du plugin RP sur la table du plugin Gestion : le
    * bouton flottant, le scanner et les formulaires doivent tous désigner LE
    * MÊME bon, sans quoi un écran proposerait d'en signer un que l'autre ignore.
    *
    * Le tri ramène en premier un bon non signé — c'est celui sur lequel il
    * reste quelque chose à faire — et à défaut le plus récent, pour pouvoir
    * signaler qu'il est déjà signé.
    *
    * N'existe que si le plugin Gestion est installé : la table est donc
    * vérifiée avant d'être interrogée, sans quoi RP seul tomberait en erreur.
    * Ne contrôle AUCUN droit : c'est à l'appelant de vérifier qu'il a le droit
    * de signer un bon avant d'en proposer un.
    *
    * @return array|null ['id', 'name', 'signed'] ou null si aucun bon
    */
   static function getBl(int $ticket_id): ?array {
      global $DB;

      if ($ticket_id <= 0
          || !Plugin::isPluginActive('gestion')
          || !$DB->tableExists('glpi_plugin_gestion_surveys')) {
         return null;
      }

      $row = $DB->request([
         'SELECT' => ['id', 'bl', 'signed'],
         'FROM'   => 'glpi_plugin_gestion_surveys',
         'WHERE'  => ['tickets_id' => $ticket_id],
         'ORDER'  => ['signed ASC', 'id DESC'],
         'LIMIT'  => 1,
      ])->current();

      if (!$row) {
         return null;
      }

      return [
         'id'     => (int)$row['id'],
         'name'   => (string)$row['bl'],
         'signed' => (int)$row['signed'],
      ];
   }

   /**
    * Bon de livraison qu'il est réellement possible de faire signer AVEC un
    * rapport, ici et maintenant.
    *
    * `getBl()` répond « lequel » ; celle-ci répond « peut-on le proposer ».
    * Elle réunit les conditions qui doivent être vraies ENSEMBLE, et qui
    * étaient recopiées à chaque écran offrant le choix — recopie qui finit
    * toujours par diverger :
    *
    *   - le plugin Gestion installé, actif, et assez récent pour porter le
    *     rendu partagé du choix ;
    *   - le droit de signer un bon ;
    *   - un bon rattaché au ticket, non signé ;
    *   - au moins une tâche : sans elle le formulaire combiné refuse de se
    *     rendre, et proposer la bascule mènerait à un message d'erreur.
    */
   static function getSignableBl(int $ticket_id): ?array {
      if (!Plugin::isPluginActive('gestion')
          || !class_exists('PluginGestionCri')
          || !method_exists('PluginGestionCri', 'renderCombinedModeRadio')
          || !Session::haveRight('plugin_gestion_survey', READ)
          || self::countTasks($ticket_id) === 0) {
         return null;
      }

      $bl = self::getBl($ticket_id);

      return ($bl !== null && $bl['signed'] === 0) ? $bl : null;
   }

   /**
    * @param int $ticket_id
    * @return array|null null si le ticket est inaccessible à l'utilisateur
    */
   static function build(int $ticket_id): ?array {
      $ticket = new Ticket();
      if ($ticket_id <= 0 || !$ticket->getFromDB($ticket_id) || !$ticket->canViewItem()) {
         return null;
      }

      // ---- État du ticket ---------------------------------------------------
      $nb_tasks = self::countTasks($ticket_id);

      // ---- Plugin Gestion : BL associé --------------------------------------
      $gestion_active = Plugin::isPluginActive('gestion') && class_exists('PluginGestionCri');
      $gestion_webdir = $gestion_active
         ? (defined('PLUGIN_GESTION_WEBDIR') ? PLUGIN_GESTION_WEBDIR : Plugin::getWebDir('gestion'))
         : '';
      $can_sign_bl = $gestion_active && Session::haveRight('plugin_gestion_survey', READ);

      $bl = $can_sign_bl ? self::getBl($ticket_id) : null;

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

      /*
       * Étape suivante recommandée.
       *
       * Clé AJOUTÉE au contrat, jamais retirée : les consommateurs qui
       * l'ignorent continuent de fonctionner exactement comme avant. Elle
       * désigne l'action la plus probable pour que l'interface la mette en
       * avant, sans supprimer les autres.
       *
       * L'ordre suit le cheminement du matériel : on le reçoit, on le répare,
       * on le rend. Une étape déjà franchie — son rapport existe — n'est plus
       * proposée comme suivante.
       */
      $state  = self::getReportState($ticket_id);
      $wanted = null;
      if (!$state['prise_en_charge'] && !$state['atelier']) {
         // Rien n'a encore été produit : entrée du matériel, ou hotline pour
         // qui n'a pas de matériel du tout.
         $wanted = 'prise_en_charge';
      } elseif (!$state['atelier']) {
         $wanted = 'preparation';
      } elseif (!$state['intervention']) {
         $wanted = 'combined';
      }

      // Une action combinée n'existe que si un BL non signé est rattaché :
      // à défaut, c'est le rapport d'intervention seul qui conclut.
      $available = array_column($actions, 'key');
      if ($wanted === 'combined' && !in_array('combined', $available, true)) {
         $wanted = 'rapport';
      }
      // L'étape visée n'est pas proposée (droits, tâche manquante, BL signé) :
      // on ne recommande rien plutôt que de pointer un bouton absent.
      $next = in_array($wanted, $available, true) ? $wanted : null;

      return [
         'ok'             => true,
         'ticket_id'      => $ticket_id,
         'ticket_name'    => (string)$ticket->fields['name'],
         'has_tasks'      => $nb_tasks > 0,
         'notice'         => $notice,
         'gestion_webdir' => $gestion_webdir,
         'rp_webdir'      => PLUGIN_RP_WEBDIR,
         'actions'        => $actions,
         'report_state'   => $state,
         'next'           => $next,
      ];
   }

   /**
    * Quels rapports existent déjà pour ce ticket.
    *
    * Le type 2 (hotline) est VOLONTAIREMENT ignoré : l'export massif
    * (front/export.massive.php) écrit ses lignes avec `type = 2` en dur, si
    * bien qu'un passage en masse marquerait des centaines de tickets comme
    * ayant un rapport hotline. Ce signal n'est pas fiable, il ne sert donc pas
    * au cheminement.
    *
    * On raisonne en PRÉSENCE par type, jamais en nombre : selon la
    * configuration `multi_doc`, une régénération met la ligne à jour en place
    * au lieu d'en créer une seconde.
    */
   static function getReportState(int $ticket_id): array {
      global $DB;

      $state = [
         'prise_en_charge' => false,   // type 0
         'intervention'    => false,   // type 1
         'atelier'         => false,   // type 3
      ];
      if ($ticket_id <= 0 || !$DB->tableExists('glpi_plugin_rp_cridetails')) {
         return $state;
      }

      $map = [0 => 'prise_en_charge', 1 => 'intervention', 3 => 'atelier'];
      foreach ($DB->request([
         'SELECT'   => ['type'],
         'DISTINCT' => true,
         'FROM'     => 'glpi_plugin_rp_cridetails',
         'WHERE'    => ['id_ticket' => $ticket_id, 'type' => array_keys($map)],
      ]) as $row) {
         $type = (int)$row['type'];
         if (isset($map[$type])) {
            $state[$map[$type]] = true;
         }
      }

      return $state;
   }
}
