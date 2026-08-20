<?php
/**
 * Tableau des rapports du plugin RP (fiches de prise en charge, rapports
 * d'intervention, hotline, préparation) avec le moteur de recherche GLPI :
 * filtres par type, entité, date, ticket, technicien, etc.
 *
 * URL conventionnelle du moteur de recherche pour l'itemtype PluginRpCriDetail.
 * Accès contrôlé par le droit de profil dédié `plugin_rp_liste`.
 */
include('../../../inc/includes.php');

/*
 * GLPI 11 charge les fichiers de `front/` par un `require` DANS une méthode
 * (LegacyFileLoadController) : la portée n'est donc pas globale et `$DB` n'y est
 * pas visible sans cette déclaration. Sans elle, toute requête directe échoue
 * sur « Call to a member function doQuery() on null ».
 */
global $DB;

Session::checkLoginUser();
Session::checkRight('plugin_rp_liste', READ);

Html::header(
   __('Rapport PDF', 'rp'),
   $_SERVER['PHP_SELF'],
   'management',
   'PluginRpCriDetail'
);

// ---- Barre de stats : chaque carte est cliquable et filtre la liste dessous ----
$rp_table     = 'glpi_plugin_rp_cridetails';
$entity_crit  = getEntitiesRestrictCriteria($rp_table);
$nb_by_type   = [];
foreach (array_keys(PluginRpCriDetail::getTypeLabels()) as $rp_type) {
   $nb_by_type[$rp_type] = countElementsInTable($rp_table, ['type' => $rp_type] + $entity_crit);
}
$nb_total = array_sum($nb_by_type);

/*
 * Documents signés par le CLIENT.
 *
 * Seuls la fiche de prise en charge (0) et le rapport d'intervention (1)
 * enregistrent réellement le nom du client dans `nameclient`.
 *
 * Le rapport hotline (2) et le rapport d'atelier (3) y écrivent celui du
 * TECHNICIEN, écrasé sans condition à la génération
 * (front/cripdf.form.php:1417 et 1423). Leur champ n'est donc jamais vide : les
 * compter reviendrait à déclarer signés des documents qui ne le sont pas, et la
 * vignette afficherait le total des rapports.
 */
$rp_types_client = [0, 1];
$nb_signed = countElementsInTable($rp_table, [
   'type'       => $rp_types_client,
   'NOT'        => ['nameclient' => null],
   'nameclient' => ['<>', ''],
] + $entity_crit);

/*
 * ---- Supervision : rapports d'atelier restés sans suite ----
 *
 * Un rapport d'atelier appelle un rapport d'intervention : le matériel repart,
 * le client signe. Un atelier sans intervention, c'est un dossier en suspens —
 * et plus il vieillit, plus il faut le voir.
 *
 * Réservé : ces chiffres montrent ce qui n'a PAS été fait. Trois leviers les
 * gouvernent, cf. PluginRpAccess::canSupervise().
 */
$rp_can_supervise = PluginRpAccess::canSupervise();
$nb_orphelins     = 0;
$nb_orphelins_7j  = 0;
$tickets_orphelins = [];

