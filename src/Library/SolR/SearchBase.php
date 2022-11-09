<?php
namespace Seolan\Library\SolR;

use \Seolan\Core\{DbIni,Ini,Logs,Shell};
use \Seolan\Core\Module\Module;

/**
 * classe de base pour la recherche => factory, swap v2 etc  
 */
abstract class SearchBase {
  static private $instanceClassname=null;
  static $singleton=NULL;
  /// securite des fonctions accessibles par le web
  static function secGroups($function, $group=NULL) {
    $g=[];
    $g['checkIndex']=array('admin');
    $g['globalSearch']=array('none');
    $g['portlet']=array('none');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return false;
  }
  /// sélection de la bonne version du moteur
  static function objectFactory($ar=NULL){
    
    if(self::$singleton==NULL){
      $instanceClassname = static::instanceClassname();
      self::$singleton = new $instanceClassname();
    }
    \Seolan\Core\Logs::notice(__METHOD__,get_class(self::$singleton));
    return self::$singleton;
    
  }
  /// Genere un identifiant à partir de l'oid, du moid et d'une langue
  protected function tzrid($oid,$moid,$lang=NULL){
    if($lang==NULL) $lang=Shell::getLangData();
    return $lang.'|'.$oid.'|'.$moid;
  }
  /**
   * retourne la classe à utiliser pour l'instance
   * on est en V2 si le module Search est en place
   * si le module est en place, normalement il est configuré
   * et le coeur a été créé
   */
  public static function instanceClassname(){
    if (static::$instanceClassname==null){
      $modsearch = Module::singletonFactory(XMODSEARCH_TOID);
      if (empty($modsearch)) {
	static::$instanceClassname = $GLOBALS['TZR_SEARCH_MANAGER'];
      } else {
	static::$instanceClassname = $GLOBALS['TZR_SEARCH_MANAGER2'];
      }
    }
    return static::$instanceClassname;
  }
  /// retourne les paramètres pour contruire la boite de recherche
  static public function portlet($ar=null){
    return Shell::toScreen2('search', 
			    'portlet', 
			    [
			      'instanceClassname'=>static::instanceClassname(),
			      'template'=>'Library/SolR.portlet.html', 
			      'userguide'=>['url'=>TZR_USERGUIDE_URL.'Components/Search.html'],
			      'filters'=>[['checked'=>true, 
					   'label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Library_SolR_SolR', 'filter_tags')],
					  ['checked'=>true, 
					   'label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Library_SolR_SolR', 'filter_users')],
					  ['checked'=>true, 
					   'label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Library_SolR_SolR', 'filter_content')]],
			      'tagPrefix'=>\Seolan\Field\Tag\Tag::$TAG_PREFIX]);
  }
  // solr v1 ou 2 activé
  public static function solrActive(){
    return ((Ini::get('solr_activated') == 1)
	 || SearchV2::getSolrConfiguration(false)->active);
  }
}

