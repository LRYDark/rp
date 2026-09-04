<?php
/**
 * Lien mobile des tickets que CETTE SESSION vient de créer.
 *
 * Interrogé par `public/js/mobilelink_rp.js` quand un toast affiche un lien
 * vers un ticket. La réponse ne contient que les tickets notés en session par
 * le hook `item_add` (PluginRpMobilelink::onTicketAdd), droit et préférence
 * vérifiés. Pour tout autre ticket — modifié, ancien, créé par quelqu'un
 * d'autre — la réponse est vide, et le toast reste tel quel.
 *
 * En GET : rien n'est écrit en base (la note de session est seulement
 * consommée), donc hors du contrôle CSRF du noyau, qui ne porte que sur les
 * envois. La réponse n'est lisible que par une page de MÊME ORIGINE.
 *
 * Entrée : tickets_id[] (au plus 20)
 * Sortie : { ok: true, links: { "<id>": { url, name } } }
 */

/*
 * Le chargement des plugins peut émettre du HTML : on le neutralise pour
 * garantir une réponse JSON valide — sans JAMAIS redescendre sous le niveau de
 * tampon d'entrée, celui que GLPI 11 ouvre autour de chaque script « legacy »
 * (le détail est dans ajax/csrf.php).
 */
$rp_ob_base = ob_get_level();
ob_start();
include('../../../inc/includes.php');
while (ob_get_level() > $rp_ob_base) {
   ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

Session::checkLoginUser();

$ids = $_GET['tickets_id'] ?? [];
if (!is_array($ids)) {
   $ids = [$ids];
}
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
   return $id > 0;
})));
$ids = array_slice($ids, 0, 20);

$links = ($ids !== []) ? PluginRpMobilelink::takeCreatedLinks($ids) : [];

// (object) : un tableau vide s'encoderait en `[]`, et le JS attend un objet.
echo json_encode(
   ['ok' => true, 'links' => (object)$links],
   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
