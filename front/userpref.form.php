<?php
/**
 * Enregistrement des préférences personnelles du plugin RP
 * (onglet « Boutons flottants » des Préférences GLPI).
 */

Session::checkLoginUser();

// GLPI 12 : la protection CSRF est assurée par le noyau (en-têtes Sec-Fetch-Site / Origin), sans jeton.
if (isset($_POST['update_rp_prefs'])) {

   /*
    * Le lien mobile ne vit pas dans la table des préférences du plugin (il est
    * stocké dans la configuration GLPI, sans migration) : il s'enregistre donc
    * à part, et avant — pour qu'un profil n'ayant QUE ce réglage voie quand
    * même son choix pris en compte, `saveForUser` renvoyant alors faux.
    */
   if (class_exists('PluginRpMobilelink')) {
      PluginRpMobilelink::saveForUser($_POST);
   }

   if (PluginRpUserpref::saveForUser($_POST)) {
      Session::addMessageAfterRedirect(__('Préférences enregistrées.', 'rp'), true, INFO);
   } else {
      Session::addMessageAfterRedirect(__("Échec de l'enregistrement des préférences.", 'rp'), true, ERROR);
   }
}

Html::back();
