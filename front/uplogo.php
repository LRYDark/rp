<?php
include ('../../../inc/includes.php');
Session::checkLoginUser();
$plugin = new Plugin();
if (!$plugin->isInstalled('rp') || !$plugin->isActivated('rp')) {
   Html::displayNotFoundError();
}
Session::checkRight('config', UPDATE);

function pluginRpUpLogoCheckCSRF(array $data): void {
    // GLPI 11 validates _glpi_csrf_token before loading legacy front files.
    // Avoid a second validation here because the token may already be consumed.
    if (!empty($data['_glpi_csrf_token'])
        && defined('GLPI_VERSION')
        && version_compare((string)GLPI_VERSION, '11.0.0', '>=')
    ) {
        return;
    }

    if (!empty($data['plugin_rp_uplogo_csrf_token'])) {
        Session::checkCSRF(['_glpi_csrf_token' => (string)$data['plugin_rp_uplogo_csrf_token']], true);
        return;
    }
    Session::checkCSRF($data, true);
}
pluginRpUpLogoCheckCSRF($_POST);

$config 		= new PluginRpConfig();
$configfile     = PluginRpConfig::getInstance();
$doc 			= new Document();

function message($msg, $msgtype){
	Session::addMessageAfterRedirect(
		__($msg, 'rp'),
		true,
		$msgtype
	);
}

$Path           = GLPI_PLUGIN_DOC_DIR;
$SeePath        = $Path . "/rp/logo/";

if (!isset($_FILES['photo']) || empty($_FILES['photo']['name'])) {
	message('Aucun fichier séléctionné', WARNING);	
	Html::back();
}

$FileName 		= basename((string)$_FILES['photo']['name']);
$FileName      = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$FileName);
$FileName      = ltrim((string)$FileName, '.');
if ($FileName === '') {
   $FileName = 'logo_' . date('YmdHis') . '.png';
}
$FilePath 		= "_plugins/rp/logo/" . $FileName;
$SeeFilePath    = $SeePath . $FileName;
$targetLogo     = (string)($_POST['IdLogo'] ?? '');

if (!in_array($targetLogo, ['logo1', 'logo2'], true)) {
   message('Cible de logo invalide', ERROR);
   Html::back();
}

if ($targetLogo == 'logo1'){
	$img 			= $doc->find(['id' => $configfile->fields['logo_id']]);
	$img 			= reset($img);
}
if($targetLogo == 'logo2'){
	$img 			= $doc->find(['id' => $configfile->fields['logo_id2']]);
	$img 			= reset($img);
}
if (isset($img['filepath'])) {
	$file_exists = GLPI_DOC_DIR . '/' . $img['filepath'];
	$file_exists_alt = GLPI_DOC_DIR . '/' . stripslashes((string)$img['filepath']);
}

if ($plugin->isActivated("rp")){ // check plugin rp activate
	if($_FILES['photo']['name']){ //upload OK (fichier séléctionné)
		if(!$_FILES['photo']['error']){//si il n'y a pas d'erreur

			$info = getimagesize($_FILES['photo']['tmp_name']);//info sur le fichier

			$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
			$finfo        = new finfo(FILEINFO_MIME_TYPE);
			$realMime     = $finfo->file($_FILES['photo']['tmp_name']);

			if ($_FILES['photo']['size'] > (10240000) || $info === false || !in_array($realMime, $allowedMimes, true)) { // taille max du fichier 10MO
				$valid_file = false;
				message("Le fichier téléchargé dépasse 10 MO ou n'est pas une image valide (JPEG, PNG, GIF, WEBP).", ERROR);
				Html::back();
			} else {
				$valid_file = true;
			}
		
			if($valid_file){ // si tout est OK
				if (!is_dir($SeePath)) {
					mkdir($SeePath, 0777, true);
				}

				if (isset($file_exists) && file_exists($file_exists)) {
					unlink($file_exists);
				} elseif (isset($file_exists_alt) && file_exists($file_exists_alt)) {
					unlink($file_exists_alt);
				}

					$input = ['name'        => $FileName,
						      'filename'    => $FileName,
							  'filepath'    => $FilePath,
							  'mime'        => (string)($info['mime'] ?? 'application/octet-stream'),
							  'users_id'    => Session::getLoginUserID(),
							  'is_recursive'=> 1];

				if($NewDoc = $doc->add($input)){
					if (!move_uploaded_file($_FILES['photo']['tmp_name'], $SeeFilePath)) {
						$doc->delete(['id' => (int)$NewDoc], 1);
						message('Erreur lors du déplacement du fichier logo.', ERROR);
						Html::back();
					}
					// GLPI 11 blackliste filepath/sha1sum dans Document::add => reecriture
					// directe, APRES le deplacement physique du fichier ci-dessus.
					pluginRpFixDocumentFile((int)$NewDoc, $FilePath);
					if(!empty($img))$doc->delete($img, 1);
					if ($targetLogo == 'logo1'){
						$config->update(['id' => 1, 'logo_id' => $NewDoc]);
					}
					if($targetLogo == 'logo2'){
						$config->update(['id' => 1, 'logo_id2' => $NewDoc]);
					}
					message('Logo chargé avec succès.', INFO);
					Html::back();
				}else{
					message('Erreur lors du chargement du logo : '.$_FILES['photo']['error'], ERROR);
					Html::back();
				}
			}
		}else{// erreur avec le fichier
			message('Erreur lors du chargement du logo : '.$_FILES['photo']['error'], ERROR);
			Html::back();
		}
	}else{// aucun fichier séléctionné 
		message('Aucun fichier séléctionné', WARNING);	
		Html::back();		
	}
}
?>
