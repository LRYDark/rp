<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Rp plugin for GLPI
 Copyright (C) 2014-2022 by the Rp Development Team.

 https://github.com/InfotelGLPI/rp
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Rp.

 Rp is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Rp is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Rp. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

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
      echo "<tr><th colspan='2'>" . __('Options', 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Affichage du temps de trajet dans les rapports', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("time", $this->fields["time"]);
         echo "</td></tr>";
            echo "<tr class='tab_bg_1 center'><td colspan='2'>
               <span style=\"font-weight:bold; color:red\">" . __("Attention : L'utilisation du temps de trajet nécessite le plugin « rt ».", 'rp') . "</td></span></tr>";
            echo "<tr class='tab_bg_2 center'><td colspan='2'>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Enregistrement de plusieurs rapports', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("multi_doc", $this->fields["multi_doc"]);
         echo "</td></tr>";
         
         if($this->fields["multi_doc"] == 1){
            if($this->fields["multi_display"] == 0){
               $DB->query("UPDATE glpi_plugin_rp_configs SET multi_display = 5 WHERE id = 1");
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
            $DB->query("UPDATE glpi_plugin_rp_configs SET multi_display = 0 WHERE id = 1");
         }
            echo "<tr class='tab_bg_1 center'><td colspan='2'>
               <span style=\"font-weight:bold; color:red\">" . __("Attention : si vous interdisez l'enregistrement de plusieurs rapport, cela écrasera le dernier rapport généré pour le remplacer.", 'rp') . "</td></span></tr>";
            echo "<tr class='tab_bg_2 center'><td colspan='2'>";

      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
      echo "<tr><th colspan='2'>" . __('Options de génération du PDF', 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Seul les tâches et suivis publics sont visible lors de la génération', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("use_publictask", $this->fields["use_publictask"]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Permettre la séléction des tâches et suivis avant la génération', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("choice", $this->fields["choice"]);
         echo "</td></tr>";

         if ($this->fields["choice"] == 1){
            echo "<tr class='tab_bg_1 top'><td>" . __("Les tâches et suivis publics sont cochés par défaut", 'rp') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("check_public", $this->fields["check_public"]);
            echo "</td></tr>";
      
            if ($this->fields["use_publictask"] == 0){
               echo "<tr class='tab_bg_1 top'><td>" . __("Les tâches et suivis privés sont cochés par défaut", 'rp') . "</td>";
               echo "<td>";
               Dropdown::showYesNo("check_private", $this->fields["check_private"]);
               echo "</td></tr>";
            }else{
               $DB->query("UPDATE glpi_plugin_rp_configs SET check_private = 0 WHERE id = 1");
            }
         }else{
            $DB->query("UPDATE glpi_plugin_rp_configs SET check_public = 0, check_private = 0 WHERE id = 1");
         }

      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
      echo "<tr><th colspan='2'>" . __('Options de signature', 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur la prise en charge', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_charge", $this->fields["sign_rp_charge"]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur le rapport technicien', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_tech", $this->fields["sign_rp_tech"]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __('Signature sur le rapport hotline', 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("sign_rp_hotl", $this->fields["sign_rp_hotl"]);
         echo "</td></tr>";

      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
      echo "<tr><th colspan='2'>" . __("Options d'envoi par mail", 'rp') . "</th></tr>";

         echo "<tr class='tab_bg_1 top'><td>" . __("Possiblité d'envoyer par email le PDF", 'rp') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("email", $this->fields["email"]);
         echo "</td></tr>";

      echo Html::hidden('id', ['value' => 1]);
      echo "<tr class='tab_bg_2 center'><td colspan='2'>";
      echo Html::submit(_sx('button', 'Save'), ['name' => 'update_config', 'class' => 'btn btn-primary']);

      echo "</td></tr>";
      echo "</table></div>";
      Html::closeForm();
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
