# Journal des changements

## 3.3.3 — 2026-11-02

**Après la mise à jour : cliquer « Mettre à jour » dans Configuration → Plugins.**

- Rapport (fiche de prise en charge) : plus d'avertissements « Undefined array key "model" / "idsession" /
  "DataSave" / "DataFormatting" » quand ces champs manquent à l'envoi (API, formulaire plus ancien).
- Mails texte sans pièce jointe envoyés par la file d'attente de GLPI : plus d'avertissement « Undefined array
  key "attach_documents_to_notifications_for_anonymous" » quand ce réglage manque dans la base de GLPI (vu en
  production le 02/11/2026).
- Mise à jour lancée en console (`bin/console plugin:install`) : la version était lue dans la session, absente
  en console, et les migrations ne jouaient pas. Elle est lue désormais dans la constante du plugin (même
  valeur sur le web), comparée avec `version_compare`.
- Dépôt : workflows modèles retirés (Codacy, CodeQL, Defender, Fortify, SonarCloud, SonarQube, PHPMD,
  rust-clippy) : sans configuration ni secrets, ils échouaient chaque semaine. Remplacés par la CI.
- CI GitHub : syntaxe sur PHP 8.2 à 8.5, installation réelle dans GLPI 11.0.12 (neuve et mise à jour depuis
  la release précédente), chargement de chaque classe, parcours des pages et de leurs onglets.
- **Gabarit « Rapport PDF » en double à chaque mise à jour** : un test inversé (`$existeColumn == 'false'`, une
  chaîne) créait un gabarit de plus à chaque mise à jour et remettait la configuration sur le premier, effaçant le
  gabarit choisi. Il n'est créé que s'il n'existe pas, et le choix est conservé. Les doublons déjà créés restent :
  à supprimer dans *Configuration → Notifications → Gabarits de notification* s'ils ne servent à rien.

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
