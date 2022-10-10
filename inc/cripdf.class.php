<?php
include ("../../../inc/includes.php");

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once(PLUGIN_RP_DIR . "/fpdf/html2pdf.php");
global $DB, $CFG_GLPI;

$plugin         = new Plugin();
$ticket         = new Ticket();
$ticket_task    = new TicketTask();
$doc            = new Document();

$UserID         = Session::getLoginUserID();
$Entitie_id     = $ticket->getEntityID();

$Ticket_id      = $_POST['REPORT_ID'];
$Path           = GLPI_PLUGIN_DOC_DIR;

date_default_timezone_set('Europe/Paris');
$date = date('d-m-Y');
$heure = date('H:i');

$User = $DB->query("SELECT name FROM glpi_users WHERE id = $UserID")->fetch_object();
$glpi_tickets = $DB->query("SELECT * FROM glpi_tickets WHERE id = $Ticket_id")->fetch_object();
$glpi_plugin_rp_cridetails = $DB->query("SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket = $Ticket_id AND users_id = $UserID ORDER BY date DESC LIMIT 1")->fetch_object();
$glpi_tickets_infos = $DB->query("SELECT * FROM glpi_tickets INNER JOIN glpi_entities ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $Ticket_id")->fetch_object();
$glpi_plugin_rp_dataclient = $DB->query("SELECT * FROM `glpi_plugin_rp_dataclient` WHERE id_ticket = $Ticket_id")->fetch_object();

