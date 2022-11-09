<?php
  /**
   * correction des données des champs fichiers mal traduits
   * 
   */
function Shell_2019xxxx(){
  if (\Seolan\Core\SHell::getMonoLang()) {
    \Seolan\Core\Logs::upgradeLog('not a multi lang console', false);
    return;
  }
  \Seolan\Core\Logs::upgradeLog('invalid file fiels data patch', true);
  // toutes les tables avec des champs fichiers
  \Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);
  

  //$list = \Seolan\Core\DataSource\DataSource::getBaseList();
  //$list = \Seolan\Core\DataSource\DataSource::getBaseList8();
  
  $tables = getDb()->fetchCol('select BTAB from BASEBASE where ifnull(TRANSLATABLE, 0) != ?', [0]);
  $notpatched = [];
  foreach($tables as $table){
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
    if (!is_a($ds, Seolan\Model\DataSource\Table\Table::class))
      continue;

    $filesFields = $ds->getFieldsList(
				      ['\Seolan\Field\File\File','\Seolan\Field\Image\Image','\Seolan\Field\Video\Video'],
				      false, //brosable
				      false, // pub
				      false, // query
				      false, // comp
				      true); // trans

    if (count($filesFields) == 0){
      $notpatched[] = $table;
      continue;
    }
    \Seolan\Core\Logs::upgradeLog("- $table");
    foreach($filesFields as $ffn){
      $fd = $ds->getField($ffn);
      if ($fd->multivalued){
	Shell_2019xxxx_processMulti($ds,$fd);
      } else {
	Shell_2019xxxx_processMono($ds, $fd);
      }
    }
  }
  \Seolan\Core\Logs::upgradeLog("no translatable files for : ".implode(",", $notpatched));
}
function Shell_2019xxxx_processMulti($ds,$fd){
  Shell_2019xxxx_processField($ds, $fd);
}
function Shell_2019xxxx_processMono($ds,$fd){
  Shell_2019xxxx_processField($ds, $fd);
}
function  Shell_2019xxxx_processField($ds, $fd){

  $multi = false;
  if ($fd->multivalued)
    $multi = true;
  
  \Seolan\Core\Logs::upgradeLog("Field '{$fd->field}' ".($multi?'multivalued':'monovalued'));

  $fn = $fd->field;
  foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$truc){

    list($tot, $distinct, $lang) = getDB()->select("select count(*), count(distinct  $fn), lang from {$ds->getTable()} where  LANG=?", [$lang])->fetch(PDO::FETCH_NUM);
    \Seolan\Core\Logs::upgradeLog("\t$lang : $distinct values in table on $tot lines");

    if ($lang != TZR_DEFAULT_LANG){ // memes valeurs base / lang sont à corriger
      $q = "select koid, lang, $fn from {$ds->getTable()} where lang=? and ifnull($fn,'') not in ('','TZR_unchanged') and $fn=(select base.$fn from {$ds->getTable()} base where base.lang=? and base.koid={$ds->getTable()}.koid)";

      $rs = getDB()->select($q, [$lang, TZR_DEFAULT_LANG]);
      \Seolan\Core\Logs::upgradeLog("\t$lang {$rs->rowCount()} lines to process");

      if ($rs->rowCount()>0){ // parcours des lignes à corriger
	while(list($koid, $lang, $rawval) = $rs->fetch(PDO::FETCH_NUM)){
	  \Seolan\Core\Logs::upgradeLog("\t$lang, $rawval,$koid");
	  list($foo,$foid)=explode(':',$koid);
	  // display du champ en FR
	  $__filename = $fd->filename(TZR_DEFAULT_LANG.'.'.$foid);
	  if ($multi){
	    $filenamefr = $fd->dirname($rawval, false, false, null);
	  } else {
	    $filenamefr = $fd->filename($rawval, false, false, null);
	  }
	  $filepathfr = $GLOBALS['DATA_DIR'].$filenamefr;
	  \Seolan\Core\Logs::upgradeLog("\t--> default : $filepathfr ($__filename)");

	  if (!file_exists($filepathfr)){
	    \Seolan\Core\Logs::upgradeLog("\t--> base file ($filepathfr) not exists, continue");
	    continue;
	  }
	  
	  // fichier dans la langue
	  $filelang = $lang.'.'.$foid;
	  $filenamelang = $fd->filename($filelang);
	  $filepathlang = $GLOBALS['DATA_DIR'].$filenamelang;
	  \Seolan\Core\Logs::upgradeLog("\t--> to $filepathlang");

	  if (file_exists($filepathlang)){
	    \Seolan\Core\Logs::upgradeLog("\t--> $koid $lang $filepathlang already exists, continue, no copy, ? update ?");
	    // à voir si update ou pas
	    $filelangexists = true;
	  } else {
	    $filelangexists = false;
	    $dirlang = dirname($filepathlang);
	    // check dir (data/$table/$fn/xx/yy (/filename ou dirname si multi)
	    \Seolan\Library\Dir::mkdir($dirlang, false);
	    if ($multi){

	      $cmd = "cp -r $filepathfr $filepathlang";
	      \Seolan\Core\Logs::upgradeLog("\t\t$cmd");
	      unset($r);
	      unset($lines);
	      $r = exec($cmd, $lines);
	      if (!file_exists($filepathlang)){
		\Seolan\Core\Logs::upgradeLog("\t\t************* error coping directory lang $cmd *******************");
	      }

	    } else {
	      $ret = copy($filepathfr, $filepathlang);
	      if (!$ret || !file_exists($filepathlang)){
		\Seolan\Core\Logs::upgradeLog("\t\t************* error coping file lang *******************");
	      }
	    }

	  }
	  // mise à jour de la valeur
	  if (!$filelangexists){
	    if ($multi){
	      $rawvallang = $filelang.';dir';
	    } else {
	      // nouvelle raw valeur
	      $jsonlang = json_decode($rawval);
	      $jsonlang->file= $filelang;
	      $rawvallang = json_encode($jsonlang);
	    }
	    \Seolan\Core\Logs::upgradeLog("\t -> update $rawvallang");
	    getDB()->execute("update {$ds->getTable()} set $fn=? where koid=? and lang=?",[$rawvallang,$koid,$lang]);
	  }
	}
      }
    }
  }
}
