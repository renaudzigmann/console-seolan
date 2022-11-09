<?php
/**
   * chargement du catalogue old school sans Config ni simple xml
 */
function loadCatalog($dirname){

  $catalogFile = $GLOBALS['DATA_DIR'].$dirname.'/.config.xml';
  if(!file_exists($catalogFile)){
    return [];
  }
  $files = [];
  $doc = new \DOMDocument();
  $doc->load($catalogFile);
  $xpath = new DOMXPath($doc);
  $nodeList = $xpath->query('//conf/directory/file[@id]');
  if ($nodeList->length > 0){
    foreach($nodeList as $fileNode){
      $file = [
	'file'=>$fileNode->getAttribute('id')
      ];
      foreach(['name', 'title', 'mime'] as $nodeName){
	$node = $xpath->query($nodeName, $fileNode);
	$value = $node[0]->nodeValue;
	$file[$nodeName]  = $value;
      }
      $files[] = $file;
    }
  }
  return $files;
}
/**
 * conversion des fichiers catalogues des champs fichiers multivalués
 */

function convertFilesXMLToJSon($tableName, $fieldName, $dirname, $koid, $fieldValue, $lang, $upd=NULL) {
  if (empty($dirname)){
    return 0;
  }
  if (!file_exists($GLOBALS['DATA_DIR'].$dirname)){
    return 0;
  }
  // conversion du catalogue
  $catalogtmp = explode(';', $fieldValue);
  $catalog = ['dir'=>array_shift($catalogtmp),
	      'files'=>loadCatalog($dirname)];

  $json = json_encode($catalog, JSON_UNESCAPED_UNICODE);// | JSON_PRETTY_PRINT);

  // mise à jour
  if(!empty($upd)) {
    getDb()->execute('update '.$tableName.' set UPD=UPD, '.$fieldName.'=? where koid=? and lang=? and UPD=?',
		     [$json,
		      $koid,
		      $lang, $upd
		     ]);
  } else {
    getDb()->execute('update '.$tableName.' set UPD=UPD, '.$fieldName.'=? where koid=? and lang=?',
		     [$json,
		      $koid,
		      $lang
		     ]);
  }
  return 1;
}
/**
 * conversion d'un champ d'une table
 * alter table pour passer en TEXT ?
 * lire les valeurs des champs de la table
 * charger le .config associé
 * transoformer le $conf en un objet json
 * mettre à jour le champ
 */
function convertField($tableName, $fieldName){
  static $batch = false;
  $batch = defined('TZR_BATCH');
  if(\Seolan\Core\System::tableExists($tableName)) {
    \Seolan\Core\Logs::upgradeLog("\t$tableName : $fieldName");
    // type SQL
    getDb()->execute('alter table '.$tableName.' modify '.$fieldName.' TEXT');
    $archiveTableName = 'A_'.$tableName;
    if(\Seolan\Core\System::tableExists($archiveTableName)) {
      getDb()->execute('alter table '.$archiveTableName.' modify '.$fieldName.' TEXT');
    }
    $fd = \Seolan\Core\Field\Field::objectFactory2($tableName, $fieldName);
    $rs = getDb()->select('select koid, '.$fieldName.' as fieldvalue, lang from '.$tableName.' order by KOID');
    \Seolan\Core\Logs::upgradeLog("\tconverting $tableName $fieldName : {$rs->rowCount()}");
    $nb=0;
    if ($batch)
      echo("\n\tdone :");
    while($ors = $rs->fetch()){
      $nb++;
      $json = json_decode($ors['fieldvalue'], true);
      if(!is_array($json)) {
        $dirname =  $fd->dirname($ors['fieldvalue'], null);  
        convertFilesXMLToJSon($tableName, $fieldName, $dirname, $ors['koid'], $ors['fieldvalue'], $ors['lang']);
      }
      if ($batch)
	echo("\r\tdone : $nb");
    }
    if ($batch)
      echo("\n\n");
    /* cas des archives */
    $nba = 0;
    if(\Seolan\Core\System::tableExists($archiveTableName)) {
      $rs = getDb()->select('select koid, UPD,'.$fieldName.' as fieldvalue, lang from '.$archiveTableName.' ORDER BY KOID');
      \Seolan\Core\Logs::upgradeLog("\tconverting $archiveTableName $fieldName : {$rs->rowCount()}");
      if ($batch)
	echo("\n\t done :");
      while($ors = $rs->fetch()){
	$nba++;
	if(!empty($ors['fieldvalue'])) {
	  $json = json_decode($ors['fieldvalue'], true);
	  if(!is_array($json)) {
	    $dirname = $fd->dirname($ors['fieldvalue'], preg_replace('/([^0-9])/','',$ors['UPD']));
	    convertFilesXMLToJSon($archiveTableName, $fieldName, $dirname, $ors['koid'], $ors['fieldvalue'], $ors['lang'], $ors['UPD']);
	  }
        }
	if ($batch)
	  echo("\r\tdone : $nba");
      }
    }
  }
  if ($batch)
    echo("\n");
}
/**
 * charge le catalogue du champ sans config ou simple_xml
 */
/**
 * conversion de tous les champs de toutes les tables
 */
function Shell_20190222(){
  \Seolan\Core\Logs::upgradeLog("\nConvert file catalog from xml external file to json column value");
  //echo PHP_EOL.'select DTAB, FIELD, FTYPE from DICT where MULTIVALUED = 1';
  $rs = getDb()->fetchAll('select DTAB, FIELD, FTYPE from DICT where MULTIVALUED = 1');
  foreach($rs as $ors) {
    $obj = new $ors['FTYPE'](array());
    if(is_subclass_of($obj, 'Seolan\Field\File\File') or get_class($obj) == 'Seolan\Field\File\File') {
      convertField($ors['DTAB'], $ors['FIELD']);
    }
  }
}

