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
function update_310_next() {
   global $DB;

   // Vérifier le type actuel de la colonne
   $query = "SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'glpi_plugin_rp_signtech' 
               AND COLUMN_NAME = 'seing';";

   $result = $DB->doQuery($query);
   if ($result && $DB->numrows($result) > 0) {
      $row = $DB->fetcharray($result);
      $columnType = strtolower($row['COLUMN_TYPE']);

      // Si ce n'est pas déjà MEDIUMTEXT, on modifie
      if ($columnType !== 'mediumtext') {
         $alter = "ALTER TABLE glpi_plugin_rp_signtech 
                     MODIFY COLUMN `seing` MEDIUMTEXT NOT NULL;";
         $DB->doQuery($alter) or die($DB->error());
      }
   }

   //---------------------------------
   $columns = $DB->doQuery("SHOW COLUMNS FROM `glpi_plugin_rp_signtech`")->fetch_all(MYSQLI_ASSOC);

   // Liste des colonnes à vérifier
   $required_columns = [
      'version'
   ];

   // Liste pour les colonnes manquantes
   $missing_columns = array_diff($required_columns, array_column($columns, 'Field'));

   if (!empty($missing_columns)) {
      $query= "ALTER TABLE glpi_plugin_rp_signtech
               ADD COLUMN `version` tinyint(4) NOT NULL DEFAULT 1;";
      $DB->doQuery($query) or die($DB->error());
   }
}
  
?>
