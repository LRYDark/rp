<?php
/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of RP plugin for GLPI.

 RP is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 RP is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @package   rp
 @authors   Nelly Mahu-Lasson, Remi Collet
 @copyright Copyright (c) 2009-2022 RP plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/rp
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
*/

include ("../../../inc/includes.php");

Plugin::load('rp', true);

$type = $_SESSION["plugin_rp"]["type"];
$item = new $type();

$tab_id = unserialize($_SESSION["plugin_rp"]["tab_id"]);
//unset($_SESSION["plugin_rp"]["tab_id"]);

//$MassRapport = new PluginRpCommon();
//$MassRapport->generateRP($tab_id);

//_________________________________________________
//_________________________________________________
require_once(PLUGIN_RP_DIR . "/fpdf/html2pdf.php");
global $DB, $CFG_GLPI;

$plugin         = new Plugin();
$ticket         = new Ticket();
$ticket_task    = new TicketTask();
$doc            = new Document();
$config         = PluginRpConfig::getInstance();
$UserID         = Session::getLoginUserID();
$Path           = GLPI_PLUGIN_DOC_DIR;

/** *********************************************************************************************************
   ------------------ Génération du pdf ---------------------------------------------------------------------
********************************************************************************************************** */
class PluginRpCriPDF extends FPDF { 
   // titre de la page
       function Titel(){
           global $DB, $CFG_GLPI;
           $config     = PluginRpConfig::getInstance();
           $doc        = new Document();
           $img        = $doc->find(['id' => $config->fields['logo_id']]);
           $img        = reset($img);

           $this->SetFont('Arial','B',15);// police d'ecriture

           // logo
           if(isset($img['filepath'])){
              $img = GLPI_DOC_DIR.'/'.$img['filepath'];
              if(file_exists($img)){
                 $this->Image($img,$config->fields['margin_left'],$config->fields['margin_top'],$config->fields['cut']);  
              }
           }

           $this->Cell(50,20,'',1,0,'C');
           // titre du pdf
            $this->Cell(90,20,$config->fields['titel_rt'],1,0,'C');
           //date et heure de génération
           $this->SetFont('Arial','',10); // police d'ecriture

           if($config->fields['date'] == 0)
               $pdf_date = utf8_decode("Date d'édition :\n" .date("Y-m-d à H:i:s"));
           $this->MultiCell(50,10,$pdf_date,1,'C');
       }
   
   // Pied de page
       function Footer(){
           $config     = PluginRpConfig::getInstance();
           // Positionnement à 1,5 cm du bas
           $this->SetY(-20);
           // Police Arial italique 8
           $this->SetFont('Arial','I',8);

               // Numéro de page
               $this->Cell(0,5,'Page '.$this->PageNo().'/{nb}',0,0,'C');
               $this->Ln();
               $this->Cell(0,5,utf8_decode($config->fields['line1']),0,0,'C');
               $this->Ln();
               $this->Cell(0,5,$config->fields['line2'],0,0,'C');
       }    

   // Clear html
       function ClearHtml($valuedes){
           $this->value = $valuedes;
           $this->value = stripcslashes($this->value);
           $this->value = htmlspecialchars_decode($this->value);
           $this->value = Glpi\RichText\RichText::getTextFromHtml($this->value);
           $this->value = strip_tags($this->value);
           $this->value = Toolbox::decodeFromUtf8($this->value);
           $this->value = Glpi\Toolbox\Sanitizer::unsanitize($this->value);
           $this->value = str_replace("’", "'", $this->value);
           $this->value = str_replace("?", "'", $this->value);
           return $this->value;
       }

   // Clear html space
       function ClearSpace($valuedes){
           $this->value = $valuedes;
           return preg_replace("# {2,}#"," \n",preg_replace("#(\r\n|\n\r|\n|\r)#"," ",$this->value));  // Suppression des saut de ligne superflu
       }
}

