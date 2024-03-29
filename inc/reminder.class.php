<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 RP plugin for GLPI
 Copyright (C) 2016-2022 by the RP Development Team.

 https://github.com/pluginsglpi/RP
 -------------------------------------------------------------------------

 LICENSE

 This file is part of RP.

 RP is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 RP is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with RP. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */


if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Class PluginSatisfactionSurvey
 *
 * Used to store reminders to send automatically
 */
class PluginRpRapport extends CommonDBTM {

   static $rightname = "plugin_rp";
   //public $dohistory = true;

   //public static $itemtype = TicketSatisfaction::class;
   //public static $items_id = 'ticketsatisfactions_id';

   const CRON_TASK_NAME = 'RpRapport';

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {
      return _n('Rp Rapport', 'Rp Rapport', $nb, 'RP');
   }

   ////// CRON FUNCTIONS ///////

   /**
    * @param $name
    *
    * @return array
    */
   static function cronInfo($name) {

      switch ($name) {
         case self::CRON_TASK_NAME:
            return ['description' => __('Envoie automatique des rapports PDF ', 'RP')];   // Optional
            break;
      }
      return [];
   }

   /**
    * Cron action
    *
    * @param  $task for log
    *
    * @global $CFG_GLPI
    *
    * @global $DB
    */
   static function cronRpRapport($task = NULL) {
      global $DB, $CFG_GLPI;

      /*function message($msg, $msgtype){
         Session::addMessageAfterRedirect(
             __($msg, 'rp'),
             true,
             $msgtype
         );
      }*/
         // génération du mail 
         $mmail = new GLPIMailer();

         // For exchange
            $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");

         if (empty($CFG_GLPI["from_email"])){
            // si mail expediteur non renseigné    
            $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
         }else{
            //si mail expediteur renseigné  
            $mmail->SetFrom($CFG_GLPI["from_email"], $CFG_GLPI["from_email_name"], false);
         }

         $mmail->AddAddress('lrydark93@gmail.com');
         $mmail->isHTML(true);

         // Objet et sujet du mail 
         $mmail->Subject = ('TEST');
         $mmail->Body = GLPIMailer::normalizeBreaks('Test mail auto RAPPORT PDF');


            // envoie du mail
            if(!$mmail->send()) {
                  message("Erreur lors de l'envoi du mail : " . $mmail->ErrorInfo, ERROR);
            }else{
                  message("<br>Mail envoyé à " . $EMAIL, INFO);
            }
            
         $mmail->ClearAddresses();
      
      /*if ($CronTask->getFromDBbyName(PluginRpRapport::class, PluginRpRapport::CRON_TASK_NAME)) {
         if ($CronTask->fields["state"] == CronTask::STATE_DISABLE) {
            return 0;
         }
      } else {
         return 0;
      }

      ?><script>
         // Code JavaScript pour écrire dans la console ***************************************************************************************************************************
         console.log("cronRpRapport");
      </script><?php*/

      self::sendReminders();
   }

