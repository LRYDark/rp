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
        $this->Cell(120, 10, 'RAPPORT', 0, 1, 'C');

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
   //récupération de l'ID de l'image s'il y en a une.
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
   $pdf->Cell($w - 2, $h - 2, mb_convert_encoding('Description du problème : ', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

   $pdf->Ln(7);

   // Texte à afficher
   $text = $pdf->ClearSpace($pdf->ClearHtml($glpi_tickets->content));
   $w = 190;
   $lineHeight = 6;

   $pdf->drawRoundedMultiCell($w, $lineHeight, $text);

   $X = $pdf->GetX();
   $Y = $pdf->GetY();

      $query = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $glpi_tickets->id AND itemtype = 'Ticket'");
      while ($data = $DB->fetchArray($query)) {
            if (isset($data['documents_id'])){
               $iddoc = $data['documents_id'];
               $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $iddoc")->fetch_object();
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

// --------- DESCRIPTION

   if($config->fields['use_publictask_massaction'] == 1){
      $is_private = "AND is_private = 0";
   }else{
      $is_private = "";
   }

// --------- TACHES
   $querytask = $DB->query("SELECT glpi_tickettasks.id FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id");
   $sumtask = 0;
   while ($datasum = $DB->fetchArray($querytask)) {
      $sumtask++;  
   }

   if ($sumtask > 0){
      $querytask = $DB->query("SELECT glpi_tickettasks.id, content, date, name, actiontime FROM glpi_tickettasks INNER JOIN glpi_users ON glpi_tickettasks.users_id = glpi_users.id WHERE tickets_id = $Ticket_id $is_private");
         $pdf->Ln(5);
            if ($sumtask < 2){
               $sumtasktext = 'Nombre de tâche : '.$sumtask;
            }else{
               $sumtasktext = 'Nombre de tâches : '.$sumtask;
            }
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
            $pdf->Cell($w - 2, $h - 2, mb_convert_encoding($sumtasktext, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');

         $pdf->Ln(2);          

      while ($data = $DB->fetchArray($querytask)) {
         //récupération de l'ID de l'image s'il y en a une.
         $IdImg = $data['id'];

        /* $pdf->Ln();
         $pdf->MultiCell(0,5,$pdf->ClearSpace($pdf->ClearHtml($data['content'])),1,'L');
         $Y = $pdf->GetY();
         $X = $pdf->GetX();*/
         $pdf->Ln();
         // Texte à afficher
         $text = $pdf->ClearSpace($pdf->ClearHtml($data['content']));
         $w = 190;
         $lineHeight = 6;

         $pdf->drawRoundedMultiCell($w, $lineHeight, $text);

         $X = $pdf->GetX();
         $Y = $pdf->GetY();

            $querytaskdoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg AND itemtype = 'TicketTask'");
            while ($data2 = $DB->fetchArray($querytaskdoc)) {
               if (isset($data2['documents_id'])){
                  $iddoc = $data2['documents_id'];
                  $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $iddoc")->fetch_object();
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
            $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
               $pdf->Ln();
            // temps d'intervention si souhaité lors de la génération
                  $pdf->Write(5,utf8_decode("Temps d'intervention : " . floor($data['actiontime'] / 3600) .  str_replace(":", "h",gmdate(":i", $data['actiontime'] % 3600))));
               $pdf->Ln();
            $sumtask += $data['actiontime'];
      } 
   }else{
      message("Attention, rapport créé sans tâche. Ticket N° $Ticket_id.", WARNING);
   }
// --------- TACHES

// --------- SUIVI
   $querysuivi = $DB->query("SELECT glpi_itilfollowups.id FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id");
   $sumsuivi = 0;
   while ($data = $DB->fetchArray($querysuivi)) {
      $sumsuivi++;  
   } 

   if ($sumsuivi > 0){
      $querysuivi = $DB->query("SELECT glpi_itilfollowups.id, content, date, name FROM glpi_itilfollowups INNER JOIN glpi_users ON glpi_itilfollowups.users_id = glpi_users.id WHERE items_id = $Ticket_id $is_private");
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
            $pdf->Cell($w - 2, $h - 2, mb_convert_encoding($sumsuivitext, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');

      $pdf->Ln(2);

      while ($data = $DB->fetchArray($querysuivi)) {
         //récupération de l'ID de l'image s'il y en a une.
         $IdImg = $data['id'];

         /*$pdf->Ln();
         $pdf->MultiCell(0,5,$pdf->ClearSpace($pdf->ClearHtml($data['content'])),1,'L');
         $Y = $pdf->GetY();
         $X = $pdf->GetX();*/
         $pdf->Ln();
         //$pdf->MultiCell(0,5,$pdf->ClearSpace($pdf->ClearHtml($_POST['SUIVIS_DESCRIPTION'.$data['id']])),1,'L');
         // Texte à afficher
         $text = $pdf->ClearSpace($pdf->ClearHtml($data['content']));
         $w = 190;
         $lineHeight = 6;

         $pdf->drawRoundedMultiCell($w, $lineHeight, $text);

         $X = $pdf->GetX();
         $Y = $pdf->GetY();

            $querysuividoc = $DB->query("SELECT documents_id FROM glpi_documents_items WHERE items_id = $IdImg AND itemtype = 'ITILFollowup'");
            while ($data2 = $DB->fetchArray($querysuividoc)) {
               if (isset($data2['documents_id'])){
                  $iddoc = $data2['documents_id'];
                  $ImgUrl = $DB->query("SELECT filepath FROM glpi_documents WHERE id = $iddoc")->fetch_object();
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
            $pdf->Write(5,utf8_decode('Créé le : ' . $data['date'] . ' par ' . $data['name']));
               $pdf->Ln();
         } 
   }
// --------- SUIVI

// --------- TEMPS D'INTERVENTION
   $pdf->Ln(5);
   if (isset($sumtask)){
      /*$pdf->Cell(80,5,utf8_decode("Temps d'intervention total"),1,0,'L',true);
      $pdf->Cell(110,5,utf8_decode(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600))),1,0,'L');
      $pdf->Ln(7);*/
      $pdf->SetFont('Arial', 'B', 11); // B pour gras
         $pdf->Cell(52,5,"Temps d'intervention total : ",0,0,'L',false);
         $pdf->SetFont('Arial', '', 11);
         $pdf->Cell(110,5,mb_convert_encoding(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8'),0,0,'L');

      //$pdf->Cell(80,5,mb_convert_encoding("Temps d'intervention total", 'ISO-8859-1', 'UTF-8'),1,0,'L',true);
      //$pdf->Cell(110,5,mb_convert_encoding(floor($sumtask / 3600) .  str_replace(":", "h",gmdate(":i", $sumtask % 3600)), 'ISO-8859-1', 'UTF-8'),1,0,'L');
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
               /*$pdf->Cell(80,5,utf8_decode('Temps de trajet total'),1,0,'L',true);
               $pdf->Cell(110,5,utf8_decode(str_replace(":", "h", gmdate("H:i",$sumroutetime*60))),1,0,'L');
               $pdf->Ln(7);*/
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
                           ($Ticket_id, $NewDoc, 2 , '$User->name' , '-' , 0, NOW(), $UserID, $Task_id)";
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









