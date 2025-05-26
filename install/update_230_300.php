<?php
include ('../../../inc/includes.php');
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

      // Requête pour vérifier l'existence de la colonne
      $result = $DB->query("SHOW COLUMNS FROM glpi_plugin_rp_configs LIKE 'gabarit';")->fetch_object();

      // Vérification du résultat
         if (!empty($result->Field)) {
            $existeColumn = true;
         } else {
            $existeColumn = false;
         }
      
      if($existeColumn == false){
         $query= "ALTER TABLE glpi_plugin_rp_configs ADD gabarit INT(10)";
         $DB->query($query) or die($DB->error()); // pour version 3.0.0

         //$DB->runFile(PLUGIN_RP_DIR . "/install/sql/empty-add-NotificationMail.sql");
         // Préparer le contenu HTML
         $content_html = '
            &#60;div class="es-wrapper-color" dir="ltr" lang="fr" style="background-color: #f6f6f6;"&#62;
            &#60;table class="es-wrapper" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; padding: 0; margin: 0; width: 100%; height: 100%; background-repeat: repeat; background-position: center top; background-color: #f6f6f6;" role="none" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" valign="top"&#62;
            &#60;table class="es-header" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; width: 100%; table-layout: fixed !important; background-color: transparent; background-repeat: repeat; background-position: center top;" role="none" cellspacing="0" cellpadding="0" align="center"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="center"&#62;
            &#60;table class="es-header-body" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; background-color: transparent; width: 600px;" role="none" cellspacing="0" cellpadding="0" align="center" bgcolor="transparent"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="margin: 0; background-position: left top; padding: 20px;" align="left"&#62;
            &#60;table class="es-left" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; float: left;" role="none" cellspacing="0" cellpadding="0" align="left"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td class="es-m-p20b" style="padding: 0; margin: 0; width: 270px;" align="left"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="presentation" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; font-size: 0px;" align="center"&#62;&#60;img class="adapt-img" style="display: block; font-size: 14px; border: 0; outline: none; text-decoration: none;" src="https://flvhveh.stripocdn.email/content/guids/CABINET_44164322675628a7251e1d7d361331e9/images/logoeasisupportnew.png" alt="" width="270"&#62;&#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;table class="es-right" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; float: right;" role="none" cellspacing="0" cellpadding="0" align="right"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; width: 270px;" align="left"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="none" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; display: none;" align="center"&#62; &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;table class="es-content" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; width: 100%; table-layout: fixed !important;" role="none" cellspacing="0" cellpadding="0" align="center"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="center"&#62;
            &#60;table class="es-content-body" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; background-color: #ffffff; width: 600px;" role="none" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="margin: 0; background-color: transparent; padding: 20px 20px 10px 20px;" align="left" bgcolor="transparent"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="none" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; width: 560px;" align="center" valign="top"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; background-position: left top;" role="presentation" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td class="es-m-txt-l" style="padding: 0; margin: 0; padding-bottom: 10px;" align="center"&#62;
            &#60;h2 style="margin: 0; font-family: arial, \'helvetica neue\', helvetica, sans-serif; mso-line-height-rule: exactly; letter-spacing: 0; font-size: 24px; font-style: normal; font-weight: normal; line-height: 28.8px; color: #333333;"&#62;&#60;strong&#62;##rapport.type.titel##&#60;/strong&#62;&#60;/h2&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;tr&#62;
            &#60;td style="padding: 20px; margin: 0;" align="center"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0px; border-bottom: 1px solid #cccccc; background: none; height: 1px; width: 100%;"&#62; &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;tr&#62;
            &#60;td class="es-m-txt-l" style="padding: 0; margin: 0; padding-bottom: 10px;" align="left"&#62;
            &#60;h2 style="margin: 0; font-family: arial, \'helvetica neue\', helvetica, sans-serif; mso-line-height-rule: exactly; letter-spacing: 0; font-size: 24px; font-style: normal; font-weight: normal; line-height: 28.8px; color: #333333;"&#62;Chère cliente, cher client,&#60;/h2&#62;
            &#60;p style="margin: 0; mso-line-height-rule: exactly; font-family: arial, \'helvetica neue\', helvetica, sans-serif; line-height: 21px; letter-spacing: 0; color: #333333; font-size: 14px;"&#62;&#60;br&#62;Veuillez trouver ci-joint ##rapport.type## en date du ##rapport.date.creation##&#60;/p&#62;
            &#60;p style="margin: 0; mso-line-height-rule: exactly; font-family: arial, \'helvetica neue\', helvetica, sans-serif; line-height: 21px; letter-spacing: 0; color: #333333; font-size: 14px;"&#62;Vous trouverez l’ensemble des informations sur le lien suivant : ##ticket.url##&#60;br&#62;&#60;br&#62;Sujet du ticket : ##ticket.title##&#60;br&#62;Numéro du Ticket : ##ticket.id##&#60;/p&#62;
            &#60;p style="margin: 0; mso-line-height-rule: exactly; font-family: arial, \'helvetica neue\', helvetica, sans-serif; line-height: 21px; letter-spacing: 0; color: #333333; font-size: 14px;"&#62;Le PDF en ligne : ##document.weblink##&#60;/p&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;table class="es-content" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; width: 100%; table-layout: fixed !important;" role="none" cellspacing="0" cellpadding="0" align="center"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="center"&#62;
            &#60;table class="es-content-body" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; background-color: #ffffff; width: 600px;" role="none" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="left"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="none" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; width: 600px;" align="center" valign="top"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="presentation" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="left"&#62;
            &#60;p style="margin: 0; mso-line-height-rule: exactly; font-family: arial, \'helvetica neue\', helvetica, sans-serif; line-height: 21px; letter-spacing: 0; color: #333333; font-size: 14px;"&#62;Cordialement,&#60;/p&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;table class="es-content" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; width: 100%; table-layout: fixed !important;" role="none" cellspacing="0" cellpadding="0" align="center"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="center"&#62;
            &#60;table class="es-content-body" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; background-color: #ffffff; width: 600px;" role="none" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="margin: 0; padding: 20px;" align="left"&#62;
            &#60;table class="es-left" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; float: left;" role="none" cellspacing="0" cellpadding="0" align="left"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td class="es-m-p20b" style="padding: 0; margin: 0; width: 180px;" align="left"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="presentation" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; font-size: 0px;" align="left"&#62;&#60;img class="adapt-img" style="display: block; font-size: 14px; border: 0; outline: none; text-decoration: none;" src="https://flvhveh.stripocdn.email/content/guids/CABINET_44164322675628a7251e1d7d361331e9/images/logo_jcd_54G.png" alt="" width="80"&#62;&#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="none" cellspacing="0" cellpadding="0" align="right"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; width: 360px;" align="left"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="presentation" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="left"&#62;
            &#60;p style="margin: 0; mso-line-height-rule: exactly; font-family: arial, \'helvetica neue\', helvetica, sans-serif; line-height: 16.8px; letter-spacing: 0; color: #333333; font-size: 14px;"&#62;&#60;br&#62;&#60;br&#62;&#60;strong&#62;L’équipe Easi Support&#60;/strong&#62;&#60;/p&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;table class="es-content" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; width: 100%; table-layout: fixed !important;" role="none" cellspacing="0" cellpadding="0" align="center"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="center"&#62;
            &#60;table class="es-content-body" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px; background-color: #ffffff; width: 600px;" role="none" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="margin: 0; padding: 20px;" align="left"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="none" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0; width: 560px;" align="center" valign="top"&#62;
            &#60;table style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; border-spacing: 0px;" role="presentation" width="100%" cellspacing="0" cellpadding="0"&#62;
            &#60;tbody&#62;
            &#60;tr&#62;
            &#60;td style="padding: 0; margin: 0;" align="left"&#62;
            &#60;p style="margin: 0; mso-line-height-rule: exactly; font-family: arial, \'helvetica neue\', helvetica, sans-serif; line-height: 18px; letter-spacing: 0; color: #333333; font-size: 12px;"&#62;&#60;br&#62;&#60;em&#62;Ce courrier électronique est envoyé automatiquement par le centre de service Easi Support.&#60;/em&#62;&#60;br&#62;&#60;br&#62;&#60;br&#62;Généré automatiquement par GLPI.&#60;/p&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/td&#62;
            &#60;/tr&#62;
            &#60;/tbody&#62;
            &#60;/table&#62;
            &#60;/div&#62;
         ';
         // Échapper le contenu HTML
         $content_html_escaped = Toolbox::addslashes_deep($content_html);

         // Construire la requête d'insertion
         $insertQuery1 = "INSERT INTO `glpi_notificationtemplates` (`name`, `itemtype`, `date_mod`, `comment`, `css`, `date_creation`) VALUES ('Rapport PDF', 'Ticket', NULL, 'Created by the plugin RP', '', NULL);";
         // Exécuter la requête
         $DB->query($insertQuery1);

         // Construire la requête d'insertion
         $insertQuery2 = "INSERT INTO `glpi_notificationtemplatetranslations` 
            (`notificationtemplates_id`, `language`, `subject`, `content_text`, `content_html`) 
            VALUES (LAST_INSERT_ID(), 'fr_FR', '[GLPI ###ticket.id##] | ##rapport.type.titel## ', '', '{$content_html_escaped}')";
         // Exécuter la requête
         $DB->query($insertQuery2);

         $ID = $DB->query("SELECT id FROM glpi_notificationtemplates WHERE NAME = 'Rapport PDF'")->fetch_object();

         $query= "UPDATE glpi_plugin_rp_configs SET gabarit = $ID->id WHERE id=1;";
         $DB->query($query) or die($DB->error()); // pour version 3.0.0
      }
}
  
?>
