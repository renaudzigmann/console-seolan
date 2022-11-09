<?php
namespace Seolan\Field\StringSet;
/// classe de manipulation des ensembles de valeurs
class Management{
  public $table='';
  public $boid='';
  public $xset=0;

  function __construct($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $this->boid=$p->get('boid');
    $this->table=$p->get('table');
    if(!empty($this->boid)) $this->xset=\Seolan\Core\DataSource\DataSource::objectFactory8($this->boid);
    else $this->xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->table);
    $this->boid=$this->xset->getBoid();
    $this->table=$this->xset->getTable();
  }

  /// Liste les valeurs
  public function browse($ar=NULL) {
    global $XLANG;
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $fdef=$this->xset->getField($field);
    $XLANG->getCodes();
    if(is_array($XLANG->codes)) {
      $nbLang = count($XLANG->codes);
      for($myi=0;$myi<$nbLang;$myi++) {
	$lang[$myi] = $XLANG->codes[$myi];
      }
    }
    $tset=$this->getTSet($field);
    $soid=array();
    $i=0;
    $stxt = array();
    foreach($tset as $oid=>$v) {
      $soid[$i]=$oid;
      for($myi=0;$myi<$nbLang;$myi++) $stxt[$i][$myi]=$v[$lang[$myi]];
      $i++;
    }
    $result=array();
    $result['lang']=$lang;
    $result['table']=$this->table;
    $result['boid']=$this->boid;
    $result['field']=$field;
    $result['tset_oid']=$soid;
    $result['tset_txt']=$stxt;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }

  /// Prepare l'ajout d'une valeur
  function newString($ar=NULL) {
    global $XLANG;
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $XLANG->getCodes();
    if(is_array($XLANG->codes)) {
      $nbLang=count($XLANG->codes);
      for($myi=0;$myi<$nbLang;$myi++) {
        if($XLANG->codes[$myi]==TZR_DEFAULT_LANG ) $snames_flag[$myi]=COMPULSORY_ITEM;
        else $snames_flag[$myi]=OPTIONAL_ITEM;
        $snames_lang[$myi]=$XLANG->codes[$myi];
      }
      $sorder_flag=OPTIONAL_ITEM;
      $sorder=$this->newTSetOrder($field);
    }
    $result=array();
    $result['snames_flag']=$snames_flag;
    $result['snames_lang']=$snames_lang;
    $result['sorder_flag']=$sorder_flag;
    $result['sorder']=$sorder;
    $result['table']=$this->table;
    $result['boid']=$this->boid;
    $result['field']=$field;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }

  /// Prepare l'edition d'une valeur
  public function editString($ar=NULL) {
    GLOBAL $XLANG;
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $soid=$p->get('soid');
    $XLANG->getCodes();
    $ors=getDB()->fetchRow('SELECT DISTINCT SORDER FROM SETS WHERE STAB=? ' .
			   'AND FIELD=? AND SOID=?', array($this->table,$field,$soid));
    $sorder=$ors['SORDER'];
    $tset=$this->getTSet($field);
    if(is_array($XLANG->codes)) {
      $nbLang=count($XLANG->codes);
      for($myi=0;$myi<$nbLang;$myi++) {
        if($XLANG->codes[$myi]==TZR_DEFAULT_LANG) $snames_flag[$myi]=COMPULSORY_ITEM;
        else $snames_flag[$myi]=OPTIONAL_ITEM;
        $snames_lang[$myi]=$XLANG->codes[$myi];
	$snames[$myi]=$tset[$soid][$XLANG->codes[$myi]];
	$old_snames[$myi]=$tset[$soid][$XLANG->codes[$myi]];
      }
      $sorder_flag=OPTIONAL_ITEM;
      $old_sorder=$sorder;
    }
    $result=array();
    $result['soid']=$soid;
    $result['boid']=$this->boid;
    $result['table']=$this->table;
    $result['field']=$field;
    $result['snames_flag']=$snames_flag;
    $result['snames_lang']=$snames_lang;
    $result['snames']=$snames;
    $result['old_snames']=$old_snames;
    $result['sorder_flag']=$sorder_flag;
    $result['sorder']=$sorder;
    $result['old_sorder']=$old_sorder;
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Ajoute une valeur
  function procNewString($ar=NULL) {
    global $XLANG;
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $label=$p->get('label');
    $sorder=$p->get('sorder');
    $soid=$p->get('soid');
    $error=false;

    // Controle des donnees obligatoires
    if(empty($label[TZR_DEFAULT_LANG])) {
      $message='Incorrect description ! Try again.';
      $error=true;
    }else{
      $XLANG->getCodes();
      if($this->tsetExists($field,$XLANG->codes,$label,NULL)) {
	$message='Value already exists ! Try again.';
	$error=true;
      } else {
        if (!empty($soid)) {
          if (getDB()->count('SELECT COUNT(*) FROM SETS WHERE STAB=? AND FIELD=? AND SOID=?', array($this->table,$field,$soid))) {
            $message='SOID already exists ! Try again.';
            $error=true;
          }
        }else{
          // Calcul oid des nouveaux tuples
          $soid=$this->newTSetOid($field);
        }
      }
    }
    if (!$error) {
      // Insertion d'un tuple par langue, tous avec le meme oid
      for($i=0;$i<count($XLANG->codes);$i++) {
        $theLang=$XLANG->codes[$i];
        $theTxt=$label[$theLang];
        if($theTxt!='') {
          getDB()->execute('INSERT INTO SETS (SOID,STAB,FIELD,SLANG,STXT,SORDER) values (?,?,?,?,?,?)',
                           array($soid,$this->table,$field,$theLang,$theTxt,$sorder));
        }
      }
      $message='Values created '.$soid;
    }
    \Seolan\Core\Shell::setNextData('message', $message);
  }

  /// Enregistre les modifications sur une valeur
  public function procEditString($ar=NULL) {
    global $XLANG;
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $soid=$p->get('soid');
    $label=$p->get('label');
    $oldLabel=$p->get('oldLabel');
    $sorder=$p->get('sorder');
    $oldSorder=$p->get('oldSorder');
    $error=false;

    // Controle des donnees obligatoires
    if($label[TZR_DEFAULT_LANG]=='') {
      $message='Incorrect description ! Try again.<br/>';
      $error=true;
    }else{
      $XLANG->getCodes();
      if($this->tsetExists($field,$XLANG->codes,$label,$soid)) {
	$message='Value already exists ! Try again.<br/>';
	$error=true;
      }else{
	// Modification d'un tuple par langue, tous avec le meme oid
	for($i=0;$i<count($XLANG->codes);$i++) {
	  $theLang=$XLANG->codes[$i];
	  $theTxt=$label[$theLang];
	  $theOldTxt=$oldLabel[$theLang];
	  if($theTxt!=$theOldTxt) {
	    if($theTxt!='') {
	      if($theOldTxt!='') {
		getDB()->execute('UPDATE SETS SET STXT=?,SORDER=? WHERE STAB=? AND FIELD=? AND SOID=? AND SLANG=?',
                                 array($theTxt,$sorder,$this->table,$field,$soid,$theLang));
		
	      }else{
		getDB()->execute('INSERT INTO SETS (SOID,STAB,FIELD,SLANG,STXT,SORDER) values (?,?,?,?,?,?)',
                                 array($soid,$this->table,$field,$theLang,$theTxt,$sorder));
	      }
	    }else{
	      getDB()->execute('DELETE FROM SETS WHERE STAB=? AND FIELD=? AND SOID=? AND SLANG=?',
			       array($this->table,$field,$soid,$theLang));
	    }
	  }
	}
	if($sorder!=$oldSorder) {
	  getDB()->execute('UPDATE SETS SET SORDER=? WHERE STAB=? AND FIELD=? AND SOID=?',
                           array($sorder,$this->table,$field,$soid));
	  
	}
	$message='Values modified.<br/>';
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$result=array('error'=>$error,'message'=>$message));
  }
      
  /// Supprime une valeur
  function delString($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $soid=$p->get('soid');
    $label=$p->get('label');
    getDB()->execute('DELETE FROM SETS where STAB="'.$this->table.'" AND FIELD=? and SOID=?', [$field, $soid]);
    $message='Deleted';
    return \Seolan\Core\Shell::toScreen1($tplentry,$result=array('message'=>$message));
  }

  /// Suppirme toutes les valeurs
  function clearStrings($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    getDB()->execute('DELETE FROM SETS WHERE STAB="'.$this->table.'" AND FIELD="'.$field.'"');
    $message='Deleted'; 
    return \Seolan\Core\Shell::toScreen1($tplentry,$result=array('message'=>$message));
  }

  /// Tri alphabétique des chaines
  function sortStrings($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $field=$p->get('field');
    $table=$this->table;
    $rs=getDB()->fetchAll('SELECT * FROM SETS WHERE STAB=? AND FIELD=? and SLANG=? order by STXT', 
			array($table, $field,TZR_DEFAULT_LANG));
    $seq=1;
    foreach($rs as $ors) {
      getDB()->execute('UPDATE SETS set SORDER=? WHERE SOID=? AND STAB=? AND FIELD=?', 
		       array($seq,$ors['SOID'],$table,$field));
      $seq++;
    }
    unset($rs);
  }

  /// Verifie si une valeur existe deja
  function tsetExists($field,$langs,$labels,$soid=NULL) {
    for($i=0;$i<count($langs);$i++) {
      $theLang=$langs[$i];
      $theTxt=$labels[$theLang];
      if($theTxt!=''){
	if($soid==NULL) {
          $nb=getDB()->count('SELECT COUNT(*) FROM SETS WHERE STAB=? AND FIELD=? AND SLANG=? AND '.
			     'STXT=?', array($this->table,$field,$theLang,$theTxt));
	}else{
          $nb=getDB()->count('SELECT COUNT(*) FROM SETS WHERE STAB=? AND FIELD=? AND SOID!=? AND '.
			     'SLANG=? and STXT=?',
			     array($this->table,$field,$soid,$theLang,$theTxt));
	}
        return $nb>0;
      }
    }
    return false;
  }

  /// Genere un nouvel soid
  function newTSetOid($field) {
    $nb=getDB()->fetchOne('SELECT MAX(cast(SOID AS UNSIGNED))+1 FROM SETS WHERE FIELD="'.$field.'" AND STAB="'.$this->table.'" AND SOID REGEXP "^[[:digit:]]+$"');
    if(empty($nb)) return 1;
    else return $nb;
  }

  /// Determine le prochain ordre pour une nouvelle valeur d'un type (dans toutes les langues)
  function newTSetOrder($field) {
    $nb=getDB()->fetchOne('SELECT MAX(SORDER)+1 FROM SETS WHERE STAB="'.$this->table.'" AND FIELD="'.$field.'"');
    if(empty($nb)) return 1;
    else return $nb;
  }

  /// Recupere la liste des valeurs dans toutes les langues
  protected function getTSet($field) {
    $tset=array();
    $rs=getDB()->fetchAll('SELECT * FROM SETS WHERE STAB=? AND FIELD=? ORDER BY SORDER,SLANG',
			  array($this->table,$field));
    foreach($rs as $ors) $tset[$ors['SOID']][$ors['SLANG']]=$ors['STXT'];
    unset($rs);
    return $tset;
  }

  /// Retourne le texte correspondant à un soid pour une lange donnée
  function getDefaultTSet($field, $soid, $lg=TZR_DEFAULT_LANG) {
    $label = '';
    $ors=getDB()->fetchRow('SELECT * FROM SETS WHERE STAB=? AND FIELD=? AND SOID=? AND SLANG=?',
			  array($this->table,$field,$soid,$lg));
    if($ors) $label=$ors['STXT'];
    return $label;
  }

  /// Retourne un soid en fonction d'un texte et d'une langue
  function getSOIDFromSTXT($field,$lg,$txt) {
    $ors=getDB()->fetchRow('SELECT SOID FROM SETS WHERE STAB=? AND FIELD=? AND SLANG=? AND STXT=?',
			  array($this->table,$field,$lg,$txt));
    if($ors) return $ors['SOID'];
    return NULL;
  }
}

