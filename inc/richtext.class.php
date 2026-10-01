<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/*
 * Classe autonome, sans héritage : elle ne représente aucun élément GLPI et ne
 * rend qu'un champ. Hériter de CommonDBTM réserverait des dizaines de noms de
 * méthodes sans rien apporter.
 */

/**
 * Zone de saisie riche des formulaires du plugin.
 *
 * Point d'entrée UNIQUE : description du ticket, tâches, suivis et travaux
 * passent tous par ici, avec exactement le même traitement. Auparavant chaque
 * formulaire refaisait l'appel de son côté, et la fiche de prise en charge
 * comme les rapports d'intervention prétraitaient la description avant de
 * l'afficher — décodage des entités, suppression des paragraphes vides et
 * surtout `strip_tags()`. Le contenu arrivait donc en texte brut dans un
 * éditeur riche : gras, listes et retours à la ligne disparaissaient, et
 * l'affichage ne correspondait plus à celui du ticket dans GLPI.
 *
 * Le contenu est transmis à `RichText::getSafeHtml()`, la fonction que GLPI
 * utilise lui-même pour afficher une description, une tâche ou un suivi. C'est
 * elle qui assainit le HTML, et elle seule. Seule préparation en amont :
 * decodeLegacy(), pour le contenu ancien stocké avec ses balises encodées.
 */
class PluginRpRichText {

   /**
    * @param string      $name    nom du champ POST
    * @param string|null $content contenu brut, tel qu'il est en base
    */
   static function show(string $name, $content): void {
      Html::textarea([
         'name'              => $name,
         'value'             => Glpi\RichText\RichText::getSafeHtml(self::decodeLegacy((string)$content)),
         'enable_richtext'   => true,
         'enable_fileupload' => false,
         'enable_images'     => false,
      ]);
   }

   /**
    * Contenu ancien stocké avec ses balises ENCODÉES, sans aucune vraie
    * balise : `&lt;p&gt;Bonjour,&lt;/p&gt;` (GLPI 9.5 et avant) ou
    * `&#60;p&#62;Bonjour,&#60;/p&#62;` (GLPI 10). getSafeHtml() le prend pour du
    * texte brut, et l'éditeur affichait les balises en toutes lettres.
    *
    * GLPI 11 décode lui-même ces contenus quand il les lit par $DB (réglage
    * `must_unsanitize_db_data`), mais la description du ticket est lue par le
    * plugin directement sur le résultat MySQL et échappe à ce décodage. On
    * applique donc le décodeur de GLPI, le même : l'éditeur montre ce que
    * montre la fiche du ticket.
    *
    * Deux passes au plus, pour le double encodage (`&amp;lt;p&amp;gt;`). Un
    * contenu qui a déjà une vraie balise n'est jamais touché. Le résultat
    * n'est pas assaini : il passe toujours ensuite par getSafeHtml().
    */
   static function decodeLegacy(string $content): string {
      $decoder = new Glpi\Toolbox\SanitizedStringsDecoder();
      for ($pass = 0; $pass < 2; $pass++) {
         if (preg_match('~<[a-z/!]~i', $content)) {
            break;
         }
         $decoded = $decoder->decodeHtmlSpecialChars($content);
         if ($decoded === $content) {
            break;
         }
         $content = $decoded;
      }
      return $content;
   }

   /**
    * Forme de comparaison pour « le technicien a-t-il modifié ce texte ? »
    * (front/cripdf.form.php, réglage update_task_on_generate).
    *
    * On compare le TEXTE, pas le HTML. L'éditeur ne rend jamais le HTML qu'il
    * a reçu : TinyMCE remplace <b> par <strong>, les couleurs rgb() par leur
    * code, ajoute des retours à la ligne entre les blocs, et retire les images
    * (désactivées dans ces éditeurs). Comparé en HTML, un texte non touché
    * passait pour modifié, et la génération réécrivait le ticket. Le PDF, lui,
    * ne garde que le texte : seule une modification du texte compte.
    *
    * decodeLegacy() d'abord : l'éditeur renvoie décodé un contenu ancien que
    * la base garde encodé. Un espace avant chaque balise, pour que deux
    * paragraphes ne se collent pas une fois les balises retirées.
    */
   static function comparable($content): string {
      $html = Glpi\RichText\RichText::getSafeHtml(self::decodeLegacy((string)$content));
      $text = Glpi\RichText\RichText::getTextFromHtml(str_replace('<', ' <', $html), false);
      return trim(preg_replace('~[\s\x{00A0}]+~u', ' ', $text));
   }
}