foreach ($tab_id as $key => $id) {
   $Ticket_id      = $id;
   $glpi_tickets = $DB->query("SELECT * FROM glpi_tickets WHERE id = $Ticket_id")->fetch_object();
   $glpi_tickets_infos = $DB->query("SELECT * FROM glpi_tickets INNER JOIN glpi_entities ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $Ticket_id")->fetch_object();
   $glpi_plugin_rp_dataclient = $DB->query("SELECT * FROM `glpi_plugin_rp_dataclient` WHERE id_ticket = $Ticket_id")->fetch_object();

   if(!empty($glpi_plugin_rp_dataclient->id_ticket)){
      $SOCIETY = $glpi_plugin_rp_dataclient->society;
      $TOWN = $glpi_plugin_rp_dataclient->town;
      $ADDRESS = $glpi_plugin_rp_dataclient->address;
      $POSTCODE = $glpi_plugin_rp_dataclient->postcode;
      $PHONE = $glpi_plugin_rp_dataclient->phone;
      $EMAIL = $glpi_plugin_rp_dataclient->email;
  }else{
      $SOCIETY = $glpi_tickets_infos->comment;
      if(empty($SOCIETY)){$SOCIETY = $glpi_tickets_infos->completename;}
      $TOWN = $glpi_tickets_infos->town;
      $ADDRESS = $glpi_tickets_infos->address;
      $POSTCODE = $glpi_tickets_infos->postcode;
      $PHONE = $glpi_tickets_infos->phonenumber;
  }

// Instanciation de la classe dérivée
   $pdf = new PluginRpCriPDF('P','mm','A4');
   $pdf->AliasNbPages();
   $pdf->AddPage();
   $pdf->SetFont('Arial','',10); // police d'ecriture
   $pdf->SetFillColor(77, 113, 166);
   $pdf->Titel();

// --------- INFO CLIENT
       if (empty($SOCIETY)) $SOCIETY = "-";
       if (empty($ADDRESS)) $ADDRESS = "-";
       if (empty($TOWN)) $TOWN = "-";
       if (empty($PHONE)) $PHONE = "-";
       if (empty($EMAIL)) $EMAIL = "-";
       
       $pdf->Cell(95,5,utf8_decode('N° du ticket'),1,0,'L',true);

       $pdf->Cell(95,5,$Ticket_id,1,0,'L',false,$_SERVER['HTTP_REFERER']);
   $pdf->Ln(10);
       $pdf->Cell(40,5,utf8_decode('Nom de la société'),1,0,'L',true);
       $pdf->Cell(150,5,utf8_decode($SOCIETY),1,0,'L');
   $pdf->Ln();
       $pdf->Cell(40,5,'Adresse',1,0,'L',true);
       $pdf->Cell(150,5,utf8_decode($ADDRESS),1,0,'L');
   $pdf->Ln();
       $pdf->Cell(40,5,'Ville',1,0,'L',true);
       $pdf->Cell(150,5,utf8_decode($TOWN),1,0,'L');
   $pdf->Ln(10);
       $pdf->Cell(40,5,utf8_decode('N° de Téléphone'),1,0,'L',true);
       $pdf->Cell(150,5,utf8_decode($PHONE),1,0,'L');
   $pdf->Ln();
       $pdf->Cell(40,5,utf8_decode('Email'),1,0,'L',true);
       $pdf->Cell(150,5,utf8_decode($EMAIL),1,0,'L');
   $pdf->Ln(10);
// --------- INFO CLIENT

// --------- DEMANDE
   $pdf->Cell(190,5,'Description de la demande',1,0,'C',true);
   $pdf->Ln(5);
   $pdf->MultiCell(0,5,$pdf->ClearHtml($glpi_tickets->name),1,'C');
   $pdf->Ln(0);
// --------- DEMANDE

   if($config->fields['use_publictask_massaction'] == 1){
      $is_private = "AND is_private = 0";
   }else{
      $is_private = "";
   }

// --------- TACHES
   $query = $DB->query("SELECT glpi_tickettasks.id FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id");
   $sumtask = 0;
   while ($datasum = $DB->fetchArray($query)) {
      $sumtask++;  
   }

   if ($sumtask > 0){
      $query = $DB->query("SELECT glpi_tickettasks.id, content, date, name, actiontime FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id $is_private");
         $pdf->Ln(5);
      $pdf->Cell(190,5,utf8_decode('Tâche(s) : '.$sumtask),1,0,'L',true);
         $pdf->Ln(2);            

      while ($data = $DB->fetchArray($query)) {
         //récupération de l'ID de l'image s'il y en a une.
         $IdImg = $data['id'];
         $ImgIdDoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
         if (isset($ImgIdDoc->documents_id)){
            $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
         }

            // si y'a une image associé au ticket 
            if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
               $img = GLPI_DOC_DIR.'/'.$ImgUrl->filepath;

               if (file_exists($img)){
                  $imageSize = getimagesize($img);
                  $width = $imageSize[0];
                  $height = $imageSize[1];
                  $taille = (100*$height)/$width;

                  $pdf->Ln();
                        $pdf->MultiCell(0,5,$pdf->ClearHtml($data['content']),1,'L');
                           $Y = $pdf->GetY();
                           $X = $pdf->GetX();
                        
                        if($pdf->GetY() + $taille > 297-15) {
                              $pdf->AddPage();
                              $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                           $pdf->Ln($taille + 5);
                        }else{
                              $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                              $pdf->SetXY($X,$Y+($taille));
                           $pdf->Ln();  
                        }                         
                        
                        $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
                  $pdf->Ln();
               }else{
                  $pdf->Ln();
                        $pdf->MultiCell(0,5,$pdf->ClearHtml($data['content']),1,'L');
                        $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
                  $pdf->Ln();
               }
            // sinon s'il y'a pas d'image associé au ticket 
            }else{
               $pdf->Ln();
                  $pdf->MultiCell(0,5,$pdf->ClearHtml($data['content']),1,'L');
                  $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
               $pdf->Ln();
            }

            // temps d'intervention si souhaité lors de la génération
                  $pdf->Write(5,utf8_decode("Temps d'intervention : " . floor($data['actiontime'] / 3600) .  str_replace(":", "h",gmdate(":i", $data['actiontime'] % 3600))));
               $pdf->Ln();
            $sumtask += $data['actiontime'];
      } 
   }
