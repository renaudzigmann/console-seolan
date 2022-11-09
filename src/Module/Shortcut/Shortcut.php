<?php
namespace Seolan\Module\Shortcut;

class Shortcut extends \Seolan\Core\Module\Module {
  public $url = '';

  // Description :
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  // suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  // cette fonction est appliquee pour afficher l'ensemble des methodes
  // de ce module
  //
  protected function _actionlist(&$my, $alfunction=true) {
    \Seolan\Core\Module\Module::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $o1=new \Seolan\Core\Module\Action($this, 'go', \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','target','text'),
			  'class='.$myclass.'&amp;moid='.$moid.
			  '&amp;_function=go&amp;tplentry=br');
    $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
    $my['home']=$o1;
    $my['default']='home';
  }
  
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','target'), 'url', 'text');
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['go']=array('list','ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  function go($ar=NULL) {
    header('Location: '.$this->url);
  }

}


?>
