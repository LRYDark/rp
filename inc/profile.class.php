<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpProfile extends Profile {

   static function getTypeName($nb = 0) {
      return _n('Right management', 'Rights management', $nb, 'rp');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Profile') {
         //return __('Rapport / Prise en charge', 'rp');
         return __('<span class="d-flex align-items-center"><i class="fa-solid fa-file me-2"></i>Rapport / Prise en charge</span>', "rp");

      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == 'Profile') {
         $ID   = $item->getID();
         $prof = new self();
         //self::addDefaultProfileInfos($ID,['plugin_rp_rapport_tech' => ALLSTANDARDRIGHT]);
         self::addDefaultProfileInfos($ID,['plugin_rp' => ALLSTANDARDRIGHT]);
         $prof->showForm($ID);
      }
      return true;
   }

   function showForm($profiles_id = 0, $openform = TRUE, $closeform = TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='" . $profile->getFormURL() . "'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);

      $rights = $this->getAllRights();
      $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                    'default_class' => 'tab_bg_2',
                                                    'title'         => __('General')]);
      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $profiles_id]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";

      echo "<p style='text-transform: uppercase; text-decoration: underline;'>Rapport technicien / Rapport hotline : </p>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Lecture : </b> Affichage des tableaux. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Mise à jour : </b> Laisse le droit à l'utilisateur de créer plusieurs Rapports et Fiches. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Créer : </b> Laisse le droit à l'utilisateur de créer un rapport ou une fiche. <br><br>";

      echo "<p style='text-transform: uppercase; text-decoration: underline;'>Rapport d'atelier : </p>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Lecture : </b> Affichage du tableau des rapports de préparation dans le ticket. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Créer : </b> Génération d'un rapport de préparation. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Mise à jour : </b> Régénération d'un rapport de préparation existant. <br><br>";

      echo "<p style='text-transform: uppercase; text-decoration: underline;'>Liste des rapports (tableau) : </p>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Lecture : </b> Accès au tableau des rapports (menu Outils) avec les filtres GLPI. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Mise à jour : </b> Modification d'une ligne (nom du signataire, e-mail). <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Purger : </b> Suppression définitive d'une ligne du tableau. <br><br>";

      echo "<p style='text-transform: uppercase; text-decoration: underline;'>Boutons flottants : </p>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Bouton d'accueil (scan) : </b> Bouton de recherche / scan (BL, ticket, QR code) sur la page d'accueil. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Bouton sur les tickets : </b> Bouton d'accès rapide aux signatures depuis un ticket. <br>";
         echo "&emsp;&emsp;&emsp;<i>Chaque utilisateur peut les désactiver depuis ses Préférences (par défaut : affichés sur mobile uniquement).</i> <br><br>";

      echo "<p style='text-transform: uppercase; text-decoration: underline;'>Signature technicien  : </p>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Lecture : </b> Affichage de la signature. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Mise à jour : </b> Laisse le droit à l'utilisateur de créer et modifier sa signature. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Créer : </b> Laisse le droit à l'utilisateur de créer une signature. <br>";
         echo "&emsp;&emsp;&emsp;<b style='text-transform: uppercase;'> Purger : </b> Laisse le droit à l'utilisateur de supprimer sa signature. <br>";
   }

   static function getAllRights($all = false) {
      $rights = [
         ['itemtype' => 'PluginRpConfig',
            'label'    => __('Rapport PDF (massives actions)', 'rp'),
            'field'    => 'plugin_rp_pdf',
            'rights'   => [CREATE  => __('Create')]
         ],
         ['itemtype' => 'PluginRpConfig',
            'label'    => __('Configuration du plugin', 'rp'),
            'field'    => 'plugin_rp',
            'rights'   => [UPDATE  => __('Update')]
         ],
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __('Rapport technicien', 'rp'),
            'field'    => 'plugin_rp_rapport_tech',
            'rights'   => [READ    => __('Read'),
                           CREATE  => __('Create'),
                           UPDATE  => __('Update')]
         ],
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __('Rapport hotline', 'rp'),
            'field'    => 'plugin_rp_rapport_hotline',
            'rights'   => [READ    => __('Read'),
                           CREATE  => __('Create'),
                           UPDATE  => __('Update')]
         ],
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __("Rapport d'atelier", 'rp'),
            'field'    => 'plugin_rp_rapport_preparation',
            'rights'   => [READ    => __('Read'),
                           CREATE  => __('Create'),
                           UPDATE  => __('Update')]
         ],
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __('Liste des rapports (tableau)', 'rp'),
            'field'    => 'plugin_rp_liste',
            'rights'   => [READ    => __('Read'),
                           UPDATE  => __('Update'),
                           PURGE   => __('Delete permanently')]
         ],
         /*
          * Droit distinct de `plugin_rp_liste` : lire le tableau des rapports
          * et surveiller les dossiers en souffrance sont deux fonctions
          * différentes. La seconde relève de l'encadrement — elle expose ce qui
          * n'a PAS été fait, information qu'on ne veut pas donner à tout
          * technicien ayant accès à la liste.
          */
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __('Supervision des rapports en attente', 'rp'),
            'field'    => 'plugin_rp_supervision',
            'rights'   => [READ => __('Read')]
         ],
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __('Boutons flottants', 'rp'),
            'field'    => 'plugin_rp_boutons',
            'rights'   => [READ   => __("Bouton d'accueil (scan)", 'rp'),
                           UPDATE => __('Bouton sur les tickets', 'rp')]
         ],
         ['itemtype' => 'PluginRpCriDetail',
            'label'    => __('Signature technicien', 'rp'),
            'field'    => 'plugin_rp_Signature'
         ]
      ];

      return $rights;
   }

   /**
    * Init profiles
    *
    **/
   static function translateARight($old_right) {
      switch ($old_right) {
         case '':
            return 0;
         case 'r' :
            return READ;
         case 'w':
            return ALLSTANDARDRIGHT;
         case '0':
         case '1':
            return $old_right;

         default :
            return 0;
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function initProfile() {
      global $DB;
      $profile = new self();
      $dbu     = new DbUtils();

   /* --------------- Nettoyage de BDD en fonction de la suppression des tickets à chaque ouverture de session--------------- */
   /*
   $DB->doQuery("DELETE FROM glpi_plugin_rp_cridetails WHERE NOT EXISTS(SELECT id FROM glpi_tickets WHERE glpi_tickets.id = glpi_plugin_rp_cridetails.id_ticket);");
   $DB->doQuery("DELETE FROM glpi_plugin_rp_dataclient WHERE NOT EXISTS(SELECT id FROM glpi_tickets WHERE glpi_tickets.id = glpi_plugin_rp_dataclient.id_ticket);");
   */
   /*---------*/

      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if ($dbu->countElementsInTable("glpi_profilerights",
                                        ["name" => $data['field']]) == 0) {
            ProfileRight::addProfileRights([$data['field']]);
         }
      }

      /*foreach ($DB->request("SELECT *
                           FROM `glpi_profilerights` 
                           WHERE `profiles_id`='" . $_SESSION['glpiactiveprofile']['id'] . "' 
                              AND `name` LIKE '%plugin_rp%'") as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }*/
      $criteria = [
         'SELECT' => '*',
         'FROM'   => 'glpi_profilerights',
         'WHERE'  => [
            'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
            'name'        => ['LIKE', '%plugin_rp%']
         ]
      ];

      foreach ($DB->request($criteria) as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function changeProfile() {
      global $DB;

      /*foreach ($DB->request("SELECT *
                           FROM `glpi_profilerights` 
                           WHERE `profiles_id`='" . $_SESSION['glpiactiveprofile']['id'] . "' 
                              AND `name` LIKE '%plugin_rp%'") as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }*/
      $criteria = [
         'SELECT' => '*',
         'FROM'   => 'glpi_profilerights',
         'WHERE'  => [
            'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
            'name'        => ['LIKE', '%plugin_rp%']
         ]
      ];

      foreach ($DB->request($criteria) as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }

   static function createFirstAccess($profiles_id) {
      self::addDefaultProfileInfos($profiles_id,
                                   ['plugin_rp'                         => ALLSTANDARDRIGHT,
                                    'plugin_rp_pdf'                     => ALLSTANDARDRIGHT,
                                    'plugin_rp_rapport_hotline'         => ALLSTANDARDRIGHT,
                                    'plugin_rp_rapport_tech'            => ALLSTANDARDRIGHT,
                                    'plugin_rp_rapport_preparation'     => ALLSTANDARDRIGHT,
                                    'plugin_rp_liste'                   => READ | UPDATE | PURGE,
                                    // Fermé par défaut : la supervision expose ce
                                    // qui n'a pas été fait, elle s'ouvre
                                    // volontairement, profil par profil.
                                    'plugin_rp_supervision'             => 0,
                                    'plugin_rp_boutons'                 => READ | UPDATE,
                                    'plugin_rp_Signature'               => ALLSTANDARDRIGHT], true);

   }

   static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }

   static function removeRightsFromDB() {
      $plugprof = new ProfileRight();
      foreach (self::getAllRights(true) as $right) {
         $plugprof->deleteByCriteria(['name' => $right['field']]);
      }
   }

   /**
    * @param $profile
    **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {

      $profileRight = new ProfileRight();
      $dbu          = new DbUtils();

      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights',
                                        ["profiles_id" => $profiles_id,
                                         "name"        => $right]) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable('glpi_profilerights',
                                         ["profiles_id" => $profiles_id,
                                          "name"        => $right])) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }
}
