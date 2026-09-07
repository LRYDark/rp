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
- Créer un ticket (fiche classique ET formulaire GLPI) : le message de création doit proposer le lien mobile à copier.
- Vérifier `front/api_docs.php` et un appel API `prepare -> generate -> sign` si vous avez une app connectée.
- Vérifier le rendu visuel (logos/titres/pied de page) sur au moins 2 entités si vous utilisez des variations.

## Nouveautés 3.3.0

### Accès individuels par utilisateur
Configuration > Rapport > carte « Accès individuels par utilisateur » : pour chaque
fonctionnalité (rapports tech/hotline/préparation, interface mobile, partage du lien mobile),
choix d'un mode (« Droits du profil GLPI », « Autoriser les utilisateurs sélectionnés »,
« Refuser les utilisateurs sélectionnés ») + liste Select2 d'utilisateurs GLPI.
Logique centralisée dans `PluginRpAccess::canUse()`, contrôlée partout :
boutons, onglet ticket, AJAX, POST direct, export massif, APIs, pages mobiles.
Liste vide ou utilisateur non listé = droits du profil (comportement historique).
« Autoriser » est ADDITIF (les listés ont accès même sans le droit de profil, personne ne
perd rien) ; « Refuser » est SOUSTRACTIF (les listés perdent l'accès même avec le droit,
personne ne gagne rien). Deux usages : droit ouvert dans le profil + « Refuser » quelques
utilisateurs, ou droit fermé dans le profil + « Autoriser » quelques utilisateurs, qui sont
alors les seuls à y accéder. Un « Autorisé » obtient tous les niveaux de la fonctionnalité
(lecture, création, modification, suppression définitive).
L'export massif (`plugin_rp_pdf`) et la supervision des rapports en attente
(`plugin_rp_supervision`) restent des droits de profil purs : aucune règle par utilisateur
n'est proposée ni appliquée pour eux (`per_user => false` dans `getFeatures()`).

