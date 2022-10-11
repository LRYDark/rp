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
            <span style=\"font-weight:bold; color:red\">" . __("Attention : L'utilisation du temps de trajet nécessite le plugin << rt >>.", 'rp') . "</td></span></tr>";
         echo "<tr class='tab_bg_2 center'><td colspan='2'>";

      echo "<tr class='tab_bg_1 top'><td>" . __('Seul les tâches publique sont visible lors de la génération', 'rp') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("use_publictask", $this->fields["use_publictask"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 top'><td>" . __('Affichage et enregistrement de plusieurs rapports', 'rp') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("multi_doc", $this->fields["multi_doc"]);
      echo "</td></tr>";
         echo "<tr class='tab_bg_1 center'><td colspan='2'>
            <span style=\"font-weight:bold; color:red\">" . __("Attention : si vous interdisez l'affichage, cela fera office d'update sur le dernier rapport généré pour en affiché seulement 1", 'rp') . "</td></span></tr>";
         echo "<tr class='tab_bg_2 center'><td colspan='2'>";

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
