<?php
/**
 * génération des différents paramètres d'un lien activable sur un browse
 * permet de sortir les méthodes du module
 * s'intègre à partit de browseActionForLine (sans s) en passant une instance de ce type
 */
namespace Seolan\Core\Module;
abstract class BrowseActionHelper {
  protected $defaultText = null;
  protected $defaultIcon = null;
  protected $defaultLvl = null;
  protected $defaultHtmlAttributes = null;
  protected $type = '';
  protected $module = null;
  function __construct($type, $module,$icon,$lvl,$attributes){
    if (is_array($icon)){
      $this->defaultText = $icon[0];
      $this->defaultIcon = $icon[1];
    } else {
      list($locale, $name) = explode(' ',$icon);
      $this->defaultText = \Seolan\Core\Labels::getTextSysLabel($locale, $name);
      $this->defaultIcon = \Seolan\Core\Labels::getSysLabel($locale, $name);
    }
    $this->defaultLvl = $lvl;
    $this->defaultHtmlAttributes = $attributes;
    $this->type = $type;
    $this->module = $module;
  }
  function browseActionText($linecontext=null){
    return $this->defaultText;
  }
  function browseActionIco($linecontext=null){
    return $this->defaultIcon;
  }
  function browseActionLvl($linecontext=null){
    return $this->module->secGroups($this->defaultLvl);
  }
  function browseActionHtmlAttributes(&$url,&$text,&$icon, $linecontext){
    return $this->defaultHtmlAttributes;
  }
  function getType(){
    return $this->type;
  }
  abstract function browseActionUrl($usersel, $linecontext=null);
}
