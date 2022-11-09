<?php
/// classe a tout faire heritee. Sans doute a supprimer dans le futur. Utilisee principalement avec des methodes statiques

namespace Seolan\Core;

class Kernel {
  public $table = '';
  public $desc = [];
  public $debug = 0;

  function __construct() {
  }


  // traitement des oids
  static public function getTable(?string $oid) {
    @list($table, $suffix)=explode(':', $oid);
    if(!empty($table) && isset($suffix)) return $table;
    return NULL;
  }
  /// Verifie si une chaine est un oid simple
  static function isAKoid(?string $t) {
    return isset($t) &&  preg_match('@^([_a-z0-9]+)\:(\*|[a-z0-9_-]+)$@i',$t);
  }

  /// Verifie si une chaine represente un oid (simple ou multiple)
  static function isAMultipleKoid(?string $t) {
    return isset($t) && preg_match('@^((\|\|)?([_a-z0-9]+)\:(\*|[a-z0-9_-]+)(\|\|)?)+$@Ui',$t);
  }

  /** rend vrai si la donnee d'oid $koid existe dans la langue specifiee
   $xx est la connection bd s'il en existe une a utiliser
  */
  static function objectExists(?string $koid, string $klang='%') {

    $table = \Seolan\Core\Kernel::getTable($koid);
    if (empty($table)) return false;

    if($klang=='%')
      $requete = 'SELECT DISTINCT KOID FROM '.$table.' where KOID=?';
    else
      $requete = 'SELECT DISTINCT KOID FROM '.$table.' where KOID=? and LANG = "'.$klang.'"';
    return getDB()->fetchExists($requete,array($koid));
  }

  /// rend vrai si la donnee d'oid $koid existe dans la langue specifiee
  static function objectExists2(?string $koid, string $klang='%') {
    $table = \Seolan\Core\Kernel::getTable($koid);
    if (empty($table)) return false;
    $cond='';
    if(\Seolan\Core\User::isNobody() && fieldExists($table, 'PUBLISH')) $cond=' AND PUBLISH=1 ';
    if(TZR_USE_APP && fieldExists($table, 'APP') && !\Seolan\Core\Shell::isRoot()) {
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      if($bootstrapApplication && $bootstrapApplication->oid) {
        $condapp = $table.'.APP="'.$bootstrapApplication->oid.'" ';
        $cond = $cond ? $cond.' AND '.$condapp : $condapp;
      }
    }
    $requete = 'SELECT KOID FROM '.$table.' WHERE KOID=? AND LANG like ? '.$cond;
    return getDB()->fetchOne($requete,array($koid, $klang));
  }

  function data_duplicate(string $koidSource, string $langSource, string $langDest, $koidDest=null) {
    // si la donnee existe deja on ne la duplique pas
    if ($koidDest == null){
      if($this->objectExists($koidSource, $langDest)){
	return;
      }
    } else {
      if($this->objectExists($koidDest, $langDest)){
	return;
      }
    }

    // obtention de la table a partir du koid
    if ( $this->table == "" ) $this->table = \Seolan\Core\Kernel::getTable($koidSource);
    // sélection de la donnee de base
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $requete = "SELECT * FROM ".$this->table." where KOID=? and LANG=?";

    $ors=getDB()->fetchRow($requete, array($koidSource,$langSource));
    if ($ors) {
      // en duplication de fiche les bonnes valeurs des champs non trad
      // sont dans l'enregistrement nouvellement créé
      if ($koidDest != null)
        $drs=getDB()->fetchRow($requete, array($koidDest,TZR_DEFAULT_LANG));
      $query = 'INSERT INTO '.$this->table.' (';
      $fields='KOID,LANG';
      if ($koidDest == null){
	$values="'$koidSource','$langDest'";
      } else {
	$values="'$koidDest','$langDest'";
      }
      $inputvalues=[];
      foreach($x->desc as $k => $v) {
	if(isset($k)) {
	  $fields .= ','.$k; 
	  if ($v->translatable) // par défaut, retourne la valeur transmise
	    $inputvalues[]=$v->data_duplicate($ors[$k],$langSource,$langDest,true);
	   elseif ($koidDest==null)
	    $inputvalues[]=$ors[$k]; 
	  else
	    $inputvalues[]=$drs[$k]; 
	  $values=$values.',?';
	}
      }
      // execution de la requete
      getDB()->execute($query.$fields.') values ('.$values.')', $inputvalues);
    }
  }

