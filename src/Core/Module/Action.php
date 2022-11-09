<?php
namespace Seolan\Core\Module;
class Action {
  public $menuable = false;
  public $actionable = false; // sont positionnables dans les actions de formulaires (tzr-action)
  public $containable = false;
  public $xfunction = NULL;
  public $xclass = NULL;
  public $xurl = NULL;
  public $name = NULL;
  public $needsconfirm = '';
  public $moid = NULL;
  public $toolbarX = NULL;
  public $group = NULL;
  public $newgroup = NULL;
  public $target=NULL;
  public $order = 99;
  public $separator=false;
  public $type='default'; // type de bouton action (tzr-action)
  public $shortkey = NULL;
  public $toolbar=null;
  static $_self=NULL;
  
  function __construct($m, $xf, $xn, $xurl, $group='actions') {
    $this->xclass=get_class($m);
    if($m instanceof \Seolan\Core\Module\Module) $this->moid=$m->_moid;
    $this->xfunction=$xf;
    $this->name=$xn;
    if(empty(\Seolan\Core\Module\Action::$_self)) \Seolan\Core\Module\Action::$_self=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    $this->setUrl($xurl);
    $this->group=$group;
  }

  /// Spécifie l'url de l'action
  public function setUrl($xurl){
    if($xurl[0]=='&' || substr($xurl,0,5)=='class') {
      if($xurl[0]!='&') $this->xurl=\Seolan\Core\Module\Action::$_self.'&'.$xurl;
      else $this->xurl=\Seolan\Core\Module\Action::$_self.$xurl;
    }else{
      $this->xurl=$xurl;
    }
    if(!empty($this->toolbarX)) $this->setToolbar();
  }

  /// Ajoute l'action dans la toolbar
  function setToolbar($domain=NULL,$ico_name=NULL,$html=NULL) {
    if(!empty($domain)) {
      $this->ico=\Seolan\Core\Labels::getSysLabel($domain,$ico_name, 'csico');
    }
    $shortkey = $this->getShortkey();
    if($html===NULL) {
      if(substr($this->xurl,0,11)=='javascript:'){
	$this->toolbar='<a '.$shortkey.' href="#" onclick="'.($this->needsconfirm?'if(!confirm(\''.addslashes($this->needsconfirm).'\')) return false;':'').
	  str_replace(['javascript:','"'],['','&quot;'],$this->xurl).'return false;" '.
	  'title="'.$this->name.'"'.($this->target?' target="'.$this->target.'"':'').'>';
	$this->toolbarX='<button '.$shortkey.' type="button" class="btn btn-default" onclick="'.($this->needsconfirm?'if(!confirm(\''.addslashes($this->needsconfirm).'\')) return false;':'').
	  str_replace(['javascript:','"'],['','&quot;'],$this->xurl).'return false;" morder="'.$this->order.'" title="'.$this->name.'">';
      }else{
	$this->toolbar='<a '.$shortkey.' href="'.$this->xurl.'" title="'.$this->name.'" '.
	  ($this->needsconfirm?'x-confirm="var ret=confirm(\''.addslashes($this->needsconfirm).'\');" ':'').
	  ($this->target?'target="'.$this->target.'"':'class="cv8-ajaxlink"').'>';
	if ($this->target){
	  $this->toolbarX='<a '.$shortkey.' target="'.$this->target.'" class="btn btn-default" href="'.$this->xurl.'" morder="'.$this->order.'" title="'.$this->name.'">';
	} else {
	  $this->toolbarX='<button '.$shortkey.' type="button" class="btn btn-default" onclick="TZR.updateModuleContainer( \''.$this->xurl.'\', jQuery(this).closest(\'.cv8-module-container\'));" morder="'.$this->order.'" title="'.$this->name.'">';
	}
      }
      $this->toolbarX.=$this->ico.'</button>';
    }else{
      $this->toolbarX=$html;
      $this->toolbar=$html;
    }
  }
  /// récupère le raccourci clavier associé à l'item de menu
  public function getShortkey(){
    if ($this->shortkey){
      return " data-shortkey=\"{$this->shortkey}\" ";
    } else {
      return "";
    }
  }
}
?>
