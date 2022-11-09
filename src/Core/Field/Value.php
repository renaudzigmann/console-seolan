<?php
namespace Seolan\Core\Field;
class Value {
  public $raw=NULL;
  public $field=NULL;
  public $table=NULL;
  public $fielddef=NULL;
  public $trace=NULL;
  public $method_executed=false;
  public $options=NULL;
  public $_cache=array();
  public $func=NULL;
  
  function __construct(&$options=NULL,&$method=null) {
    $this->raw='';
    $this->options=$options;
    $this->method=$method;
  }
  /// Appel une fonction du xfielddef
  public function __call($f,$param){
    array_unshift($param, $this);
    return call_user_func_array(array($this->fielddef,$f),$param);
  }
  /// Retourne une propriété encore non definie
  public function __get($name){
    // On teste la valeur car avec empty(), __get peut etre appelé 2 fois à la suite et on ne veut construire l'info qu'une fois
    if(property_exists($this,$name)) return $this->$name;

    // Texte
    if($name=='text'){
      if($this->fielddef->multivalued && $this->fielddef->get_fgender()=='Oid') return $this->fielddef->multiOidText($this);
      else return $this->fielddef->toText($this);
    }
    // Raw décodé
    if($name=='decoded_raw') return $this->fielddef->decodeRaw($this);
    // HTML sur un champ oid multivalué
    if($name=='html' && $this->fielddef->multivalued && $this->fielddef->get_fgender()=='Oid') return $this->fielddef->multiOidHtml($this);
    // Si l'objet doit etre rempli via une methode différée, on l'exécute
    if($this->method && !$this->method_executed){
      $this->method_executed=true;
      $this->fielddef->{$this->method.'_deferred'}($this);
      return @$this->$name;
    }
    // Défaut
    $this->$name=null;
    return $this->$name;
  }

  /// Vérifie si une propriété encore non définie existe
  function __isset($name){
    $this->__get($name);
    return isset($this->$name);
  }
}