// --------- TACHES

// --------- SUIVI
   $query = $DB->query("SELECT glpi_itilfollowups.id FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id");
   $sumsuivi = 0;

   while ($data = $DB->fetchArray($query)) {
      $sumsuivi++;  
   } 

   if ($sumsuivi > 0){
      $query = $DB->query("SELECT glpi_itilfollowups.id, content, date, name FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id $is_private");
         $pdf->Ln(5);
      $pdf->Cell(190,5,utf8_decode('Suivi(s) : '.$sumsuivi),1,0,'L',true);
         $pdf->Ln(2);

      while ($data = $DB->fetchArray($query)) {

         //récupération de l'ID de l'image s'il y en a une.
         $IdImg = $data['id'];
         $ImgIdDoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg")->fetch_object();
         if (isset($ImgIdDoc->documents_id)){
            $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $ImgIdDoc->documents_id")->fetch_object();
         }
               
            // si y'a une image associé au ticket 
            if (isset($ImgIdDoc->documents_id) && !empty($ImgUrl->filepath)){
               $img = GLPI_DOC_DIR.'/'.$ImgUrl->filepath;

               if (file_exists($img)){
                  $imageSize = getimagesize($img);
                  $width = $imageSize[0];
                  $height = $imageSize[1];
                  $taille = (100*$height)/$width;

                  $pdf->Ln();
                        $pdf->MultiCell(0,5,preg_replace("# {2,}#"," \n",preg_replace("#(\r\n|\n\r|\n|\r)#"," ",$pdf->ClearHtml($data['content']))),1,'L');
                           $Y = $pdf->GetY();
                           $X = $pdf->GetX();
                  
                           if($pdf->GetY() + $taille > 297-15) {
                                    $pdf->AddPage();
                                    $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                              $pdf->Ln($taille + 5);
                           }else{
                                    $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                                    $pdf->SetXY($X,$Y+($taille));
                              $pdf->Ln();  
                           }

                        $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
                  $pdf->Ln();
               }else{
                  $pdf->Ln();
                        $pdf->MultiCell(0,5,preg_replace("# {2,}#"," \n",preg_replace("#(\r\n|\n\r|\n|\r)#"," ",$pdf->ClearHtml($data['content']))),1,'L');
                        $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
                  $pdf->Ln();
               }

            // sinon s'il y'a pas d'image associé au ticket 
            }else{
               $pdf->Ln();
                  $pdf->MultiCell(0,5,preg_replace("# {2,}#"," \n",preg_replace("#(\r\n|\n\r|\n|\r)#"," ",$pdf->ClearHtml($data['content']))),1,'L');
                  $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
               $pdf->Ln();
            }
      } 
   }
