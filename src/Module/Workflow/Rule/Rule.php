<?php
namespace Seolan\Module\Workflow\Rule;
class Rule extends \Seolan\Module\Table\Table{
  function __construct($ar=NULL){
    parent::__construct($ar);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['insertRule']=array('rw','rwv','admin');
    $g['procInsertRule']=array('rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Prépare l'insertion d'une nouvelle règle
  function insertRule($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $amoid=$p->get('amoid');
    $atemplate=$p->get('atemplate');
    $mod=\Seolan\Core\Module\Module::objectFactory($amoid);
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$mod->editSelection($ar);
    $ret['amoid']=$amoid;
    $ret['atemplate']=$atemplate;
    $ret['_rule']=$this->xset->input(array('tplentry'=>TZR_RETURN_DATA,'options'=>array('q'=>array('filter'=>'modid="'.$amoid.'"'))));
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Insère une nouvelle règle
  function procInsertRule($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $amoid=$p->get('amoid');
    $ar['amoid']=$amoid;
    if(!empty($ar)) $param=array_merge($ar,$_POST);
    else $param=$_POST;
    $ar['p']=serialize($param);
    return $this->procInsert($ar);
  }

  /// Retourne 0 ou 1 selon si le module $moid possede au moins une regle
  static function hasRule($moid){
    return getDB()->count('select count(*) from _RULES where modid="'.$moid.'" limit 1');
  }

  /// Applique les regles d'un module sur un oid
  static function applyRules($mod,$oid){
    if(!\Seolan\Core\System::tableExists('_RULES')) return;
    if(!is_object($mod)) $mod=\Seolan\Core\Module\Module::objectFactory($mod);
    $rs=getDB()->select('select _RULES.*,QUERIES.query from _RULES left outer join QUERIES on _RULES.q=QUERIES.KOID where _RULES.modid="'.$mod->_moid.'" and '.
		    '_RULES.PUBLISH=1');
    while($ors=$rs->fetch()){
      $q=$mod->procQuery(array('_storedquery'=>$ors['q'],'getselectonly'=>true,'_options'=>array('local'=>1)));
      $q=str_ireplace(' where ',' where '.$mod->table.'.KOID="'.$oid.'" and ',$q);
      $rs2=getDB()->select($q);
      if($rs2->rowCount()){
	$ar=unserialize($ors['p']);
	$ar['editbatch']=true;
	$ar['oid']=$oid;
	$ar['_options']['local']=true;
	$ar['applyrules']=false;
	$ar['_logname']='rule';
	$mod->procEdit($ar);
      }
    }
  }
}
?>