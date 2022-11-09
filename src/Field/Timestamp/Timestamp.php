<?php
namespace Seolan\Field\Timestamp;
class Timestamp extends \Seolan\Core\Field\Field {

  use \Seolan\Field\Date\DateTrait;
  
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function my_export($value) {
    return $this->dateFormat($value);
  }
  function my_display_deferred(&$r){
    $r->html=$this->dateFormat($r->raw);
    return $r;
  }
  function getDefaultValue() {
    return date('YmdHis');
  }
  static function default_timestamp() {
    return date('YmdHis');
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    if(!isset($value)) $value=date('Y-m-d H:i:s');
    $o=$this->my_display($value,$options);
    $r->raw=$o->raw;
    $r->html=$o->html;
    return $r;
  }
  function my_query($value,$options=NULL) {
    $o=(object)array('FIELD'=>$this->field,'COMPULSORY'=>$this->compulsory,'TRANSLATABLE'=>$this->translatable);
    $dt=new \Seolan\Field\DateTime\DateTime($o);
    $r=$this->_newXFieldVal($options);
    if (empty($options['op']) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_op'])) {
      $options['op'] = @$options['fields_complement']['query_comp_field_op'];
    }
    $r2=$dt->my_query($value,$options);
    $r->html=$r2->html;
    return $r;
  }
  function my_quickquery($value,$options=NULL) {
    $ar['query_format']=\Seolan\Core\Field\Field::QUICKQUERY_FORMAT;
    $r=$this->query($value,$options);
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">'.$r->html;
    return $r;
  }
  function post_query($o,$ar) {
    $dt=new \Seolan\Field\DateTime\DateTime();
    $dt->table=$this->table;
    $dt->field=$this->field;
    return $dt->post_query($o,$ar);
  }
  function sqltype() {
    return 'timestamp';
  }
  /// Créé un timestamp à partir d'une date internationale (identique à strtotime en plus léger)
  static function dateToTimestamp($date) {
    $expl=preg_split('@[:\ \-]@',$date);
    if(count($expl)>3) $timestamp=mktime($expl[3],$expl[4],$expl[5],$expl[1],$expl[2],$expl[0]);
    elseif(count($expl)==3) $timestamp=mktime(0,0,0,$expl[1],$expl[2], $expl[0]);
    else $timestamp=mktime(substr($date,8,2),substr($date,10,2),substr($date,12,2),substr($date,4,2),substr($date,6,2),substr($date,0,4));
    return $timestamp;
  }
  /// tenir compte des cas en recherche où l'heure n'est pas transmise
  static function dateFormat($date) {
    if(empty($date) || $date==TZR_DATETIME_EMPTY) return '';
    $lang=\Seolan\Core\Shell::getLangUser();
    if (preg_match('/^[\d]{4}-[\d]{2}-[\d]{2} *$/', $date))
      $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    else
      $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'].' H:i:s';
    $fmted=date($fmt,static::dateToTimestamp($date));
    return $fmted;
  }
  /**
   * Function isQueryEmpty
   * @return true si il n'y a pas de recherche en cours sur le champ
   */
  public function isQueryEmpty($query=array(), $isValueEmpty=false){
    $p = new \Seolan\Core\Param($query);
    $field = ($query && count($query['_FIELDS'])) ? array_search($this->field, $query['_FIELDS']) : $this->field;
    $fieldVal = $p->get($field);
    $isValueEmpty = (is_array($fieldVal) && empty($fieldVal['date']) && empty($fieldVal['hour']));

    return parent::isQueryEmpty($query, $isValueEmpty);
  }
}
?>
