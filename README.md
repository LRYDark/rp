# RP (Rapport / CRI PDF)

Plugin GLPI de génération de rapports PDF (prise en charge, rapport technicien, rapport hotline), avec signatures, personnalisation avancée du rendu (logos, titres, couleurs, pied de page), export massif ZIP et APIs d'intégration pour applications tierces.

Le plugin est conçu pour industrialiser la production de comptes-rendus et preuves d'intervention tout en conservant un rendu homogène et des options de signature adaptées à plusieurs usages.

## Ce que fait le plugin (lecture rapide)

- Génère plusieurs types de rapports PDF depuis un ticket.
- Permet de choisir les tâches/suivis à inclure avant génération (selon configuration).
- Gère les signatures sur les documents (prise en charge / technicien / hotline).
- Peut intégrer images, description ticket, éléments publics/privés selon vos règles.
- Propose un export massif de rapports en ZIP.
- Expose des APIs (`prepare / generate / sign`) pour apps ou intégrations externes.

## Fonctionnement (parcours type)

### Génération depuis un ticket (usage classique)

1. L'administrateur configure le rendu PDF, les signatures, les options de contenu et les gabarits.
2. Le technicien ouvre un ticket et lance la préparation RP.
3. Selon vos réglages, un écran permet de sélectionner les tâches, suivis et images à intégrer.
4. Le plugin génère le PDF (un document ou plusieurs selon mode multi-doc).
5. Le document peut être signé, téléchargé, envoyé et/ou archivé selon vos options.

### Export massif (usage exploitation / clôture en lot)

1. Sélectionner plusieurs tickets via les actions massives.
2. Lancer l'export RP massif.
3. Le plugin génère les PDF ticket par ticket puis prépare un ZIP.
4. Télécharger le ZIP final.

### Intégration applicative (API)

- `ticket_prepare`: prépare le contexte de génération (données, sélections possibles, etc.).
- `ticket_generate`: génère effectivement le document.
- `ticket_sign`: enregistre la signature et finalise le flux prévu par l'application cliente.

## Configuration plugin (ce que chaque zone active)

### 1. Configuration mail / notifications

Cette zone sert à piloter l'envoi après génération/signature:
- choix du gabarit de notification (`NotificationTemplate`)
- activation des envois par email
- utilisation de balises dans le contenu (ticket, technicien, client, etc. selon gabarit)

Pourquoi l'utiliser:
- standardiser les emails de remise de rapport
- éviter les envois manuels ticket par ticket

### 2. Options de génération PDF (contenu)

Options typiques vues dans le plugin:
- afficher uniquement les éléments publics (`use_publictask`, options massives équivalentes)
- autoriser la sélection manuelle des tâches/suivis avant génération (`choice`)
- inclure les images des tâches/suivis (`ImgTasks`, `ImgSuivis`)
- inclure/masquer certaines dates (`date`)
- cocher par défaut tâches/suivis publics/privés (`check_public_*`, `check_private_*`)
- mettre à jour les modifications faites dans le modal avant génération (`update_task_on_generate`)

Ces options servent à adapter le document à votre usage:
- rapport client épuré (public uniquement)
- rapport interne complet (public + privé)
- génération rapide (sélections automatiques)
- génération contrôlée (sélection manuelle)

### 3. Signatures

- `sign_rp_charge`: signature sur la prise en charge
- `sign_rp_tech`: signature sur le rapport technicien
- `sign_rp_hotl`: signature sur le rapport hotline

Pourquoi c'est utile:
- vous pouvez imposer la signature seulement sur certains documents
- vous évitez de rallonger le flux sur les rapports qui n'en ont pas besoin

### 4. Titres des rapports

- `titel_pc`: titre rapport prise en charge
- `titel_rt`: titre rapport technicien
- `titel_rh`: titre rapport hotline
- `potitle`: positionnement des titres (selon la logique du plugin)

Cette zone sert à adapter le wording à votre métier (maintenance, régie, hotline, audit, etc.).