  function data_autoTranslate(string $koidSource, string $lang='*') {
    global $XLANG;
    if(($lang!=TZR_DEFAULT_LANG)&&($lang!='*')) {
      $this->data_duplicate($koidSource,TZR_DEFAULT_LANG,$lang);
    } else {
      $XLANG->getCodes();
      for ( $myi=0; $myi<$XLANG->nbLang; $myi++ ) {
	if ( $XLANG->codes[$myi] != TZR_DEFAULT_LANG )
	  $this->data_duplicate($koidSource,TZR_DEFAULT_LANG,$XLANG->codes[$myi]);
      }
    }
  }

  /// Retourne les oids en cours passés via les variables _selected ou oid
  static function getSelectedOids($p=NULL, bool $keys=true, bool $forcearray=true){
    if(empty($p)) $p=new \Seolan\Core\Param([]);
    elseif(is_array($p)) $p=new \Seolan\Core\Param($p);
    $selectedok=$p->get('_selectedok');
    $oids=[];
    if($selectedok=='ok'){
      $oids=$p->get('_selected');
      if($keys && is_array($oids)) $oids=array_keys($oids);
    }
    if($selectedok!='ok' || empty($oids)) $oids=$p->get('oid');
    if($forcearray && !is_array($oids)) $oids=array($oids);
    return $oids;
  }


  /// suppression d'un objet
  static function delObject(string $oid) {
    \Seolan\Core\Kernel::data_forcedDel(array('oid'=>$oid));
  }

  /// Efface des oids
  // RZ verifier si on ne peut pas se reduire à $oid en parametre avec string et pas array
  static function data_forcedDel($ar=NULL) {
    $oid=$ar['_selected']??NULL;
    $nolog=$ar['_nolog']??false;
    $mytable=$ar['_mytable']??'';
    $selectedok=$ar['_selectedok']??false;
    $mode='';
    $ret = array('oid' => []);
    $LANG_DATA=\Seolan\Core\Shell::getLangData();
    if($selectedok!='ok' || empty($oid)) $oid=$ar['oid'];
    if(!is_array($oid)) $oid=array($oid=>1);
    foreach($oid as $k=>$v) {
      if(!$mode){
	if(\Seolan\Core\Kernel::isAKoid($k)) $mode='k';
	elseif(\Seolan\Core\Kernel::isAKoid($v)) $mode='v';
      }
      if($mode=='v'){
	$k=$v;
	$v=1;
      }
      if(!empty($v)) {
        if(!empty($mytable) && \Seolan\Core\Kernel::getTable($k)!=$mytable) bugWarning('\Seolan\Core\Kernel::data_forcedDel: Trying to delete '.$k.' with wrong \Seolan\Core\DataSource\DataSource');
        \Seolan\Core\Kernel::data_sqlDel($k,$LANG_DATA,$nolog);
        $ret['oid'][] = $k;
	getDB()->execute('DELETE FROM OPTS WHERE user=? OR specs like ?',array($k,'%'.$k.'%'));
        \Seolan\Core\Module\Module::removeRegisteredOid($k);
      }
    }
    return $ret;
  }

