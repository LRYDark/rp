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
 * Le signataire est enregistré dans `nameclient`, mais seuls la prise en
 * charge, le rapport d'intervention et la hotline y mettent réellement le nom
 * du client : le rapport d'atelier y stocke celui du technicien, toujours
 * renseigné (cf. front/cripdf.form.php). L'inclure ferait passer pour signés
 * des documents qui ne le sont pas, et la vignette vaudrait le total.
 */
$rp_types_client = [0, 1, 2];
$nb_signed = countElementsInTable($rp_table, [
   'type'       => $rp_types_client,
   'NOT'        => ['nameclient' => null],
   'nameclient' => ['<>', ''],
] + $entity_crit);

// Option de recherche 2 = type de rapport (datatype specific, recherche "equals")
$self_url = PLUGIN_RP_WEBDIR . '/front/cridetail.php';
$url_type = static function (int $type) use ($self_url): string {
   return $self_url . '?reset=reset&criteria[0][link]=AND&criteria[0][field]=2'
      . '&criteria[0][searchtype]=equals&criteria[0][value]=' . $type;
};

/*
 * Filtre « signés » : option 5 = nameclient, avec la valeur spéciale `^` que
 * GLPI interprète comme « champ non vide ». Le second critère écarte le rapport
 * d'atelier, dont le nom enregistré est celui du technicien.
 */
$url_signed = $self_url . '?reset=reset'
   . '&criteria[0][link]=AND&criteria[0][field]=5&criteria[0][searchtype]=contains&criteria[0][value]=' . rawurlencode('^')
   . '&criteria[1][link]=AND&criteria[1][field]=2&criteria[1][searchtype]=notequals&criteria[1][value]=3';

$stat_cards = [
   ['url' => $self_url . '?reset=reset', 'label' => __('Tous les rapports', 'rp'),        'count' => $nb_total,      'color' => 'primary',   'icon' => 'ti ti-list'],
   ['url' => $url_signed,                'label' => __('Signés par le client', 'rp'),     'count' => $nb_signed,     'color' => 'green',     'icon' => 'ti ti-signature'],
   ['url' => $url_type(1),               'label' => __("Rapports d'intervention", 'rp'),  'count' => $nb_by_type[1], 'color' => 'success',   'icon' => 'ti ti-file-check'],
   ['url' => $url_type(2),               'label' => __('Rapports hotline', 'rp'),         'count' => $nb_by_type[2], 'color' => 'warning',   'icon' => 'ti ti-headset'],
   ['url' => $url_type(3),               'label' => __("Rapports d'atelier", 'rp'),  'count' => $nb_by_type[3], 'color' => 'purple',    'icon' => 'ti ti-tools'],
   ['url' => $url_type(0),               'label' => __('Fiches de prise en charge', 'rp'), 'count' => $nb_by_type[0], 'color' => 'azure',     'icon' => 'ti ti-file-text'],
];

echo '<div class="card mb-2" id="rpReportStatsBar">';
echo '<div class="card-body py-2 px-3 d-flex flex-wrap align-items-center">';
$first = true;
foreach ($stat_cards as $c) {
   if (!$first) {
      echo '<div class="vr mx-3 my-1"></div>';
   }
   $first = false;
   echo '<a href="' . htmlspecialchars($c['url'], ENT_QUOTES) . '" class="d-flex align-items-center gap-2 text-decoration-none text-reset py-1" title="' . htmlspecialchars(__('Cliquer pour filtrer la liste', 'rp'), ENT_QUOTES) . '">';
   echo '<span class="avatar avatar-sm bg-' . $c['color'] . '-lt"><i class="' . $c['icon'] . '"></i></span>';
   echo '<span class="d-flex flex-column lh-sm">';
   echo '<span class="h2 fw-bold mb-0">' . (int)$c['count'] . '</span>';
   echo '<span class="text-muted small">' . htmlspecialchars($c['label']) . '</span>';
   echo '</span></a>';
}
echo '</div></div>';

Search::show('PluginRpCriDetail');

Html::footer();