### 5. Pied de page / identité visuelle / entités parentes

Le plugin permet de personnaliser le rendu via:
- lignes de texte (`line1` à `line4`)
- prise en compte d'entités parentes (`entity_parrent1`, `entity_parrent2`)
- logo et positionnement (`margin_left`, `margin_top`, `cut`)
- couleurs / éléments visuels selon configuration

Usage typique:
- marque groupe + filiale
- mentions légales / support / hotline
- adaptation du PDF selon l'entité GLPI

### 6. Temps de trajet (si plugin `rt` installé)

Certaines options permettent d'afficher le temps de trajet dans les rapports technicien/hotline.

Pourquoi l'activer:
- faire apparaître un temps facturable ou informatif
- harmoniser le rapport avec les données saisies dans le plugin `rt`

### 7. Mode multi-doc et affichage

Le plugin peut gérer la conservation / affichage de plusieurs rapports selon les options activées (`multi_doc`, `multi_display`).

À utiliser si:
- vous devez conserver plusieurs éditions d'un même rapport
- vous voulez afficher plusieurs documents générés dans le ticket

## Menus / écrans utilisés (vue fonctionnelle)

- Configuration du plugin RP.
- Écrans de génération depuis ticket (préparation, sélection, génération, envoi).
- Actions massives d'export.
- Documentation/API (`front/api_docs.php`).

## Prérequis

