<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Partage du lien mobile d'un ticket.
 *
 * Le lien est EXACTEMENT celui du QR code imprimé sur le rapport d'atelier
 * (`PluginRpQrcode::getTicketUrl`) : même page, même jeton HMAC, mêmes
 * verrous. Rien de nouveau n'est exposé — on donne seulement le moyen de le
 * transmettre sans passer par le papier, pour le coller dans le planning d'un
 * technicien qui n'aura jamais le rapport en main.
 *
 * La page d'arrivée se dégrade seule : avec le plugin Gestion elle propose
 * « Rapport + BL », sans lui le rapport seul (cf. front/mobile.php).
 *
 * ---- Deux endroits, un seul droit ----
 *
 *  1. un CHAMP dans le panneau de droite de la fiche du ticket (showForItem) ;
 *  2. le MESSAGE qui confirme la création d'un ticket — « Élément ajouté » de
 *     la fiche classique, « Élément créé » d'un formulaire GLPI — où le lien
 *     arrive prêt à copier, sans avoir à ouvrir le ticket.
 *
 * Le droit `lien_rapide` ferme les deux d'un coup ; chacun peut ensuite
 * retirer l'un ou l'autre de SON interface (onglet des préférences).
 *
 * ---- Comment le lien arrive dans le message ----
 *
 * Le toast n'est pas le nôtre. Celui de la fiche classique est rendu par le
 * noyau à la page suivante ; celui du formulaire est construit en JavaScript
 * à partir d'une réponse JSON que le plugin ne peut pas modifier. Le lien y
 * est donc AJOUTÉ après coup, côté navigateur :
 *
 *   - le hook `item_add` (onTicketAdd) note en session les tickets que CETTE
 *     session vient de créer ;
 *   - `public/js/mobilelink_rp.js` observe les toasts affichés, y repère les
 *     liens vers des tickets et interroge `ajax/mobilelink.php` ;
 *   - l'AJAX (takeCreatedLinks) ne répond que pour les tickets notés, droits
 *     vérifiés, puis les oublie.
 *
 * La note en session est ce qui distingue un toast de CRÉATION d'un toast de
 * modification : « Élément modifié : Ticket #12 » porte le même lien vers le
 * ticket, et n'a rien à faire du lien mobile.
 */
class PluginRpMobilelink {

   /**
    * Clé des préférences, dans la configuration GLPI plutôt qu'en base plugin.
    *
    * UNE LIGNE PAR UTILISATEUR ET PAR REFUS : le réglage par défaut ne stocke
    * rien. C'est ce qui permet de les ajouter sans toucher au schéma — donc
    * sans migration ni changement de version — et, chacun n'écrivant que ses
    * propres lignes, deux utilisateurs ne peuvent pas s'écraser l'un l'autre
    * comme le ferait une liste commune.
    */
   const CONFIG_CONTEXT = 'plugin:rp';

   /** Tickets créés par la session courante, en attente de leur message */
   const SESSION_KEY = 'plugin_rp_mobilelink_created';

   /** Au-delà, un ticket créé n'est plus « récent » (secondes) */
   const CREATED_TTL = 900;

   /** Nombre maximal de tickets mémorisés par session */
   const CREATED_MAX = 20;

   /**
    * Durée d'affichage (ms) d'un toast où le lien a été ajouté.
    *
    * Les toasts de GLPI disparaissent au bout de 10 s : le temps de lire
    * « Élément ajouté », pas celui de copier un lien. Bootstrap suspend de
    * toute façon le compte à rebours tant que la souris est sur le toast.
    */
   const TOAST_DELAY = 30000;

   /** @var array<int,array{field:bool,toast:bool}> cache par utilisateur */
   private static $flags_cache = [];