// --------- SUIVI

// --------- TEMPS D'INTERVENTION
   $pdf->Ln(5);
   if (isset($sumtask)){
      $pdf->Cell(80,5,utf8_decode("Temps d'intervention total"),1,0,'L',true);
      $pdf->Cell(110,5,utf8_decode(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600))),1,0,'L');
      $pdf->Ln(7);
   }
// --------- TEMPS D'INTERVENTION

// --------- TEMPS DE TRAJET
   if ($plugin->isActivated('rt')) {
       if ($config->fields['time_hotl'] == 1){
           $sumroutetime = 0;
           $timeroute = $DB->query("SELECT routetime FROM `glpi_plugin_rt_tickets` WHERE tickets_id = $Ticket_id");
               while ($data = $DB->fetchArray($timeroute)) {
                   $sumroutetime += $data['routetime'];
               }

           if ($sumroutetime != 0){
               $pdf->Cell(80,5,utf8_decode('Temps de trajet total'),1,0,'L',true);
               $pdf->Cell(110,5,utf8_decode(str_replace(":", "h", gmdate("H:i",$sumroutetime*60))),1,0,'L');
               $pdf->Ln(7);
           }
       }
   }
// --------- TEMPS DE TRAJET

      $FileName           = date('Ymd-His')."_R_Ticket_".$Ticket_id. ".pdf";
      $FilePath           = "_plugins/rp/rapportsMass/" . $FileName;
      $SeePath            = $Path . "/rp/rapportsMass/";
      $SeeFilePath            = $SeePath . $FileName;
      $pdf->Output($SeeFilePath, 'F'); //enregistrement du pdf
}

//_________________________________________________
//_________________________________________________

/*$itemrp = new $PLUGIN_HOOKS['plugin_rp'][$type]($item);
$itemrp->generateRP($tab_id);*/

//print_r($tab_id);

/*$result = $DB->request('glpi_plugin_rp_preferences',
                       ['SELECT' => 'tabref',
                        'WHERE'  => ['users_ID' => $_SESSION['glpiID'],
                                     'itemtype' => $type]]);

$tab = [];

foreach ($result as $data) {
   if ($data["tabref"] == 'landscape') {
      $pag = 1;
   } else {
      $tab[]= $data["tabref"];
   }
}
   if (empty($tab)) {
      $tab[] = $type.'$main';
   }

if (isset($PLUGIN_HOOKS['plugin_rp'][$type])) {

   $itemrp = new $PLUGIN_HOOKS['plugin_rp'][$type]($item);
   $itemrp->generateRP($tab_id, $tab, (isset($pag) ? $pag : 0));
} else {
   die("Missing hook");
}*/