<?php
namespace Seolan\Field\Module;
class Module extends \Seolan\Core\Field\Field {
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','filter'), 'filter', 'text',array('rows'=>10,'cols'=>60));
  }

  function my_display_deferred(&$r){
    $modtmp=\Seolan\Core\Module\Module::objectFactory($r->raw);
    if($modtmp) {
      $r->text=$modtmp->getLabel();
      $group = \Seolan\Core\Labels::getTextSyslabel('Seolan_Core_General','group');
      $i18n = \Seolan\Core\Labels::getTextSyslabel('Seolan_Core_General','i18ncolon');
      $r->html = "<span title='{$group}{$i18n}{$modtmp->group}, moid{$i18n}{$r->raw}'>{$r->text}</span>";
    }
    return $r;
  }

  // generation du champ pour modification du fichier
  //
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $lang = \Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    $r->raw = $value;
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field.'['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
    } else {
      $fname=$this->field;
    }
    $r->html = $this->getSelect($fname, $value, $this->compulsory ? false : '---', $this->multivalued, $this->getFilter(), ($options['filterCallback']??null));
    return $r;
  }
  function sqltype() {
    return 'varchar(255)';
  }
  function my_query($value,$options=NULL) {
    $lang = \Seolan\Core\Shell::getLangUser();
    if(is_array($value)) $value=implode($value);
    $r = $this->_newXFieldVal($options);
    $r->raw = $value;
    $fieldname = @$options['fieldname'] ?: $this->field;
    $first_value = (@$options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? false : '---');
    $r->html = '<input type="hidden" name="_FIELDS['.$fieldname.']" value="'.$this->field.'" />'.
      $this->getSelect($fieldname, $value, $first_value, false, $this->getFilter());
    return $r;
  }

  function my_quickquery($value, $options=NULL) {
    return $this->my_query($value, $options);
  }

  /**
   * Retourne le code HTML d'une balise select
   * @param $fieldname string Nom du champ à récupérer dans le $_REQUEST après soumission
   * @param $selected string|array Valeur(s) sélectionnée(s) par défaut dans le select
   * @param $first_value_text string|boolean Texte à afficher en premier (false pour ne pas avoir de premier item)
   * @param $multivalued boolean Vrai pour pouvoir sélectionner plusieurs modules
   * @param $filter string Filtre SQL pour la sélection de modules (sans le WHERE)
   * @return string Code HTML de la balise select
   */
  private function getSelect($fieldname, $selected = null, $first_value_text = '---', $multivalued = false, $filter = '', $filterCallback=null) {

    // On ne calcule qu'une fois la liste des modules par filtre demandé
    static $modules_list = array();
    if (empty($modules_list[$filter])) {
      $modules_list[$filter] = [];
      $modules = getDB()->fetchAll('SELECT MOID FROM MODULES '.($filter ? ' WHERE '.$filter : '').' ORDER BY MODULE');
      foreach($modules as $module) {
      	$moid=$module['MOID'];

      	$modtmp=\Seolan\Core\Module\Module::objectFactory($moid);
        if ($filterCallback != null && !$filterCallback($modtmp)){
          continue;
        }
      	$modules_list[ $filter ][ $modtmp->group ][ $moid ] = $modtmp->getLabel();
      }
      ksort($modules_list[$filter]);
    }

    // Création du code HTML
    $html = '<select '.($first_value_text === false ? 'required' : '').' name="'.$fieldname.'">';
    if ($first_value_text !== false) $html .= '<option value="">'.$first_value_text.'</option>';
    if ($multivalued) $html = '<select '.($first_value_text === false ? 'required' : '').' name="'.$fieldname.'[]" size="6" multiple>';
    if (!is_array($selected))
      $selected = [$selected];
    $selected = $selected??[];

    foreach($modules_list[$filter] as $group => $module) {
      $html .= '<optgroup label="'.htmlentities($group, ENT_COMPAT, TZR_INTERNAL_CHARSET).'">';
      foreach($module as $moid => $modulename) {
        $moids .= " $moid";
        $isSelected = in_array($moid, $selected) || in_array($moid, array_keys($selected));
        $html .= '<option value="'.$moid.'" '.($isSelected ? 'selected="selected"' : '').'>'.$modulename.'</option>';
      }
    }
    return $html.'</select>';
  }
  function post_edit($value, $options = NULL, &$fields_complement = NULL) {
    return parent::post_edit($value, $options, $fields_complement);
  }
}
?>
