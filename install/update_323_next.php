<?php
/**
 * Migration 3.2.3 : reparation des Documents GLPI crees par le plugin dont le
 * `filepath` a ete vide par GLPI 11 (Document::filterFields blackliste filepath
 * et sha1sum de l'input => document.send.php resolvait le dossier racine et
 * plantait en Safe\fread). Concerne : rapports, fiches (prise en charge),
 * rapports hotline, exports massifs (PDF + ZIP) et logos.
 *
 * Idempotente : ne touche que les documents dont filepath est vide/NULL, et
 * n'ecrit que si le fichier physique est retrouve (par nom, match UNIQUE) sous
 * GLPI_DOC_DIR/_plugins/rp.
 */
function update_323_next() {
   global $DB;

   $result = $DB->doQuery("
      SELECT id, filename FROM glpi_documents
      WHERE (filepath = '' OR filepath IS NULL)
        AND filename IS NOT NULL AND filename <> ''");
   if (!$result || $result->num_rows === 0) {
      return;
   }

   // Carte nom de fichier => chemin relatif sous _plugins/rp (false si nom ambigu).
   $base = GLPI_DOC_DIR . '/_plugins/rp';
   $map  = [];
   if (is_dir($base)) {
      $it = new RecursiveIteratorIterator(
         new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($it as $file) {
         if (!$file->isFile()) {
            continue;
         }
         $name = $file->getFilename();
         $rel  = '_plugins/rp' . str_replace('\\', '/', substr($file->getPathname(), strlen($base)));
         $map[$name] = array_key_exists($name, $map) ? false : $rel;
      }
   }

   $fixed   = 0;
   $skipped = 0;
   while ($row = $result->fetch_assoc()) {
      $name = ltrim((string)$row['filename'], '/');
      if (!isset($map[$name]) || $map[$name] === false) {
         continue; // pas un fichier du plugin rp (ou nom ambigu) : on ne touche pas
      }
      $fullpath = GLPI_DOC_DIR . '/' . $map[$name];
      if (!is_file($fullpath)) {
         $skipped++;
         continue;
      }
      $sha1 = @sha1_file($fullpath);
      if ($DB->update('glpi_documents', [
         'filepath' => $map[$name],
         'sha1sum'  => ($sha1 !== false ? $sha1 : null),
      ], ['id' => (int)$row['id']])) {
         $fixed++;
      } else {
         $skipped++;
      }
   }

   if ($fixed > 0 || $skipped > 0) {
      Toolbox::logInFile('plugin-rp', "[update_323] Reparation filepath documents : $fixed corrige(s), $skipped non repare(s)\n");
   }
}
