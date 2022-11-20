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

if ($plugin->isActivated("rp")){ // check plugin rp activate
	$name = basename($_FILES['photo']['name']);
	$name = explode('.',$name);
	if ($name[0] == 'logo'){ // check name fichier 
		if($_FILES['photo']['name']){ //upload OK (fichier séléctionné)
			if(!$_FILES['photo']['error']){//si il n'y a pas d'erreur
	
				$new_file_name = strtolower($_FILES['photo']['name']);
				$info = getimagesize($_FILES['photo']['tmp_name']);//info sur le fichier
	
				if($_FILES['photo']['size'] > (10240000) || $info === false){ // taille max du fichier 10MO
					$valid_file = false;
					message("Le fichier téléchargé dépasse 10 MO ou il est impossible de déterminer le type d'image du fichier téléchargé.", ERROR);
					header('Location: ../front/config.form.php ');
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

					message('Logo chargé avec succès', INFO);
					header('Location: ../front/config.form.php ');
				}
			}else{// erreur avec le fichier
				header('Location: ../front/config.form.php ');
				message('Erreur lors du chargement du logo : '.$_FILES['photo']['error'], ERROR);
			}
		}else{// aucun fichier séléctionné 
				header('Location: ../front/config.form.php ');
				message('Aucun fichier séléctionné', WARNING);			
		}
	}else{// le nom du fichier est différent de logo
		header('Location: ../front/config.form.php ');
		message('Le nom du fichier est différent de « logo »', ERROR);			
	}
}?>