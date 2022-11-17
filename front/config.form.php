<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Manageentities plugin for GLPI
 Copyright (C) 2014-2022 by the Manageentities Development Team.

 https://github.com/InfotelGLPI/manageentities
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Manageentities.

 Manageentities is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Manageentities is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Manageentities. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

if (Plugin::isPluginActive("rp")) {
   if (Session::haveRight("plugin_rp", UPDATE)) {
      $config = new PluginRpConfig();

      if (isset($_POST["update_config"])) {
         Session::checkRight("config", UPDATE);
         $config->update($_POST);
         Html::back();

      } else {
         Html::header(__('Setup'), '', "config", "plugins");
         $config->GetFromDB(1);
         $config->showConfigForm();

         Html::footer();
      }

   } else {
      Html::header(__('Setup'), '', "config", "plugins");
      echo "<div class='alert alert-important alert-warning d-flex'>";
      echo "<b>" . __("You don't have permission to perform this action.") . "</b></div>";
      Html::footer();
   }

} else {
   Html::header(__('Setup'), '', "config", "plugins");
   echo "<div class='alert alert-important alert-warning d-flex'>";
   echo "<b>" . __('Please activate the plugin', 'rp') . "</b></div>";
   Html::footer();
}