/* -- VARIABLES -- */
    if (empty($_POST['url'])) $_POST['url'] = " ";
    if (empty($_POST['email'])) $_POST['email'] = " ";
    if (empty($_POST['name'])) $_POST['name'] = " ";
    if (empty($_POST['society'])) $_POST['society'] = " ";
    if (empty($_POST['town'])) $_POST['town'] = " ";
    if (empty($_POST['address'])) $_POST['address'] = " ";
    if (empty($_POST['postcode'])) $_POST['postcode'] = 0;
    if (empty($_POST['phone'])) $_POST['phone'] = " ";

    if (empty($_POST['serialnumber'])) $_POST['serialnumber'] = 0;

    if (empty($_POST['mouse'])) $_POST['mouse'] = " ";
    if (empty($_POST['keyboard'])) $_POST['keyboard'] = " ";
    if (empty($_POST['bag'])) $_POST['bag'] = " ";
    if (empty($_POST['feed'])) $_POST['feed'] = " ";
    if (empty($_POST['dockstation'])) $_POST['dockstation'] = " ";
    if (empty($_POST['other'])) $_POST['other'] = " ";
    if (empty($_POST['equal'])) $_POST['equal'] = " ";

    if (empty($_POST['userpassword'])) $_POST['userpassword'] = " ";
    if (empty($_POST['NameRespMat'])) $_POST['NameRespMat'] = " ";
    if (empty($_POST['CoordRespMat'])) $_POST['CoordRespMat'] = " ";
    if (empty($_POST['NameUtilpMat'])) $_POST['NameUtilpMat'] = " ";
    if (empty($_POST['CoordUtilpMat'])) $_POST['CoordUtilpMat'] = " ";
    if (empty($_POST['mailtoclient'])) $_POST['mailtoclient'] = 0;

    $FORM = $_POST["Form"];
    $URL = $_POST["url"];
    $MAILTOCLIENT = $_POST["mailtoclient"];

    $EMAIL = $_POST["email"];
    $NAME = $_POST["name"];
    $SOCIETY = $_POST["society"];
    $TOWN = $_POST["town"];
    $ADDRESS = $_POST["address"];
    $POSTCODE = $_POST["postcode"];
    $PHONE = $_POST["phone"];
    $SERIALNUMBER = $_POST["serialnumber"];

    if($glpi_tickets->requesttypes_id != 7 && $FORM == 'FormClient'){ // fiche de prise en charge | formulaire
        $MOUSE = $_POST["mouse"];
        $KEYBOARD = $_POST["keyboard"];
        $BAG = $_POST["bag"];
        $FEED = $_POST["feed"];
        $DOCKSTATION = $_POST["dockstation"];
        $OTHER = $_POST["other"];
        $EQUAL = $_POST["equal"];

        $PASSWORD = $_POST["userpassword"];
        $NAMERESPMAT = $_POST["NameRespMat"];
        $COORDRESPMAT = $_POST["CoordRespMat"];
        $NAMEUTILPMAT = $_POST["NameUtilpMat"];
        $COORDUTILPMAT = $_POST["CoordUtilpMat"];

        $MODEL = $_POST['model'];
        $IDSESSION = $_POST['idsession'];
        $DATASAVE = $_POST['DataSave'];
        $DATAFORMATTING = $_POST['DataFormatting'];
    
    }
    if($FORM == 'FormRapport' || $FORM == 'FormRapportHotline'){ // rapport d'intervention
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
    }

    $content = "";
    if($glpi_tickets->requesttypes_id != 7 && $FORM == 'FormClient'){
        $content .= "&#60;h1&#62;Prise en charge du materiel le ".$date." à ".$heure."&#60;/h1&#62;
                    &#60;h2&#62;Informations client&#60;/h2&#62;
                    &#60;div&#62;&#60;strong&#62;1) Numéro de serie : &#60;/strong&#62;". $SERIALNUMBER ."&#60;/div&#62;
                    &#60;div&#62;&#60;strong&#62;2) Marque / Model : &#60;/strong&#62;". $MODEL ."&#60;/div&#62;
                    &#60;div&#62;&#60;strong&#62;3) Nom de session : &#60;/strong&#62;". $IDSESSION ."&#60;/div&#62;
                    &#60;div&#62;&#60;strong&#62;4) Mot de passe : &#60;/strong&#62;". $PASSWORD ."&#60;/div&#62;
                    &#60;div&#62;&#60;strong&#62;5) Nom de la personne en charge du materiel : &#60;/strong&#62;". $NAMERESPMAT ."&#60;/div&#62;
                    &#60;div&#62;&#60;strong&#62;6) Téléphone / Mail de la personne en charge du materiel : &#60;/strong&#62;". $COORDRESPMAT ."&#60;/div&#62;";
    
        if($EQUAL == 'equal'){
            $content .= "&#60;div&#62;&#60;strong&#62;7) L'utilisateur du materiel est différent de la personne l'ayant pris en charge : &#60;/strong&#62;Oui&#60;/div&#62;
    
                        &#60;div&#62;&#60;strong&#62;8) Nom de l'utilisateur du materiel : &#60;/strong&#62;". $NAMEUTILPMAT ."&#60;/div&#62;
                        &#60;div&#62;&#60;strong&#62;9) Téléphone / Mail de l'utilisateur du materiel : &#60;/strong&#62;". $COORDUTILPMAT ."&#60;/div&#62;";
        }else{
            $content .= "&#60;div&#62;&#60;strong&#62;7) L'utilisateur du materiel est différent de la personne l'ayant pris en charge : &#60;/strong&#62;Non&#60;/div&#62;";
        }          
            $content .= "&#60;h2&#62;&#60;/h2&#62;
                        &#60;h2&#62;Sauvegarde des données&#60;/h2&#62;
                        &#60;div&#62;&#60;strong&#62;1) Sauvegarde des données : &#60;/strong&#62;". $DATASAVE ."&#60;/div&#62;
                        &#60;div&#62;&#60;strong&#62;2) Formatage autorisé : &#60;/strong&#62;". $DATAFORMATTING ."&#60;/div&#62;
                        &#60;h2&#62;&#60;/h2&#62;
                        &#60;h2&#62;Accessoire(s)&#60;/h2&#62;
                        &#60;div&#62;&#60;strong&#62;1) &#60;/strong&#62;". $MOUSE . $KEYBOARD . $BAG . $FEED . $DOCKSTATION . $OTHER ."&#60;/div&#62;";
    }
