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
