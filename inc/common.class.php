<?php

class PluginRpCommon extends CommonGLPI {

   protected $obj= NULL;

   static $rightname = "plugin_rp";

   /**
    * Constructor, should intialize $this->obj property
   **/
   function __construct(CommonGLPI $obj=NULL) {
   }

   /**
    * @since version 0.85
   **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'DoIt':
            $cont = $ma->POST['container'];
            $opt = ['id' => 'rpmassubmit'];
            echo Html::submit(_sx('button', 'Post'), $opt);
            return true;
      }
      // return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      switch ($ma->getAction()) {
         case 'DoIt' :
            foreach ($ids as $key => $val) {
               if ($val) {
                  $tab_id[]=$key;
                }
             }
             $_SESSION["plugin_rp"]["type"]   = $item->getType();
             $_SESSION["plugin_rp"]["tab_id"] = serialize($tab_id);
             echo "<script type='text/javascript'>
                      location.href='../plugins/rp/front/export.massive.php'</script>";
             break;
      }
   }

   /**
    * Generate the RP for some object
    *
    * @param $tab_id  Array   of ID of object to print
    * @param $tabs    Array   of name of tab to print
    * @param $page    Integer 1 for landscape, 0 for portrait
    * @param $render  Boolean send result if true,  return result if false
    *
    * @return rp output if $render is false
   **/
   function generateRP($tab_id) {
      
      $dbu = new DbUtils();

      foreach ($tab_id as $key => $id) {
         return $id."<br>";
      }
   }
}