/* -- VARIABLES -- */

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
            $this->SetFont('Arial','B',15);// police d'ecriture
            // logo
            $this->Image('../img/logo.png',21,15,27);
            $this->Cell(50,20,'',1,0,'C');
            // titre du pdf
            if($_POST["Form"] == 'FormClient'){
                $this->Cell(90,20,'FICHE DE PRISE EN CHARGE',1,0,'C');
            }
            if($_POST["Form"] == 'FormRapport'){
                $this->Cell(90,20,"RAPPORT D'INTERVENTION",1,0,'C');
            }
            if($_POST["Form"] == "FormRapportHotline"){
                $this->Cell(90,20,"RAPPORT HOTLINE",1,0,'C');
            }
            //date et heure de génération
            $this->SetFont('Arial','',10); // police d'ecriture
            $this->Cell(50,20,utf8_decode(date("Y-m-d à H:i:s")),1,0,'C');
            
            // Saut de ligne
            $this->Ln(20);
        }
    
    // Pied de page
        function Footer(){
            // Positionnement à 1,5 cm du bas
            $this->SetY(-20);
            // Police Arial italique 8
            $this->SetFont('Arial','I',8);

                // Numéro de page
                $this->Cell(0,5,'Page '.$this->PageNo().'/{nb}',0,0,'C');
                $this->Ln();
                $this->Cell(0,5,utf8_decode('193 rue du Général Metman, 57070 Metz'),0,0,'C');
                $this->Ln();
                $this->Cell(0,5,'03 87 18 49 20',0,0,'C');
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

            return $this->value;
            //return preg_replace("# {2,}#"," \n",preg_replace("#(\r\n|\n\r|\n|\r)#"," ",$this->value));  // Suppression des saut de ligne superflu
        }
}

// Instanciation de la classe dérivée
$pdf = new PluginRpCriPDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',10); // police d'ecriture
$pdf->SetFillColor(77, 113, 166);
$pdf->Titel();

// --------- INFO CLIENT
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
    $pdf->Ln(5);
// --------- DEMANDE

// --------- DESCRIPTION
    if($FORM == 'FormClient'){
        $pdf->Cell(190,5,utf8_decode('Description du problème'),1,0,'C',true);
        $pdf->Ln();
        $pdf->MultiCell(0,5,$pdf->ClearHtml($glpi_tickets->content.$content),1,'L');
        $pdf->Ln();

        // commentaire
        $pdf->Cell(190,5,utf8_decode('Commentaire(s)'),1,0,'C',true);
        $pdf->Ln();
        $tx = "...............................................................................................................................................................................................";
        $pdf->MultiCell(190,8,$tx.$tx.$tx,1,'L');
        $pdf->Ln();
    }
// --------- DESCRIPTION

// --------- TACHES
    if($FORM == 'FormRapport' || $FORM == 'FormRapportHotline'){
        $query = $DB->query("SELECT * FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id AND is_private = 0 ");
        $NbrTask = $DB->numrows($query);
        $sumtask = 0;

        if($NbrTask > 0){
            $pdf->Cell(190,5,utf8_decode('Tâche(s) : ').$NbrTask,1,0,'L',true);
            $pdf->Ln(2);

            while ($data = $DB->fetchArray($query)) {
                $LoadData = $data["content"];
                $date = $data["date"]; 
                $Tech =  $data["name"];
                $Time = $data['actiontime'];
                
                $pdf->Ln();
                    $pdf->MultiCell(0,5,$pdf->ClearHtml($LoadData),1,'L');
                    $pdf->Write(5,utf8_decode('Créé le : ' . $date . ' par ' . $Tech));
                $pdf->Ln();
                    $pdf->Write(5,utf8_decode("Temps d'intervention : " . str_replace(":", "h", gmdate("H:i",$Time))));
                $pdf->Ln();

                $sumtask += $Time;
            }
        }
// --------- TACHES

// --------- SUIVI
        $query = $DB->query("SELECT * FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id AND is_private = 0");
        $NbrSuivi = $DB->numrows($query);

        if($NbrSuivi > 0){
            $pdf->Ln(5);
            $pdf->Cell(190,5,utf8_decode('Suivi(s) : ').$NbrSuivi,1,0,'L',true);
            $pdf->Ln(2);
        
            while ($data = $DB->fetchArray($query)) {
                $LoadData = $data["content"];
                $date = $data["date"]; 
                $Tech =  $data["name"];
                
                $pdf->Ln();
                    $pdf->MultiCell(0,5,preg_replace("# {2,}#"," \n",preg_replace("#(\r\n|\n\r|\n|\r)#"," ",$pdf->ClearHtml($LoadData))),1,'L');
                    $pdf->Write(5,utf8_decode('Créé le : ' . $date . ' par ' . $Tech));
                $pdf->Ln();
            }
        }
// --------- SUIVI

// --------- TEMPS D'INTERVENTION
        $pdf->Ln(5);
        $pdf->Cell(80,5,utf8_decode("Temps d'intervention total"),1,0,'L',true);
        $pdf->Cell(110,5,utf8_decode(str_replace(":", "h", gmdate("H:i",$sumtask))),1,0,'L');
        $pdf->Ln(7);
    }
