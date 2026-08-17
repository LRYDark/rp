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
 * Le contenu est transmis TEL QUEL à `RichText::getSafeHtml()`, la fonction
 * que GLPI utilise lui-même pour afficher une description, une tâche ou un
 * suivi. C'est elle qui assainit le HTML, et elle seule.
 */
class PluginRpRichText {

   /**
    * @param string      $name    nom du champ POST
    * @param string|null $content contenu brut, tel qu'il est en base
    */
   static function show(string $name, $content): void {
      Html::textarea([
         'name'              => $name,
         'value'             => Glpi\RichText\RichText::getSafeHtml((string)$content),
         'enable_richtext'   => true,
         'enable_fileupload' => false,
         'enable_images'     => false,
      ]);
   }
}
