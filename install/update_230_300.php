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

/**
 * Update from 2.1.4 to 2.1.5
 *
 * @return bool for success (will die for most error)
 * */
function update230to300() {
   global $DB;

      $query= "ALTER TABLE glpi_plugin_rp_configs ADD gabarit INT(10)";
      $DB->query($query) or die($DB->error()); // pour version 3.0.0

      $DB->runFile(PLUGIN_RP_DIR . "/install/sql/empty-add-NotificationMail.sql");

      $ID = $DB->query("SELECT id FROM glpi_notificationtemplates WHERE NAME = 'Rapport PDF' AND comment = 'Created by the plugin RP'")->fetch_object();

      $query= "UPDATE glpi_plugin_rp_configs SET gabarit = $ID->id WHERE id=1;";
      $DB->query($query) or die($DB->error()); // pour version 3.0.0
}
  
   




?>