// --------- TEMPS D'INTERVENTION

// --------- TEMPS DE TRAJET
    if ($plugin->isActivated('rt') && $_POST["Form"] == 'FormRapport') {

        $sumroutetime = 0;
        $timeroute = $DB->query("SELECT routetime FROM `glpi_plugin_rt_tickets` WHERE tickets_id = $Ticket_id");
        while ($data = $DB->fetchArray($timeroute)) {
            $sumroutetime += $data['routetime'];
        }

        $pdf->Cell(80,5,utf8_decode('Temps de trajet total'),1,0,'L',true);
        $pdf->Cell(110,5,utf8_decode(str_replace(":", "h", gmdate("H:i",$sumroutetime*60))),1,0,'L');
        $pdf->Ln(7);
    }
// --------- TEMPS DE TRAJET

// --------- SIGNATURE
    if($FORM != "FormRapportHotline"){
        $pdf->SetAutoPageBreak(true, 50);
        $glpi_plugin_rp_signtech = $DB->query("SELECT seing FROM glpi_plugin_rp_signtech WHERE user_id = $UserID")->fetch_object();

        $pdf->Cell(95,5,'Client',1,0,'C',true);
            $Y = $pdf->GetY(); //recupere coordonné de Y
        $pdf->Cell(95,5,'Technicien',1,0,'C',true);
        $pdf->Ln(5);
            
        $Y = $pdf->GetY();//recupere coordonné de Y
        $X = $pdf->GetX();//recupere coordonné de X

            $pdf->Cell(95,15,'Signature : ',0,0,'L',0);
            $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup   
            if(!empty($URL)) $pdf->Image($URL,15,$Y+10,0,0,'PNG');
                    
        $pdf->Cell(95,32,' ',1,0,'L',0);
        $pdf->Ln(0);

        $pdf->Cell(95,5,"Nom : ". utf8_decode($NAME),0,0,'L');

            $Y = $pdf->GetY();//recupere coordonné de Y
            $X = $pdf->GetX();//recupere coordonné de X
        $pdf->Cell(95,32," ",1,0,'L');	

            $pdf->SetXY($X,$Y); // on deplace le curceur aux coordonnées recup
        $pdf->Cell(0,5,"Nom : " . utf8_decode($User->name),0,0,'L');			
            $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup

        $pdf->Cell(95,15,'Signature : ',0,0,'L',0);
        if (isset($glpi_plugin_rp_signtech)) $pdf->Image($glpi_plugin_rp_signtech->seing,110,$Y+10,0,0,'PNG');
    }
// --------- SIGNATURE

        $pdf->Output(); // affichage du PDF

