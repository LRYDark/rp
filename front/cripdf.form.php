<?php
include ("../../../inc/includes.php");

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once(PLUGIN_RP_DIR . "/fpdf/fpdf.php");
global $DB, $CFG_GLPI;

$plugin         = new Plugin();
$ticket         = new Ticket();
$ticket_task    = new TicketTask();
$doc            = new Document();
$config         = PluginRpConfig::getInstance();
$UserID         = Session::getLoginUserID();

$Ticket_id      = $_POST['REPORT_ID'];
$Path           = GLPI_PLUGIN_DOC_DIR;

date_default_timezone_set('Europe/Paris');
$date = date('d-m-Y');
$heure = date('H:i');

$User = $DB->doQuery("SELECT name FROM glpi_users WHERE id = $UserID")->fetch_object();
$glpi_tickets = $DB->doQuery("SELECT * FROM glpi_tickets WHERE id = $Ticket_id")->fetch_object();
$glpi_tickets_infos = $DB->doQuery("SELECT * FROM glpi_tickets INNER JOIN glpi_entities ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $Ticket_id")->fetch_object();
$glpi_plugin_rp_dataclient = $DB->doQuery("SELECT * FROM `glpi_plugin_rp_dataclient` WHERE id_ticket = $Ticket_id")->fetch_object();
$ticket_entities = $DB->doQuery("SELECT glpi_tickets.entities_id FROM glpi_tickets INNER JOIN glpi_entities ON glpi_tickets.entities_id = glpi_entities.id WHERE glpi_tickets.id = $Ticket_id")->fetch_object();

