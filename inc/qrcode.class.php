<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR code du plugin RP.
 *
 * Utilise bacon/bacon-qr-code, déjà présent dans le vendor de GLPI 11 (TOTP du
 * coeur) : aucune dépendance externe. Pour le PDF, la matrice est dessinée
 * directement en rectangles FPDF (pas besoin d'Imagick/GD ni de fichier
 * temporaire) ; pour l'écran, rendu SVG natif.
 *
 * Le QR encode une URL signée vers la page mobile du plugin :
 *   <url_base>/plugins/rp/front/mobile.php?id=<ticket>&k=<HMAC>
 * Le HMAC (secret stocké en config) empêche l'énumération de tickets ; la page
 * mobile exige de toute façon une session GLPI + droits + règles d'accès RP.
 */
class PluginRpQrcode {

   /**
    * Secret HMAC stocké dans la config du plugin (généré par la migration 3.3.0,
    * régénéré ici en secours s'il est vide).
    */
   static function getSecret(): string {
      global $DB;

      $config = PluginRpConfig::getInstance();
      $secret = trim((string)($config->fields['qr_secret'] ?? ''));
      if ($secret !== '') {
         return $secret;
      }

      if (!$DB->fieldExists('glpi_plugin_rp_configs', 'qr_secret')) {
         // Migration pas encore jouée : secret de session impossible à stocker
         return '';
      }

      $secret = bin2hex(random_bytes(32));
      $DB->update('glpi_plugin_rp_configs', ['qr_secret' => $secret], ['id' => 1]);
      // invalide le singleton pour relecture propre
      $config->fields['qr_secret'] = $secret;
      return $secret;
   }

   /**
    * Jeton HMAC liant un ticket à l'URL mobile.
    */
   static function getTicketToken(int $ticket_id): string {
      $secret = self::getSecret();
      if ($secret === '' || $ticket_id <= 0) {
         return '';
      }
      return substr(hash_hmac('sha256', 'rp_mobile:' . $ticket_id, $secret), 0, 40);
   }

   /**
    * Vérification en temps constant du jeton d'une URL scannée.
    */
   static function checkTicketToken(int $ticket_id, string $token): bool {
      $expected = self::getTicketToken($ticket_id);
      return $expected !== '' && $token !== '' && hash_equals($expected, $token);
   }

   /**
    * URL absolue encodée dans le QR (page mobile du ticket).
    */
   static function getTicketUrl(int $ticket_id): string {
      global $CFG_GLPI;

      $token = self::getTicketToken($ticket_id);
      if ($token === '') {
         return '';
      }
      $base = rtrim((string)($CFG_GLPI['url_base'] ?? ''), '/');
      if ($base === '') {
         $base = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/');
      }
      return $base . '/plugins/rp/front/mobile.php?id=' . $ticket_id . '&k=' . $token;
   }

   /**
    * Dessine le QR code dans un PDF FPDF en rectangles pleins.
    *
    * @param \FPDF  $pdf     instance FPDF (ou dérivée)
    * @param string $content contenu à encoder (URL)
    * @param float  $x       abscisse (mm) du coin haut-gauche
    * @param float  $y       ordonnée (mm) du coin haut-gauche
    * @param float  $size    taille (mm) du QR, zone de silence comprise
    * @return bool  false si l'encodage a échoué (PDF non modifié)
    */
   static function drawInPdf($pdf, string $content, float $x, float $y, float $size): bool {
      if ($content === '') {
         return false;
      }

      try {
         $qr = Encoder::encode($content, ErrorCorrectionLevel::M());
      } catch (\Throwable $e) {
         Toolbox::logInFile('plugin-rp', "QR code : échec d'encodage : " . $e->getMessage() . "\n");
         return false;
      }

      $matrix = $qr->getMatrix();
      $modules = $matrix->getWidth();
      if ($modules <= 0) {
         return false;
      }

      $quiet   = 4; // zone de silence standard : 4 modules
      $mod_mm  = $size / ($modules + 2 * $quiet);
      $offset  = $quiet * $mod_mm;

      // fond blanc (le QR doit rester lisible quel que soit le fond)
      $pdf->SetFillColor(255, 255, 255);
      $pdf->Rect($x, $y, $size, $size, 'F');

      $pdf->SetFillColor(0, 0, 0);
      for ($row = 0; $row < $modules; $row++) {
         for ($col = 0; $col < $modules; $col++) {
            if ($matrix->get($col, $row) === 1) {
               $pdf->Rect(
                  $x + $offset + $col * $mod_mm,
                  $y + $offset + $row * $mod_mm,
                  $mod_mm,
                  $mod_mm,
                  'F'
               );
            }
         }
      }
      $pdf->SetFillColor(0, 0, 0);
      return true;
   }

   /**
    * Rendu SVG pour l'affichage écran (onglet ticket).
    *
    * @return string balisage SVG, ou '' en cas d'échec
    */
   static function renderSvg(string $content, int $size = 180): string {
      if ($content === '') {
         return '';
      }
      try {
         $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
         );
         $writer = new Writer($renderer);
         return $writer->writeString($content);
      } catch (\Throwable $e) {
         Toolbox::logInFile('plugin-rp', "QR code : échec du rendu SVG : " . $e->getMessage() . "\n");
         return '';
      }
   }
}