if ($rp_can_supervise) {
   /*
    * NOT EXISTS plutôt qu'une jointure : on cherche une ABSENCE, et le moteur
    * s'arrête à la première ligne trouvée. Requête sans aucune donnée
    * utilisateur — la restriction d'entité vient de la session.
    */
   /*
    * PAS de mode récursif ici : le 5e argument ferait générer une clause sur une
    * colonne `is_recursive` que `glpi_plugin_rp_cridetails` ne possède pas — un
    * rapport appartient à une entité, il ne se propage pas aux filles. MySQL
    * rejetait alors la requête, et la page entière tombait en erreur dès qu'un
    * superviseur se plaçait sur une entité fille. Invisible en mono-entité.
    *
    * Même règle que la ligne qui compte les rapports par type plus haut, qui
    * utilise getEntitiesRestrictCriteria() sans récursivité.
    */
   $entity_sql = getEntitiesRestrictRequest('AND', 'c3', 'entities_id');
   $sql_orphelins = "SELECT c3.id_ticket, MAX(c3.date) AS date_atelier
                     FROM `glpi_plugin_rp_cridetails` c3
                     WHERE c3.type = 3
                       AND NOT EXISTS (
                          SELECT 1 FROM `glpi_plugin_rp_cridetails` c1
                          WHERE c1.id_ticket = c3.id_ticket AND c1.type = 1
                       )
                       $entity_sql
                     GROUP BY c3.id_ticket
                     ORDER BY date_atelier ASC";

   /*
    * try/catch et non `if (!$DB->doQuery(...))` : sur GLPI 11, doQuery LÈVE une
    * exception, elle ne renvoie jamais false — un test de retour serait du code
    * mort. Et surtout, l'échec de cette carte de supervision ne doit pas
    * emporter toute la page : les compteurs restent à zéro, la liste s'affiche.
    */
   try {
      $res_orphelins = $DB->doQuery($sql_orphelins);
      $limite_7j = strtotime('-7 days');
      while ($row_orph = $DB->fetchAssoc($res_orphelins)) {
         $nb_orphelins++;
         if (strtotime((string)$row_orph['date_atelier']) < $limite_7j) {
            $nb_orphelins_7j++;
         }
         $tickets_orphelins[] = (int)$row_orph['id_ticket'];
      }
   } catch (\Throwable $e) {
      Toolbox::logInFile('plugin-rp', "Supervision : " . $e->getMessage() . "\n");
      $rp_can_supervise = false;
   }
}

// Option de recherche 2 = type de rapport (datatype specific, recherche "equals")
$self_url = PLUGIN_RP_WEBDIR . '/front/cridetail.php';
$url_type = static function (int $type) use ($self_url): string {
   return $self_url . '?reset=reset&criteria[0][link]=AND&criteria[0][field]=2'
      . '&criteria[0][searchtype]=equals&criteria[0][value]=' . $type;
};

/*
 * Filtre « signés » : option 5 = nameclient, avec la valeur spéciale `^` que
 * GLPI interprète comme « champ non vide ». Les deux critères suivants écartent
 * la hotline et le rapport d'atelier, dont le nom enregistré est celui du
 * technicien — sans eux, la liste filtrée contenait des documents non signés.
 */
$url_signed = $self_url . '?reset=reset'
   . '&criteria[0][link]=AND&criteria[0][field]=5&criteria[0][searchtype]=contains&criteria[0][value]=' . rawurlencode('^')
   . '&criteria[1][link]=AND&criteria[1][field]=2&criteria[1][searchtype]=notequals&criteria[1][value]=2'
   . '&criteria[2][link]=AND&criteria[2][field]=2&criteria[2][searchtype]=notequals&criteria[2][value]=3';

$stat_cards = [
   ['url' => $self_url . '?reset=reset', 'label' => __('Tous les rapports', 'rp'),        'count' => $nb_total,      'color' => 'primary',   'icon' => 'ti ti-list'],
   ['url' => $url_signed,                'label' => __('Signés par le client', 'rp'),     'count' => $nb_signed,     'color' => 'green',     'icon' => 'ti ti-signature'],
   ['url' => $url_type(1),               'label' => __("Rapports d'intervention", 'rp'),  'count' => $nb_by_type[1], 'color' => 'success',   'icon' => 'ti ti-file-check'],
   ['url' => $url_type(2),               'label' => __('Rapports hotline', 'rp'),         'count' => $nb_by_type[2], 'color' => 'warning',   'icon' => 'ti ti-headset'],
   ['url' => $url_type(3),               'label' => __("Rapports d'atelier", 'rp'),  'count' => $nb_by_type[3], 'color' => 'purple',    'icon' => 'ti ti-tools'],
   ['url' => $url_type(0),               'label' => __('Fiches de prise en charge', 'rp'), 'count' => $nb_by_type[0], 'color' => 'azure',     'icon' => 'ti ti-file-text'],
];

