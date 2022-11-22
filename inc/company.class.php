<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginRpCompany extends CommonDBTM {

   static $rightname = 'plugin_rp';

   static function getTypeName($nb = 0) {
      return _n('LOGO', '', $nb, 'rp');
   }

   /*function rawSearchOptions() {
      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'       => '2',
         'table'    => $this->getTable(),
         'field'    => 'id',
         'name'     => __('ID'),
         'datatype' => 'number'
      ];

      $tab[] = [
         'id'            => '9',
         'table'         => $this->getTable(),
         'field'         => 'address',
         'name'          => __('Address'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      return $tab;
   }*/

   function setSessionValues() {
      if (isset($_SESSION['plugin_rp']['company']) && !empty($_SESSION['plugin_rp']['company'])) {
         foreach ($_SESSION['plugin_rp']['company'] as $key => $val) {
            $this->fields[$key] = $val;
         }
      }
      unset($_SESSION['plugin_rp']['company']);
   }

   function showForm($ID, $options = []) {
      global $CFG_GLPI, $DB;

      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, CREATE);
      }

      // Set session saved if exists
      $this->setSessionValues();

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>Nom </td>";
      echo "<td>";
      echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
      echo "</td>";
      echo "<td></td><td></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Logo (format JPG or JPEG)', 'rp') . "</td>";
      if ($this->fields["logo_id"] != 0) {
         echo "<td>";
         echo "<div  id='picture'>";
         echo "<img height='50px' alt=\"" . __s('Picture') . "\" src='" . $CFG_GLPI["root_doc"] . "/front/document.send.php?docid=" . $this->fields["logo_id"] . "'>";
         echo "</div></td>";
      }
      echo "<td>";
      echo Html::file(['multiple' => false, 'onlyimages' => true]);
      echo "</td>";
      if ($this->fields["logo_id"] == 0) {
         echo "<td></td>";
      }
      echo "<td></td></tr>";

      $this->showFormButtons($options);

      return true;
   }

   static function addNewCompany($options = []) {

      $addButton = "";

      if (Session::haveRight('plugin_rp', UPDATE)) {
         $rand = mt_rand();

         $addButton = "<form method='post' name='company_form'.$rand.'' id='company_form" . $rand . "'
               action='" . Toolbox::getItemTypeFormURL('PluginRpCompany') . "'>";
         $addButton .= Html::hidden('company_id', ['value' => 'company']);
         $addButton .= Html::hidden('id', ['value' => '']);
         $addButton .= Html::submit(_sx('button', 'Add'), ['name' => 'add_company', 'class' => 'btn btn-primary']);
      }

      if (isset($options['title'])) {
         echo '<table class="tab_cadre_fixe">';
         echo '<tr><th>' . $options['title'] . '</th></tr>';
         echo '<tr class="tab_bg_1">
               <td class="center">';
         echo $addButton;
         Html::closeForm();
         echo '</td></tr></table>';
      } else {
         echo '<tr class="tab_bg_1">
               <td class="center" colspan="' . $options['colspan'] . '">';
         echo $addButton;
         Html::closeForm();
         echo '</td></tr>';
      }
   }

   //--------------------------------------------------------------------------------------------------------
   function prepareInputForUpdate($input) { // update img

      if (isset($input["_filename"])) {
         $plugin_company = new PluginRpCompany();
         $company        = $plugin_company->find(['id' => $input['id']]);
         $company        = reset($company);

         $tmp       = explode(".", $input["_filename"][0]);
         $extension = array_pop($tmp);
         if (!in_array($extension, ['jpg', 'jpeg'])) {
            Session::addMessageAfterRedirect(__('The format of the image must be in JPG or JPEG', 'rp'), false, ERROR);
            unset($input);
         } elseif ($company['logo_id'] != 0) {
            $doc = new Document();
            $img = $doc->find(['id' => $company["logo_id"]]);
            $img = reset($img);
            $doc->delete($img, 1);

            //$plugin_company->update(['documents_id'  => $docID]);
         }
      }
      return $input;
   }

   /*Function prepareInputForUpdate($input) { // add img

      if (isset($input["_filename"])) {
         $tmp       = explode(".", $input["_filename"][0]);
         $extension = array_pop($tmp);
         if (!in_array($extension, ['jpg', 'jpeg'])) {
            Session::addMessageAfterRedirect(__('The format of the image must be in JPG or JPEG', 'rp'), false, ERROR);
            return [];
         }
      }
      return $input;
   }*/

   Function prepareInputForAdd($input) { // add img

      if (isset($input["_filename"])) {
         $tmp       = explode(".", $input["_filename"][0]);
         $extension = array_pop($tmp);
         if (!in_array($extension, ['jpg', 'jpeg'])) {
            Session::addMessageAfterRedirect(__('The format of the image must be in JPG or JPEG', 'rp'), false, ERROR);
            return [];
         }
      }
      return $input;
   }

   function post_addItem($history = 1) {
      $img = $this->addFiles($this->input);
      foreach ($img as $key => $name) {
         $this->fields['logo_id'] = $key;
         $this->updateInDB(['logo_id']);
      }
   }

   function post_updateItem($history = 1) {
      if ($this->fields['logo_id'] == 0) {
         $img = $this->addFiles($this->input);
         foreach ($img as $key => $name) {
            $this->fields['logo_id'] = $key;
            $this->updateInDB(['logo_id']);
         }
      }
   }

   function addFiles(array $input, $options = []) {
      global $CFG_GLPI;

      /*Session::addMessageAfterRedirect(__('test', 'rp'), false, ERROR);


      $file = $this->input['_filename'];
      $filename = GLPI_TMP_DIR . "/" . $file;

      $plugin_company = new PluginRpCompany();
      $plugin_company->update(['id' => 1,'logo_id'  => $docID]);*/







      $default_options = [
         'force_update'  => false,
         'content_field' => 'content',
      ];
      $options         = array_merge($default_options, $options);

      if (!isset($input['_filename'])
          || (count($input['_filename']) == 0)) {
         return $input;
      }
      $docadded     = [];
      $donotif      = isset($input['_donotif']) ? $input['_donotif'] : 0;
      $disablenotif = isset($input['_disablenotif']) ? $input['_disablenotif'] : 0;


      foreach ($this->input['_filename'] as $key => $file) {
         $doc      = new Document();
         $docitem  = new Document_Item();
         $docID    = 0;
         $filename = GLPI_TMP_DIR . "/" . $file;
         $input2   = [];

         // Crop/Resize image file if needed
         if (isset($this->input['_coordinates']) && !empty($this->input['_coordinates'][$key])) {
            $image_coordinates = json_decode(urldecode($this->input['_coordinates'][$key]), true);
            Toolbox::resizePicture($filename, $filename, $image_coordinates['img_w'], $image_coordinates['img_h'], $image_coordinates['img_y'], $image_coordinates['img_x'], $image_coordinates['img_w'], $image_coordinates['img_h'], 0);
         } else {
            Toolbox::resizePicture($filename, $filename, 0, 0, 0, 0, 0, 0, 0);
         }

         //If file tag is present
         if (isset($input['_tag_filename'])
             && !empty($input['_tag_filename'][$key])) {
            $input['_tag'][$key] = $input['_tag_filename'][$key];
         }

         //retrieve entity
         $entities_id = isset($this->fields["entities_id"])
            ? $this->fields["entities_id"]
            : $_SESSION['glpiactive_entity'];

         // Check for duplicate
         if ($doc->getFromDBbyContent($entities_id, $filename)) {
            if (!$doc->fields['is_blacklisted']) {
               $docID = $doc->fields["id"];
            }
            // File already exist, we replace the tag by the existing one
            if (isset($input['_tag'][$key])
                && ($docID > 0)
                && isset($input[$options['content_field']])) {

               $input[$options['content_field']]
                                        = preg_replace('/' . Document::getImageTag($input['_tag'][$key]) . '/',
                                                       Document::getImageTag($doc->fields["tag"]),
                                                       $input[$options['content_field']]);
               $docadded[$docID]['tag'] = $doc->fields["tag"];
            }

         } else { // add doc glpi_document
            $input2["name"]                    = addslashes(sprintf(__('Logo %d', 'rp'), $this->getID()));
            $input2["entity_id"]               = $this->fields["entity_id"];
            $input2["_only_if_upload_succeed"] = 1;
            $input2["_filename"]               = [$file];
            $input2["is_recursive"]            = 1;
            $docID                             = $doc->add($input2);
         }

         //if ($docID > 0) {

            $input2["name"]                    = addslashes(sprintf(__('Logo %d', 'rp'), $this->getID()));
            $input2["entity_id"]               = $this->fields["entity_id"];
            $input2["_only_if_upload_succeed"] = 1;
            $input2["_filename"]               = [$file];
            $input2["is_recursive"]            = 1;
            $docID                             = $doc->add($input2);
            

            $filename = GLPI_TMP_DIR . "/" . $file;
            $plugin_company = new PluginRpCompany();
            $plugin_company->update(['id' => 1,'logo_id'  => $docID]);

            /*if ($docitem->add(['documents_id'  => $docID,
                               '_do_notif'     => $donotif,
                               '_disablenotif' => $disablenotif,
                               'itemtype'      => $this->getType(),
                               'items_id'      => $this->getID()])) {
               $docadded[$docID]['data'] = sprintf(__('%1$s - %2$s'), stripslashes($doc->fields["name"]), stripslashes($doc->fields["filename"]));

               if (isset($input2["tag"])) {
                  $docadded[$docID]['tag'] = $input2["tag"];
                  unset($this->input['_filename'][$key]);
                  unset($this->input['_tag'][$key]);
               }
               if (isset($this->input['_coordinates'][$key])) {
                  unset($this->input['_coordinates'][$key]);
               }
            }*/
         //}
         // Only notification for the first New doc
         $donotif = 0;
      }
      return $docadded;
   }

   /**
    * Returns the company's address
    *
    * @param type $obj
    *
    * @return string address
    */
   /*static function getAddress($obj) {
      $plugin_company = new PluginRpCompany();
      $company        = $plugin_company->find(['entity_id' => $obj->entite[0]->fields['id']]);
      $company        = reset($company);
      $dbu            = new DbUtils();
      if ($company == false) {
         $companies = $plugin_company->find();
         foreach ($companies as $data) {
            if ($data['recursive'] == 1) {
               $sons = $dbu->getSonsOf("glpi_entities", $data['entity_id']);
               foreach ($sons as $son) {
                  if ($son == $obj->entite[0]->fields['id']) {
                     return $data['address'];
                  }
               }
            }
         }
      } else {
         return $company['address'];
      }
   }*/

   /**
    * Returns the company logo
    *
    * @param type $obj
    *
    * @return type
    */
   /*static function getLogo($obj) {
      $plugin_company = new PluginRpCompany();
      $company        = $plugin_company->find(['entity_id' => $obj->entite[0]->fields['id']]);
      $company        = reset($company);
      $doc            = new Document();
      $dbu            = new DbUtils();
      if ($company == false) {
         $companies = $plugin_company->find();
         foreach ($companies as $data) {
            if ($data['recursive'] == 1) {
               $sons = $dbu->getSonsOf("glpi_entities", $data['entity_id']);
               foreach ($sons as $son) {
                  if ($son == $obj->entite[0]->fields['id']) {
                     if ($doc->getFromDB($data["logo_id"])) {
                        return $doc->fields['filepath'];
                     }
                  }
               }
            }
         }

      } else {
         if ($company["logo_id"] != 0) {
            $doc->getFromDB($company["logo_id"]);
            return $doc->fields['filepath'];
         }
      }
      return null;
   }

   /**
    * Returns company comments
    *
    * @param type $obj
    *
    * @return type
    */
   /*static function getComment($obj) {
      $plugin_company = new PluginRpCompany();
      $company        = $plugin_company->find(['entity_id' => $obj->entite[0]->fields['id']]);
      $company        = reset($company);
      $dbu            = new DbUtils();
      if ($company == false) {
         $companies = $plugin_company->find();
         foreach ($companies as $data) {
            if ($data['recursive'] == 1) {
               $sons = $dbu->getSonsOf("glpi_entities", $data['entity_id']);
               foreach ($sons as $son) {
                  if ($son == $obj->entite[0]->fields['id']) {
                     return $data['comment'];
                  }
               }
            }
         }
      } else {
         return $company['comment'];
      }
      return null;
   }*/

}
