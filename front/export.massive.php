<?php
include ("../../../inc/includes.php");

require_once(PLUGIN_RP_DIR . "/fpdf/html2pdf.php");
global $DB, $CFG_GLPI;

Plugin::load('rp', true);

$type           = $_SESSION["plugin_rp"]["type"];
$item           = new $type();
$plugin         = new Plugin();
$ticket         = new Ticket();
$ticket_task    = new TicketTask();
$doc            = new Document();
$config         = PluginRpConfig::getInstance();
$UserID         = Session::getLoginUserID();
$Path           = GLPI_PLUGIN_DOC_DIR;

$tab_id = unserialize($_SESSION["plugin_rp"]["tab_id"]);
unset($_SESSION["plugin_rp"]["tab_id"]);
/*********************************************************************************
MESSAGE D'INFORMATION 

$msg        = message (popup) apres la redirection 
$msgtype    = type de message [ERROR | INFO | WARNING]
*********************************************************************************/
function message($msg, $msgtype){
   Session::addMessageAfterRedirect(
       __($msg, 'rp'),
       true,
       $msgtype
   );
}

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
            $this->Cell(90,20,$config->fields['titel_rh'],1,0,'C');
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
   $User = $DB->query("SELECT name FROM glpi_users WHERE id = $UserID")->fetch_object();
   $glpi_tickets = $DB->query("SELECT * FROM glpi_tickets WHERE id = $Ticket_id")->fetch_object();
   $glpi_tickets_infos = $DB->query("SELECT * FROM glpi_tickets INNER JOIN glpi_entities ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $Ticket_id")->fetch_object();
   $glpi_plugin_rp_dataclient = $DB->query("SELECT * FROM `glpi_plugin_rp_dataclient` WHERE id_ticket = $Ticket_id")->fetch_object();
   $ticket_entities = $DB->query("SELECT glpi_tickets.entities_id FROM glpi_tickets INNER JOIN glpi_entities ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $Ticket_id")->fetch_object();
   
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
                  if($width != 0)$taille = (100*$height)/$width;

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

// --------- SIGNATURE
   /*$glpi_plugin_rp_signtech = $DB->query("SELECT seing FROM glpi_plugin_rp_signtech WHERE user_id = $UserID")->fetch_object();

   $pdf->Cell(95,37," ",1,0,'L');	//tableau 1
   $pdf->Cell(95,37," ",1,0,'L'); //tableau 2

      $pdf->Ln(0);
   $pdf->Cell(95,5,'Client',1,0,'C',true); //tableau 1
      $Y = $pdf->GetY();//recupere coordonné de Y
      $X = $pdf->GetX();//recupere coordonné de X
   $pdf->Cell(95,5,'Technicien',1,0,'C',true); //tableau 2

   // ------ tableau 1
      $pdf->Write(5,"Nom : "); 
            $pdf->Ln();
      $pdf->Write(5,"Signature :");
            $pdf->Ln();
      if(!empty($URL)) $pdf->Image($URL,15,$Y+15,0,0,'PNG');
   // ------ tableau 1

   // ------ tableau 2
            $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup 
      $pdf->Write(15,"Nom : " . utf8_decode($User->name)); 
            $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup 
      $pdf->Write(25,"Signature :");
            $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup 
      if (isset($glpi_plugin_rp_signtech)) $pdf->Image($glpi_plugin_rp_signtech->seing,110,$Y+15,0,0,'PNG');
   // ------ tableau 2   */