if ($rp_can_supervise) {
   /*
    * Le moteur de recherche ne sait pas exprimer « sans rapport d'intervention »
    * — c'est une absence, pas un critère de colonne. On lui passe donc la liste
    * des tickets concernés, en critères liés par OU.
    *
    * Plafonnée : au-delà, l'URL deviendrait illisible pour le navigateur. Le
    * COMPTE, lui, reste exact — c'est lui qui alerte ; le lien ne sert qu'à
    * ouvrir les plus anciens, ceux qui pressent le plus (tri par date croissante).
    */
   $rp_url_orphelins = static function (array $tickets) use ($self_url): string {
      if (empty($tickets)) {
         return $self_url . '?reset=reset';
      }
      $url = $self_url . '?reset=reset';
      foreach (array_slice($tickets, 0, 40) as $i => $ticket_id) {
         $url .= '&criteria[' . $i . '][link]=' . ($i === 0 ? 'AND' : 'OR')
              . '&criteria[' . $i . '][field]=3'
              . '&criteria[' . $i . '][searchtype]=equals'
              . '&criteria[' . $i . '][value]=' . (int)$ticket_id;
      }
      return $url;
   };

   // Les plus anciens d'abord : la requête trie déjà par date croissante.
   $tickets_7j = array_slice($tickets_orphelins, 0, $nb_orphelins_7j);

   $stat_cards[] = [
      'url'     => $rp_url_orphelins($tickets_orphelins),
      'label'   => __("Ateliers sans rapport d'intervention", 'rp'),
      'tooltip' => __("Rapports d'atelier qui n'ont donné lieu à aucun rapport d'intervention", 'rp'),
      'count'   => $nb_orphelins,
      'color'   => 'orange',
      'icon'    => 'ti ti-alert-triangle',
   ];
   $stat_cards[] = [
      'url'   => $rp_url_orphelins($tickets_7j),
      'label' => __('… dont plus de 7 jours', 'rp'),
      // Libellé court volontairement elliptique dans la barre, mais l'infobulle
      // doit se suffire à elle-même : lue seule, « … dont plus de 7 jours » ne
      // dit pas de quoi il s'agit.
      'tooltip' => __("Rapports d'atelier sans rapport d'intervention depuis plus de 7 jours", 'rp'),
      'count'   => $nb_orphelins_7j,
      'color'   => 'red',
      'icon'    => 'ti ti-clock-exclamation',
   ];
}

echo '<div class="card mb-2" id="rpReportStatsBar">';
echo '<div class="card-body py-2 px-3 d-flex flex-wrap align-items-center">';
$first = true;
foreach ($stat_cards as $c) {
   if (!$first) {
      echo '<div class="vr mx-3 my-1"></div>';
   }
   $first = false;
   /*
    * L'infobulle doit se suffire à elle-même : un libellé abrégé pour tenir dans
    * la barre n'a plus de sens lu isolément. D'où `tooltip`, qui donne la phrase
    * complète, et le libellé en repli quand il est déjà explicite.
    * Que la vignette soit cliquable se voit au curseur, inutile de l'écrire.
    */
   $c_title = trim((string)($c['tooltip'] ?? '')) !== '' ? $c['tooltip'] : $c['label'];
   echo '<a href="' . htmlspecialchars($c['url'], ENT_QUOTES) . '" class="d-flex align-items-center gap-2 text-decoration-none text-reset py-1" title="' . htmlspecialchars($c_title, ENT_QUOTES) . '">';
   echo '<span class="avatar avatar-sm bg-' . $c['color'] . '-lt"><i class="' . $c['icon'] . '"></i></span>';
   echo '<span class="d-flex flex-column lh-sm">';
   echo '<span class="h2 fw-bold mb-0">' . (int)$c['count'] . '</span>';
   echo '<span class="text-muted small">' . htmlspecialchars($c['label']) . '</span>';
   echo '</span></a>';
}
echo '</div></div>';

Search::show('PluginRpCriDetail');

Html::footer();