/** *********************************************************************************************************
   ------------------ Informations d'enregistement -------------------------------------------------------
********************************************************************************************************** */
// par defaut
$Task_id        = 'NULL'; 
$AddValue       = 'true';
$AddDetails     = 'false';
$AddDoc         = 'false';

    if($MAILTOCLIENT == ''){
        $MAILTOCLIENT = 0;
    }
    if($MAILTOCLIENT == 0){
        $EMAIL = '';
    }
        
    if($FORM == 'FormClient'){ // formulaire de prise en charge
        $TypeRapport        = 0;
        $FileName           = date('Ymd-His')."_F_Ticket_".$Ticket_id. ".pdf";
        $FilePath           = "_plugins/rp/fiches/" . $FileName;
        $SeePath            = $Path . "/rp/fiches/";
    }elseif($FORM == 'FormRapport'){ // rapport 
        $TypeRapport        = 1;
        $FileName           = date('Ymd-His')."_R_Ticket_".$Ticket_id. ".pdf";
        $FilePath           = "_plugins/rp/rapports/" . $FileName;
        $SeePath            = $Path . "/rp/rapports/";
    }elseif($FORM == 'FormRapportHotline'){ // rapport hotline
        $TypeRapport        = 2;
        $FileName           = date('Ymd-His')."_RH_Ticket_".$Ticket_id. ".pdf";
        $FilePath           = "_plugins/rp/rapportsHotline/" . $FileName;
        $SeePath            = $Path . "/rp/rapportsHotline/";
    }

    $SeeFilePath            = $SeePath . $FileName;

    // documents -> generation pdf + liaison bdd table document / table cridetails -> add id task si une tache est crée via le form client.
    $input = ['name'        => addslashes('PDF : Fiche - ' . str_replace("?", "°", $glpi_tickets->name)),
              'filename'    => addslashes($FileName),
              'filepath'    => addslashes($FilePath),
              'mime'        => 'application/pdf',
              'users_id'    => Session::getLoginUserID(),
              'tickets_id'  => $Ticket_id];

    if($NewDoc = $doc->add($input)){
        $AddDoc = 'true';
        if($FORM == 'FormClient'){ // formulaire de prise en charge
            if($glpi_tickets->requesttypes_id != 7){
                if(!empty($glpi_plugin_rp_cridetails->id_task)){
                    $TaskExiste = $DB->query("SELECT * FROM glpi_tickettasks WHERE tickets_id = $Ticket_id AND id = $glpi_plugin_rp_cridetails->id_task")->fetch_object();
                }

                $origin = date_create($glpi_plugin_rp_cridetails->date);
                $target = date_create(date("Y-m-d H:i:s"));
                $interval = date_diff($origin, $target);
                $hour = $interval->format('%h');
                $day = $interval->format('%y%m%d');

                if($day == 000 && $hour < 1 && !empty($glpi_plugin_rp_cridetails->id_task) && !empty($TaskExiste->id)){

                    message("<i class='fa-solid fa-triangle-exclamation'></i> Une prise en charge datent de moins 1H déjà existante. <br> 
                            Modification automatique de la tâche en cours ...", WARNING); 

                    $Task_id = $TaskExiste->id;
                    $input = ['id' => $Task_id,
                                'tickets_id' => $Ticket_id,
                                'content' => addslashes($content)];
        
                    if($ticket_task->update($input)){
                        message('Élément mit à jour avec succès : (Tâche -> '.$Task_id.')', INFO);
                    }else{
                        message('Échec de la mise à jour : (Tâche -> '.$Task_id.')', WARNING);
                    }

                    $DB->query("UPDATE glpi_plugin_rp_cridetails 
                                SET id_documents = $NewDoc, nameclient = '$NAME', email = '$EMAIL', send_mail = $MAILTOCLIENT, date = NOW(), users_id = $UserID
                                WHERE id_task = $Task_id AND id_ticket = $Ticket_id");
                    $AddValue = 'false';
                    $AddDetails = 'true';
                }else{
                    $input = ['tickets_id'      => $Ticket_id,
                              'users_id'        => Session::getLoginUserID(),
                              'users_id_tech'   => Session::getLoginUserID(),
                              'content'         => addslashes($content),
                              'state'           => 1,
                              'actiontime'      => 10,
                              'is_private'      => 0];

                    if($Task_id = $ticket_task->add($input)){
                        message('Élément ajouté avec succès : Tâche', INFO);
                    }else{
                        message("Échec de l'ajout : Tâche de prise en charge", WARNING);
                    }
                }
            }
            // info client mise a jour des coordonnés sur le ticket ----------------------
                if($SOCIETY != $glpi_tickets_infos->comment || $TOWN != $glpi_tickets_infos->town || $ADDRESS != $glpi_tickets_infos->address || $POSTCODE != $glpi_tickets_infos->postcode || $PHONE != $glpi_tickets_infos->phonenumber){
                    if(empty($glpi_plugin_rp_dataclient)){
                        $query= "INSERT INTO `glpi_plugin_rp_dataclient` (`id_ticket`, `society`, `address`, `town`, `postcode`, `phone`, `email`, `serial_number`) 
                                VALUES ($Ticket_id ,'$SOCIETY' ,'$ADDRESS' ,'$TOWN' ,'$POSTCODE' ,'$PHONE' ,'$EMAIL', '$SERIALNUMBER');";
                        if(!$DB->query($query)){
                            message("Echec de la mise à jour des informations client", WARNING);
                        }else{
                            message("Information(s) client mit à jour avec succès.", INFO);
                        }
                    }else{
                        if($SOCIETY != $glpi_plugin_rp_dataclient->society || $TOWN != $glpi_plugin_rp_dataclient->town || $ADDRESS != $glpi_plugin_rp_dataclient->address || $POSTCODE != $glpi_plugin_rp_dataclient->postcode || $PHONE != $glpi_plugin_rp_dataclient->phone){
                            $update= "UPDATE glpi_plugin_rp_dataclient SET society='$SOCIETY', address='$ADDRESS', town='$TOWN', postcode='$POSTCODE', 
                                    phone='$PHONE', email='$EMAIL' , serial_number = '$SERIALNUMBER' WHERE id_ticket=$Ticket_id;";
                            if(!$DB->query($update)){
                                message("Echec de la mise à jour des informations client", WARNING);
                            }else{
                                message("Information(s) client mit à jour avec succès.", INFO);
                            }
                        }
                    }
                }
            // info client mise a jour des coordonnés sur le ticket ----------------------
        }
    }else{
        $AddDoc = 'false';
        message("Erreur de l'enregistrement du PDF (link error) -> glpi_documents", ERROR);
    }

    if($AddValue == 'true'){
        $query= "INSERT INTO glpi_plugin_rp_cridetails 
                (`id_ticket`, `id_documents`, `type`, `nameclient`, `email`, `send_mail`, `date`, `users_id`, `id_task`) 
                VALUES 
                ($Ticket_id, $NewDoc, $TypeRapport , '$NAME' , '$EMAIL' , $MAILTOCLIENT, NOW(), $UserID, $Task_id)";

        if($DB->query($query)){
            $AddDetails = 'true';
        }else{
            $AddDetails = 'false';
            message("Erreur de l'enregistrement des données ou du PDF (link error) -> glpi_plugin_rp_cridetails", ERROR);
        }
    }

    if($AddDetails == 'true' && $AddDoc == 'true'){
        message("Document enregistré avec succès : <br><a href='document.send.php?docid=$NewDoc'>$FileName</a>", INFO);
    }else{
        message("Echec de l'enregistrement du document.", ERROR);
    }

        $pdf->Output($SeeFilePath, 'F'); //enregistrement du pdf

if ($MAILTOCLIENT == 1){

    // contenu du mail
    $urlmail = "http://localhost/glpi/front/ticket.form.php?id=".$Ticket_id;
    $logomail = "https://fvjwbn.stripocdn.email/content/guids/CABINET_44164322675628a7251e1d7d361331e9/images/logoeasisupportnew.png";
    $signaturemail = "https://fvjwbn.stripocdn.email/content/guids/CABINET_44164322675628a7251e1d7d361331e9/images/logo_jcd_54G.png";
    $signaturetxt = "L'équipe JCD";

        if($FORM == 'FormClient'){
            $titelmail = "Fiche de prise en charge";
            $textmail = "Veuillez trouver ci-joint la fiche de prise en charge de votre matériel en date du ".$date."<br>".
                        "Vous trouverez l’ensemble des informations sur le lien suviant : <a href=".$urlmail.">Ticket N°".$ID."</a>";
        }
        if($FORM == 'FormRapport' || $FORM == 'FormRapportHotline'){
            $titelmail = "Rapport d'intervention";
            $textmail = "Veuillez trouver ci-joint le rapport d'intervention en date du ".$date."<br>".
                        "Vous trouverez l’ensemble des informations sur le lien suviant : <a href=".$urlmail.">Ticket N°".$ID."</a>";
        }
        $header = '
            <head>
                <meta charset="UTF-8">
                <meta content="width=device-width, initial-scale=1" name="viewport">
                <meta name="x-apple-disable-message-reformatting">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta content="telephone=no" name="format-detection">
                <title></title>
                <!--[if (mso 16)]>    <style type="text/css">    a {text-decoration: none;}    </style>    <![endif]-->
                <!--[if gte mso 9]><style>sup { font-size: 100% !important; }</style><![endif]-->
                <!--[if gte mso 9]>
            <xml>
                <o:OfficeDocumentSettings>
                <o:AllowPNG></o:AllowPNG>
                <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            </head>

            <body>
                <div class="es-wrapper-color">
                    <!--[if gte mso 9]>
                        <v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
                            <v:fill type="tile" color="#f6f6f6"></v:fill>
                        </v:background>
                    <![endif]-->
                    <table class="es-wrapper" width="100%" cellspacing="0" cellpadding="0">
                        <tbody>
                            <tr>
                                <td class="esd-email-paddings" valign="top">
                                    <table class="es-header esd-header-popover" cellspacing="0" cellpadding="0" align="center">
                                        <tbody>
                                            <tr>
                                                <td class="esd-stripe" align="center">
                                                    <table class="es-header-body" style="background-color: transparent;" width="600" cellspacing="0" cellpadding="0" bgcolor="transparent" align="center">
                                                        <tbody>
                                                            <tr>
                                                                <td class="esd-structure es-p20t es-p20b es-p20r es-p20l" style="background-position: left top;" align="left">
                                                                    <!--[if mso]><table width="560" cellpadding="0" cellspacing="0"><tr><td width="270" valign="top"><![endif]-->
                                                                    <table class="es-left" cellspacing="0" cellpadding="0" align="left">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="es-m-p20b esd-container-frame" width="270" align="left">
                                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-block-image" style="font-size: 0px;" align="center">
                                                                                                    <a target="_blank"><img class="adapt-img" src="'.$logomail.'" alt style="display: block;" width="270"></a>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                    <!--[if mso]></td><td width="20"></td><td width="270" valign="top"><![endif]-->
                                                                    <table class="es-right" cellspacing="0" cellpadding="0" align="right">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="esd-container-frame" width="270" align="left">
                                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-empty-container" style="display: none;" align="center"></td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                    <!--[if mso]></td></tr></table><![endif]-->
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="es-content" cellspacing="0" cellpadding="0" align="center">
                                        <tbody>
                                            <tr>
                                                <td class="esd-stripe" align="center">
                                                    <table class="es-content-body" width="600" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center">
                                                        <tbody>
                                                            <tr>
                                                                <td class="esd-structure es-p20t es-p10b es-p20r es-p20l" esd-custom-block-id="54652" style="background-color: transparent;" bgcolor="transparent" align="left">
                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="esd-container-frame" width="560" valign="top" align="center">
                                                                                    <table style="background-position: left top;" width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-block-text es-p10b es-m-txt-l" align="center">
                                                                                                    <h2>'. $titelmail .'<br></h2>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td class="esd-block-spacer es-p20" align="center">
                                                                                                    <table width="100%" height="100%" cellspacing="0" cellpadding="0" border="0">
                                                                                                        <tbody>
                                                                                                            <tr>
                                                                                                                <td style="border-bottom: 1px solid #cccccc; background:none; height:1px; width:100%; margin:0px 0px 0px 0px;"></td>
                                                                                                            </tr>
                                                                                                        </tbody>
                                                                                                    </table>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td class="esd-block-text es-p10b es-m-txt-l" align="left">
                                                                                                    <h2><br>Chère cliente, cher client,<br></h2><br>'. $textmail .'<br><br><br><br>Sujet du ticket : '. $titel .'<br>Numéro du Ticket : '. $ID .' <br><br></td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="es-content" cellspacing="0" cellpadding="0" align="center">
                                        <tbody>
                                            <tr>
                                                <td class="esd-stripe" align="center">
                                                    <table class="es-content-body" width="600" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center">
                                                        <tbody>
                                                            <tr>
                                                                <td class="esd-structure" align="left">
                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="esd-container-frame" width="600" valign="top" align="center">
                                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-block-text" align="left">
                                                                                                    <p>Cordialement,</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="es-content" cellspacing="0" cellpadding="0" align="center">
                                        <tbody>
                                            <tr>
                                                <td class="esd-stripe" align="center">
                                                    <table class="es-content-body" width="600" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center">
                                                        <tbody>
                                                            <tr>
                                                                <td class="es-p20t es-p20b es-p20r es-p20l esd-structure" align="left">
                                                                    <!--[if mso]><table width="560" cellpadding="0" cellspacing="0"><tr><td width="180" valign="top"><![endif]-->
                                                                    <table class="es-left" cellspacing="0" cellpadding="0" align="left">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="esd-container-frame es-m-p20b" width="180" align="left">
                                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-block-image" style="font-size: 0px;" align="left">
                                                                                                    <a target="_blank"><img class="adapt-img" src="'.$signaturemail.'" alt style="display: block;" width="80"></a>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                    <!--[if mso]></td><td width="20"></td><td width="360" valign="top"><![endif]-->
                                                                    <table cellspacing="0" cellpadding="0" align="right">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="esd-container-frame" width="360" align="left">
                                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-block-text" align="left">
                                                                                                    <p style="line-height: 120%;"><br><br><strong>'.$signaturetxt.'</strong></p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                    <!--[if mso]></td></tr></table><![endif]-->
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="es-content esd-footer-popover" cellspacing="0" cellpadding="0" align="center">
                                        <tbody>
                                            <tr>
                                                <td class="esd-stripe" align="center">
                                                    <table class="es-content-body" width="600" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center">
                                                        <tbody>
                                                            <tr>
                                                                <td class="es-p20t es-p20b es-p20r es-p20l esd-structure" align="left">
                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="esd-container-frame" width="560" valign="top" align="center">
                                                                                    <table width="100%" cellspacing="0" cellpadding="0">
                                                                                        <tbody>
                                                                                            <tr>
                                                                                                <td class="esd-block-text" align="left">
                                                                                                    <p style="font-size: 12px;"><br><em>Ce courrier électronique est envoyé automatiquement par le centre de service Easi Support.</em><br><br><br>Généré automatiquement par GLPI.<br></p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </body>

            </html>
        ';
        $footer = "</body></html>";

    $mmail = new GLPIMailer();
    $subject = "Ticket : " . $glpi_tickets->name;

    $mmail->AddAddress($EMAIL);
    $mmail->addAttachment($FILE); // Ajouter un attachement (documents)
    $mmail->isHTML(true);
    $mmail->Subject = $subject;
    $mmail->Body = $header.GLPIMailer::normalizeBreaks($body).$footer;

        if(!$mmail->send()) {
            message("Erreur lors de l'envoi du mail : " . $mmail->ErrorInfo, ERROR);
        }else{
            message("Mail envoyé à " . $EMAIL, INFO);
        }
        
    $mmail->ClearAddresses();
}