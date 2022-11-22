<?php
include ('../../../inc/includes.php');
Session::checkLoginUser();
$plugin = new Plugin();

function message($msg, $msgtype){
	Session::addMessageAfterRedirect(
		__($msg, 'rp'),
		true,
		$msgtype
	);
}

$config 		= new PluginRpConfig();
$configfile     = PluginRpConfig::getInstance();
$Path           = GLPI_PLUGIN_DOC_DIR;
$SeePath            = $Path . "/rp/logo/";

$FileName 		= basename($_FILES['photo']['name']);
$FilePath 		= "_plugins/rp/logo/" . $FileName;
$SeeFilePath    = $SeePath . $FileName;

$doc = new Document();
$img = $doc->find(['id' => $configfile->fields['logo_id']]);
$img = reset($img);

$doc->delete($img['id'][$configfile->fields['logo_id']], 1);

echo $img['filepath'];

	$fichier = GLPI_PLUGIN_DOC_DIR.'/'.$configfile->fields['logo_id'];
	echo $fichier;
	if(file_exists($fichier))unlink($fichier);

	$input = ['name'        => addslashes($FileName),
			'filename'    => addslashes($FileName),
			'filepath'    => addslashes($FilePath),
			'mime'        => 'image/jpeg',
			'users_id'    => Session::getLoginUserID(),
			'is_recursive'=> 1];
	if($NewDoc = $doc->add($input)){
		move_uploaded_file($_FILES['photo']['tmp_name'], $SeeFilePath);
		$config->update(['id' => 1, 'logo_id' => $NewDoc]);
		//Html::back();
	}

/*

if ($plugin->isActivated("rp")){ // check plugin rp activate
	if($_FILES['photo']['name']){ //upload OK (fichier séléctionné)
		$name = basename($_FILES['photo']['name']);
		$name = explode('.',$name);
		if ($name[0] == 'logo'){ // check name fichier 
			if(!$_FILES['photo']['error']){//si il n'y a pas d'erreur
	
				$new_file_name = strtolower($_FILES['photo']['name']);
				$info = getimagesize($_FILES['photo']['tmp_name']);//info sur le fichier
	
				if($_FILES['photo']['size'] > (10240000) || $info === false){ // taille max du fichier 10MO
					$valid_file = false;
					message("Le fichier téléchargé dépasse 10 MO ou il est impossible de déterminer le type d'image du fichier téléchargé.", ERROR);
					Html::back();
				}else $valid_file = true; 
			
				if($valid_file){ // si tout est OK
					
	
					$dir = "../img/";
					if (is_dir($dir)) {
						if ($dh = opendir($dir)) {
							while (($file = readdir($dh)) !== false) {
								$save_extension = $file;
							}
							closedir($dh);
						}
					}

					$save_extension = explode('logo.',$save_extension,2);
					if(isset($save_extension[1])) {
						unlink('../img/logo.'.$save_extension[1]);
					}

					$target = '../img/' . basename($_FILES['photo']['name']);
					move_uploaded_file($_FILES['photo']['tmp_name'], $target);

					message('Logo chargé avec succès.', INFO);
					Html::back();
				}
			}else{// erreur avec le fichier
				message('Erreur lors du chargement du logo : '.$_FILES['photo']['error'], ERROR);
				Html::back();
			}
		}else{// le nom du fichier est différent de logo
			message('Le nom du fichier est différent de « logo »', ERROR);
			Html::back();			
		}
	}else{// aucun fichier séléctionné 
		message('Aucun fichier séléctionné', WARNING);	
		Html::back();		
	}
}*/

?>