<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 rp plugin for GLPI
 Copyright (C) 2016-2022 by the rp Development Team.

 https://github.com/pluginsglpi/rp
 -------------------------------------------------------------------------

 LICENSE

 This file is part of rp.

 rp is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 rp is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with rp. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/**
 * Class PluginRpMenu
 */
class PluginRpMenu extends CommonGLPI
{
   static $rightname = 'plugin_rp';

   /**
    * @return translated
    */
   static function getMenuName() {
      return __('Rapport mail automatique', 'rp');
   }

   /**
    * @return array
    */
   static function getMenuContent() {

      $menu = [];

      if (Session::haveRight('plugin_rp', READ)) {
         $menu['title'] = self::getMenuName();
         $menu['page'] = PLUGIN_RP_NOTFULL_WEBDIR."/front/survey.php";
         $menu['links']['search'] = self::getSearchURL(false);
         if (PluginRpSurvey::canCreate()) {
            $menu['links']['add'] = PluginRpSurvey::getFormURL(false);
         }
      }

      $menu['icon'] = self::getIcon();
      return $menu;
   }

   static function getIcon() {
      return "fa-fw ti ti-report";
   }

   static function removeRightsFromSession() {
      if (isset($_SESSION['glpimenu']['admin']['types']['PluginRpMenu'])) {
         unset($_SESSION['glpimenu']['admin']['types']['PluginRpMenu']);
      }
      if (isset($_SESSION['glpimenu']['admin']['content']['pluginrpmenu'])) {
         unset($_SESSION['glpimenu']['admin']['content']['pluginrpmenu']);
      }
   }
}
