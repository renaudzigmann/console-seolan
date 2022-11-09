<?php
// exemple: php-seolan10 $HOME/csx/src/Core/upgrades/Shell/convert-catalogue.php --home='$HOME'
$opts = getopt('',array('home::'));
$HOME = $opts['home'] ?: $_SERVER['HOME'];

if (false === include_once($HOME.'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

if(\Seolan\Core\System::tableExists('_SECS') && !XModSecurity_secure($_SERVER['REMOTE_ADDR'])) {
  XModSecurity_reject($_SERVER['REMOTE_ADDR']);
  die();
}

/**
 * conversion des fichies catalogues des champs fichiers multivalués
 */

function convert($tableName, $fieldName, $dirname, $settings, $koid, $fieldValue, $lang) {
  if (empty($dirname)){
    echo($koid.' '.$fieldName.' is empty skipped<br>'.PHP_EOL);
    return 0;
  }
  if (!file_exists($GLOBALS['DATA_DIR'].$dirname)){
    echo($dirname.' not exists in '.$GLOBALS['DATA_DIR'].' skipped<br>'.PHP_EOL);
    return 0;
  }
  echo($dirname.' seems ok, converting<br>'.PHP_EOL);
  // conversion du catalogue

  if (empty($settings) || count($settings) == 0 || !isset($settings['root']['conf']['directory'])){
    echo($dirname.' empty settings, root, directory or files : skip ?<br>'.PHP_EOL);
    return 0;
  }
  $files = (isset($settings['root']['conf']['directory']['file']['@']))?[$settings['root']['conf']['directory']['file']]:$settings['root']['conf']['directory']['file'];
  // conversion
  $catalog = ['dir'=>array_shift(explode(';', $fieldValue)),'files'=>[]];
  foreach($files as $file){
    $catalog['files'][] = [
      'file'=>$file['@']['id'],
      'name'=>$file['name'],
      'title'=>$file['title'],
      'mime'=>$file['mime']
    ];
  }
  $json = json_encode($catalog, JSON_UNESCAPED_UNICODE);// | JSON_PRETTY_PRINT);
  // mise à jour
  echo 'update '.$tableName.' set '.$fieldName.'="'.$json.'" where koid="'.$koid.'" and lang="'.$lang.'"<br>'.PHP_EOL;
  getDb()->execute('update '.$tableName.' set '.$fieldName.'=? where koid=? and lang=?',
  [$json,
  $koid,
  $lang
  ]);
  return 0;
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
  if(\Seolan\Core\System::tableExists($tableName)) {
    echo '<br>'.PHP_EOL;
    echo '==== '.$tableName.':'.$fieldName.' ====<br>'.PHP_EOL;
    // type SQL
    echo 'alter table '.$tableName.' modify '.$fieldName.' TEXT<br>'.PHP_EOL;
    getDb()->execute('alter table '.$tableName.' modify '.$fieldName.' TEXT');
    $archiveTableName = 'A_'.$tableName;
    if(\Seolan\Core\System::tableExists($archiveTableName)) {
      echo 'alter table '.$archiveTableName.' modify '.$fieldName.' TEXT<br>'.PHP_EOL;
      getDb()->execute('alter table '.$archiveTableName.' modify '.$fieldName.' TEXT');
    }
    $fd = \Seolan\Core\Field\Field::objectFactory2($tableName, $fieldName);
    $rs = getDb()->select('select koid, '.$fieldName.' as fieldvalue, lang from '.$tableName.'');
    echo 'select koid, '.$fieldName.' as fieldvalue, lang from '.$tableName.'<br>'.PHP_EOL;
  
    while($ors = $rs->fetch()){
      $json = json_decode($ors['fieldvalue'], true);
      if(!is_array($json)) {
        $dirname =  $fd->dirname($ors['fieldvalue'], null);  
        $settings = $fd->loadCatalog($dirname.'/', true);
        convert($tableName, $fieldName, $dirname, $settings, $ors['koid'], $ors['fieldvalue'], $ors['lang']);
      }
    }

    /* cas des archives*/
    if(\Seolan\Core\System::tableExists($archiveTableName)) {
      $rs = getDb()->select('select koid, '.$fieldName.' as fieldvalue, lang from '.$archiveTableName.'');
      echo 'select koid, '.$fieldName.' as fieldvalue, lang from '.$archiveTableName.'<br>'.PHP_EOL;
  
      while($ors = $rs->fetch()){
        $dirs = array_filter(glob($GLOBALS['DATA_DIR'].$archiveTableName.'/*' , GLOB_ONLYDIR));
        sort($dirs);
        foreach($dirs as $archiveDir) {
          $json = json_decode($ors['fieldvalue'], true);
          if(!is_array($json)) {
            $dir = basename($archiveDir);
            $dirname = $fd->dirname($ors['fieldvalue'], $dir);
            $settings = $fd->loadCatalog($dirname.'/', true);
            convert($archiveTableName, $fieldName, $dirname, $settings, $ors['koid'], $ors['fieldvalue'], $ors['lang']);
          }
        }
      }
    }
  }
}
/**
 * conversion de tous les champs de toutes les tables
 */
function convertDatabse(){
  echo 'select DTAB, FIELD, FTYPE from DICT where MULTIVALUED = 1<br>'.PHP_EOL;
  $rs = getDb()->fetchAll('select DTAB, FIELD, FTYPE from DICT where MULTIVALUED = 1');
  foreach($rs as $ors) {
    $obj = new $ors['FTYPE']();
    if(is_subclass_of($obj, 'Seolan\Field\File\File') or get_class($obj) == 'Seolan\Field\File\File') {
      convertField($ors['DTAB'], $ors['FIELD']);
    }
  }
}


convertDatabse();