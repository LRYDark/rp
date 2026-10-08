# Journal des changements

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