   /**
    * Hook `post_item_form` : rendu dans la colonne de droite du ticket.
    *
    * Le hook est appelé pour TOUS les itemtypes et à plusieurs endroits du
    * formulaire (timeline, sous-formulaires de tâche ou de suivi) : les
    * conditions ci-dessous sont ce qui empêche la carte d'apparaître six fois
    * sur la page.
    */
   static function showForItem($params): void {
      $item = is_array($params) ? ($params['item'] ?? null) : null;
      if (!($item instanceof Ticket)) {
         return;
      }

      $ticket_id = (int)$item->getID();
      if ($ticket_id <= 0) {
         return; // création : le ticket n'a pas encore d'URL
      }

      /*
       * Le droit ferme la fonctionnalite d'un coup.
       *
       * Sans utilisateur autorise — droit de profil absent, ou regle « Refuser »
       * dans la configuration RP — la carte n'est rendue nulle part, et il n'y a
       * donc aucun autre endroit d'ou le lien pourrait fuir.
       */
      if (!PluginRpAccess::canUse('lien_rapide')) {
         return;
      }

      /*
       * Chacun peut le retirer de SON ticket.
       *
       * Un technicien qui ne diffuse jamais de lien n'a pas a subir une ligne
       * de plus dans un panneau deja dense. Le reglage vit dans ses
       * preferences GLPI (onglet du plugin) et ne regarde que lui : il ne
       * retire le droit a personne d'autre.
       */
      if (!self::isEnabledForUser()) {
         return;
      }

      // Le lien ne vaut que pour qui voit deja le ticket : la page d'arrivee
      // le reverifie, mais proposer de partager ce qu'on ne peut pas ouvrir
      // n'aurait aucun sens.
      if (!$item->canViewItem()) {
         return;
      }

      $url = PluginRpQrcode::getTicketUrl($ticket_id);
      if ($url === '') {
         /*
          * Secret HMAC indisponible (migration du plugin pas encore jouee).
          * On le DIT : un champ absent laisserait croire a un manque de
          * droits, alors que c'est une mise a jour qui manque.
          */
         echo "<div class='form-field row align-items-center col-12 glpi-full-width mb-2'>";
         echo "  <label class='col-form-label col-xxl-5 text-xxl-end'>" . __('Lien mobile', 'rp') . "</label>";
         echo "  <div class='col-xxl-7 field-container'><span class='text-secondary'>"
            . __("Indisponible : mettez à jour le plugin RP.", 'rp')
            . "</span></div>";
         echo "</div>";
         return;
      }

      $dom_id = 'rp_mobile_link_' . $ticket_id;

      /*
       * Un CHAMP du panneau, pas une carte posee dessus.
       *
       * En carte, le bloc debordait a droite des listes voisines : le panneau
       * aligne ses champs sur une grille (`col-xxl-5` / `col-xxl-7`), qu'une
       * carte pleine largeur ignore. Les classes ci-dessous sont exactement
       * celles que produit la macro `fields.textField` employee juste au-dessus
       * pour « ID externe » — le lien s'aligne donc par construction, et non
       * par un reglage de marge qui casserait au premier changement de theme.
       */
      echo "<div class='form-field row align-items-center col-12 glpi-full-width mb-2 rp-mobilelink-field'>";
      echo "  <label class='col-form-label col-xxl-5 text-xxl-end' for='" . $dom_id . "'>"
         . __('Lien mobile', 'rp') . "</label>";
      echo "  <div class='col-xxl-7 field-container'>";

      /*
       * L'URL est MONTREE, pas seulement copiee.
       *
       * Un bouton qui annonce « copié » sans rien afficher demande de le
       * croire sur parole. Le champ, en lecture seule, permet aussi la
       * selection manuelle — seul recours si le presse-papier est refuse par
       * le navigateur.
       */
      /*
       * Le bouton est un ELEMENT du groupe, pas un bouton pose dans une
       * cellule.
       *
       * Enveloppe dans un `input-group-text`, il restait plus petit que la
       * cellule qui le contenait : au survol, seul son carre central changeait
       * de couleur, et la moitie de la surface cliquable ne reagissait pas. En
       * enfant direct de l'`input-group`, il prend toute la hauteur du champ —
       * ce qu'on survole est exactement ce sur quoi on clique.
       *
       * Le bouton est cable par `public/js/mobilelink_rp.js` (attribut
       * `data-rp-copy-link`), charge par setup.php sous les MEMES conditions
       * que ce champ : droit `lien_rapide` et preference de l'utilisateur.
       */
      echo "    <div class='input-group'>";
      echo "      <input type='text' class='form-control' readonly"
         . " id='" . $dom_id . "' value='" . htmlspecialchars($url, ENT_QUOTES) . "'"
         . " onclick='this.select();'>";
      echo "      <button type='button' class='btn btn-outline-secondary px-3'"
         . " data-rp-copy-link='" . $dom_id . "'"
         . " title='" . __s('Copier le lien', 'rp') . "'"
         . " aria-label='" . __s('Copier le lien', 'rp') . "'>";
      echo "        <i class='ti ti-copy'></i>";
      echo "      </button>";
      echo "    </div>";
      echo "    <div class='form-hint'>"
         . __("Ouvre la page mobile du ticket : signature du rapport, et des bons de livraison s'il y en a.", 'rp')
         . "</div>";
      echo "  </div>";
      echo "</div>";
   }