### Rapport d'intervention : deux portes
La carte « Rapport d'intervention » de l'onglet suit son seul droit. Mais le rapport
d'atelier se conclut par un rapport d'intervention — « Le client repart avec » dans son
formulaire, QR code scanné chez le client, étape suivante après la livraison. Le droit
« Rapport d'atelier » en création ouvre donc aussi la production du rapport d'intervention
(`PluginRpAccess::canProduce('rapport_tech')`) : formulaire, PDF, bouton flottant, scanner,
bandeau « Étape suivante », APIs, et le combiné « Rapport + BL » de Gestion (qui se replie sur
l'ancien test si RP est plus ancien). Seule la carte reste cachée ; régénération et
suppression définitive restent au droit d'intervention. Un refus individuel de
l'intervention ne ferme pas cette porte. L'écran de signature du technicien (menu Outils)
s'ouvre avec n'importe lequel des quatre droits de rapport.

Pour que le technicien atelier voie ce qu'il a fait signer, la carte « Rapport d'atelier »
liste AUSSI les rapports d'intervention du ticket, sous un sous-titre, avec la même limite
d'affichage que les autres cartes (« Enregistrement de plusieurs rapports »), et porte un
badge « Intervention signée ». Un rapport d'atelier pur reste un rapport d'atelier ; le
rapport d'intervention est le même document, qu'il vienne de l'atelier ou de sa carte. Le
bloc n'apparaît que s'il existe au moins un rapport d'intervention.

**Étape suivante** : chaîne de candidats, le premier proposable l'emporte — fiche (dossier
vierge, sans tâche), atelier (seulement pour qui n'a pas le droit officiel du rapport
d'intervention — sinon l'intervention prime — dès qu'il manque, même si une intervention
existe déjà, et de nouveau quand le rapport est dépassé), combiné « Rapport + BL », rapport
seul, hotline (pour qui n'a ni l'atelier ni l'intervention) ; puis le bon en attente, dernière
étape d'un dossier conclu. Un dossier est conclu quand un rapport d'intervention ou hotline
existe et qu'aucune tâche ni suivi n'a été ajouté ou modifié depuis — même comptage que le
modal « Rapport + BL » de Gestion (`countChangesSinceReport`). Dépassé, le rapport compte
comme absent et le cycle reprend, dans l'onglet comme dans le bouton flottant et le scanner.

### Fiche de prise en charge et rapport d'intervention : deux droits
Longtemps confondus sous `plugin_rp_rapport_tech`, ils sont séparés : la fiche (type 0)
relève de `plugin_rp_fiche` et de la règle `fiche`, le rapport (type 1) garde
`plugin_rp_rapport_tech` et la règle `rapport_tech`. Onglet ticket, purge, formulaire,
génération, bouton flottant, APIs : chaque type consulte le sien. La signature du
technicien (menu Outils) s'ouvre avec l'un ou l'autre. L'interface mobile
(`plugin_rp_mobile`) et le partage du lien mobile (`plugin_rp_lien_mobile`) ont aussi leur
droit de profil, une case Lecture, au lieu de dépendre du rapport d'intervention : plus
besoin de lister chaque utilisateur dans les règles individuelles pour les ouvrir largement.
**Aucune migration à jouer** : au premier changement de profil après la mise à jour des
fichiers, `PluginRpProfile::migrateSplitRights()` crée chaque droit absent, profil par
profil, à partir de `plugin_rp_rapport_tech` (valeur copiée telle quelle pour la fiche ;
Lecture cochée si Créer l'était, pour les deux droits mobiles), et recopie la règle
individuelle `rapport_tech` vers `fiche`. Elle ne fait rien pour un droit déjà présent.

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

### Bandeau « Étape suivante »
`PluginRpCridetail::getNextStepHtml()` rend l'étape recommandée par
`PluginRpTicketActions::build()` (clé `next`) — la même source que le bouton flottant et le
scanner, donc les écrans ne peuvent pas diverger. Placé **tout en haut** de l'onglet RP,
avant le bandeau « Signatures » et les quatre cartes (on ouvre cet onglet pour avancer, pas
pour relire), et **au-dessus du tableau** de l'onglet « Gestion BL » du plugin Gestion, qui
l'appelle si RP est actif. Ordre suivi : prise en charge → atelier → BL + rapport (ou
rapport seul si aucun BL non signé). Rien n'est affiché si aucune étape ne se dégage.
`showNextStep()` reste l'équivalent qui écrit directement à l'écran.

Bouton en `btn-info` + liseré `card-status-start bg-info`, là où tous les autres boutons de
ces écrans sont en `primary` : sans cela le bandeau se noyait dans la colonne d'actions. Le
vert est évité (déjà pris par les badges « Signé »). Classes sémantiques Tabler et jamais de
couleur en dur — `--tblr-info` suit le thème GLPI, sombre compris.

**Plusieurs BL non signés** : au-delà d'un seul, `gestion/ajax/cri.php` bascule sur
`showCombinedMultiForm()`, qui propose chaque bon avec une case à cocher — on en signe un,
plusieurs ou tous — avec un unique rapport et une seule signature. Les actions `combined` et
`bl` passent donc au pluriel et annoncent le nombre de bons en attente au lieu d'en citer un.
Comptage : `countUnsignedBls()`.

**Étape `bl`** : quand le rapport d'intervention est déjà fait et qu'il reste des bons non
signés, `build()` recommande « Signer les bons de livraison » (mode `bl_only` côté Gestion :
pas de second rapport). Le cheminement s'arrêtait auparavant à cet endroit et ne
recommandait plus rien, alors qu'il restait la chose la plus visible du ticket.

### Rangement des PDF : `<type>/<année>/<mois>/`
`pluginRpDatedFolder()` (setup.php) range désormais tous les PDF produits par le plugin en
`_plugins/rp/<type>/<année>/<mois>/` — mois en toutes lettres sans accent, **exactement la
convention du plugin Gestion** (`front/traitement.php`). Concerne `fiches`, `rapports`,
`rapportsHotline`, `rapportsPreparation` et `rapportsMass` (PDF **et** archive ZIP de
l'export massif). Le dossier est créé au besoin ; en cas d'échec, repli sur le dossier plat
plutôt que de perdre le rapport.

**Aucune migration** : les PDF déjà rangés à plat restent lisibles. L'existence d'un fichier
se juge sur `glpi_documents.filepath` — le seul chemin qui fasse foi — et non sur un dossier
reconstruit à partir du type. Cette reconstruction était d'ailleurs déjà fausse pour les
rapports produits en lot (`rapportsMass`).

Le dossier est créé **récursivement** (l'année comme le mois manquent au premier document du
mois). En cas d'échec : repli sur le dossier historique à plat, qui est lui aussi recréé si
besoin — un PDF mal rangé vaut mieux qu'un rapport perdu. `cripdf.form.php` vérifie ensuite
que le fichier est bien sur le disque **avant** d'enregistrer son chemin en base, et le
signale à l'écran sinon : un Document qui pointe dans le vide produit le « Fichier
introuvable sur le disque » des listes, découvert des semaines plus tard.

### Régénération : remplace ou ajoute, selon le mode
- **`multi_doc = 0`** (un seul document) — régénérer **remplace** : la ligne `glpi_documents`
  est réécrite vers le nouveau PDF, et **l'ancien fichier est maintenant supprimé du disque**.
  Il ne l'était pas : chaque régénération laissait un orphelin de plus. La suppression a lieu
  **après** écriture du nouveau PDF, et seulement si celui-ci est bien présent — mieux vaut
  garder l'ancien qu'une génération ratée.
- **`multi_doc = 1`** (plusieurs rapports) — régénérer **ajoute** : chaque PDF est un
  exemplaire daté, **rien n'est supprimé**. `multi_display` limite l'affichage, pas le stockage.

### Onglet ticket : cartes repliables
La carte « Signatures » a été **retirée** : elle reprenait dans une seconde mise en page ce
que les cartes de couleur portent maintenant elles-mêmes.

- **Badge « Signé » contre le titre** (`signedBadge()`), à la place exacte de l'ancien chevron.
- **« Signé le : <date> »** sur chaque ligne — « Généré le » quand la signature n'est pas
  établie.

Le **pliage des cartes a été supprimé** avec le chevron : il cachait la liste derrière un
geste, et obligeait à afficher le badge deux fois (en-tête + ligne) pour compenser. Les cartes
restent ouvertes, un seul badge dit l'état.

La notion de « signé » reste celle de `getSignedRows()` : prise en charge et rapport
d'intervention signés par le client, rapport d'atelier signé par le technicien. Le rapport
hotline en est exclu — son champ signataire est écrasé par le nom du technicien à la
génération, il serait déclaré signé à tort ; sa carte n'a donc jamais de badge.

`getSignedRows()` / `getSignedTypes()` / `getSignedRowIds()` sont la source **unique** du
badge de carte et du libellé de date : les deux ne peuvent pas diverger.

### Contenu des cards : liste, plus de tableau
Les quatre tableaux à cinq colonnes sont remplacés par une **liste** (`showDocumentList()`) :
nom du fichier en gras, signataire et destinataire en gris dessous, statut, actions et date à
droite. Même présentation que l'onglet « Gestion BL » du plugin Gestion, et lisible sur
téléphone — ce qu'un tableau à cinq colonnes n'était pas.

`getDocumentTypeDef()` porte ce qui distingue chaque type (dossier(s) de stockage, libellé du
signataire, présence d'un destinataire, message « aucun document », droit associé). Les quatre
blocs recopiés à la main — et leurs divergences involontaires — disparaissent. Le nombre de
lignes affichées suit toujours le réglage `multi_display`.

Un fichier absent du disque n'est plus masqué : la ligne s'affiche en rouge avec « Fichier
introuvable sur le disque », et reste supprimable pour faire le ménage.

### Suppression définitive : actions massives GLPI
Pas de bouton « Supprimer » par ligne — il aurait doublonné avec la barre « Actions » et
chargé la ligne d'un élément de plus. Chaque carte porte une **case à cocher** par document
et **une** barre « Actions » sous la liste, comme l'onglet « Gestion BL » du plugin Gestion.

Le ménage est fait par `PluginRpCriDetail::cleanDBonPurge()`, donc **identique quelle que soit
la voie empruntée** — carte du ticket ou tableau « Rapport PDF » du menu Gestion. Ce dernier
supprimait jusqu'ici des lignes sans toucher aux fichiers : les PDF restaient sur le disque,
référencés par plus rien. Le Document GLPI est purgé (`delete(..., 1)`), ce qui déclenche
`Document::cleanDBonPurge()` et retire le PDF ; le Document est épargné s'il est encore
référencé par une autre ligne de rapport.

Droits, deux portes (`canPurgeItem()`) :
- le droit **Purger** du TYPE (`plugin_rp_fiche` / `_rapport_tech` / `_hotline` / `_preparation`), ajouté
  à la matrice des profils — pour le technicien qui fait le ménage sur son ticket ;
- `plugin_rp_liste` en purge, droit historique du tableau, conservé tel quel.

`canPurge()` (droit de classe) est élargi de la même façon : sans cela, un profil autorisé à
purger ses rapports d'atelier mais pas le tableau général n'aurait jamais vu l'action.

> ⚠️ Une seule barre d'actions est affichée (sous la liste) — il faut donc lui passer
> `'forcecreate' => true` : `Html::showMassiveActions()` ne déclare la fenêtre modale que sur
> l'appel `ontop`, et sans cela le lien appelle une fonction JS jamais définie
> (« modal_massiveaction_window… is not defined »).

> ⚠️ `createFirstAccess()` attribue `ALLSTANDARDRIGHT` (= 31, PURGE compris) à ces trois
> droits. Les profils déjà créés ainsi voient donc l'action **immédiatement**. Pour la
> fermer, décocher « Purger » sur le profil concerné.

### QR code + interface mobile
Le PDF de préparation porte un QR code (généré via bacon-qr-code du vendor GLPI,
aucune dépendance) vers `front/mobile.php?id=<ticket>&k=<HMAC>` (secret `qr_secret`).
La page exige session GLPI + HMAC valide + **visibilité native du ticket**
(`canViewItem`), puis oriente selon le public — le QR voyage avec le matériel, il est
scanné aussi bien par le technicien que par le client :
- **avec** la fonctionnalité RP `mobile` (droit de profil `plugin_rp_mobile`) :
  l'écran d'action décrit ci-dessous ;
- **sans** : redirection vers le ticket natif `front/ticket.form.php?id=<ticket>`, qui
  sert les deux interfaces — le client demandeur atterrit sur son ticket dans son espace
  simplifié. Un technicien redirigé (profil du téléphone ≠ profil du poste) reçoit en
  plus un message le lui expliquant.

Seul refus restant : ne pas voir le ticket (ou QR périmé). L'écran d'action affiche
l'essentiel (ticket, client, matériel, statut, BL) et deux boutons :
- **Compléter l'intervention** : ouvre le **ticket GLPI** avec le formulaire de tâche
  natif déjà déplié (aucun formulaire parallèle). Une fois la tâche enregistrée, le
  modal de signature du rapport s'ouvre automatiquement (cf. `public/js/fab_rp.js`).
- **Livré — faire signer** : ouvre le rapport d'intervention ; si le plugin Gestion est
  actif et qu'un BL non signé existe, le flux combiné « Rapport + BL » de Gestion est
  ouvert automatiquement (aucune recherche manuelle du BL).

### Lien mobile (fiche du ticket et message de création)
Le lien mobile est **l'URL du QR code** du rapport d'atelier
(`front/mobile.php?id=<ticket>&k=<HMAC>`, cf. `PluginRpQrcode::getTicketUrl`) : même
page, même jeton, mêmes verrous. Il se transmet sans passer par le papier — collé dans
le planning d'un technicien, envoyé au client. Deux endroits :
- **Fiche du ticket** : champ « Lien mobile » dans le panneau de droite (hook
  `post_item_form`, `inc/mobilelink.class.php`), avec bouton de copie.
- **Message de création** : le toast qui confirme la création d'un ticket — « Élément
  ajouté » de la fiche classique, ou « Élément créé » d'un formulaire GLPI — reçoit le
  lien prêt à copier, sans ouvrir le ticket. Le toast reste alors affiché 30 s (10 s par
  défaut), et tant que la souris est dessus.

Le toast n'appartient pas au plugin (celui du formulaire est construit en JS à partir
d'une réponse JSON du noyau) : le lien y est **ajouté après coup, côté navigateur**. Le
hook `item_add` note en session les tickets que la session vient de créer ;
`public/js/mobilelink_rp.js` observe les toasts (`shown.bs.toast`), y repère les liens
vers des tickets et interroge `ajax/mobilelink.php`, qui ne répond que pour les tickets
notés — droit et préférence vérifiés — puis les oublie. C'est ce qui distingue une
création d'une modification : « Élément modifié : Ticket #12 » ne reçoit rien.

**Droit** : fonctionnalité `lien_rapide` (« Partage du lien mobile depuis le ticket »),
droit de profil `plugin_rp_lien_mobile` (Lecture), surchargeable par utilisateur dans les
accès individuels. Sans ce droit, le lien n'apparaît nulle part.
**Préférences** : carte « Lien mobile » de l'onglet du plugin dans les Préférences GLPI,
deux réglages indépendants — le champ de la fiche, le lien dans le message de création —
chacun Afficher (défaut) / Masquer. Stockés dans `glpi_configs` (contexte `plugin:rp`,
une ligne par refus) : aucune migration.

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
bit *Mise à jour* = bouton sur les tickets. Avec Gestion actif, RP fournit seul les
boutons, et le droit « Boutons flottants » de l'un OU de l'autre plugin
(`plugin_gestion_boutons`) suffit à les afficher (`PluginRpUserpref::hasAnyRight()`). Ces
droits ne décident que de l'affichage : le contenu des boutons reproduit l'onglet du ticket
et suit les droits des fonctionnalités — rapports selon RP, bons selon `plugin_gestion_survey`.
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
