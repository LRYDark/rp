<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpConfig extends CommonDBTM {

   private static $instance;

   function showConfigForm() {

      global $DB, $CFG_GLPI;
      echo "<form name='form' method='post' action='" .
           Toolbox::getItemTypeFormURL('PluginRpConfig') . "'>";

         echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
         echo "<tr><th colspan='2'>" . __('Configuration Mail', 'rp') . "</th></tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td> Gabarit : Modèle de notifications </td>";
         echo "<td>";

         //notificationtemplates_id
         Dropdown::show('NotificationTemplate', [
            'name' => 'gabarit',
            'value' => $this->fields["gabarit"],
            'display_emptychoice' => 1,
            'specific_tags' => [],
            'itemtype' => 'NotificationTemplate',
            'displaywith' => [],
            'emptylabel' => "-----",
            'used' => [],
            'toadd' => [],
            'entity_restrict' => 0,
         ]); 
         echo "</td></tr>";

         // balises prise en charge
            echo "<tr class='tab_bg_1'><td> <b>Balises prisent en charge :</b>  </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##document.weblink##  </td><td> Document : Lien web (PDF) </td></tr>";
            echo "<tr class='tab_bg_1'><td>  ##ticket.id##  </td><td> ticket : ID </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.url##   </td><td> ticket : URL </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.creationdate##  </td><td> ticket : Date d'ouverture </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.closedate##  </td><td> ticket : Date de clôture </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##task.time##  </td><td> Tâche  : Durée des taches séléctioné pour le rapport </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.description##  </td><td> Ticket : Description </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.entity.address##   </td><td> Entité (Adresse) </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.entity##  </td><td> Entité (Nom complet) </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.category##  </td><td> ticket : Catégorie </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.time##  </td><td> ticket : Durée totale </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##ticket.title##  </td><td> ticket : Titre </td></tr>";
            echo "<tr class='tab_bg_1'><td> <b>Nouvelles balises :</b>  </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##rapport.type.titel##  </td><td> rapport : Type(Titre) </td></tr>";
            echo "<tr class='tab_bg_1'><td>  ##rapport.type##  </td><td> rapport : Type </td></tr>";
            echo "<tr class='tab_bg_1'><td> ##rapport.date.creation##  </td><td> rapport : date de création du rapport </td></tr>";
         // balises prise en charge
      
         echo "<tr><th colspan='2'>" . __('Options', 'rp') . "</th></tr>";

         /*echo "<tr class='tab_bg_1'>";
         echo "<td> Token GitHub </td>";
         echo "<td>";
         echo Html::input('token', ['value' => $this->fields['token'], 'size' => 60, 'maxlength' => 80]); // bouton / token github
         echo "</td>";*/

         echo "<tr class='tab_bg_1 top'><td>" . __('Affichage du temps de trajet dans les rapports technicien', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("time", $this->fields["time"]); // bouton d'affchage du temps de trajet pour les rapports tech
         echo "</td></tr>";
         echo "<tr class='tab_bg_1 top'><td>" . __('Affichage du temps de trajet supérieur à 0 dans les rapports hotline', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("time_hotl", $this->fields["time_hotl"]); // bouton d'affchage du temps de trajet pour les rapports hotline
         echo "</td></tr>";
            echo "<tr class='tab_bg_1 center'><td colspan='2'>
               <span style=\"font-weight:bold; color:red\">" . __("Attention : L'utilisation du temps de trajet nécessite le plugin « rt ».", 'rp') . "</td></span></tr>";
            echo "<tr class='tab_bg_2 center'><td colspan='2'>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Enregistrement de plusieurs rapports', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("multi_doc", $this->fields["multi_doc"]); // bouton de possiblité de créé plusieurs rapport
         echo "</td></tr>";
         
         if($this->fields["multi_doc"] == 1){ //si plusieurs rapports = oui alors on laisse la possibilité d'afficher un nombre de rapport voulu sur l'ecran des rapports dans le ticket / max = 20
            if($this->fields["multi_display"] == 0){
               $DB->doQuery("UPDATE glpi_plugin_rp_configs SET multi_display = 5 WHERE id = 1"); // update si plusieurs rapports = oui on mais affichage de rapport sur 5 par defaut
               $this->fields["multi_display"] = 5;
            }
               echo "<tr class='tab_bg_1 top'><td>" . __('Nombre(s) de rapport(s) affiché(s)', 'rp') . "</td>";
               echo "<td>";
               Dropdown::showNumber("multi_display", ['value' => $this->fields["multi_display"],
                                                      'min'   => 1,
                                                      'max'   => 20,
                                                      'step'  => 1]);
               echo "</td></tr>";
         }else{
            $DB->doQuery("UPDATE glpi_plugin_rp_configs SET multi_display = 0 WHERE id = 1");
         }
            echo "<tr class='tab_bg_1 center'><td colspan='2'>
               <span style=\"font-weight:bold; color:red\">" . __("Attention : si vous interdisez l'enregistrement de plusieurs rapport, cela écrasera le dernier rapport généré pour le remplacer.", 'rp') . "</td></span></tr>";
            echo "<tr class='tab_bg_2 center'><td colspan='2'>";

      echo "<tr><th colspan='2'>" . __('Options de génération du PDF', 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("L'affichage des images pour les tâches sont cochés par défaut", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("ImgTasks", $this->fields["ImgTasks"]); // bouton d'affchage des tâches et suivis publics uniquement
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("L'affichage des images pour les suivis sont cochés par défaut", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("ImgSuivis", $this->fields["ImgSuivis"]); // bouton affichage de la séléction des tâches et suivis
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("Désactiver la date de création dans l'entête du PDF", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("date", $this->fields["date"]); // bouton d'affchage de la date dans le pdf
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Seul les tâches et suivis publics sont visible lors de la génération', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("use_publictask", $this->fields["use_publictask"]); // bouton d'affchage des tâches et suivis publics uniquement
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Permettre la séléction des tâches et suivis avant la génération', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("choice", $this->fields["choice"]); // bouton affichage de la séléction des tâches et suivis
         echo "</td></tr>";

         //taches 
         if ($this->fields["choice"] == 1){
            echo "<tr class='tab_bg_1 top'><td>" . __("Les tâches publics sont cochés par défaut", 'rp') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("check_public_task", $this->fields["check_public_task"]);
            echo "</td></tr>";
      
            if ($this->fields["use_publictask"] == 0){
               echo "<tr class='tab_bg_1 top'><td>" . __("Les tâches privés sont cochés par défaut", 'rp') . "</td>";
               echo "<td>";
               Dropdown::showYesNo("check_private_task", $this->fields["check_private_task"]);
               echo "</td></tr>";
               
            }else{
               $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_private_task = 0 WHERE id = 1"); // update si tâches et suivis publics visible = non
            }
         }else{
            $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_public_task = 0, check_private = 0 WHERE id = 1"); // update si Permettre la séléction des tâches et suivis = non
         }

         //suivis
         if ($this->fields["choice"] == 1){
            echo "<tr class='tab_bg_1 top'><td>" . __("Les suivis publics sont cochés par défaut", 'rp') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("check_public_suivi", $this->fields["check_public_suivi"]);
            echo "</td></tr>";
      
            if ($this->fields["use_publictask"] == 0){
               echo "<tr class='tab_bg_1 top'><td>" . __("Les suivis privés sont cochés par défaut", 'rp') . "</td>";
               echo "<td>";
               Dropdown::showYesNo("check_private_suivi", $this->fields["check_private_suivi"]);
               echo "</td></tr>";
               
            }else{
               $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_private_suivi = 0 WHERE id = 1"); // update si tâches et suivis publics visible = non
            }
         }else{
            $DB->doQuery("UPDATE glpi_plugin_rp_configs SET check_public_suivi = 0, check_private = 0 WHERE id = 1"); // update si Permettre la séléction des tâches et suivis = non
         }

      echo "<tr><th colspan='2'>" . __('Options de génération du PDF Massives Actions', 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Seul les tâches et suivis publics sont visible lors de la génération des PDF avec massives actions', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("use_publictask_massaction", $this->fields["use_publictask_massaction"]); // bouton d'affchage des tâches et suivis publics uniquement
         echo "</td></tr>";

      echo "<tr><th colspan='2'>" . __('Options de signature', 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur la prise en charge', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_charge", $this->fields["sign_rp_charge"]); // bouton fonctionnalité affichage ou non de la signature prise en charge
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur le rapport technicien', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_tech", $this->fields["sign_rp_tech"]); // bouton fonctionnalité affichage ou non de la signature tech
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur le rapport hotline', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_hotl", $this->fields["sign_rp_hotl"]); // bouton fonctionnalité affichage ou non de la signature hotline
         echo "</td></tr>";

      echo "<tr><th colspan='2'>" . __("Options d'envoi par mail", 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("Possiblité d'envoyer par email le PDF", 'rp') . "</td>"; // bouton fonctionnalité envoie mail
         echo "<td>";
         Dropdown::showYesNo("email", $this->fields["email"]);
         echo "</td></tr>";

      echo "<tr><th colspan='2'>" . __("Titre des rapports", 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Prise en charge </td>";
         echo "<td>";
         echo Html::input('titel_pc', ['value' => $this->fields['titel_pc'], 'size' => 40, 'maxlength' => 25]); // bouton / titre de la prise en charge 
         echo "</td>";
         echo "<td></td><td></td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Rapport technicien </td>";
         echo "<td>";
         echo Html::input('titel_rt', ['value' => $this->fields['titel_rt'], 'size' => 40, 'maxlength' => 25]); // bouton / titre du rapport technicien
         echo "</td>";
         echo "<td></td><td></td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> Rapport hotline </td>";
         echo "<td>";
         echo Html::input('titel_rh', ['value' => $this->fields['titel_rh'], 'size' => 40, 'maxlength' => 25]); // bouton / titre du rapport hotline
         echo "</td>";
         echo "<td></td><td></td></tr>";

   // Logo config taille et bas de de page ------------------------------------------------------
      echo "<tr><th colspan='2'>" . __("Configuration du bas de page et du logo", 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> 1er ligne du bas de page </td>";
         echo "<td>";
         echo Html::input('line1', ['value' => $this->fields['line1'], 'size' => 60, 'maxlength' => 80]);// bouton configuration du bas de page line 1
         echo "</td>";
         echo "<td></td><td></td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td> 2ème ligne du bas de page </td>";
         echo "<td>";
         echo Html::input('line2', ['value' => $this->fields['line2'], 'size' => 60, 'maxlength' => 80]); // bouton configuration du bas de page line 2
         echo "</td>";
         echo "<td></td><td></td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Marge à gauche du logo', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showNumber("margin_left", ['value' => $this->fields["margin_left"], // bouton configuration de la marge a gauche
                                                'min'   => 1,
                                                'max'   => 60,
                                                'step'  => 1]);
         echo " dpi </td></tr>";
         echo "<tr class='tab_bg_1 top'><td>" . __('Marge au dessus du logo', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showNumber("margin_top", ['value' => $this->fields["margin_top"], // bouton configuration de la marge au dessus
                                                'min'   => 1,
                                                'max'   => 60,
                                                'step'  => 1]);
         echo " dpi </td></tr>";
         echo "<tr class='tab_bg_1 top'><td>" . __('Taille du logo', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showNumber("cut", ['value' => $this->fields["cut"], // bouton configuration de la taille du logo
                                                'min'   => 1,
                                                'max'   => 60,
                                                'step'  => 1]);
         echo " dpi </td></tr>";
   // Logo config taille et bas de de page ------------------------------------------------------
         
         echo Html::hidden('id', ['value' => 1]); // revoie l'id 1 dans la methode post (id = 1 car la bdd config comptient que 1 seul ligne)
         echo "<tr class='tab_bg_2 center'><td colspan='2'>";
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update_config', 'class' => 'btn btn-primary']); // bouton save
         echo "</td></tr>";
      echo "</table></div>";

      Html::closeForm();

   // Logo index
      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
         echo "<tr><th colspan='3'>" . __("LOGO", 'rp') . "</th></tr>";
         echo "<tr class='tab_bg_1'>";
            echo "<td width='35%'>";

            $realpath = realpath('../../../'); // recupére le chemin racine du site
            $realpath = explode('\\', $realpath); // enregiste le chemin sous forme de tableau 
            $realpath = end($realpath); // recupére le nom du dossier racine du site (le dernier nom du tableau si dessus)

            $doc = new Document();
            $img = $doc->find(['id' => $this->fields['logo_id']]); // explore et recupére les values bdd comptenu dans document a la ligne id = logo_id enregistré en base config 
            $img = reset($img); // remet le curseur au debut du tableau ci dessus
            if(isset($img['filepath'])){ // verification que la varible soit non vide
               $file_exists = GLPI_DOC_DIR.'/'.$img['filepath']; // chemmin ficher
               if(file_exists($file_exists)){ // verification de l'existance du fichier
                  if(strtoupper(substr(PHP_OS,0,3))==='WIN'){ // verification de system sur le quel tourne le site
                     $fichier = '/'.$realpath.'/front/document.send.php?docid='.$this->fields["logo_id"]; // fichier sous windows
                     echo "<img src='$fichier' height='110' />";
                  }else {
                     $fichier = '/front/document.send.php?docid='.$this->fields["logo_id"]; // fichier sous un autre system
                     echo "<img src='$fichier' height='110' />";
                  }
               }else echo 'Aucun logo';
            }else echo 'Aucun logo';
            echo "</td>";
            echo "<td>";
               echo "<form action='../front/uplogo.php' method='post' enctype='multipart/form-data' class='fileupload'> 
                     <input type='file' name='photo' size='25' /><p><br>
                     <input class='submit' type='submit' name='submit' value='".__('Send')."' />"; // formulaire d'enregistrement du logo
            echo "</td>";
         echo "<td></td><td></td></tr>";
      echo "</table></div>";
      Html::closeForm(); 
	// Logo index	
   }

   public static function getInstance() {
      if (!isset(self::$instance)) {
         $temp = new PluginRpConfig();
         $temp->getFromDB('1');
         self::$instance = $temp;
      }

      return self::$instance;
   }
}
