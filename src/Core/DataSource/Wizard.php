<?php
namespace Seolan\Core\DataSource;
class Wizard implements \Seolan\Core\ISec {
  public $_step;
  public $_datasource=NULL;
  /** @var \Seolan\Core\Options $_options */
  public $_options;
  
  function __construct($ar=NULL) {
    $this->_options=new \Seolan\Core\Options();
    $p = new \Seolan\Core\Param($ar, array());
    $this->_step='1';
    $this->_datasource=NULL;
    $step=$p->get('step');
    if(issetSessionVar('DataSourceWd') && ($step!=1)) {
      $a=unserialize(getSessionVar('DataSourceWd'));
      foreach($a as $k => $v) {
	$this->$k = $v;
      }
    }
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('irun'=>array('admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return false;
  }

  function secList() {
    return array('admin');
  }

  /// Fonction executee pour le wizard d'installation
  function irun($ar=NULL) {
    global $XSHELL;
    $p=new \Seolan\Core\Param($ar,array());
    $step=$p->get("step");
    if(isset($step)) $this->_step=1;
    $fname = "istep".$this->_step;
    $XSHELL->tpldata["wd"]['classname']=get_class($this);
    $XSHELL->tpldata["wd"]['functionname']='irun';
    $XSHELL->tpldata["wd"]['template']='Module/DataSource.sourceWizard.html';
    $XSHELL->tpldata["wd"]['step']=$this->_step;
    $this->_step++;
    $this->_options->clearOpts();
    if(method_exists($this,$fname)) {
      $this->istep();
      $this->$fname();
      $opts=$this->_options->getDialog($this->_datasource,array(),'datasource');
      \Seolan\Core\Shell::toScreen2('wd','options',$opts);
      setSessionvar('DataSourceWd',serialize(get_object_vars($this)));
    } else {
      $this->istep();
      $this->iend();
      $opts=$this->_options->getDialog($this->_datasource,array(),'datasource');
    }
  }

  function istep() {
    $p=new \Seolan\Core\Param(array(),array());
    $ds=$p->get('datasource');
    if(is_array($ds)) $all=array_merge((array)$this->_datasource,$ds);
    else $all=$this->_datasource;
    $this->_datasource=(object)$all;
  }

  /// Fonction de fin
  function iend($ar=NULL) {
  }

  static function getNewBoid(){
    $ok=false;
    do{
      $boid=md5(uniqid());
      $nb=getDB()->count('select count(BOID) from BASEBASE where BOID="'.$boid.'"');
      if($nb==0) $ok=true;
    }while($ok==false);
    return $boid;
  }
}
?>