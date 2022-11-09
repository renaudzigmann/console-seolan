<?php
namespace Seolan\Core\Module;
class Wizard implements \Seolan\Core\ISec {
  public $_step;
  public $_moid;
  public $_module=NULL;
  /** @var \Seolan\Core\Options */
  public $_options;
  protected $_selectedMoid;

  static function newMoid() {
    static $nmoid=0;
    if($ors=getDB()->fetchRow("select MAX(MOID) from MODULES")) {
      $moid=$ors['MAX(MOID)']+1;
    } else {
      $moid=1;
    }
    if($nmoid==0 || $moid>$nmoid) $nmoid=$moid;
    elseif($moid<=$nmoid) {
      $moid=$nmoid+1;
      $nmoid++;
    } 
    return $moid;
  }
  
  protected function setNewMoid() {
    $moid = 0;
    if ($this->_selectedMoid>0) {
      $exist = getDB()->fetchRow("select MOID from MODULES where MOID=?",[$this->_selectedMoid]);
      if (!$exist)
        $moid = $this->_selectedMoid;
    }
    if (!$moid) {
      $moid = self::newMoid();
    }
    $this->_selectedMoid = NULL;
    return $moid;
  }

  function __construct($ar=NULL) {
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    $this->_options = new \Seolan\Core\Options();
    $p = new \Seolan\Core\Param($ar, array());
    $this->_step="1";
    $this->_moid=$p->get("newmoid");//toid
    $this->_selectedMoid = $p->get("selectedmoid");
    $this->_module = NULL;
    $step=$p->get("step");
    if(issetSessionVar("ModWd") && ($step!=1)) {
      $a=unserialize(getSessionVar("ModWd"));
      foreach($a as $k => $v) {
	$this->$k = $v;
      }
    }
  }
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

  // fonction executee pour le wizard d'installation
  function irun($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $step=$p->get("step");
    if(isset($step)) $this->_step=1;
    $fname = "istep".$this->_step;
    global $XSHELL;
    $XSHELL->tpldata["wd"]['classname']=get_class($this);
    $XSHELL->tpldata["wd"]['step']=$this->_step;
    $this->_step++;
    $this->_options->clearOpts();
    if(method_exists($this,$fname)) {
      $this->istep();
      $this->$fname();
      $opts=$this->_options->getDialog($this->_module, array(),"module");
      \Seolan\Core\Shell::toScreen2("wd","options",$opts);
      setSessionvar("ModWd",serialize(get_object_vars($this)));
    } else {
      $this->istep();
      $this->iend();
      $opts=$this->_options->getDialog($this->_module, array(),"module");
    }
  }

  function istep() {
    $p=new \Seolan\Core\Param(array(),array());
    $mod = $p->get("module");
    $mod=array_stripslashes($mod);
    if(is_array($mod)) {
      $all= array_merge((array)$this->_module,$mod);
    } else {
      $all = $this->_module;
    }
    //$this->_options->setValues($this->_module, $all);
    $this->_module=(object)$all;
  }
  public function istep1() {
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','comment'),'comment','ttext',array('compulsory'=>true,'rows'=>2,'cols'=>'40'));
  }

  // fonction de fin
  function iend($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    global $XSHELL;
    $module=(array)$this->_module;
    $message = $p->get('message');
    $moid=$this->setNewMoid();
    $json=\Seolan\Core\Options::rawToJSON($module,TZR_ADMINI_CHARSET); 
    if(isset($module['comment'])){
      \Seolan\Core\Labels::updateAMsg('module:'.$moid.':comment',$module['comment']);
    }
    \Seolan\Core\Labels::updateAMsg('module:'.$moid.':modulename',$module['modulename']);
    // ?
    if (empty($this->_moid)){
      $this->_moid = \Seolan\Core\Module\Module::getToidFromClassname(static::getModuleClassname(get_class($this)));
    }
    getDB()->execute("INSERT INTO MODULES(MOID,TOID,MODULE,MPARAM) values (?,?,?,?) ",
                     array($moid, $this->_moid, $module['modulename'], $json));
    // Un nouveau module a été créé, le cache doit être raffraichit
    \Seolan\Core\Module\Module::clearCache();
    // Construction du message de retour
    $XSHELL->tpldata['wd']['message'] = $message.'<br>Installation end';
    $XSHELL->tpldata['wd']['isend'] = '1';
    // Raffraichit la liste des modules dans le menu
    setSessionVar('_reloadmenu',1);
    setSessionVar('_reloadmods',1);
    // Réinitialise le wizard
    clearSessionVar('ModWd');
    \Seolan\Core\DbIni::clear('modules%');
    return $moid;
  }
  function quickCreate($modulename, $options) {
    if(isset($options['selectedmoid']))
      $this->_selectedMoid = $options['selectedmoid'];
    $moid=$this->setNewMoid();
    $toid = \Seolan\Core\Module\Module::getToidFromClassname(static::getModuleClassname(get_class($this)));
    if(!empty($toid)) {
      $options['modulename']=$modulename;
	
      $json=\Seolan\Core\Options::rawToJSON($options,TZR_ADMINI_CHARSET);
      if(isset($options['comment'])){
	\Seolan\Core\Labels::updateAMsg('module:'.$moid.':comment',$options['comment']);
      }
      \Seolan\Core\Labels::updateAMsg('module:'.$moid.':modulename',$modulename);
      getDB()->execute("INSERT INTO MODULES(MOID,TOID,MODULE,MPARAM) values (?,?,?,?) ",[$moid, $toid, $modulename, $json]);    
      return $moid;
    }
    return null;
  }
  function color_selector($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $name=$p->get("fieldname");
    $style=$p->get("style");
    $t="<input type=\"text\" size=\"20\" name=\"$name\"/>";
    return $t;
  }
  function yes_no_selector($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $fname=$p->get("fieldname");
    return "<input type=\"checkbox\" name=\"$fname\" $value=\"1\"/>";
  }
  function createFields(&$x, $ar1) {
    for($i=0;$i<count($ar1);$i++) {
      $ar=array();
      $ar["field"]=$ar1[$i][0];
      $ar["ftype"]=$ar1[$i][1];
      $ar["fcount"]=$ar1[$i][2];
      $ar["forder"]=($i+1);
      $ar["compulsory"]=$ar1[$i][3];
      $ar["queryable"]=$ar1[$i][4];
      $ar["browsable"]=$ar1[$i][5];
      $ar["translatable"]=$ar1[$i][6];
      $ar["multivalued"]=$ar1[$i][7];
      $ar["published"]=$ar1[$i][8];
      $lg = TZR_DEFAULT_LANG;
      $ar["label"][$lg]=$ar1[$i][9];
      $ar["label"]['GB']=$ar1[$i][10];
      $ar["target"]=$ar1[$i][11];
      if($ar1[$i][12]!='') {
	$this->_module[$ar1[$i][12]]=$ar1[$i][0];
      }
      $ar['_todo']="save";
      $x->procNewField($ar);
    }
  }
  /// retourne la classe du module à partir de la classe du wizard
  static protected function getModuleClassname($wizardClassname){
    return \Seolan\Core\Module\Module::getModuleClassnameFromWizard($wizardClassname);
  }
}
