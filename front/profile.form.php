<?php
Session::checkRight("profile", UPDATE);

$prof = new PluginRpProfile();

//Save profile
if (isset ($_POST['update'])) {
   $prof->update($_POST);
   Html::back();
}

?>