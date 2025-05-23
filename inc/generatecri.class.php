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
      if(Session::haveRight("plugin_rp_rapport_tech", CREATE)){
         if(Session::haveRight("plugin_rp_Signature", CREATE) && Session::haveRight("plugin_rp_Signature", READ)){
            global $DB, $CFG_GLPI;
            $UserID = Session::getLoginUserID();
            $seing = $DB->doQuery("SELECT seing FROM `glpi_plugin_rp_signtech` WHERE user_id = $UserID")->fetch_object();

            echo "<form method='post' action='" . self::getFormUrl() . "'>";

               echo "<table class='tab_cadre' width='60%'>";

                     echo'<textarea readonly name="url" id="sig-dataUrl" class="form-control" rows="0" cols="150" style=" color: transparent; border: none; background: none; outline: none;  resize : none; "></textarea><br>';

                  // Signature
                  echo "<tr class='tab_bg_1'>";
                     echo "<th colspan='4' style='padding-top:16px; font-weight: bold;'>";
                        echo __('Signature Personnelle', 'rp');
                     echo "</th>";
                  echo "</tr>";

                  echo "<tr class='tab_bg_1'>";
                     echo "<td>";
                        echo _n('Signature', 'Signature', 2, 'rp');
                     echo "</td>";
                     echo "<td>";
                        //echo "<canvas id='sig-canvas' class='sig' value='sig-image' widtd='320' height='80'></canvas>";
                        echo "<canvas id='sig-canvas' class='sig' value='sig-image' width='320' height='80' style='border: 1px solid black;'></canvas>";
                        ?><style>
                           #sig-canvas {
                           border: 1px solid #ccc;
                           border-radius: 6px;
                           background-color: #ffffff;
                           box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                           }
                        </style><?php
                     echo "</td>";
                  echo "</tr>";

                  if(Session::haveRight("plugin_rp_Signature", READ)){
                  // Signature
                     echo "<tr class='tab_bg_1'>";
                        echo "<td>";
                           echo _n('Signature enregistrée', 'Signature enregistrée', 2, 'rp');
                        echo "</td>";
                        echo "<td>";
                           if(!empty($seing)){
                              echo '<img type="image" src="'.$seing->seing.'">';
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
                           echo "<input type='submit' id='sig-clearBtn' name='remove' value='Vider la signature' class='btn btn-outline-warning me-2'>";
                        }else{
                           if(Session::haveRight("plugin_rp_Signature", UPDATE)){
                              echo "<input type='submit' name='generatecri' id='sig-submitBtn' value='Enregistrer' class='submit'> &emsp;"; 
                              echo "<input type='submit' id='sig-clearBtn' name='remove' value='Vider la signature' class='btn btn-outline-warning me-2'>";
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
            <script>
               //--------------------------------------------------- signature
                  window.requestAnimFrame = (function(callback) {
                     return window.requestAnimationFrame ||
                        window.webkitRequestAnimationFrame ||
                        window.mozRequestAnimationFrame ||
                        window.oRequestAnimationFrame ||
                        window.msRequestAnimaitonFrame ||
                        function(callback) {
                        window.setTimeout(callback, 1000 / 60);
                        };
                  })();

                  var canvas = document.getElementById("sig-canvas");
                  var ctx = canvas.getContext("2d");
                  ctx.strokeStyle = "#222222";
                  ctx.lineWidtd = 1;

                  var drawing = false;
                  var mousePos = {
                     x: 0,
                     y: 0
                  };
                  var lastPos = mousePos;

                  canvas.addEventListener("mousedown", function(e) {
                     drawing = true;
                     lastPos = getMousePos(canvas, e);
                  }, false);

                  canvas.addEventListener("mouseup", function(e) {
                     drawing = false;
                  }, false);

                  canvas.addEventListener("mousemove", function(e) {
                     mousePos = getMousePos(canvas, e);
                  }, false);

                  // Add touch event support for mobile
                  canvas.addEventListener("touchmove", function(e) {
                     var touch = e.touches[0];
                     e.preventDefault(); 
                     var me = new MouseEvent("mousemove", {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                     });
                     canvas.dispatchEvent(me);
                  }, false);

                  canvas.addEventListener("touchstart", function(e) {
                     mousePos = getTouchPos(canvas, e);
                     e.preventDefault(); 
                     var touch = e.touches[0];
                     var me = new MouseEvent("mousedown", {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                     });
                     canvas.dispatchEvent(me);
                  }, false);

                  canvas.addEventListener("touchend", function(e) {
                     e.preventDefault(); 
                     var me = new MouseEvent("mouseup", {});
                     canvas.dispatchEvent(me);
                  }, false);

                  function getMousePos(canvasDom, mouseEvent) {
                     var rect = canvasDom.getBoundingClientRect();
                     return {
                        x: mouseEvent.clientX - rect.left,
                        y: mouseEvent.clientY - rect.top
                     }
                  }

                  function getTouchPos(canvasDom, touchEvent) {
                     var rect = canvasDom.getBoundingClientRect();
                     return {
                        x: touchEvent.touches[0].clientX - rect.left,
                        y: touchEvent.touches[0].clientY - rect.top
                     }
                  }

                  function renderCanvas() {
                     if (drawing) {
                        ctx.moveTo(lastPos.x, lastPos.y);
                        ctx.lineTo(mousePos.x, mousePos.y);
                        ctx.stroke();
                        lastPos = mousePos;
                     }
                  }

                  // Prevent scrolling when touching tde canvas
                  document.body.addEventListener("touchstart", function(e) {
                     if (e.target == canvas) {
                        e.preventDefault();
                     }
                  }, false);
                  document.body.addEventListener("touchend", function(e) {
                     if (e.target == canvas) {
                        e.preventDefault();
                     }
                  }, false);
                  document.body.addEventListener("touchmove", function(e) {
                     if (e.target == canvas) {
                        e.preventDefault();
                     }
                  }, false);

                  (function drawLoop() {
                     requestAnimFrame(drawLoop);
                     renderCanvas();
                  })();

               // Set up tde UI
                  var sigText = document.getElementById("sig-dataUrl");
                  var submitBtn = document.getElementById("sig-submitBtn");

                  submitBtn.addEventListener("click", function(e) {
                     var dataUrl = canvas.toDataURL();
                     sigText.innerHTML = dataUrl;                            
                  }, false);
                  
                  //--------------------------------------------------- BTN SUPPRIMER
                  var clearBtn = document.getElementById("sig-clearBtn");
                  clearBtn.addEventListener("click", function(e) {
                     location.reload();
                  }, false);
               
               </script>
            <?php    
         }
      }
   }
}