/* -- VARIABLES -- */
    if (empty($_POST['url'])) $_POST['url'] = " ";
    if (empty($_POST['email'])) $_POST['email'] = " ";
    if (empty($_POST['name'])) $_POST['name'] = "-";
    if (empty($_POST['society'])) $_POST['society'] = "-";
    if (empty($_POST['town'])) $_POST['town'] = "-";
    if (empty($_POST['address'])) $_POST['address'] = "-";
    if (empty($_POST['postcode'])) $_POST['postcode'] = 0;
    if (empty($_POST['phone'])) $_POST['phone'] = "-";

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

    $URL = $_POST["url"];
    $FORM = $_POST["Form"];
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
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }

    function drawRoundedMultiCell($w, $lineHeight, $text, $radius = 2) {
        $x = $this->GetX();
        $y = $this->GetY();
        $startPage = $this->PageNo();
        $startY = $y;

        // Écrit le texte
        $this->SetXY($x + 1, $y + 1);
        $this->MultiCell($w - 2, $lineHeight, $text, 0, 'L');

        $endPage = $this->PageNo();
        $endY = $this->GetY();

        $k = $this->k;
        $arc = 4 / 3 * (sqrt(2) - 1);

        if ($startPage == $endPage) {
            $h = $endY - $startY;
            $this->RoundedRect($x, $startY, $w, $h, $radius, 'D');
        } else {
            // --- PAGE DE DÉBUT ---
            $this->page = $startPage;
            $bottomY = $this->GetPageHeight() - $this->bMargin;

            // Haut + coins haut
            $this->_out(sprintf('%.2F %.2F m', ($x + $radius) * $k, ($this->h - $startY) * $k));
            $this->_out(sprintf('%.2F %.2F l', ($x + $w - $radius) * $k, ($this->h - $startY) * $k));
            $this->_Arc($x + $w - $radius + $arc * $radius, $startY,
                        $x + $w, $startY + $radius - $arc * $radius,
                        $x + $w, $startY + $radius);
            $this->_out('S');

            // Côté droit
            $this->_out(sprintf('%.2F %.2F m', ($x + $w) * $k, ($this->h - ($startY + $radius)) * $k));
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($this->h - $bottomY) * $k));
            $this->_out('S');

            // Côté gauche + coin haut gauche
            $this->_out(sprintf('%.2F %.2F m', $x * $k, ($this->h - $bottomY) * $k));
            $this->_out(sprintf('%.2F %.2F l', $x * $k, ($this->h - ($startY + $radius)) * $k));
            $this->_Arc($x, $startY + $radius - $arc * $radius,
                        $x + $radius - $arc * $radius, $startY,
                        $x + $radius, $startY);
            $this->_out('S');

            // --- PAGES INTERMÉDIAIRES ---
            for ($p = $startPage + 1; $p < $endPage; $p++) {
                $this->page = $p;
                $topY = $this->tMargin;
                $bottomY = $this->GetPageHeight() - $this->bMargin;

                // Ligne gauche
                $this->_out(sprintf('%.2F %.2F m', $x * $k, ($this->h - $topY) * $k));
                $this->_out(sprintf('%.2F %.2F l', $x * $k, ($this->h - $bottomY) * $k));
                $this->_out('S');

                // Ligne droite
                $this->_out(sprintf('%.2F %.2F m', ($x + $w) * $k, ($this->h - $topY) * $k));
                $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($this->h - $bottomY) * $k));
                $this->_out('S');
            }

            // --- PAGE DE FIN ---
            $this->page = $endPage;
            $topY = $this->tMargin;

            // Ligne gauche
            $this->_out(sprintf('%.2F %.2F m', $x * $k, ($this->h - $topY) * $k));
            $this->_out(sprintf('%.2F %.2F l', $x * $k, ($this->h - ($endY - $radius)) * $k));
            // Coin bas gauche
            $this->_Arc($x, $endY - $radius + $arc * $radius,
                        $x + $radius - $arc * $radius, $endY,
                        $x + $radius, $endY);

            // Ligne bas
            $this->_out(sprintf('%.2F %.2F l', ($x + $w - $radius) * $k, ($this->h - $endY) * $k));

            // Coin bas droit
            $this->_Arc($x + $w - $radius + $arc * $radius, $endY,
                        $x + $w, $endY - $radius + $arc * $radius,
                        $x + $w, $endY - $radius);

            // Ligne droite
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($this->h - $topY) * $k));
            $this->_out('S');
        }
    }

    function Titel() {
        $config = PluginRpConfig::getInstance();
        $doc = new Document();
        $img = $doc->find(['id' => $config->fields['logo_id']]);
        $img = reset($img);

        // Logo
        if (isset($img['filepath'])) {
            $imgPath = GLPI_DOC_DIR . '/' . $img['filepath'];
            if (file_exists($imgPath)) {
                $this->Image($imgPath, 10, 10, 30);
            }
        }

        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(45, 12);
        $this->SetFillColor(41, 128, 185);
        $this->SetTextColor(255, 255, 255);
        $this->RoundedRect(45, 12, 120, 10, 2, 'F'); // coins arrondis avec rayon 2
        $this->SetXY(45, 12);
        //$this->Cell(120, 10, 'RAPPORT D\'INTERVENTION', 0, 1, 'C');
        // titre du pdf
        if($_POST["Form"] == 'FormClient'){
            $this->Cell(120,10,$config->fields['titel_pc'],0,1,'C');
        }
        if($_POST["Form"] == 'FormRapport'){
            $this->Cell(120,10,$config->fields['titel_rt'],0,1,'C');
        }
        if($_POST["Form"] == "FormRapportHotline"){
            $this->Cell(120,10,$config->fields['titel_rh'],0,1,'C');
        }

        // Date
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0);
        if ($config->fields['date'] == 0) {
            date_default_timezone_set('Europe/Paris');
            $this->SetXY(140, 25);
            $date = date("Y-m-d / H:i:s");
            //$this->Cell(60, 5, utf8_decode("Date d'édition : ") . $date, 0, 1, 'R');
            $this->Cell(60, 5, mb_convert_encoding("Date d'édition : ", "ISO-8859-1", "UTF-8") . $date, 0, 1, 'R');
        }

        $this->Ln(10);
    }

    function Footer() {
        $config = PluginRpConfig::getInstance();
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'C');
        $this->Cell(0, 5, mb_convert_encoding($config->fields['line1'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Cell(0, 5, $config->fields['line2'], 0, 0, 'C');
    }
    
    function ClearHtml($text) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        $text = stripcslashes($text);
        $text = htmlspecialchars_decode($text);

        // MAJUSCULES UTF-8 pour les <strong>
        $text = preg_replace_callback('/<strong[^>]*>(.*?)<\/strong>/is', function($matches) {
            return mb_strtoupper($matches[1], 'UTF-8');
        }, $text);

        // Remplacer les balises vides (p, h1-h6) par un marqueur temporaire de saut
        $text = preg_replace('/<\s*(p|h[1-6])[^>]*>\s*(Â|&nbsp;|\xc2\xa0|\s)*<\/\s*\1>/iu', '__FAKE_LINE__', $text);

        // Remplacer les <br> par des vrais sauts de ligne
        $text = str_ireplace(["<br>", "<br/>", "<br />"], "\n", $text);

        // Supprimer toutes les autres balises HTML
        $text = strip_tags($text);

        // Remplace le marqueur temporaire par une vraie ligne vide
        $text = str_replace('__FAKE_LINE__', "\n", $text);

        // Nettoyage final
        $text = Toolbox::decodeFromUtf8($text);
        $text = Glpi\Toolbox\Sanitizer::unsanitize($text);
        $text = str_replace(["’", "?"], "'", $text);

        return $text;
    }

    function ClearSpace($text) {
        $text = preg_replace("/\r\n|\r/", "\n", $text);

        $lines = explode("\n", $text);
        $result = [];
        $emptyCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $emptyCount++;
            } else {
                if ($emptyCount >= 2) {
                    $result[] = ''; // garde un seul saut de ligne
                }
                $emptyCount = 0;
                $result[] = $trimmed;
            }
        }

        if ($emptyCount >= 2) {
            $result[] = '';
        }

        return implode("\n", $result);
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
    if (empty($SOCIETY)) $SOCIETY = "-";
    if (empty($ADDRESS)) $ADDRESS = "-";
    if (empty($TOWN)) $TOWN = "-";
    /*if (empty($PHONE)) $PHONE = "-";
    if (empty($EMAIL)) $EMAIL = "-";*/
    $parts = explode('>', $SOCIETY);
    $clientName = trim(end($parts)); // Résultat : "JCD"
    
    // Position à gauche pour le numéro de ticket
    $pdf->SetFont('Arial', 'B', 11); // B pour gras
    //$pdf->Cell(95, 5, mb_convert_encoding('TICKET : '.$Ticket_id, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C', false, $_SERVER['HTTP_REFERER']);
    // Coordonnées et dimensions
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $w = 60;
    $h = 6;
    $r = 2; // Rayon des coins

    // Dessine le rectangle arrondi
    $pdf->RoundedRect($x, $y, $w, $h, $r, 'D'); // 'DF' pour fond + bord

    // Ajoute le texte à l'intérieur
    $pdf->SetXY($x + 1, $y + 1); // Légèrement décalé pour ne pas coller aux bords
    $pdf->Cell($w - 2, $h - 2, mb_convert_encoding('TICKET : '.$Ticket_id, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C', false, $_SERVER['HTTP_REFERER']);

    // Positionnement à droite
    $x = 90;
    $y = $pdf->GetY();
    $pdf->SetXY($x, $y);
    $pdf->SetFont('Arial', 'B', 11);

    if ($glpi_tickets->requesttypes_id != 7 && $FORM == 'FormClient') {
        $pdf->MultiCell(110, 5, mb_convert_encoding($clientName." / ".$NAMERESPMAT, 'ISO-8859-1', 'UTF-8'), 0, 'L');
    } else {
        $pdf->MultiCell(110, 5, mb_convert_encoding($clientName, 'ISO-8859-1', 'UTF-8'), 0, 'L');
    }

    // Récupérer la nouvelle position Y après MultiCell
    $y = $pdf->GetY();
    $pdf->SetXY($x, $y);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(110, 5, mb_convert_encoding($ADDRESS, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $y = $pdf->GetY();
    $pdf->SetXY($x, $y);
    $pdf->MultiCell(110, 5, mb_convert_encoding($TOWN, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    if (!empty($PHONE)){
        $y = $pdf->GetY();
        $pdf->SetXY($x, $y);
        $pdf->MultiCell(110, 5, mb_convert_encoding($PHONE, 'ISO-8859-1', 'UTF-8'), 0, 'L');
    }

    if (!empty($EMAIL)){
        $y = $pdf->GetY();
        $pdf->SetXY($x, $y);
        $pdf->MultiCell(110, 5, mb_convert_encoding($EMAIL, 'ISO-8859-1', 'UTF-8'), 0, 'L');
    }

    $pdf->Ln(10);
// --------- INFO CLIENT

// --------- DEMANDE
    $pdf->SetFont('Arial', 'B', 12); // B pour gras
    $pdf->Cell(57,5,'Description de la demande : ',0,0,'L',false);
    //$pdf->Ln(5);
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0,5,$pdf->ClearHtml($glpi_tickets->name),0,'L');
    $pdf->Ln(0);
    $pdf->SetFont('Arial', '', 10);
// --------- DEMANDE

// --------- DESCRIPTION
    if(!empty($_POST['CHECK_DESCRIPTION_TICKET']) == 'check'){
        $pdf->Ln(5);
        //$pdf->Cell(190,5,mb_convert_encoding('Description du problème', 'ISO-8859-1', 'UTF-8'),1,0,'C',true);
        // Coordonnées et dimensions
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $w = 190;
        $h = 6;
        $r = 2; // Rayon des coins

        // Dessine le rectangle arrondi
        $pdf->RoundedRect($x, $y, $w, $h, $r, 'F'); // 'DF' pour fond + bord

        // Ajoute le texte à l'intérieur
        $pdf->SetXY($x + 1, $y + 1); // Légèrement décalé pour ne pas coller aux bords
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($w - 2, $h - 2, mb_convert_encoding('Description du problème : ', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        $pdf->SetTextColor(0);

        $pdf->Ln(7);

        //$pdf->MultiCell(0,5,$pdf->ClearSpace($pdf->ClearHtml($_POST['DESCRIPTION_TICKET'].$content)),1,'L');
        // Texte à afficher
        $text = $pdf->ClearSpace($pdf->ClearHtml($_POST['DESCRIPTION_TICKET'].'<br>'.$content));
        $w = 190;
        $lineHeight = 6;

        $pdf->drawRoundedMultiCell($w, $lineHeight, $text);

        $X = $pdf->GetX();
        $Y = $pdf->GetY();
     
            $query = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $glpi_tickets->id AND itemtype = 'Ticket'");
            while ($data = $DB->fetchArray($query)) {
                if (isset($data['documents_id'])){
                    $iddoc = $data['documents_id'];
                    $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $iddoc")->fetch_object();
                }
            
                $img = GLPI_DOC_DIR.'/'.$ImgUrl->filepath;
        
                if (file_exists($img)){
                    $imageSize = getimagesize($img);
                    $width = $imageSize[0];
                    $height = $imageSize[1];
        
                    if($width != 0 && $height != 0){
                        $taille = (100*$height)/$width;
                        
                        if($pdf->GetY() + $taille > 297-15) {
                                $pdf->AddPage();
                                $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                            $pdf->Ln($taille + 5);
                        }else{
                                $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                                $pdf->SetXY($X,$Y+($taille));
                            $pdf->Ln();
                        }  
                    }
                    $Y = $pdf->GetY();
                    $X = $pdf->GetX();             
                }
            }
        // Créé par + temps
        $pdf->SetXY($X,$Y);
    }

    if($FORM == 'FormClient'){
        // commentaire
        $pdf->Ln(5);
        /*$pdf->Cell(190,5,mb_convert_encoding('Commentaire(s)'),1,0,'C',true);
        $pdf->Ln();
        $tx = "...............................................................................................................................................................................................";
        $pdf->MultiCell(190,8,$tx.$tx.$tx,1,'L');
        $pdf->Ln();*/
    }
// --------- DESCRIPTION

if($config->fields['use_publictask'] == 1){
    $is_private = "AND is_private = 0";
}else{
    $is_private = "";
}
// --------- TACHES
    if($FORM == 'FormRapport' || $FORM == 'FormRapportHotline'){
        $querytask = $DB->doQuery("SELECT glpi_tickettasks.id FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id");
        $sumtask = 0;

        while ($datasum = $DB->fetchArray($querytask)) {
            if(!empty($_POST['tasks_pdf_'.$datasum['id']])) $sumtask++;  
        }

        if ($sumtask > 0){
            $querytask = $DB->doQuery("SELECT glpi_tickettasks.id, content, date, name, actiontime FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id $is_private");
               $pdf->Ln(5);
                    if ($sumtask < 2){
                        $sumtasktext = 'Nombre de tâche : '.$sumtask;
                    }else{
                        $sumtasktext = 'Nombre de tâches : '.$sumtask;
                    }
                    //$pdf->Cell(30,5,mb_convert_encoding('Nombre de Tâche(s) : '.$sumtask, 'ISO-8859-1', 'UTF-8'),1,0,'L',true);
                    // Coordonnées et dimensions
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    $w = 45;
                    $h = 6;
                    $r = 2; // Rayon des coins

                    // Dessine le rectangle arrondi
                    $pdf->RoundedRect($x, $y, $w, $h, $r, 'F'); // 'DF' pour fond + bord

                    // Ajoute le texte à l'intérieur
                    $pdf->SetXY($x + 1, $y + 1); // Légèrement décalé pour ne pas coller aux bords
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->Cell($w - 2, $h - 2, mb_convert_encoding($sumtasktext, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
                    $pdf->SetTextColor(0);

                $pdf->Ln(2);            
      
            while ($data = $DB->fetchArray($querytask)) {
                //verifications que la variable existe
                if(!empty($_POST['tasks_pdf_'.$data['id']])){
        
                    $pdf->Ln();
                    //$pdf->MultiCell(0,5,$pdf->ClearSpace($pdf->ClearHtml($_POST['TASKS_DESCRIPTION'.$data['id']])),0,'L');
                    // Texte à afficher
                    $text = $pdf->ClearSpace($pdf->ClearHtml($_POST['TASKS_DESCRIPTION' . $data['id']]));
                    $w = 190;
                    $lineHeight = 6;

                    $pdf->drawRoundedMultiCell($w, $lineHeight, $text);

                    $X = $pdf->GetX();
                    $Y = $pdf->GetY();
        
                    if (isset($_POST['rapportimgtask'])){
                        //récupération de l'ID de l'image s'il y en a une.
                        $IdImg = $data['id'];
                        $querytaskdoc = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg AND itemtype = 'TicketTask'");
                        while ($data2 = $DB->fetchArray($querytaskdoc)) {
                            if (isset($data2['documents_id'])){
                            $iddoc = $data2['documents_id'];
                            $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $iddoc")->fetch_object();
                            }
                        
                            $img = GLPI_DOC_DIR.'/'.$ImgUrl->filepath;
            
                            if (file_exists($img)){
                                $imageSize = getimagesize($img);
                                $width = $imageSize[0];
                                $height = $imageSize[1];
                
                                if($width != 0 && $height != 0){
                                    $taille = (100*$height)/$width;
                                    
                                        if($pdf->GetY() + $taille > 297-15) {
                                            $pdf->AddPage();
                                            $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                                            $pdf->Ln($taille + 5);
                                        }else{
                                            $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                                            $pdf->SetXY($X,$Y+($taille));
                                            $pdf->Ln();
                                        }  
                                }
                                $Y = $pdf->GetY();
                                $X = $pdf->GetX();             
                            }
                        }
                    }
            
                    // Créé par + temps
                    $pdf->SetXY($X,$Y+1);
                        $pdf->Write(5,mb_convert_encoding('Créé le : ' . $_POST['tasks_date_'.$data['id']] . ' par ' . $_POST['tasks_name_'.$data['id']], 'ISO-8859-1', 'UTF-8'));
                    $pdf->Ln();
                    // temps d'intervention si souhaité lors de la génération
                        $pdf->Write(5,mb_convert_encoding("Temps d'intervention : " . floor($_POST['tasks_time_'.$data['id']] / 3600) .  str_replace(":", "h",gmdate(":i", $_POST['tasks_time_'.$data['id']] % 3600)), 'ISO-8859-1', 'UTF-8'));
                    $pdf->Ln();
                    $sumtask += $_POST['tasks_time_'.$data['id']];
    
                }
            } 
        }
    
// --------- TACHES

// --------- SUIVI
        $query = $DB->doQuery("SELECT glpi_itilfollowups.id FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id");
        $sumsuivi = 0;

        while ($data = $DB->fetchArray($query)) {
            if(!empty($_POST['suivis_pdf_'.$data['id']])) $sumsuivi++;  
        } 

        if ($sumsuivi > 0){
            $querysuivi = $DB->doQuery("SELECT glpi_itilfollowups.id, content, date, name FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id $is_private");
               $pdf->Ln(5);
                    if ($sumsuivi < 2){
                        $sumsuivitext = 'Nombre de suivi : '.$sumsuivi;
                    }else{
                        $sumsuivitext = 'Nombre de suivis : '.$sumsuivi;
                    }
                    //$pdf->Cell(190,5,mb_convert_encoding('Suivi(s) : '.$sumsuivi, 'ISO-8859-1', 'UTF-8'),1,0,'L',true);
                    // Coordonnées et dimensions
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    $w = 45;
                    $h = 6;
                    $r = 2; // Rayon des coins

                    // Dessine le rectangle arrondi
                    $pdf->RoundedRect($x, $y, $w, $h, $r, 'F'); // 'DF' pour fond + bord

                    // Ajoute le texte à l'intérieur
                    $pdf->SetXY($x + 1, $y + 1); // Légèrement décalé pour ne pas coller aux bords
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->Cell($w - 2, $h - 2, mb_convert_encoding($sumsuivitext, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
                    $pdf->SetTextColor(0);

               $pdf->Ln(2);

            while ($data = $DB->fetchArray($querysuivi)) {
                //verifications que la variable existe
                if(!empty($_POST['suivis_pdf_'.$data['id']])){
                    
                    $pdf->Ln();
                    //$pdf->MultiCell(0,5,$pdf->ClearSpace($pdf->ClearHtml($_POST['SUIVIS_DESCRIPTION'.$data['id']])),1,'L');
                    // Texte à afficher
                    $text = $pdf->ClearSpace($pdf->ClearHtml($_POST['SUIVIS_DESCRIPTION' . $data['id']]));
                    $w = 190;
                    $lineHeight = 6;

                    $pdf->drawRoundedMultiCell($w, $lineHeight, $text);

                    $X = $pdf->GetX();
                    $Y = $pdf->GetY();

                    if (isset($_POST['rapportimgsuivi'])){
                        //récupération de l'ID de l'image s'il y en a une.
                        $IdImg = $data['id'];
                
                        $querysuividoc = $DB->doQuery("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg AND itemtype = 'ITILFollowup'");
                        while ($data2 = $DB->fetchArray($querysuividoc)) {
                            if (isset($data2['documents_id'])){
                                $iddoc = $data2['documents_id'];
                                $ImgUrl = $DB->doQuery("SELECT filepath FROM glpi_documents WHERE id = $iddoc")->fetch_object();
                            }
                        
                            $img = GLPI_DOC_DIR.'/'.$ImgUrl->filepath;
            
                            if (file_exists($img)){
                                $imageSize = getimagesize($img);
                                $width = $imageSize[0];
                                $height = $imageSize[1];
            
                                if($width != 0 && $height != 0){
                                $taille = (100*$height)/$width;
                                
                                    if($pdf->GetY() + $taille > 297-15) {
                                            $pdf->AddPage();
                                            $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                                        $pdf->Ln($taille + 5);
                                    }else{
                                            $pdf->Image($img,$X,$pdf->GetY()+2,100,$taille);
                                            $pdf->SetXY($X,$Y+($taille));
                                        $pdf->Ln();
                                    }  
                                }
                                $Y = $pdf->GetY();
                                $X = $pdf->GetX();                
                            }
                        }
                    }
            
                    // Créé par + temps
                    $pdf->SetXY($X,$Y+1);
                    $pdf->Write(5,mb_convert_encoding('Créé le : ' . $_POST['suivis_date_'.$data['id']] . ' par ' . $_POST['suivis_name_'.$data['id']], 'ISO-8859-1', 'UTF-8'));
                    $pdf->Ln();
                   
                }         
            } 
        }
// --------- SUIVI

// --------- TEMPS D'INTERVENTION
            $pdf->Ln(5);
        if (isset($_POST['rapporttime'])){
                $pdf->SetFont('Arial', 'B', 11); // B pour gras
                $pdf->Cell(52,5,"Temps d'intervention total : ",0,0,'L',false);
                $pdf->SetFont('Arial', '', 11);
                $pdf->Cell(110,5,mb_convert_encoding(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8'),0,0,'L');

            //$pdf->Cell(80,5,mb_convert_encoding("Temps d'intervention total", 'ISO-8859-1', 'UTF-8'),1,0,'L',true);
            //$pdf->Cell(110,5,mb_convert_encoding(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8'),1,0,'L');
            $pdf->Ln(7);
        }
    }
// --------- TEMPS D'INTERVENTION

// --------- TEMPS DE TRAJET
    if ($plugin->isActivated('rt')) {
        if ($FORM == "FormRapportHotline" && $config->fields['time_hotl'] == 1 || $FORM == 'FormRapport' && $config->fields['time'] == 1){
            $sumroutetime = 0;
            $timeroute = $DB->doQuery("SELECT routetime FROM `glpi_plugin_rt_tickets` WHERE tickets_id = $Ticket_id");
                while ($data = $DB->fetchArray($timeroute)) {
                    $sumroutetime += $data['routetime'];
                }

            if ($FORM == "FormRapportHotline" && $sumroutetime != 0){
                $pdf->SetFont('Arial', 'B', 11); // B pour gras
                $pdf->Cell(42,5,'Temps de trajet total : ',0,0,'L',false);
                $pdf->SetFont('Arial', '', 11);
                $pdf->Cell(110,5,mb_convert_encoding(str_replace(":", "h", gmdate("H:i",$sumroutetime*60)), 'ISO-8859-1', 'UTF-8'),0,0,'L');

                //$pdf->Cell(80,5,mb_convert_encoding('Temps de trajet total', 'ISO-8859-1', 'UTF-8'),1,0,'L',true);
                //$pdf->Cell(110,5,mb_convert_encoding(str_replace(":", "h", gmdate("H:i",$sumroutetime*60)), 'ISO-8859-1', 'UTF-8'),1,0,'L');
                $pdf->Ln(7);
            }elseif ($FORM != "FormRapportHotline"){
                $pdf->SetFont('Arial', 'B', 11); // B pour gras
                $pdf->Cell(42,5,'Temps de trajet total : ',0,0,'L',false);
                $pdf->SetFont('Arial', '', 11);
                $pdf->Cell(110,5,mb_convert_encoding(str_replace(":", "h", gmdate("H:i",$sumroutetime*60)), 'ISO-8859-1', 'UTF-8'),0,0,'L');

                //$pdf->Cell(80,5,mb_convert_encoding('Temps de trajet total', 'ISO-8859-1', 'UTF-8'),1,0,'L',true);
                //$pdf->Cell(110,5,mb_convert_encoding(str_replace(":", "h", gmdate("H:i",$sumroutetime*60)), 'ISO-8859-1', 'UTF-8'),1,0,'L');
                $pdf->Ln(7);
            }
        }
    }
// --------- TEMPS DE TRAJET

// --------- SIGNATURE
$signature = "false";
if ($FORM == "FormRapportHotline" && $config->fields['sign_rp_hotl'] == 1)$signature = "true";
if ($FORM == "FormRapport" && $config->fields['sign_rp_tech'] == 1)$signature = "true";
if ($FORM == "FormClient" && $config->fields['sign_rp_charge'] == 1)$signature = "true";

    if($signature == 'true'){
        $glpi_plugin_rp_signtech = $DB->doQuery("SELECT seing FROM glpi_plugin_rp_signtech WHERE user_id = $UserID")->fetch_object();

        $pdf->Ln(10);
        //$pdf->Cell(95,39," ",1,0,'L');	//tableau 1
        //$pdf->Cell(95,39," ",1,0,'L'); //tableau 2                
        $pdf->Cell(95, 35, " ", 'LRB', 0, 'L'); // L = gauche, R = droite, B = bas
        $pdf->Cell(95, 35, " ", 'LRB', 0, 'L'); // L = gauche, R = droite, B = bas    

            $pdf->Ln(-7);
        /*$pdf->Cell(95,5,'Client',1,0,'C',true); //tableau 1
            $Y = $pdf->GetY();//recupere coordonné de Y
            $X = $pdf->GetX();//recupere coordonné de X
        $pdf->Cell(95,5,'Technicien',1,0,'C',true); //tableau 2*/

        // Coordonnées et dimensions
        $x = $pdf->GetX() + 2;
        $y = $pdf->GetY();
        $w = 91;
        $h = 6;
        $r = 2; // Rayon des coins

        // Dessine le rectangle arrondi
        $pdf->RoundedRect($x, $y, $w, $h, $r, 'F'); // 'DF' pour fond + bord

        // Ajoute le texte à l'intérieur
        $pdf->SetXY($x + 1, $y + 1); // Légèrement décalé pour ne pas coller aux bords
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($w - 2, $h - 2, mb_convert_encoding('Client', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        $pdf->SetTextColor(0);

        $Y = $pdf->GetY();//recupere coordonné de Y
        $X = $pdf->GetX()+3;//recupere coordonné de X

        $x = $pdf->GetX() + 5;
        $y = $pdf->GetY() - 1;
        $w = 91;
        $h = 6;
        $r = 2; // Rayon des coins

        // Dessine le rectangle arrondi
        $pdf->RoundedRect($x, $y, $w, $h, $r, 'F'); // 'DF' pour fond + bord

        // Ajoute le texte à l'intérieur
        $pdf->SetXY($x + 1, $y + 1); // Légèrement décalé pour ne pas coller aux bords
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($w - 2, $h - 2, mb_convert_encoding('Technicien', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        $pdf->SetTextColor(0);

        // ------ tableau 1
            $pdf->Write(5,"Nom : " . mb_convert_encoding($NAME, 'ISO-8859-1', 'UTF-8')); 
                $pdf->Ln();
            $pdf->Write(5,"Signature :");
                $pdf->Ln();
            if(!empty($URL)) $pdf->Image($URL,15,$Y+15,0,0,'PNG');
        // ------ tableau 1

        // ------ tableau 2
                $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup 
            $pdf->Write(15,"Nom : " . mb_convert_encoding($User->name, 'ISO-8859-1', 'UTF-8')); 
                $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup 
            $pdf->Write(25,"Signature :");
                $pdf->SetXY($X,$Y);// on deplace le curceur aux coordonnées recup 
            if (isset($glpi_plugin_rp_signtech)) $pdf->Image($glpi_plugin_rp_signtech->seing,110,$Y+15,0,0,'PNG');
        // ------ tableau 2   
    }
// --------- SIGNATURE

        $pdf->Output(); // affichage du PDF

/** *********************************************************************************************************
   ------------------ Informations d'enregistement -------------------------------------------------------
********************************************************************************************************** */
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
    $NAME               = $User->name;
}
$SeeFilePath            = $SeePath . $FileName;

$glpi_plugin_rp_cridetails = $DB->doQuery("SELECT * FROM `glpi_plugin_rp_cridetails` WHERE id_ticket = $Ticket_id AND users_id = $UserID AND type = $TypeRapport ORDER BY date DESC LIMIT 1")->fetch_object();
    // par defaut
    $Task_id        = 'NULL'; 
    $AddValue       = 'true';
    $AddDetails     = 'false';
    $AddDoc         = 'false';
    $AddOrUpdate    = "false";
    $Verfi_query_rp_cridetails = 'false';

    if($MAILTOCLIENT == ''){
        $MAILTOCLIENT = 0;
    }
    if($MAILTOCLIENT == 0){
        $EMAIL = '';
    }

// documents -> generation pdf + liaison bdd table document / table cridetails -> add id task si une tache est crée via le form client.
    $glpi_plugin_rp_cridetails_MultiDoc = $DB->doQuery("SELECT id, id_documents, id_task FROM `glpi_plugin_rp_cridetails` WHERE id_ticket = $Ticket_id AND type = $TypeRapport ORDER BY date DESC LIMIT 1")->fetch_object();
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
                            SET nameclient = '$NAME', email = '$EMAIL', send_mail = $MAILTOCLIENT, date = NOW(), users_id = $UserID
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
            message("Erreur de l'enregistrement du PDF (link error) -> glpi_documents", ERROR);
        }
    }

    if($FORM == 'FormClient'){ // formulaire de prise en charge
        if(!empty($glpi_plugin_rp_cridetails->id_task)){
            $TaskExiste = $DB->doQuery("SELECT id FROM glpi_tickettasks WHERE tickets_id = $Ticket_id AND id = $glpi_plugin_rp_cridetails->id_task")->fetch_object();
            $Task_id = $TaskExiste->id;
        }
        if($glpi_tickets->requesttypes_id != 7){
            $origin = date_create($glpi_plugin_rp_cridetails->date);
            $target = date_create(date("Y-m-d H:i:s"));
            $interval = date_diff($origin, $target);
            $hour = $interval->format('%h');
            $day = $interval->format('%y%m%d');

            if($day == 000 && $hour < 1 && !empty($glpi_plugin_rp_cridetails->id_task) && !empty($TaskExiste->id)){

                message("<i class='fa-solid fa-triangle-exclamation'></i> Une prise en charge datent de moins 1H déjà existante. <br> 
                        Modification automatique de la tâche en cours ...", WARNING); 
                        
                $input = ['id' => $Task_id,
                            'tickets_id' => $Ticket_id,
                            'content' => addslashes($content)];

                if($ticket_task->update($input)){
                    message('Élément mit à jour avec succès : (Tâche -> '.$Task_id.')', INFO);
                }else{
                    message('Échec de la mise à jour : (Tâche -> '.$Task_id.')', WARNING);
                }
    
                if($config->fields['multi_doc'] == 1){
                    $DB->doQuery("UPDATE glpi_plugin_rp_cridetails 
                    SET id_documents = $NewDoc, nameclient = '$NAME', email = '$EMAIL', send_mail = $MAILTOCLIENT, date = NOW(), users_id = $UserID
                    WHERE id_task = $Task_id AND id_ticket = $Ticket_id");
                }
                $AddValue = "false";
            }else{
                $input = ['tickets_id'      => $Ticket_id,
                        'users_id'        => Session::getLoginUserID(),
                        'users_id_tech'   => Session::getLoginUserID(),
                        'content'         => addslashes($content),
                        'state'           => 1,
                        'actiontime'      => 300,
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
                    if(!$DB->doQuery($query)){
                        message("Echec de la mise à jour des informations client", WARNING);
                    }else{
                        message("Information(s) client mit à jour avec succès.", INFO);
                    }
                }else{
                    if($SOCIETY != $glpi_plugin_rp_dataclient->society || $TOWN != $glpi_plugin_rp_dataclient->town || $ADDRESS != $glpi_plugin_rp_dataclient->address || $POSTCODE != $glpi_plugin_rp_dataclient->postcode || $PHONE != $glpi_plugin_rp_dataclient->phone){
                        $update= "UPDATE glpi_plugin_rp_dataclient SET society='$SOCIETY', address='$ADDRESS', town='$TOWN', postcode='$POSTCODE', 
                                phone='$PHONE', email='$EMAIL' , serial_number = '$SERIALNUMBER' WHERE id_ticket=$Ticket_id;";
                        if(!$DB->doQuery($update)){
                            message("Echec de la mise à jour des informations client", WARNING);
                        }else{
                            message("Information(s) client mit à jour avec succès.", INFO);
                        }
                    }
                }
            }
        // info client mise a jour des coordonnés sur le ticket ----------------------
    }
    if($AddValue == 'true'){
        $query_rp_cridetails= "INSERT INTO glpi_plugin_rp_cridetails 
                            (`id_ticket`, `id_documents`, `type`, `nameclient`, `email`, `send_mail`, `date`, `users_id`, `id_task`) 
                            VALUES 
                            ($Ticket_id, $NewDoc, $TypeRapport , '$NAME' , '$EMAIL' , $MAILTOCLIENT, NOW(), $UserID, $Task_id)";
        $Verfi_query_rp_cridetails = 'true';
    }
    if ($Verfi_query_rp_cridetails == 'true'){
        if($DB->doQuery($query_rp_cridetails)){
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

if ($MAILTOCLIENT == 1 && $config->fields['email'] == 1){

    // génération et gestion des balises
        //VARIABLE AVANT BALISES
        $Rapportdetails = $DB->doQuery("SELECT date, id_documents FROM `glpi_plugin_rp_cridetails` WHERE id_ticket = $Ticket_id AND users_id = $UserID AND type = $TypeRapport ORDER BY date DESC LIMIT 1")->fetch_object();
        $CategorieTicket = $DB->doQuery("SELECT name FROM glpi_itilcategories WHERE id=$glpi_tickets->itilcategories_id")->fetch_object();
        $WebUrl = substr($_SERVER['REQUEST_URI'], 0, 5);
        if ($WebUrl != '/glpi'){$WebUrl = $_SERVER['HTTP_HOST'];}else{$WebUrl = $_SERVER['HTTP_HOST'] . $WebUrl;}
        if ($FORM == "FormRapportHotline"){$RapportTypeTitel = "Rapport d'intervention";$RapportType = "le rapport";}
        if ($FORM == "FormRapport"){$RapportTypeTitel = "Rapport d'intervention";$RapportType = "le rapport d'intervention";}
        if ($FORM == "FormClient"){$RapportTypeTitel = "Fiche de prise en charge";$RapportType = "la fiche de prise en charge";}

        //BALISES
        $Balises = array(
            array('Balise' => '##document.weblink##'        , 'Value' => "<a href='$WebUrl/front/document.send.php?docid=$Rapportdetails->id_documents'>Adresse du document</a>"),
            array('Balise' => '##ticket.id##'               , 'Value' => sprintf("%07d", $Ticket_id)),
            array('Balise' => '##ticket.url##'              , 'Value' => "<a href='$WebUrl/front/ticket.form.php?id=$Ticket_id'>Adresse du ticket</a>"),
            array('Balise' => '##ticket.creationdate##'     , 'Value' => $glpi_tickets->date_creation),
            array('Balise' => '##ticket.closedate##'        , 'Value' => $glpi_tickets->closedate),
            array('Balise' => '##task.time##'               , 'Value' => mb_convert_encoding(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8')),
            array('Balise' => '##ticket.description##'      , 'Value' => html_entity_decode($glpi_tickets->content, ENT_QUOTES, 'UTF-8')),
            array('Balise' => '##ticket.entity.address##'   , 'Value' => mb_convert_encoding($ADDRESS, 'ISO-8859-1', 'UTF-8')),
            array('Balise' => '##ticket.entity##'           , 'Value' => mb_convert_encoding($SOCIETY, 'ISO-8859-1', 'UTF-8')),
          //array('Balise' => '##ticket.entity.email##'     , 'Value' => mb_convert_encoding($EMAIL)),
            array('Balise' => '##ticket.category##'         , 'Value' => $CategorieTicket->name),
            array('Balise' => '##ticket.time##'             , 'Value' => mb_convert_encoding(floor($glpi_tickets->actiontime / 3600) .  str_replace(":", "h",gmdate(":i", $glpi_tickets->actiontime % 3600)), 'ISO-8859-1', 'UTF-8')),
            array('Balise' => '##ticket.title##'            , 'Value' => html_entity_decode($glpi_tickets->name, ENT_QUOTES, 'UTF-8')),
            array('Balise' => '##rapport.type.titel##'      , 'Value' => $RapportTypeTitel),
            array('Balise' => '##rapport.type##'            , 'Value' => $RapportType),
            array('Balise' => '##rapport.date.creation##'   , 'Value' => $Rapportdetails->date),
        );
    // génération et gestion des balises

    function balise($corps){
        global $Balises;
        foreach($Balises as $balise) {
            $corps = str_replace($balise['Balise'], $balise['Value'], $corps);
        }
        return $corps;
    }
   
    // génération du mail 
    $mmail = new GLPIMailer();

    $notificationtemplates_id = $config->fields['gabarit'];
    $NotifMailTemplate = $DB->doQuery("SELECT * FROM glpi_notificationtemplatetranslations WHERE notificationtemplates_id=$notificationtemplates_id")->fetch_object();
        $BodyHtml = html_entity_decode($NotifMailTemplate->content_html, ENT_QUOTES, 'UTF-8');
        $BodyText = html_entity_decode($NotifMailTemplate->content_text, ENT_QUOTES, 'UTF-8');

    $footer = $DB->doQuery("SELECT value FROM glpi_configs WHERE name = 'mailing_signature'")->fetch_object();
    if(!empty($footer->value)){$footer = html_entity_decode($footer->value, ENT_QUOTES, 'UTF-8');}else{$footer='';}

    // For exchange
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");

    if (empty($CFG_GLPI["from_email"])){
        // si mail expediteur non renseigné    
        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
    }else{
        //si mail expediteur renseigné  
        $mmail->SetFrom($CFG_GLPI["from_email"], $CFG_GLPI["from_email_name"], false);
    }

    $mmail->AddAddress($EMAIL);
    $mmail->addAttachment($SeeFilePath); // Ajouter un attachement (documents)
    $mmail->isHTML(true);

    // Objet et sujet du mail 
    $mmail->Subject = balise($NotifMailTemplate->subject);
        $mmail->Body = GLPIMailer::normalizeBreaks(balise($BodyHtml)).$footer;
        $mmail->AltBody = GLPIMailer::normalizeBreaks(balise($BodyText)).$footer;

        // envoie du mail
        if(!$mmail->send()) {
            message("Erreur lors de l'envoi du mail : " . $mmail->ErrorInfo, ERROR);
        }else{
            message("<br>Mail envoyé à " . $EMAIL, INFO);
        }
        
    $mmail->ClearAddresses();
}