   // ======================================================================
   //  Préférences de l'utilisateur
   // ======================================================================

   /**
    * Clés de configuration d'un utilisateur : une par endroit où le lien peut
    * apparaître. La ligne n'existe que si l'utilisateur a dit non.
    *
    * @return array{field:string,toast:string}
    */
   private static function configKeys(int $users_id): array {
      return [
         'field' => 'mobilelink_off_' . $users_id,
         'toast' => 'mobilelink_toast_off_' . $users_id,
      ];
   }

   /**
    * Les deux réglages d'un utilisateur, lus en UNE requête et gardés en
    * cache : setup.php les consulte à chaque page pour décider de charger le
    * JS, une requête par réglage aurait doublé le coût pour rien.
    *
    * @return array{field:bool,toast:bool}
    */
   private static function getFlags(?int $users_id = null): array {
      $users_id = $users_id ?? (int)Session::getLoginUserID();
      if ($users_id <= 0) {
         return ['field' => false, 'toast' => false];
      }
      if (isset(self::$flags_cache[$users_id])) {
         return self::$flags_cache[$users_id];
      }

      $keys   = self::configKeys($users_id);
      $values = Config::getConfigurationValues(self::CONFIG_CONTEXT, array_values($keys));

      return self::$flags_cache[$users_id] = [
         'field' => (int)($values[$keys['field']] ?? 0) !== 1,
         'toast' => (int)($values[$keys['toast']] ?? 0) !== 1,
      ];
   }

   /**
    * L'utilisateur veut-il le champ sur la fiche de ses tickets ? (défaut : oui)
    */
   static function isEnabledForUser(?int $users_id = null): bool {
      return self::getFlags($users_id)['field'];
   }

   /**
    * L'utilisateur veut-il le lien dans le message de création ? (défaut : oui)
    */
   static function isToastEnabledForUser(?int $users_id = null): bool {
      return self::getFlags($users_id)['toast'];
   }

   /**
    * Enregistrement depuis l'onglet des préférences.
    *
    * Champs : `rp_mobilelink_show` (fiche) et `rp_mobilelink_toast` (message),
    * 1 = afficher, 0 = masquer. Un champ absent du POST est laissé tel quel :
    * le formulaire ne montre que ce que le profil autorise.
    *
    * La ligne est SUPPRIMEE quand l'utilisateur réactive un endroit, au lieu
    * d'être remise à zéro : l'absence de ligne est déjà le comportement par
    * défaut, et la table ne garde donc que les refus effectifs.
    */
   static function saveForUser(array $input): void {
      $users_id = (int)Session::getLoginUserID();
      if ($users_id <= 0) {
         return;
      }
      if (!PluginRpAccess::canUse('lien_rapide')) {
         return; // sans le droit, rien à régler
      }

      $keys = self::configKeys($users_id);
      $map  = [
         'rp_mobilelink_show'  => $keys['field'],
         'rp_mobilelink_toast' => $keys['toast'],
      ];

      $enable  = [];
      $disable = [];
      foreach ($map as $field => $key) {
         if (!array_key_exists($field, $input)) {
            continue;
         }
         if ((int)$input[$field] === 1) {
            $enable[] = $key;
         } else {
            $disable[$key] = 1;
         }
      }

      if ($enable !== []) {
         Config::deleteConfigurationValues(self::CONFIG_CONTEXT, $enable);
      }
      if ($disable !== []) {
         Config::setConfigurationValues(self::CONFIG_CONTEXT, $disable);
      }
      unset(self::$flags_cache[$users_id]);
   }

   // ======================================================================
   //  Lien dans le message de création d'un ticket
   // ======================================================================