- GLPI 11.x (selon version du plugin)
- PHP compatible GLPI
- Bibliothèque PDF disponible côté plugin / environnement GLPI (selon l'implémentation installée)
- Plugin `rt` optionnel (temps de trajet)
- Plugin `gestion` optionnel (flux BL + RP combinés)

## Droits / profils

- Les profils plugin RP contrôlent l'accès à la configuration, aux menus et à la génération.
- Les actions massives et les flux de signature sont conditionnés par ces droits.
- Les APIs s'utilisent avec une authentification GLPI (OAuth v2 / mode legacy selon votre installation).

## APIs / intégrations

### APIs publiques

- `public/api/ticket_prepare.php` : prépare un ticket pour génération (sélections, métadonnées, options disponibles).
- `public/api/ticket_generate.php` : génère le PDF selon les choix transmis.
- `public/api/ticket_sign.php` : finalise la signature et l'enregistrement côté RP.

### AJAX

- `ajax/cri.php` : traitements côté interface RP (selon le parcours de génération utilisé).

## Tâches cron

- Pas de tâche cron principale dédiée détectée dans le plugin RP (le flux est principalement déclenché par l'utilisateur ou par API).

## Architecture (résumé court)

- Le plugin sépare la préparation des données, la génération PDF et la signature.
- Les écrans `front/` gèrent les workflows manuels (ticket, modal, export massif, config).
- Les APIs `public/api` exposent le même métier pour les applications externes.
- Le rendu PDF réutilise des options de branding, de contenu et de signatures centralisées en configuration.

## Vérifications rapides après mise à jour

- Générer un PDF de type prise en charge.
- Générer un PDF de type rapport technicien.
- Générer un PDF de type hotline.
- Tester signatures (si activées).
- Tester l'export massif ZIP.
- Vérifier `front/api_docs.php` et un appel API `prepare -> generate -> sign` si vous avez une app connectée.
- Vérifier le rendu visuel (logos/titres/pied de page) sur au moins 2 entités si vous utilisez des variations.

## Nouveautés 3.3.0

### Accès individuels par utilisateur
Configuration > Rapport > carte « Accès individuels par utilisateur » : pour chaque
fonctionnalité (rapports tech/hotline/préparation, interface mobile, export massif),
choix d'un mode (« Droits du profil GLPI », « Autoriser les utilisateurs sélectionnés »,
« Refuser les utilisateurs sélectionnés ») + liste Select2 d'utilisateurs GLPI.
Logique centralisée dans `PluginRpAccess::canUse()`, contrôlée partout :
boutons, onglet ticket, AJAX, POST direct, export massif, APIs, pages mobiles.
Liste vide ou utilisateur non listé = droits du profil (comportement historique).

### Rapport de préparation (type 3)
Nouveau rapport atelier avant livraison, présenté comme les autres modals du plugin
(cartes colorées, éditeur riche, case « Visible dans le rapport »). Formulaire volontairement
minimal : **numéro de série + marque** (préremplis), **description du problème** (celle du
ticket) et **travaux effectués** (diagnostic, travaux et tests regroupés dans un seul champ).
Le PDF est signé automatiquement par le technicien atelier à partir de sa signature
enregistrée (aucune saisie dans le formulaire ; désactivable via `sign_rp_prep`).
Stocké comme Document du ticket (`_plugins/rp/rapportsPreparation/`), données conservées
dans `glpi_plugin_rp_preparations` (préremplissage / page mobile). Droit dédié
`plugin_rp_rapport_preparation`, titre configurable (`titel_prep`).

### Préremplissage automatique (PluginRpTicketInfo)
`inc/ticketinfo.class.php` détecte les informations déjà présentes pour éviter les
doubles saisies, par ordre de fiabilité : données RP déjà enregistrées > matériel
associé au ticket (n° de série, n° d'inventaire, fabricant, modèle) > réponses d'un
formulaire GLPI ayant créé le ticket > texte du ticket, des suivis et des tâches
(variantes reconnues : `SN`, `S/N`, `s.n`, `N° série`, `numéro de série`,
`Serial Number`, `serial no`, `Service Tag`, valeur sur la ligne suivante) >
demandeur du ticket (nom, e-mail, téléphone). Utilisé par le rapport de préparation
(n° de série, marque) et par la fiche de prise en charge (n° de série,
marque / modèle, nom et coordonnées du responsable matériel, téléphone client).
Toutes les valeurs restent modifiables.

### QR code + interface mobile
Le PDF de préparation porte un QR code (généré via bacon-qr-code du vendor GLPI,
aucune dépendance) vers `front/mobile.php?id=<ticket>&k=<HMAC>` (secret `qr_secret`).
La page mobile exige session GLPI + HMAC valide + droits + règles RP + visibilité du
ticket, affiche l'essentiel (ticket, client, matériel, statut, BL) et deux boutons :
- **Compléter l'intervention** : ouvre le **ticket GLPI** avec le formulaire de tâche
  natif déjà déplié (aucun formulaire parallèle). Une fois la tâche enregistrée, le
  modal de signature du rapport s'ouvre automatiquement (cf. `public/js/fab_rp.js`).
- **Livré — faire signer** : ouvre le rapport d'intervention ; si le plugin Gestion est
  actif et qu'un BL non signé existe, le flux combiné « Rapport + BL » de Gestion est
  ouvert automatiquement (aucune recherche manuelle du BL).

### Boutons flottants (accueil et tickets)
`public/js/fab_rp.js` fournit le socle commun : bouton rond **déplaçable au doigt**
(position mémorisée par navigateur), construit avec les classes natives
`btn btn-primary btn-icon rounded-circle`.

- **Accueil** : bouton de scan / recherche (voir ci-dessous).
- **Ticket** : bouton de signature. Il interroge `ajax/ticket_actions.php` et
  n'affiche que les actions réellement possibles — rapport d'intervention (si des
  tâches existent), fiche de prise en charge (sinon), signature du BL, ou le modal
  combiné « BL + rapport » de Gestion qui propose déjà le choix en haut. Les modals
  ouverts sont ceux qui existent déjà (`rp_loadCriForm` / `gestion_loadCriForm`).

Les deux boutons fonctionnent avec **le plugin RP seul, Gestion seul, ou les deux** :
chaque action est conditionnée à la présence du plugin et aux droits.

**Droits** : droit de profil `plugin_rp_boutons` — bit *Lecture* = bouton d'accueil,
bit *Mise à jour* = bouton sur les tickets.
**Préférences** : onglet « Rapport » des Préférences GLPI
(`inc/userpref.class.php`, table `glpi_plugin_rp_userprefs`), une option par bouton :
**Sur mobile uniquement** (défaut), Toujours, Jamais. L'absence de ligne vaut le
défaut : rien à migrer pour les comptes existants, et le droit de profil reste
prioritaire sur la préférence.

### Tableau « Rapport PDF »
Menu Gestion > Rapport PDF (`front/cridetail.php`) : liste de tous les rapports générés
avec le moteur de recherche GLPI (filtres type, entité, date, ticket, technicien,
signataire, nom du document...). Colonnes par défaut installées par la migration
(type, ticket, date, signataire, document, bouton Visualiser). Droit dédié
`plugin_rp_liste` (lecture / mise à jour / purge). La purge supprime la ligne du
tableau, jamais le Document signé. Le QR code n'apparaît que sur le PDF de
préparation, pas à l'écran.

Barre de statistiques en haut de la liste (même principe que la liste des BL de
Gestion) : total, rapports d'intervention, hotline, préparation et fiches de prise en
charge — chaque carte est cliquable et applique le filtre correspondant. Les compteurs
respectent la restriction d'entité de l'utilisateur. La colonne « Visualiser » affiche
un bouton d'ouverture directe du PDF (et « Document supprimé » si le fichier n'existe
plus), sur le modèle du bouton « Signer » de Gestion.

### Bouton flottant « Scanner / Rechercher » (accueil GLPI)
`public/js/scan_rp.js` + `ajax/scan.php`. Sur la page d'accueil, un bouton flottant
ouvre un modal qui permet de rechercher un BL (`BL123456`), un numéro de ticket ou un
mot-clé, et de scanner avec la caméra : QR code via l'API native `BarcodeDetector`, ou
photo + OCR (bibliothèque chargée uniquement au moment du besoin). L'OCR reconnaît
**les BL comme les tickets** (`BL208207`, `B L 208195`, `TICKET : 55375`, `Ticket n° 55364`,
`#0055339`), le numéro de ticket étant justement imprimé sur les rapports du plugin.

L'habillage utilise les composants natifs GLPI/Tabler (modal Bootstrap, `input-group`,
`list-group`, `badge`, `alert`, `btn`) — le CSS du plugin ne définit que la position du
bouton flottant et le cadre de visée de la caméra. Le plein écran sur téléphone est
obtenu par la classe native `modal-fullscreen-sm-down`.

