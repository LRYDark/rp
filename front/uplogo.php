<?php
include ('../../../inc/includes.php');
Session::checkLoginUser();
$plugin 		= new Plugin();
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
$FileName 		= basename($_FILES['photo']['name']);
$FilePath 		= "_plugins/rp/logo/" . $FileName;
$SeeFilePath    = $SeePath . $FileName;

if ($_POST['IdLogo'] == 'logo1'){
	$img 			= $doc->find(['id' => $configfile->fields['logo_id']]);
	$img 			= reset($img);
}
if($_POST['IdLogo'] == 'logo2'){
	$img 			= $doc->find(['id' => $configfile->fields['logo_id2']]);
	$img 			= reset($img);
}
if(isset($img['filepath']))$file_exists = GLPI_DOC_DIR.'/'.$img['filepath'];

if ($plugin->isActivated("rp")){ // check plugin rp activate
	if($_FILES['photo']['name']){ //upload OK (fichier séléctionné)
		if(!$_FILES['photo']['error']){//si il n'y a pas d'erreur

			$new_file_name = strtolower($_FILES['photo']['name']);
			$info = getimagesize($_FILES['photo']['tmp_name']);//info sur le fichier

			if($_FILES['photo']['size'] > (10240000) || $info === false){ // taille max du fichier 10MO
				$valid_file = false;
				message("Le fichier téléchargé dépasse 10 MO ou il est impossible de déterminer le type d'image du fichier téléchargé.", ERROR);
				Html::back();
			}else $valid_file = true; 
		
			if($valid_file){ // si tout est OK
				if(isset($file_exists))if(file_exists($file_exists))unlink($file_exists);

					$input = ['name'        => addslashes($FileName),
						      'filename'    => addslashes($FileName),
							  'filepath'    => addslashes($FilePath),
							  'mime'        => 'image/jpeg',
							  'users_id'    => Session::getLoginUserID(),
							  'is_recursive'=> 1];

				if($NewDoc = $doc->add($input)){
					if(!empty($img))$doc->delete($img, 1);
					move_uploaded_file($_FILES['photo']['tmp_name'], $SeeFilePath);
					if ($_POST['IdLogo'] == 'logo1'){
						$config->update(['id' => 1, 'logo_id' => $NewDoc]);
					}
					if($_POST['IdLogo'] == 'logo2'){
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