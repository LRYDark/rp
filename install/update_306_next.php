<?php
//include ('../../../inc/includes.php');
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
 * Update from 1.4.4 to next version
 *
 * @return bool for success (will die for most error)
 * */
function update_306_next() {
   global $DB;

   // Vérifier si les colonnes existent déjà
   $columns = $DB->query("SHOW COLUMNS FROM `glpi_plugin_rp_configs`")->fetch_all(MYSQLI_ASSOC);

   // Liste des colonnes à vérifier
   $required_columns = [
      'logo_id2',
      'line3',
      'line4',
      'entity_parrent1',
      'entity_parrent2',
      'color1',
      'color2',
      'color_text1',
      'color_text2'
   ];

   // Liste pour les colonnes manquantes
   $missing_columns = array_diff($required_columns, array_column($columns, 'Field'));

   if (!empty($missing_columns)) {
      $query= "ALTER TABLE glpi_plugin_rp_configs
               ADD COLUMN `logo_id2` INT(10),
               ADD COLUMN `line3` VARCHAR(255) NULL,
               ADD COLUMN `line4` VARCHAR(255) NULL,
               ADD COLUMN `color1` VARCHAR(255) NOT NULL DEFAULT '#2980b9',
               ADD COLUMN `color2` VARCHAR(255) NOT NULL DEFAULT '#8ab021',
               ADD COLUMN `color_text1` VARCHAR(255) NOT NULL DEFAULT '#ffffff',
               ADD COLUMN `color_text2` VARCHAR(255) NOT NULL DEFAULT '#ffffff',
               ADD COLUMN `entity_parrent1` INT(10),
               ADD COLUMN `entity_parrent2` INT(10);";
      $DB->query($query) or die($DB->error());
   }
}
  
?>
