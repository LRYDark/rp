<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Rp plugin for GLPI
 Copyright (C) 2014-2022 by the Rp Development Team.

 https://github.com/InfotelGLPI/rp
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Rp.

 Rp is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Rp is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Rp. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

$plugin = new Plugin();
if (Session::getCurrentInterface() == 'central') {
   Html::header(__('Rapport', 'rp'), '', "helpdesk", "pluginrpgeneratecri");
} else {
      Html::helpHeader(__('Rapport', 'rp'));
}

if (Session::haveRight("ticket", CREATE)) {
   $generatecri = new PluginRpGenerateCRI();
   $generatecri->showWizard($ticket = new Ticket(), $_SESSION['glpiactive_entity']);
} else {
   Html::displayRightError();
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