Le bouton s'affiche sur l'accueil, quelle que soit la route utilisée par GLPI 11
(`/Central`, `/front/central.php`, `/Helpdesk`, racine). Il reste utilisable si l'un
des deux plugins est désactivé : `ajax/scan.php?caps=1` annonce les capacités réelles
(BL et/ou ticket, selon le plugin Gestion actif et les droits de l'utilisateur) et
l'interface adapte ses libellés — recherche ticket seule si Gestion est coupé,
recherche BL seule si l'utilisateur n'a pas de droit sur les tickets.

Aucune logique métier n'est dupliquée : `ajax/scan.php` réutilise
`pluginGestionBlNumber()` pour normaliser les numéros, la table
`glpi_plugin_gestion_surveys` pour retrouver les BL, et renvoie vers les pages
existantes — signature du BL (`gestion/front/survey.form.php`), ticket GLPI, ou page
mobile RP. Le QR code d'un rapport de préparation est reconnu et ouvre directement la
page mobile (jeton HMAC vérifié). Chaque résultat propose les actions disponibles
(signer le BL / voir le BL signé, ouvrir le ticket, intervention mobile) avec un badge
d'état. Le script n'est chargé que pour les utilisateurs concernés et chaque résultat
est filtré par les droits (visibilité du ticket, droit `plugin_gestion_survey`).

### Sécurité
`front/cripdf.form.php`, `ajax/cri.php`, `front/export.massive.php`,
`front/download.export.php` et les APIs vérifient désormais session + droits + règles
d'accès RP ; les requêtes SQL des chemins de génération sont paramétrées.
