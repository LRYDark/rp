<?php
//include('../../../inc/includes.php');

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginRpGenerateCRI
 */
class PluginRpGenerateCRI extends CommonGLPI {

   static $rightname = "ticket";
   /**
    * @param int $nb
    *
    * @return string|\translated
    * @see CommonDBTM::getTypeName($nb)
    *
    */
   static function getMenuName($nb = 0) {
      return __('Signature', 'rp');
   }

   /**
    * @return array
    */
   static function getMenuContent() {

      $menu = [];

      $menu['title'] = self::getMenuName();
      $menu['page'] = PLUGIN_RP_NOTFULL_WEBDIR."/front/generatecri.php";
      $menu['links']['search'] = self::getSearchURL(false);
      $menu['icon'] = self::getIcon();

      return $menu;
   }

   /**
    * @return string
    */
   static function getIcon() {
      return "fa-solid fa-signature";
   }

   /**
    * @param $ticket
    * @param $entities
    *
    * @throws \GlpitestSQLError
    */
   function showWizard($ticket, $entities) {
      // Règles d'accès et non droit de profil brut : un utilisateur écarté du
      // rapport technicien par une règle ne doit pas non plus accéder à l'écran
      // de sa signature personnelle. La fiche porte aussi cette signature.
      if(PluginRpAccess::canUse('rapport_tech', CREATE) || PluginRpAccess::canUse('fiche', CREATE)){
         if(Session::haveRight("plugin_rp_Signature", CREATE) && Session::haveRight("plugin_rp_Signature", READ)){
            global $DB, $CFG_GLPI;
            $UserID = Session::getLoginUserID();
            $seing = $DB->doQuery("SELECT seing FROM `glpi_plugin_rp_signtech` WHERE user_id = $UserID")->fetch_object();

            echo '<link rel="stylesheet" href="' . PLUGIN_RP_WEBDIR . '/public/css/signature_rp.css?r=' . (defined('PLUGIN_RP_ASSETS_REV') ? PLUGIN_RP_ASSETS_REV : '1') . '">';
            echo '<script src="' . PLUGIN_RP_WEBDIR . '/public/js/scripts_rp.js?r=' . (defined('PLUGIN_RP_ASSETS_REV') ? PLUGIN_RP_ASSETS_REV : '1') . '" defer></script>';

            echo "<form method='post' action='" . self::getFormUrl() . "'>";
            echo Html::hidden('plugin_rp_generatecri_csrf_token', ['value' => Session::getNewCSRFToken(true)]);

               echo "<table class='tab_cadre' width='60%'>";

                  echo'<textarea readonly name="url" id="sig-dataUrl" class="form-control" rows="0" cols="150" style=" color: transparent; border: none; background: none; outline: none;  resize : none; "></textarea><br>';
                  $uniq = 'cri'.mt_rand(10000,99999);
                  // SOUS-CARTE 2 : Canvas signature

                  echo "<tr class='tab_bg_1'>";
                     echo "<th colspan='4' style='padding-top:16px; font-weight: bold;'>";
                        echo __('Signature Personnelle', 'rp');
                     echo "</th>";
                  echo "</tr>";

                  echo "<tr class='tab_bg_1'>";
                     echo "<td>";                        
                     echo "</td>";
                     
                     echo '<td style="width: 800px; overflow: hidden; text-overflow: ellipsis;">';
                        echo '<div class="signature-sub-title"><i class="fa-solid fa-signature"></i></div>';
                        echo "<div id='".$uniq."' class='cri-signature-root'>";
                           echo "  <div class='signature-container'>";
                           echo "    <button type='button' class='zoom-btn'>Agrandir <i class='fa-solid fa-up-right-and-down-left-from-center'></i></button>";
                           echo "    <canvas id='sig-canvas-".$uniq."' height='80' class='sig-base'></canvas>";
                           echo "  </div>";
                           echo "  <button type='button' id='sig-clearBtn-".$uniq."' class='resetButton'>Supprimer la signature</button>";

                           // Fenêtre d'agrandissement : modal natif GLPI (Bootstrap)
                           echo "  <div class='modal fade sig-modal' id='sig-modal-".$uniq."' tabindex='-1' aria-hidden='true'>";
                           echo "    <div class='modal-dialog sig-dialog modal-xl modal-fullscreen-md-down'>";
                           echo "      <div class='modal-content'>";
                           echo "        <div class='modal-header py-2'>";
                           echo "          <h5 class='modal-title'><i class='ti ti-signature me-2'></i>Signature</h5>";
                           echo "          <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Fermer'></button>";
                           echo "        </div>";
                           echo "        <div class='modal-body sig-modal-body'>";
                           echo "          <div class='cri-canvas-wrapper'>";
                           echo "            <canvas id='modal-canvas-".$uniq."' class='modal-canvas'></canvas>";
                           echo "          </div>";
                           echo "        </div>";
                           echo "        <div class='modal-footer py-2'>";
                           echo "          <button type='button' class='btn btn-outline-secondary sig-btn-clear'><i class='ti ti-eraser me-1'></i>Effacer</button>";
                           echo "          <button type='button' class='btn btn-outline-secondary sig-btn-cancel' data-bs-dismiss='modal'>Annuler</button>";
                           echo "          <button type='button' class='btn btn-primary sig-btn-validate'><i class='ti ti-check me-1'></i>Valider</button>";
                           echo "        </div>";
                           echo "      </div>";
                           echo "    </div>";
                           echo "  </div>";
                        echo "</div>";
                     echo "</td>";
                  echo "</tr>";

                  if(Session::haveRight("plugin_rp_Signature", READ)){
                  // Signature
                     echo "<tr class='tab_bg_1'>";
                        echo "<td style='padding-top:16px; font-weight: bold;'>";
                           echo _n('Signature enregistrée', 'Signature enregistrée', 2, 'rp');
                        echo "</td>";
                        echo "<td>";
                           if(!empty($seing)){
                              echo '<img type="image" src="'.$seing->seing.'" width="300" height="auto">';
                           }else{
                              echo 'Aucune signature enregistrée';
                           }
                        echo "</td>";
                     echo "</tr>";
                  }

                  //TABLEAU 4 BOUTON generation pdf
                  echo "<tr>";
                     echo "<td>";
                        echo '';
                     echo "</td>";

                     echo "<td>";
                        if(empty($seing)){
                           echo "<input type='submit' name='generatecri' id='sig-submitBtn' value='Enregistrer' class='submit'> &emsp;"; 
                        }else{
                           if(Session::haveRight("plugin_rp_Signature", UPDATE)){
                              echo "<input type='submit' name='generatecri' id='sig-submitBtn' value='Enregistrer' class='submit'> &emsp;"; 
                           }
                        }

                        if(Session::haveRight("plugin_rp_Signature", PURGE)){
                           if(!empty($seing)){
                              echo "<input type='submit' name='delete' value='Supprimer la signature' class='btn btn-danger me-2'>";
                           }
                        }
                     echo "</td>";
                  echo "</tr>";

               echo "<table class='tab_cadre' width='60%'>";
            Html::closeForm();
         
            ?>
            <style>
               /* Tableaux : largeur auto en desktop, pleine largeur en mobile */
               .tab_cadre {
               width: 60%;
               max-width: 100%;
               table-layout: auto;
               }

               /* Supprimer la largeur figée à 800px */
               td[style*="width: 800px"] {
               width: auto !important;
               max-width: 100% !important;
               }

               /* Rendre le canvas de base fluide (la modale est dimensionnée par le JS,
                  ne pas la contraindre en CSS sous peine de décalage du tracé) */
               .sig-base {
               max-width: 100%;
               display: block;
               }

               /* Responsive : empile les colonnes en petit écran */
               @media (max-width: 768px) {
                  .tab_cadre, .tab_cadre tr, .tab_cadre td, .tab_cadre th {
                     display: block;
                     width: 100% !important;
                     box-sizing: border-box;
                  }
               }

               .cri-signature-root .resetButton {
                  background: #3fac00ff;
                  color: #fff;
                  border: 0;
                  padding: 6px 12px;
                  border-radius: 3px;
                  cursor: pointer;
                  font-size: 12px;
                  font-weight: 500;
                  transition: .2s;
                  margin-top: 10px;
                  display: inline-block;
                  text-align: center;
                  float: none !important;
                  clear: both;
                  margin-left: 0 !important;
                  margin-right: auto !important;
               }
            </style>

            <script>
               setTimeout(function() {
                  // 3. Initialiser la signature
                  function initSignature() {
                     if (typeof initializeSignatureRp === 'function') {
                        initializeSignatureRp('<?php echo $uniq; ?>');
                     } else {
                        setTimeout(initSignature, 100);
                     }
                  }
                  initSignature();
                  
               }, 100); // Délai de 100ms pour s'assurer que tout est chargé
               </script>
            <?php    
         }
      }
   }
}
