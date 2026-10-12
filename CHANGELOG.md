# Journal des changements

## 3.4.0 — 2026-10-12

Première version pour GLPI 12 (12.0.x). La série 3.3.x reste celle de GLPI 11.

- **Compatible GLPI 12 uniquement.** GLPI 12 déclare le type des propriétés de ses classes (`$rightname`,
  `$dohistory`…) ; une classe du plugin qui les redéfinit doit porter le même type, ce que GLPI 11 refuse. Les deux
  séries ne sont donc pas interchangeables.
- API retirées par GLPI 12 remplacées : `Plugin::getWebDir()`, `Html::entities_deep()`, `Html::display*Error()`,
  `Toolbox::sendFile()`, `Toolbox::addslashes_deep()`, `Glpi\Toolbox\Sanitizer`.
- **File d'attente des signatures hors-ligne sans jeton** (`rp_outbox.js`, jumeau de celui de Gestion).
  `ajax/csrf.php` est supprimé.
- **API du plugin et API v3 de GLPI** : les écrans et la documentation pointent vers `/api.php/v3.0/token`.
  `ticket_prepare` ouvre une session temporaire, comme l'API de GLPI ; `ticket_generate` et `ticket_sign` gardent une
  session enregistrée, nécessaire au générateur qu'ils rejouent.
- Documents créés par le plugin : la taille du fichier est renseignée (nouvelle colonne de GLPI 12).
- Correction : `front/profile.form.php` contrôlait un droit inexistant.
- **Plus de jeton CSRF.** GLPI 12 contrôle l'origine de la requête (en-têtes `Sec-Fetch-Site` / `Origin`) : les
  champs `_glpi_csrf_token`, les en-têtes `X-Glpi-Csrf-Token` et les appels à `Session::checkCSRF()` /
  `getNewCSRFToken()` sont retirés.
- Inclusion de `inc/includes.php` retirée (obsolète).
- Aucune migration de base.

## 3.3.2 — 2026-10-06

- **Garanties d'envoi** : si la file d'attente est indisponible (écriture refusée, erreur de base), le mail part en direct comme avant — la file n'empêche jamais un envoi. Un mail envoyé aussitôt est mis en file avec une heure d'envoi décalée de 5 minutes, pour que la tâche « queuednotification » ne l'envoie pas une seconde fois pendant l'envoi immédiat.
- **Mail du rapport au client envoyé par la file d'attente des notifications de GLPI**, aussitôt : le message
  « Mail envoyé à … » s'affiche toujours tout de suite. En cas d'échec, le mail reste en file et GLPI le renvoie
  automatiquement (avertissement à l'écran). Le PDF du rapport est joint par GLPI : il est rattaché à la ligne du
  rapport (pas au ticket : il n'apparaît pas dans les documents du ticket ni dans ses autres notifications).
- **Repli en envoi direct, comme avant**, quand GLPI ne pourrait pas joindre le PDF : destinataire sans compte
  GLPI alors que l'option « Ajouter les documents aux notifications envoyées aux utilisateurs anonymes » est
  désactivée (Configuration → Notifications → Configuration des suivis par courriels), ou rattachement impossible.
  Le client reçoit toujours son PDF.
- Aucune migration de base.
