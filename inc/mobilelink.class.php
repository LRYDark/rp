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
 */
class PluginRpMobilelink {

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

      self::renderScriptOnce();
   }

   /**
    * Clé de la préférence, dans la configuration GLPI plutôt qu'en base plugin.
    *
    * UNE LIGNE PAR UTILISATEUR, et seulement pour ceux qui ont dit non : le
    * réglage par défaut ne stocke rien. C'est ce qui permet de l'ajouter sans
    * toucher au schéma — donc sans migration ni changement de version — et,
    * chacun n'écrivant que sa propre ligne, deux utilisateurs ne peuvent pas
    * s'écraser l'un l'autre comme le ferait une liste commune.
    */
   const CONFIG_CONTEXT = 'plugin:rp';

   private static function configKey(?int $users_id = null): string {
      $users_id = $users_id ?? (int)Session::getLoginUserID();
      return 'mobilelink_off_' . $users_id;
   }

   /**
    * L'utilisateur veut-il voir le lien sur ses tickets ? (défaut : oui)
    */
   static function isEnabledForUser(?int $users_id = null): bool {
      $users_id = $users_id ?? (int)Session::getLoginUserID();
      if ($users_id <= 0) {
         return false;
      }
      $key    = self::configKey($users_id);
      $values = Config::getConfigurationValues(self::CONFIG_CONTEXT, [$key]);
      return (int)($values[$key] ?? 0) !== 1;
   }

   /**
    * Enregistrement depuis l'onglet des préférences.
    *
    * La ligne est SUPPRIMEE quand l'utilisateur réactive le lien, au lieu
    * d'être remise à zéro : l'absence de ligne est déjà le comportement par
    * défaut, et la table ne garde donc que les refus effectifs.
    */
   static function saveForUser(array $input): void {
      $users_id = (int)Session::getLoginUserID();
      if ($users_id <= 0 || !array_key_exists('rp_mobilelink_show', $input)) {
         return;
      }
      if (!PluginRpAccess::canUse('lien_rapide')) {
         return; // sans le droit, rien à régler
      }

      $key = self::configKey($users_id);
      if ((int)$input['rp_mobilelink_show'] === 1) {
         Config::deleteConfigurationValues(self::CONFIG_CONTEXT, [$key]);
      } else {
         Config::setConfigurationValues(self::CONFIG_CONTEXT, [$key => 1]);
      }
   }

   /**
    * Le script de copie, emis UNE SEULE fois par page.
    *
    * `navigator.clipboard` n'existe QUE dans un contexte securise : sur un GLPI
    * servi en http — le cas de bien des installations sur reseau local — il est
    * simplement absent, et un bouton qui s'appuierait sur lui seul ne ferait
    * rien du tout, sans erreur visible. D'ou le repli sur `execCommand('copy')`,
    * obsolete mais universel, puis la selection du champ en dernier recours :
    * l'utilisateur n'a alors qu'a copier lui-meme.
    */
   private static function renderScriptOnce(): void {
      static $done = false;
      if ($done) {
         return;
      }
      $done = true;

      $label_ok = json_encode(__('Lien copié', 'rp'));
      $label_ko = json_encode(__('Copie impossible : sélectionnez le lien.', 'rp'));
      ?>
      <script>
      (function () {
         if (window.rpMobileLinkBound) {
            return;
         }
         window.rpMobileLinkBound = true;

         function flash(button, text, ok) {
            var icon = button.innerHTML;
            button.innerHTML = '<i class="ti ' + (ok ? 'ti-check text-success' : 'ti-alert-triangle text-warning')
               + '"></i>';
            button.setAttribute('title', text);
            setTimeout(function () { button.innerHTML = icon; }, 1800);
         }

         document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-rp-copy-link]');
            if (!button) {
               return;
            }
            event.preventDefault();

            var input = document.getElementById(button.getAttribute('data-rp-copy-link'));
            if (!input) {
               return;
            }
            var okText = <?php echo $label_ok; ?>;
            var koText = <?php echo $label_ko; ?>;

            if (navigator.clipboard && window.isSecureContext) {
               navigator.clipboard.writeText(input.value)
                  .then(function () { flash(button, okText, true); })
                  .catch(function () { input.select(); flash(button, koText, false); });
               return;
            }

            // Contexte non sécurisé (http) : `navigator.clipboard` est absent.
            input.select();
            input.setSelectionRange(0, input.value.length);
            var done = false;
            try {
               done = document.execCommand('copy');
            } catch (e) {
               done = false;
            }
            flash(button, done ? okText : koText, done);
         });
      })();
      </script>
      <?php
   }
}
