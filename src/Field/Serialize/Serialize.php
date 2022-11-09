<?php
namespace Seolan\Field\Serialize;
/*
 * Champs contenant une valeur sérialisée en json
 */

class Serialize extends \Seolan\Field\Text\Text{

  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    if(is_array($value) || is_object($value)){
      $value=json_encode($value);
    }
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    $treewalker = new \TreeWalker(array("debug"=>false, "returntype"=>"object"));

    // préparation des objets pour le diff des logs
    if(is_string($value)) $value=json_decode($value);
    $old=json_decode($options['old']->raw);

    // calcul de la différence entre l'ancienne et la nouvelle valeur
    $diff = $treewalker->getdiff($value ?? [], $old ?? [], true);

    // on vire les champs non renseignés dans l'objet diff
    foreach(['new','edited','removed'] as $field) {
      if(empty($diff->$field)) unset($diff->$field);
    }
    $sdiff=json_encode($diff);

    // pas de trace si pas de diff
    if($sdiff!='{}') $this->trace(@$options['old'],$r, $sdiff);
    return $r;
  }

  public function my_edit(&$value, &$options, &$fields_complement = NULL) {
    $ret = parent::my_edit($value, $options, $fields_complement);

    $color = \Seolan\Core\Ini::get('error_color');
    $js = 'TZR.addValidator(["' . $ret->varid . '","","","' . $color . '","\Seolan\Field\Serialize\Serialize"]);';
    $ret->html .= "<script>$js</script>";

    return $ret;
  }

  function my_display_deferred(&$r){
    $r->html=\Seolan\Field\Serialize\Serialize::jsonToHtml($r->raw);
    return $r;
  }
  
  function my_browse_deferred(&$r){
    $this->my_display_deferred($r);
  }

  function decodeRaw($r,$assoc=false){
    $r->decoded_raw=json_decode($r->raw,$assoc);
    if(!$r->decoded_raw) $r->decoded_raw=array();
    return $r->decoded_raw;
  }

  static function jsonToHtml($value){
    $out = print_r(json_decode($value,true),true);
    $out = preg_replace('/^Array\s*\(/iUms','<table class="tzr-serialize">',$out);
    $out = preg_replace('/\)\s*$/', '</table>', $out);
    $out = preg_replace('/^\s*\)\s*$/m', '</table></td></tr>', $out);
    $out = preg_replace('/[ \t]*\[([^\]]+)\][ \t]*\=\>[ \t]*[a-z0-9 \t_]+\n[ \t]*\(/iU','<tr><th>$1</th><td><table>', $out);
    $out = preg_replace('/[ \t]*\[([^\]]+)\][ \t]*\=\>[ \t]*(.+)\n[ \t]*/iU','<tr><th>$1</th><td>$2</td></tr>', $out);
    return $out;
  }
}