  /// Efface des oids de la base sql
  static function data_sqlDel(string $oid,string $lang,$nolog=false) {
    $st=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oid);
    $tolog = $st->toLog() && empty($nolog);
    $table=\Seolan\Core\Kernel::getTable($oid);
    if(empty($nolog)) $d=$st->rDisplayText($oid,$lang);
    if($lang==TZR_DEFAULT_LANG || !$st->isTranslatable()) {
      getDB()->execute('DELETE FROM '.$table.' where KOID=?', array($oid));
      getDB()->execute('DELETE FROM ACL4 where AKOID=?', array($oid));
      if($tolog) \Seolan\Core\Logs::secEvent(__METHOD__,"Delete object ($oid) and related ACL", $oid);
    } else {
      getDB()->execute('DELETE FROM '.$table.' where KOID=? and LANG=?', array($oid, $lang));
      getDB()->execute('DELETE FROM ACL4 where AKOID=? and ALANG=?', array($oid, $lang));
      if($tolog) \Seolan\Core\Logs::secEvent(__METHOD__,"Delete object ($oid,$lang) and related ACL", $oid);
    }
    if($table=='USERS') getDB()->execute('DELETE FROM ACL4_CACHE where AGRP=?', array($oid));
    if($table=='GRP') getDB()->execute('DELETE FROM ACL4_CACHE where AGRP=?',array($oid));
    if($tolog) \Seolan\Core\Logs::update('delete',NULL,$d['link'].' ('.$oid.')');
  }


  public function baseSelector($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>''));
    $tplentry = $p->get('tplentry');
    $LANG_USER = $p->get('LANG_USER');
    $name=$p->get('fieldname');
    $value=$p->get('value');
    $emptyok=$p->get('emptyok');
    $misc=$p->get('misc');
    $rs=getDB()->fetchAll('select * from BASEBASE,AMSG where AMSG.MOID=BASEBASE.BOID and '.
		    '(AMSG.MLANG=? or AMSG.MLANG=?) order by AMSG.MTXT, AMSG.MLANG desc', array($LANG_USER,TZR_DEFAULT_LANG));
    $retval="<select name=\"$name\" $misc >";
    if($emptyok) $retval.="<option value=\"0\">---</option>";
    $ors=[];
    $oldt='';
    foreach($rs as $ors) {
      $t = $ors['BTAB'];
      if($t!=$oldt) {
	if($value==$t) $s='selected'; else $s='';
	$retval.="<option value=\"$t\" $s>".$ors['MTXT'].'</option>';
	$oldt=$t;
      }
    }
    unset($rs);
    return $retval.'</select>';
  }

  /// on suit les liens à partir d'un lien ou d'un ensemble de liens
  static function followLinksUp($links_table, $limits=[], $include_orig=true, $maxNb=1000) {
    $final_links = [];
    if (empty($links_table)) $links_table = [];
    if(!is_array($links_table)) $links_table=array($links_table);
    $limit=0;
    while(!empty($links_table) && ($limit<$maxNb)) {
      $limit++;
      $link = array_pop($links_table);
      if(in_array($link, $final_links)) continue;
      array_push($final_links, $link);
      $table=\Seolan\Core\Kernel::getTable($link);
      $rs=getDB()->fetchAll("select DTAB,FIELD from DICT where TARGET = ?",array($table));
      foreach($rs as $o) {
	if(empty($limits) || (is_array(@$limits[$o['DTAB']]) && in_array($o['FIELD'],$limits[$o['DTAB']]))) {
	  $rs2=getDB()->fetchAll('select KOID from '.$o['DTAB'].' where '.$o['FIELD'].' like "%|'.$link.'|%" or '.$o['FIELD'].'=?',
				 array($link));
	  foreach($rs2 as $o2) {
	    if(!in_array($o2['KOID'], $final_links))  array_push($links_table, $o2['KOID']);
	  }
	  unset($rs2);
	}
      }
      unset($rs);
    }
    return $final_links;
  }

  /// on suit les liens à partir d'un lien ou d'un ensemble de liens pour obtenir l'ensemble des objets
  static function followLinks($links_table,$limits=[], $include_orig=true) {
    static $cache = [];
    $cacheidx=NULL;
    if(count($links_table)<=2) {
      $cacheidx = implode('',$links_table).$include_orig;
      if(isset($cache[$cacheidx])) {
	return $cache[$cacheidx];
      }
    }
    $lang = \Seolan\Core\Shell::getLangData();
    $final_links = [];
    $datasource_cache=[];
    while(!empty($links_table)) {
      $link = array_pop($links_table);
      if(in_array($link, $final_links)) continue;
      $table = \Seolan\Core\Kernel::getTable($link);
      if(!\Seolan\Core\DataSource\DataSource::sourceExists($table)) continue;
      if(empty($datasource_cache[$table])) {
	$datasource_cache[$table]['datasource']=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('&SPECS='.$table);
	$xs=&$datasource_cache[$table]['datasource'];
	$datasource_cache[$table]['fields']=$xs->getXLinkDefs($limits[$table]);
	if(empty($datasource_cache[$table]['fields']))
	  $datasource_cache[$table]['fieldlist']='';
	else
	  $datasource_cache[$table]['fieldlist']=','.implode(',', $datasource_cache[$table]['fields'])??'';
      }
      $xs=&$datasource_cache[$table]['datasource'];
      $fields=&$datasource_cache[$table]['fields'];
      $fieldlist=$datasource_cache[$table]['fieldlist'];
      
      // recherche de l'enregistrement
      if($xs->isTranslatable())
	$o=getDB()->fetchRow("select KOID,LANG$fieldlist from $table where KOID=? and LANG=?", [$link,$lang]);
      else
	$o=getDB()->fetchRow("select KOID,LANG$fieldlist from $table where KOID=?", [$link]);
      if($o) {
	array_push($final_links, $link);
	if(empty($fieldlist)) continue;
	foreach($fields as $i => $field) {
	  $oids = explode('||',$o[$field] );
	  foreach($oids as $j => $oid) {
	    if(\Seolan\Core\Kernel::isAKoid($oid)) {
	      array_push($links_table, $oid);
	    }
	  }
	}
      }
    }
    if(!$include_orig) {
      $final_links = array_diff($final_links, $links_table);
    }
    if(!empty($cacheidx))
      $cache[$cacheidx]=$final_links;
    unset($datasource_cache);
    return $final_links;
  }

}