   static function sendReminders() {

      $entityDBTM = new Entity();

      //$pluginSatisfactionSurveyDBTM         = new PluginSatisfactionSurvey();
      //$pluginSatisfactionSurveyReminderDBTM = new PluginSatisfactionSurveyReminder();
      $pluginRpRapportDBTM       = new PluginRpRapport();

      $surveys = $pluginSatisfactionSurveyDBTM->find(['is_active' => true]);

      foreach ($surveys as $survey) {

         // Entity
         $entityDBTM->getFromDB($survey['entities_id']);

         // Don't get tickets RP with date older than max_close_date
         // $max_close_date = date('Y-m-d', strtotime($entityDBTM->getField('max_closedate')));
         $nb_days = $survey['reminders_days'];
         $dt             = date("Y-m-d");
         $max_close_date = date('Y-m-d', strtotime("$dt - ".$nb_days." day"));

         // Ticket Rp 
         $ticketSatisfactions = self::getTicketSatisfaction($max_close_date, null, $survey['entities_id']);

         ?><script>
            // Code JavaScript pour écrire dans la console ***************************************************************************************************************************
            console.log("send reminders 1");
         </script><?php
         
 
         foreach ($ticketSatisfactions as $k => $ticketSatisfaction) {

            // Survey Reminders
            $surveyReminderCrit = [
               'plugin_satisfaction_surveys_id' => $survey['id'],
               'is_active'                      => 1,
            ];
            $surveyReminders    = $pluginSatisfactionSurveyReminderDBTM->find($surveyReminderCrit);

            $potentialReminderToSendDates = [];

            ?><script>
               // Code JavaScript pour écrire dans la console ***************************************************************************************************************************
               console.log("send reminders 2");
            </script><?php

            // Calculate the next date of next reminders
            foreach ($surveyReminders as $surveyReminder) {

               $reminders = null;
               $reminders = $pluginRpRapportDBTM->find(['tickets_id' => $ticketSatisfaction['tickets_id'],
                                                                   'type'       => $surveyReminder['id']]);

               ?><script>
                  // Code JavaScript pour écrire dans la console ***************************************************************************************************************************
                  console.log("send reminders 3");
               </script><?php

               if (count($reminders)) {
                  continue;
               } else {
                  ?><script>
                     // Code JavaScript pour écrire dans la console ***************************************************************************************************************************
                     console.log("send reminders 4");
                  </script><?php

                  $lastSurveySendDate = date('Y-m-d', strtotime($ticketSatisfaction['date_begin']));

                  // Date when glpi RP was sended for the first time
                  $reminders_to_send = $pluginRpRapportDBTM->find(['tickets_id' => $ticketSatisfaction['tickets_id']]);
                  if (count($reminders_to_send)) {
                     $Rapport           = array_pop($reminders_to_send);
                     $lastSurveySendDate = date('Y-m-d', strtotime($Rapport['date']));
                  }

                  $date = null;

                  switch ($surveyReminder[PluginSatisfactionSurveyReminder::COLUMN_DURATION_TYPE]) {

                     case PluginSatisfactionSurveyReminder::DURATION_DAY:
                        $add  = " +" . $surveyReminder[PluginSatisfactionSurveyReminder::COLUMN_DURATION] . " day";
                        $date = strtotime(date("Y-m-d", strtotime($lastSurveySendDate)) . $add);
                        $date = date('Y-m-d', $date);
                        break;

                     case PluginSatisfactionSurveyReminder::DURATION_MONTH:
                        $add  = " +" . $surveyReminder[PluginSatisfactionSurveyReminder::COLUMN_DURATION] . " month";
                        $date = strtotime(date("Y-m-d", strtotime($lastSurveySendDate)) . $add);
                        $date = date('Y-m-d', $date);
                        break;
                     default:
                        $date = null;
                  }

                  if (!is_null($date)) {
                     $potentialReminderToSendDates[] = ["tickets_id" => $ticketSatisfaction['tickets_id'],
                                                        "type"       => $surveyReminder['id'],
                                                        "date"       => $date];
                  }
               }
            }
            // Order dates
            if (!function_exists("date_sort")) {
               function date_sort($a, $b) {
                  return strtotime($a["date"]) - strtotime($b["date"]);
               }
            }
            usort($potentialReminderToSendDates, "date_sort");
            $dateNow = date("Y-m-d");

            if (isset($potentialReminderToSendDates[0])) {

               $potentialTimestamp = strtotime($potentialReminderToSendDates[0]['date']);
               $nowTimestamp       = strtotime($dateNow);
               //
               if ($potentialTimestamp <= $nowTimestamp) {
                  // Send notification
                  PluginSatisfactionNotificationTargetTicket::sendReminder($ticketSatisfaction['tickets_id']);
                  $self = new self();
                  $self->add([
                                'type'       => $potentialReminderToSendDates[0]['type'],
                                'tickets_id' => $ticketSatisfaction['tickets_id'],
                                'date'       => $dateNow
                             ]);
               }
            }
         }
      }
   }
}