// --------- SIGNATURE

      $FileName           = date('Ymd-His')."_R_Ticket_".$Ticket_id. ".pdf";
      $FilePath           = "_plugins/rp/rapportsMass/" . $FileName;
      $SeePath            = $Path . "/rp/rapportsMass/";
      $SeeFilePath            = $SeePath . $FileName;
      $pdf->Output($SeeFilePath, 'F'); //enregistrement du pdf

      // Ajoutez le chemin du fichier PDF au tableau
      $pdfFiles[] = $SeeFilePath;

   //-------------------------------------------------------------------------------------------------------------------------------------
   //-------------------------------------------------------------------------------------------------------------------------------------
   $glpi_plugin_rp_cridetails = $DB->query("SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket = $Ticket_id AND users_id = $UserID AND type = 2 ORDER BY date DESC LIMIT 1")->fetch_object();
      // par defaut
         $Task_id        = 'NULL'; 
         $AddValue       = 'true';
         $AddDetails     = 'false';
         $AddDoc         = 'false';
         $AddOrUpdate    = "false";
         $Verfi_query_rp_cridetails = 'false';

   // documents -> generation pdf + liaison bdd table document / table cridetails -> add id task si une tache est crée via le form client.
      $glpi_plugin_rp_cridetails_MultiDoc = $DB->query("SELECT id, id_documents, id_task FROM `glpi_plugin_rp_cridetails` WHERE id_ticket = $Ticket_id AND type = 2 ORDER BY date DESC LIMIT 1")->fetch_object();
      if($config->fields['multi_doc'] == 0 && !empty($glpi_plugin_rp_cridetails_MultiDoc->id)){
         // update document
         $AddValue = "false";
         $input = ['id'          => $glpi_plugin_rp_cridetails_MultiDoc->id_documents,
                  'name'        => addslashes('PDF : Fiche - ' . str_replace("?", "°", $glpi_tickets->name)),
                  'filename'    => addslashes($FileName),
                  'filepath'    => addslashes($FilePath),
                  'users_id'    => Session::getLoginUserID(),
                  'entities_id' => $ticket_entities->entities_id,
                  'is_recursive'=> 1];

         if($NewDoc = $doc->update($input)){
            $AddDoc         = 'true';
                  // update tableau rapport
                  $query_rp_cridetails = "UPDATE glpi_plugin_rp_cridetails 
                              SET nameclient = '-', email = '-', send_mail = 0, date = NOW(), users_id = $UserID
                              WHERE id = $glpi_plugin_rp_cridetails_MultiDoc->id";
                  $Verfi_query_rp_cridetails = 'true';
            $AddDetails = 'true';
            $NewDoc = $glpi_plugin_rp_cridetails_MultiDoc->id_documents;
         }
      }else{
         $input = ['name'        => addslashes('PDF : Fiche - ' . str_replace("?", "°", $glpi_tickets->name)),
                  'filename'    => addslashes($FileName),
                  'filepath'    => addslashes($FilePath),
                  'mime'        => 'application/pdf',
                  'users_id'    => Session::getLoginUserID(),
                  'entities_id' => $ticket_entities->entities_id,
                  'tickets_id'  => $Ticket_id,
                  'is_recursive'=> 1];

         if($NewDoc = $doc->add($input)){
            $AddDoc = 'true';
            $AddDetails = 'true';
         }else{
            $AddDoc = 'false';
            message("Erreur de l'enregistrement du PDF (link error) -> glpi_documents : $FileName", ERROR);
         }
      }
   if($AddValue == 'true'){
      $query_rp_cridetails= "INSERT INTO glpi_plugin_rp_cridetails 
                           (`id_ticket`, `id_documents`, `type`, `nameclient`, `email`, `send_mail`, `date`, `users_id`, `id_task`) 
                           VALUES 
                           ($Ticket_id, $NewDoc, 2 , '-' , '-' , 0, NOW(), $UserID, $Task_id)";
      $Verfi_query_rp_cridetails = 'true';
   }
   if ($Verfi_query_rp_cridetails == 'true'){
      if($DB->query($query_rp_cridetails)){
      $AddDetails = 'true';
      }else{
            $AddDetails = 'false';
            message("Erreur de l'enregistrement des données ou du PDF (link error) -> glpi_plugin_rp_cridetails : $FileName", ERROR);
      }
   }
   if($AddDetails == 'true' && $AddDoc == 'true'){
      //message("Document enregistré avec succès : <br><a href='document.send.php?docid=$NewDoc'>$FileName</a>", INFO);
   }else{
      message("Echec de l'enregistrement du document : $FileName.", ERROR);
   }
}

   $export = new PluginRpCommon();
   $export->exportZIP($SeePath, $pdfFiles);

   Html::redirect($CFG_GLPI["root_doc"] . "/front/ticket.php");