   /**
    * Hook `item_add` sur Ticket : note le ticket comme « créé par cette
    * session ».
    *
    * Aucun droit n'est vérifié ICI, et c'est voulu : les tickets des
    * formulaires GLPI sont créés sous `Session::callAsSystem()`, où tout
    * `haveRight()` répond oui. Un contrôle à cet endroit ne contrôlerait
    * rien. C'est l'AJAX, hors de ce contexte, qui décide (takeCreatedLinks).
    *
    * La note est un simple [id => horodatage], borné (CREATED_MAX) et
    * périssable (CREATED_TTL) : l'API REST passe aussi par ce hook sans
    * jamais afficher de message, et sa session ne doit pas grossir sans fin.
    */
   static function onTicketAdd($item): void {
      if (!($item instanceof Ticket) || isCommandLine() || Session::isCron()) {
         return;
      }
      $ticket_id = (int)$item->getID();
      if ($ticket_id <= 0 || (int)Session::getLoginUserID() <= 0) {
         return;
      }

      $list = $_SESSION[self::SESSION_KEY] ?? [];
      if (!is_array($list)) {
         $list = [];
      }

      $now = time();
      foreach ($list as $id => $stamp) {
         if (($now - (int)$stamp) > self::CREATED_TTL) {
            unset($list[$id]);
         }
      }

      $list[$ticket_id] = $now;

      if (count($list) > self::CREATED_MAX) {
         asort($list); // les plus anciens d'abord : ce sont eux qui partent
         $list = array_slice($list, -self::CREATED_MAX, null, true);
      }

      $_SESSION[self::SESSION_KEY] = $list;
   }

   /**
    * Liens mobiles des tickets demandés, PARMI ceux que la session vient de
    * créer. Tout autre ticket est ignoré sans un mot : c'est ce silence qui
    * laisse les messages de modification tranquilles.
    *
    * Chaque ticket servi est retiré de la note : le message qui l'annonçait
    * est unique, et un message ultérieur sur le même ticket ne doit pas
    * recevoir le lien à son tour.
    *
    * @param int[] $ids identifiants demandés par le navigateur
    * @return array<int,array{url:string,name:string}>
    */
   static function takeCreatedLinks(array $ids): array {
      if (!PluginRpAccess::canUse('lien_rapide') || !self::isToastEnabledForUser()) {
         return [];
      }

      $list = $_SESSION[self::SESSION_KEY] ?? [];
      if (!is_array($list) || $list === []) {
         return [];
      }

      $now = time();
      $out = [];
      foreach ($ids as $id) {
         $id = (int)$id;
         if ($id <= 0 || !isset($list[$id])) {
            continue;
         }
         $stamp = (int)$list[$id];
         unset($list[$id]);
         if (($now - $stamp) > self::CREATED_TTL) {
            continue;
         }

         $ticket = new Ticket();
         if (!$ticket->getFromDB($id) || !$ticket->canViewItem()) {
            continue;
         }
         $url = PluginRpQrcode::getTicketUrl($id);
         if ($url === '') {
            continue; // secret HMAC absent : migration pas encore jouée
         }

         $out[$id] = [
            'url'  => $url,
            'name' => (string)($ticket->fields['name'] ?? ''),
         ];
      }

      $_SESSION[self::SESSION_KEY] = $list;
      return $out;
   }

   /**
    * Déclaration lue par `public/js/mobilelink_rp.js` (balise <meta>).
    *
    * Le hook add_header_tag ne rend que des balises à attributs : le JSON
    * voyage dans `content`. Les libellés passent par ici pour que le JS n'ait
    * aucune chaîne en dur — ils restent traduisibles au même endroit que le
    * reste du plugin.
    *
    * @param bool $toast l'utilisateur veut-il le lien dans le message de création ?
    */
   static function headerTag(bool $toast): array {
      return [
         'tag'        => 'meta',
         'properties' => [
            'name'    => 'rp:mobilelink',
            'content' => json_encode([
               'ajax'   => PLUGIN_RP_WEBDIR . '/ajax/mobilelink.php',
               'toast'  => $toast ? 1 : 0,
               'delay'  => self::TOAST_DELAY,
               'labels' => [
                  'title'  => __('Lien mobile', 'rp'),
                  'copy'   => __('Copier le lien', 'rp'),
                  'copied' => __('Lien copié', 'rp'),
                  'failed' => __('Copie impossible : sélectionnez le lien.', 'rp'),
               ],
            ]),
         ],
      ];
   }
}
