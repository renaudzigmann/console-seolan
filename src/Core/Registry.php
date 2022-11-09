<?php

namespace Seolan\Core;
/*
La base des registres est un lieu de stockage de l’information
exploité uniquement par programme. Il ne s’agit pas d’information
temporaire, mais d’information stockées par des programmes et pour des
programmes. Ni l’utilisateur, ni l’administrateur n’ont accès à cette
information depuis le backoffice.   

Documentation : https://docs.seolan.com/registry.html
 */
class Registry {

  static $_instance;
  static public $upgrades=['20210506'=>''];
  
  public function __construct() {
  }

  static function getInstance() {
    if (empty(self::$_instance)) {
      self::$_instance = new Registry();
    }
    return self::$_instance;
  }

  static function createStructure() {
    if(\Seolan\Core\System::tableExists('_REGISTRY')) return false;
    
    \Seolan\Library\Upgrades::addTable("_REGISTRY", [TZR_DEFAULT_LANG=>"Registre", "GB"=>"Registry"], []);
    $fields =
      [
       ["field"        => "regmoid",
	"label"        => "Module",
	"ftype"        => "\Seolan\Field\Module\Module",
	"fcount"       => 0,
	"forder"       => 1,
	"compulsory"   => 0,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "reguseroid",
	"label"        => "Utilisateur",
	"ftype"        => "\Seolan\Field\User\User",
	"fcount"       => 0,
	"forder"       => 2,
	"compulsory"   => 0,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "regkoid",
	"label"        => "Objet",
	"ftype"        => "\Seolan\Field\Link\Link",
	"fcount"       => 0,
	"forder"       => 3,
	"compulsory"   => 0,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "%",
	"options"      => []],
       ["field"        => "regcategory",
	"label"        => "Catégorie",
	"ftype"        => "\Seolan\Field\ShortText\ShortText",
	"fcount"       => 20,
	"forder"       => 5,
	"compulsory"   => 1,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "regvalue",
	"label"        => "Valeur",
	"ftype"        => "\Seolan\Field\Serialize\Serialize",
	"fcount"       => 80,
	"forder"       => 6,
	"compulsory"   => 0,
	"queryable"    => 0,
	"browsable"    => 0,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       
       ];
    \Seolan\Library\Upgrades::addFields("_REGISTRY", $fields);
  }

  private function _buildCond($moid=NULL, $user=NULL, $koid=NULL, $category="default") {
    $params[":regmoid"]=$moid;
    $params[":reguseroid"]=$user;
    $params[":regkoid"]=$koid;
    $params[":regcategory"]=$category;
    $condSql="regmoid=:regmoid and reguseroid=:reguseroid and regkoid=:regkoid and regcategory=:regcategory";
    
    return [$condSql, $params];
  }

  function moduleReady() {
    if(!\Seolan\Core\System::tableExists('_REGISTRY')) return false;
    else return true;
  }

  function cleanForUser($useroid) {
    if(!$this->moduleReady()) return false;
    getDB()->execute("DELETE FROM _REGISTRY WHERE reguseroid=:user",[":user"=>$useroid]);
    return true;
  }

  function set($moid, $user, $koid, $category, $value) {
    if(!$this->moduleReady()) return "failed";
    
    list($condSql, $params)=$this->_buildCond($moid, $user, $koid, $category);
    
    if(getDB()->fetchOne("SELECT 1 FROM _REGISTRY WHERE $condSql", $params)) {
      $params[":regvalue"]=json_encode($value);
      getDB()->execute("UPDATE _REGISTRY SET regvalue=:regvalue WHERE $condSql", $params);
      return "updated";
    } else {
      $params[":koid"]=\Seolan\Core\Datasource\Datasource::getNewBasicOID("_REGISTRY");
      $params[":lang"]=TZR_DEFAULT_LANG;
      $params[":regmoid"]=$moid;
      $params[":reguseroid"]=$user;
      $params[":regkoid"]=$koid;
      $params[":regcategory"]=$category;
      $params[":regvalue"]=json_encode($value);
      getDB()->execute("insert into _REGISTRY (KOID, LANG, regmoid, reguseroid, regkoid, regcategory, regvalue) ".
		       " values (:koid, :lang, :regmoid, :reguseroid, :regkoid, :regcategory, :regvalue)", $params);
      return "inserted";
    }
    return "failed";
  }
  function get($moid, $user, $koid, $category) {
    if(!$this->moduleReady()) return NULL;

    list($condSql, $params)=$this->_buildCond($moid, $user, $koid, $category);
    $value=getDB()->fetchOne("SELECT regvalue FROM _REGISTRY WHERE $condSql", $params);
    if($value) return json_decode($value);
    else return NULL;
  }
}
