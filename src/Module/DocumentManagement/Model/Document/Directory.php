<?php
/// Classe de base d'un document container (repertoire/dossier) integre dans la base documentaire (\Seolan\Module\DocumentManagement\DocumentManagement)
namespace Seolan\Module\DocumentManagement\Model\Document;

/// Classe de base d'un document container (repertoire/dossier) integre dans la base documentaire (\Seolan\Module\DocumentManagement\DocumentManagement)
class Directory extends \Seolan\Module\DocumentManagement\Model\Document\Document {
  public $documents=array();
  public $directories=array();

  function __construct($oid, $ors, &$docmgt, $ar=NULL) {
    parent::__construct($oid, $ors, $docmgt, $ar);
    $this->node=true;
    $this->comment=$this->getDescription();
    
    $this->icon = (Object)['url'=>null,'html'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','directorybig','csico')];
    $this->smallicon=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','defaultsdir', 'csico');
    // à voir - jtree / images versus css icon (index3 par exemple)
    $this->smalliconurl='/csx/public/jtree/close.png';
  }

  /// la gestion des types possibles peut etre modifiee avec allowedChildType
  function getNewTypes($docs=false, $dirs=false) {
    if ($docs || $dirs){
      $phrase = '';
      $exp = $this->tpl['ocrules']->raw;
      $l = preg_split('/$/m', $exp);
      $convs = array();		/* tableau des associations entre symboles et lettres simples */
      $exps = array();		/* tableau des expressions à vérifier */
      foreach($l as $foo=>$c){
	$c = trim($c);
	if (empty($c)) continue;
	/* si la chaine commence par un : c'est une affectation */
	$f = strpos($c, ':');
	if ($f !== false && $f == 0) {
	  $c1 = substr($c, 1);
	  list($symbol, $letter) = explode('=', $c1);
	  $convs[$symbol] = $letter;
	} else {
	  $exps[] = $c;
	}
      }
      $childsTypes = array();	/* tableau des types de descendants */
      foreach($this->documents as $oiddoc=>$cdoc){
	$s = $cdoc->tpl['osymbol']->raw;
	if (isset($convs[$s])) $s = $convs[$s];
	$childsTypes[] = $s;
      }
      if(is_array($this->directories)) {
	foreach($this->directories as $oiddoc=>$cdoc){
	  $s = $cdoc->tpl['osymbol']->raw;
	  if (isset($convs[$s])) $s = $convs[$s];
	  $childsTypes[] = $s;
	}
      }
    }

    // calcul d'une expression régulière complète
    $exp = '';
    foreach($exps as $foo=>$e){
      if($exp != '') $exp .= '|'.$e;
      else $exp.=$e;
    }
    // si pas d'expression, on autorise tout
    if(empty($exp)) $exp=".*";

    if($docs) {
      $typesname=array();
      $dtypes=$this->module->getTypes();
      foreach($dtypes as $i=>&$tpl) {
	$childsTypes2 = $childsTypes;
	$s = $tpl['osymbol']->raw;
	if (isset($convs[$s])) $s = $convs[$s];
	else $s='x';
	$childsTypes2[] = $s;
	sort($childsTypes2);
	$phrase2 = implode($childsTypes2);
	/// BUG: childs n'existe pas
	if($tpl['onode']->raw==2 && preg_match('/^('.$exp.')$/', $phrase2) && $this->allowedChildType($tpl, $childs) && $tpl['_rwsecure']) {
	  $this->newdocs[$i]=$tpl;
	  $typesname[$i]=$tpl['otitle']->raw;
	}
      }
       array_multisort($typesname,$this->newdocs);
    }
    if($dirs) {
      $typesname=array();
      $oidtoprotect=$this->module->protectedOids();
      $dtypes=&$this->module->getTypes();
      foreach($dtypes as $i=>&$tpl) {
	if(!in_array($tpl['oid'], $oidtoprotect)) {
	  $childsTypes2 = $childsTypes;
	  $s = $tpl['osymbol']->raw;
	  if (isset($convs[$s])) $s = $convs[$s];
	  else $s='x';
	  $childsTypes2[] = $s;
	  sort($childsTypes2);
	  $phrase2 = implode($childsTypes2);
	  /// BUG: childs n'existe pas
	  if($tpl['onode']->raw==1 && preg_match('/^('.$exp.')$/i', $phrase2) && $this->allowedChildType($tpl, $childs) && $tpl['_rwsecure']) {
	    $this->newdirs[$i]=&$tpl;
	    $typesname[$i]=$tpl['otitle']->raw;
	  }
	}
      }
      array_multisort($typesname,$this->newdirs);
    }
  }

  /// types possibles pour un dossier des modeles
  function getNewTypesM($docs=false, $dirs=false) {
    $dtypes=&$this->module->getTypes();
    if($docs) {
      foreach($dtypes as $i=>&$tpl) {
	if($tpl['onode']->raw==2 && $tpl['_rwsecure']) {
	  $this->newdocs[$i]=&$tpl;
	}
      }
    }
    if($dirs) {
      foreach($dtypes as $i=>&$tpl) {
	if($tpl['onode']->raw==1 && $tpl['_rwsecure']) {
	  $this->newdirs[$i]=&$tpl;
	}
      }
    }
  }
  /// controle sur les regles du type : ne doit pas pouvoir etre surchargee
  private function _allowedChildType($csymbol, $phrase){
    return true;
  }
  protected function allowedChildType($csymbol) {
    return true;
  }
  function getDocumentsDetails($oids=NULL) {
    if(!empty($this->documents)) return;
    if(empty($this->docsoids)) return;
    $order=$this->getOption('fileorder');
    list($order,$direction)=explode(' ',$order);
    // recherche des documents qui sont dans ce repertoire
    foreach($this->docsoids as $oid) {
      if(empty($set)) $set="'".$oid."'";
      else  $set.=",'".$oid."'";
    }

    $rs=getDB()->select('SELECT * FROM '.$this->module->idx.','.$this->module->id.',_TYPES where KOIDSRC in ('.$set.') '.
		     ' and KOIDSRC='.$this->module->id.'.KOID and node=2 and '.$this->module->id.'.DTYPE=_TYPES.KOID/*2*/');
    if($rs) {
      $this->documents=array();
      $documents=array();
      $tmporder=array();	/* tableau temporaire construit pour le tri */
      $tmporder2=array();	/* tableau temporaire construit pour le tri */

      while($ors=$rs->fetch()) {
	if(!empty($ors['KOIDSRC'])) {
	  // voir si document sur xdatasource et pas module -> false toujours ?
	  $seenhere=in_array($ors['KOIDSRC'], $this->docsoids);
	  if(!$seenhere) continue;
	  $doc1 = &\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($ors['KOIDSRC'], $this->module, $ors);
          // Si l'objet n'est pas accessible (dépublié par exemple)
          if (!is_object($doc1)) continue;
	  $doc1->getActions();
	  $documents[$ors['KOIDSRC']]=$doc1;
	  $this->documents[$ors['KOIDSRC']]=NULL;
	  if(isset($documents[$ors['KOIDSRC']]->fields['o'.$order])) {
	    $tmporder[$ors['KOIDSRC']]=$documents[$ors['KOIDSRC']]->fields['o'.$order]->raw;
	  } elseif($order=='doctype') {
	    $tmporder[$ors['KOIDSRC']]=$documents[$ors['KOIDSRC']]->tpl['osymbol']->raw;
	  } else {
	    $tmporder[$ors['KOIDSRC']]=$documents[$ors['KOIDSRC']]->title;
	  }
	  $tmporder2[$ors['KOIDSRC']]=$documents[$ors['KOIDSRC']]->title;
	}
      }
    }
    if($direction=='DESC') array_multisort($tmporder, SORT_DESC, SORT_LOCALE_STRING, $tmporder2, $this->documents);
    else array_multisort($tmporder, SORT_ASC, SORT_LOCALE_STRING, $tmporder2, $this->documents);
    foreach($this->documents as $key=>&$val)
      $val=$documents[$key];
  }

  function getDirectoriesDetails($level=1) {
    if(!empty($this->directories)) return;
    if(empty($this->dirsoids)) return;
    $order=$this->getOption('directoryorder');
    @list($order,$direction)=explode(' ',$order);
    
    // recherche des documents qui sont dans ce repertoire
    $set="";
    foreach($this->dirsoids as $oid) {
      if(empty($set)) $set="?";
      else  $set.=",?";
    }
    $rs=getDB()->fetchAll('SELECT * FROM '.$this->module->idx.','.$this->module->id.',_TYPES where KOIDSRC in ('.$set.') '.
			  ' and KOIDSRC='.$this->module->id.'.KOID and '.$this->module->id.'.DTYPE=_TYPES.KOID/*1*/', $this->dirsoids);

    $this->directories=array(); /* tableau des documents tries */
    $directories=array();       /* tableau des documents non tries */
    $tmporder=array();	  /* tableau temporaire construit pour le tri */
    $tmporder2=array();	  /* tableau temporaire construit pour le tri */
    foreach($rs as $ors) {
      if(!empty($ors['KOIDSRC'])) {
	if(!$this->module->secure1($ors['KOIDSRC'], 'index',$ors['node'])) continue;
	$doc1 = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($ors['KOIDSRC'], $this->module, $ors);
	// Si l'objet n'est pas accessible (dépublié par exemple)
	if (!is_object($doc1)) continue;
	$doc1->parents[]=&$this;
	$doc1->parentsoids[]=$this->oid;
	$this->directories[$ors['KOIDSRC']]=NULL;
	$directories[$ors['KOIDSRC']]=$doc1;
	$directories[$ors['KOIDSRC']]->getContent();
	if($level>0) {
	  $directories[$ors['KOIDSRC']]->getDirectoriesDetails($level-1);
	}
	if(isset($directories[$ors['KOIDSRC']]->fields['o'.$order])) {
	  $tmporder[$ors['KOIDSRC']]=$directories[$ors['KOIDSRC']]->fields['o'.$order]->raw;
	} elseif($order=='doctype') {
	  $tmporder[$ors['KOIDSRC']]=$directories[$ors['KOIDSRC']]->tpl['osymbol']->raw;
	} else {
	  $tmporder[$ors['KOIDSRC']]=$directories[$ors['KOIDSRC']]->title;
	  }
	$tmporder2[$ors['KOIDSRC']]=$directories[$ors['KOIDSRC']]->title;
      }
      if($direction=='DESC') array_multisort($tmporder, SORT_DESC, SORT_LOCALE_STRING, $tmporder2, $this->directories);
      else array_multisort($tmporder, SORT_ASC, SORT_LOCALE_STRING , $tmporder2, $this->directories);
      foreach($this->directories as $key=>&$val)
	$val=$directories[$key];
    }
  }
  // notice du repertoire pour moteur de recherche
  // le reste = document (si fichiers ils sont pris)
  function getSearchEngineNotice(){
    $notice = parent::getSearchEngineNotice();
    if (isset($this->fields['oremark']) && is_object($this->fields['oremark']) && (!$this->fields['oremark']->fielddef->indexable)){
      $notice = $this->fields['oremark']->text.' '.$notice;
    }
    return $notice;
  }
  // ajout d'un descendant
  //
  function addChild($oid) {
    $q="SELECT COUNT(*) FROM {$this->module->idx} WHERE KOIDSRC=? AND KOIDDST=?";
    $rs=getDB()->select($q, array($oid, $this->oid));
    $ors=$rs->fetch();
    if($ors['COUNT(*)']<=0) {
      $q='INSERT INTO '.$this->module->idx.' SET KOIDSRC=?, KOIDDST=?';
      getDB()->execute($q, array($oid, $this->oid));
    }
  }
}
