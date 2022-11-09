<?php
// Autoload des classes
function tzr_autoload($classname) {
  global $autoloadLogs;
  $autoloadLogs['calls']++;
  // Décodage du chemin
  $path=explode('\\',$classname);
  if(empty($path[0])) $path=array_shift($path); // voir decodeclassname
  if (count($path) == 1) array_unshift($path, 'Local');
  if (!isset($GLOBALS['REPOSITORIES'][$path[0]]))
    return;
  if (TZR_DEBUG_MODE == E_ALL)
    include_once(decodeClassname($classname));
  else
    @include_once(decodeClassname($classname));
  
  if(!class_exists($classname,false) && !interface_exists($classname,false) && !trait_exists($classname,false)) {
    $errorpath = decodeClassname($classname);
    $autoloadLogs['missing'][] = $classname;
  }else{
    $autoloadLogs['loaded'][] = $classname;
  }
  
}
/// Vérifie qu'une version minimale est en place
function checkUpgradeVersion(){
  if (!defined('TZR_UPGRADE_RELEASE'))
      return;
  list($classname, $upgradeno) = TZR_UPGRADE_RELEASE;
  $upgrades = \Seolan\Core\DbIni::getStatic('upgrades','val');
  if (!in_array($upgradeno, @$upgrades[$classname]??[]))
    die('Niveau d\'upgrade trop bas '.TZR_UPGRADE_RELEASE[0].'::'.TZR_UPGRADE_RELEASE[1].' requis');
}
/// Récupère le répertoire d'une classe et le fichier d'une classe à partir de son nom complet
function decodeClassname($classname,$separator='\\',$path_only=true){
  $path=explode($separator,$classname);
  if(empty($path[0])) $path=array_shift($path); // permet de commencer par un \ ex: \Local\MaClasse
  if (count($path) == 1) array_unshift($path, 'Local');
  if (isset($GLOBALS['REPOSITORIES'][$path[0]])){
    $from=array_shift($path);

    if (!isset($GLOBALS['HAS_VHOSTS']) || $from!='MINISITES') {
      $path = $GLOBALS['REPOSITORIES'][$from]['src'].implode('/',$path).'.php';
    } else {
      //Minisites
      $model = array_shift($path);
      $path = $GLOBALS['REPOSITORIES'][$from][$model].implode('/',$path).'.php';
    }
  } else {
    $path=implode('/',$path).'.php';
  }
  if($path_only) return $path;
  else return array('dir'=>dirname($path),'file'=>basename($path),'path'=>$path);
}
/// Retourn le numéro de version complet de la console
function getFullTZRVersion(){
  return TZR_CONSOLE_RELEASE.'.'.TZR_CONSOLE_SUB_RELEASE;
}

/**
 * rend la connection base de donnee active/ouverte
 * @return \Seolan\Library\Database
 */
function getDB() {
  return \Seolan\Library\Database::instance();
}

function bugWarning($message, $assert=false, $exit=true) {
  if(!$assert) {
    if(class_exists('\Seolan\Core\Logs')){
      \Seolan\Core\Logs::update('security','', $message);
      \Seolan\Core\Logs::critical('security',$message);
    }
    $message.="\n".'REQUEST_URI: '.$_SERVER['REQUEST_URI'];
    $message.="\nBackTrace:\n".backtrace2();
    $mail = new \Seolan\Library\Mail(true,false);
    $mail->isHTML(false);
    $mail->FromName = 'CSX';
    $mail->From = 'console@xsalto.com';
    $mail->AddAddress(TZR_DEBUG_ADDRESS);
    $mail->Subject = '[CSX] Bug alert '.TZR_SERVER_NAME;
    $mail->Body = $GLOBALS['HOME_ROOT_URL']."\n".wordwrap($message, 65)."\n";
    $res=$mail->Send();
    if($exit) critical_exit($message);
  }
}

/// generation d'un nom unique utilisable dans les formulaires, non aleatoire
function getUniqID($root='v') {
  return uniqid($root);
}

// rend le serveur smtp a utiliser en envoi, qui est different en
// fonction du nombre de mails a envoyer.
//
function getSmtp($nb) {
  if($nb<30) return \Seolan\Core\Ini::get('i_smtp_ip');
  else return \Seolan\Core\Ini::get('smtp_ip');
}

// initialisation du module
function syscall($command){
  if ($proc = popen("($command)2>&1","r")){
    $result='';
    while (!feof($proc)) $result .= fgets($proc, 1000);
    pclose($proc);
    return $result;
  }
}
function get_user_browser() {
  global $TZR;
  if(empty($_SERVER['HTTP_USER_AGENT'])) return $TZR;
  if(preg_match('/msie:/',$_SERVER['HTTP_USER_AGENT'])) {
    $TZR['navigator'][0]='IE';
    if(preg_match('/msie 6.0/',$_SERVER['HTTP_USER_AGENT'])) {
      $TZR['navigator']=array('IE','6');
    }
  }
  elseif(preg_match('/gecko/',$_SERVER['HTTP_USER_AGENT'])) {
    $TZR['navigator'][0]='Gecko';
  }
  return $TZR;
}

/// fonction de decodage
function unicode_decode($txt) {
  return urldecode(preg_replace('/%u([[:alnum:]]{4})/', '&#x$1;',$txt));
}
/// Récupère la liste des procédures
function getRoutines($type='PROCEDURE'){
  return getDB()->fetchCol('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_TYPE=? and ROUTINE_SCHEMA=?', [$type, $GLOBALS['DATABASE_NAME']]);
}
/// Récupère la liste des tables avec contraintes clé étrangères
function getTablesWithForeignKeys(){
  return getDB()->fetchCol('SELECT DISTINCT TABLE_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND CONSTRAINT_TYPE=?', [$GLOBALS['DATABASE_NAME'],
																	       'FOREIGN KEY']);
}
/// Recupere la liste des tables de la base
function getMetaTables() {
  $tables=getDB()->fetchAll('SHOW FULL TABLES');
  $t=array();
  foreach($tables as $i=>$a) {
    $type=array_pop($a);
    $table=array_pop($a);
    $t[$table]=array('type'=>$type, 'table'=>$table);
  }
  return $t;
}

/// Recupere le nom des champs d'une table
function &getColumnNames($table){
  $raw_column_data=getDB()->fetchAll('SHOW COLUMNS FROM '.$table);
  foreach($raw_column_data as $outer_key=>$array){
    $column_names[]=$array['Field'];
  }
  return $column_names;
}

/// Recupere le nom des champs d'une table
function getColumnDesc($table, $field){
  $allfields=getDB()->fetchAll('SHOW FULL COLUMNS FROM '.$table);
  foreach($allfields as $fieldDesc) {
    if($fieldDesc['Field']==$field) return $fieldDesc;
  }
  return NULL;
}

/// Recupere les cles primaires d'une table
function &getMetaPrimaryKeys($table){
  $keys=array();
  $rs=getDB()->fetchAll('SHOW INDEX FROM '.$table);
  foreach($rs as $i=>$row){
    if($row['Key_name']=='PRIMARY'){
      $keys[]=$row['Column_name'];
    }
  }
  unset($rs);
  return $keys;
}
/// Recupere les colonnes d'un index d'une table
function getMetaKeys($table, $iname){
  $rs = getDB()->fetchAll('SHOW INDEX FROM '.$table.' where Key_name=?', [$iname]);
  $keys=[];
  foreach($rs as $i=>$row){
      $keys[]=$row['Column_name'];
  }
  unset($rs);
  return (count($keys)>0)?$keys:false;
}
/// teste l'existence d'un champ dans une table
function fieldExists($table,$field) {
  $cols=getColumnNames($table);
  return in_array($field,$cols);
}
function VarDump(&$vardump,$methods=true,$level=0,$max_level=0,$details=0,$export=false) {
  $m1='';
  if($level>$max_level) return;
  if($level==0) $m1.="<pre>\n";
  if($details>0) $m1.='('.gettype($vardump).')&nbsp;';
  switch(gettype($vardump))  {
  case 'object':
    $m1.='<ul>';
    if($methods) {
      $m1.=get_class($vardump)."(\n";
      $vars=(array)get_object_vars($vardump);
      echo '<ul>';
      foreach($vars as $var=>$varval)	{
	if(is_string($varval))$varval=htmlentities($varval);
	if(trim($var)) $m1.= '$'.$var." => ".$varval."\n";
      }
      $mets=(array)get_class_methods($vardump);
      foreach($mets as $met) {
	if(trim($met))  $m1.= $met."\n";
      }
      $m1.= "</ul>";
      $m1.= ")\n";
    } else $m1.= get_class($vardump)."\n";
    $m1.= "</ul>";
    break;
  case "array":
    foreach ($vardump as $key => $value) {
      $m1.= "<ul> ";
      $m1.= "[".htmlentities($key)."]";
      $m1.= VarDump($value,$methods,$level+1,$max_level,$details,true);
      $m1.= "</ul>";
    }
    break;
  default:
    $m1.= htmlentities($vardump)."\n";
  }
  if($level==0)$m1.= "\n</pre>\n";
  if($export) return $m1;
  else echo $m1;
}
function get_memory() {
  if(function_exists('memory_get_usage'))
    return round(memory_get_usage(false)/(1024*1024));
  else
    return 0;
}

function escapeJavascript($string){
  return strtr($string, array('\\'=>'\\\\',"'"=>"\\'",'"'=>'\\"',"\r"=>'\\r',"\n"=>'\\n','</'=>'<\/'));
}

function array_stripslashes($a) {
  if(!is_array($a)) return stripslashes($a);
  $n = array();
  foreach($a as $i => $v) {
    if(is_array($v)) $n[$i]=array_stripslashes($v);
    else $n[$i]=stripslashes($v);
  }
  return $n;
}
$TRANS_ARRAY = array();
for ($i=127; $i<255; $i++) {
    $TRANS_ARRAY[chr($i)] = "&#" . $i . ";";
}
function specialchar2html($intext) {
    $outtext = strtr($intext, $GLOBALS['TRANS_ARRAY']);
    return $outtext;
}

function getBillingCode() {
 $cmd=\Seolan\Core\Ini::get('billing_code');
 if(isset($cmd)&&($cmd!='')) return $cmd;
 else return $GLOBALS['HOME'];
}

// ligne csv (historique)
function csv2array ($str, $delim=';', $enclosure="\"") {
  return str_getcsv($str, $delim, $enclosure);
}

/// Transforme les données d'un fichier csv en tableau
function &_getCSVData($data,$spec) {
  $lineseparator = $spec->general->endofline;
  if(empty($lineseparator)) {
    $lineseparator="\r\n";
  }
  $separator = $spec->general->separator;
  if(empty($separator)) $separator="\t";
  $quote = (string)$spec->general->quote;
  $odata=explode($lineseparator, $data);
  foreach($odata as $i => $line) {
    $line=mb_trim($line);
    if(!empty($line)) {
      $odata[$i]=str_getcsv($line,$separator,$quote);
    }
  }
  return $odata;
}


/// Transforme les données d'un fichier excel en tableau
function &_getXLSData($file) {
  try{
      $data=array();
      $type = PHPExcel_IOFactory::identify($file);
      $reader = PHPExcel_IOFactory::createReader($type);
      $reader->setReadDataOnly(true);
      $ss = $reader->load($file);
      $ss->setActiveSheetIndex(0);
      $ws = $ss->getActiveSheet();

      //return $ws->toArray();

      $i=$ws->getRowIterator();
      // On saute la premiere ligne qui ne contient pas de données
      //$i->next(); à priori ce n'est plus nécessaire et ça casse l'import

      // Se base sur la première ligne pour identifier le nombre de colonnes à prendre en compte
      // pour ne pas surplomber la mémoire lorsque le nombre de colonne est fixé par défaut à 1024
      $max_cols = 0;
      $firstColCellIterator = $i->current()->getCellIterator();
      $firstColCellIterator->setIterateOnlyExistingCells(false);
      foreach ($firstColCellIterator as $cell) {
        $max_cols++;
      }
      $max_cols = PHPExcel_Cell::columnIndexFromString($ws->getHighestColumn());
      $countLigneEmpty = 0;
      while($i->valid()){
          $row=array();
          $r=$i->current();
          $i->next();
          $ri=$r->getCellIterator();
          // Provoque une boucle infinie lors de l'import d'un XLSX généré par la Console
          $ri->setIterateOnlyExistingCells(false);
          $empty = true;
          while($ri->valid()){
              $c=$ri->current();
              $ri->next();
              $v=$c->getCalculatedValue();
              if($v===NULL) $v='';
              if ($v != '') $empty = false;
              $row[]=$v;
              // Empêche la boucle infinie
              if (count($row) >= $max_cols) break;
          }
          if($empty || empty($row)){
              $countLigneEmpty++;
              if($countLigneEmpty>5){
                  \Seolan\Core\Logs::critical('_getXLSData', '5 following lines are empty');
                  return $data;
              }
              continue;
          }else{
              $countLigneEmpty=0;
          }
          $data[]=$row;
      }
  } catch (Exception $e){
      \Seolan\Core\Logs::critical('_getXLSData',$e->getMessage());
  }
  return $data;
}
/// Envoie un object PHPExcel dans le format spécifié vers le flux de sortie
function sendPHPExcelFile($ss,$fmt,$name='file'){
  if($fmt=='csv'){
    ob_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Transfer-Encoding:UTF-8');
    header('Content-disposition: attachment; filename='.$name.'.csv');
    header('Cache-Control: max-age=0');
    $objWriter=new PHPExcel_Writer_CSV($ss);
    $objWriter->setDelimiter(';');
    $objWriter->setEnclosure('"');
    $objWriter->setLineEnding("\r\n");
    $objWriter->save('php://output');
  }elseif($fmt=='xl07'){
    ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename='.$name.'.xlsx');
    header('Cache-Control: max-age=0');
    $objWriter=new PHPExcel_Writer_Excel2007($ss);
    $objWriter->setPreCalculateFormulas(true);
    $objWriter->save('php://output');
  }elseif($fmt=='xl'){
    ob_clean();
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename='.$name.'.xls');
    header('Cache-Control: max-age=0');
    $objWriter=new PHPExcel_Writer_Excel5($ss);
    $objWriter->setPreCalculateFormulas(true);
    $objWriter->save('php://output');
  }
  exit(0);
}

function sendPhpSpreadsheetFile($ss, $fmt, $name = 'file') {
  ob_clean();
  header('Cache-Control: max-age=0');
  switch ($fmt) {
    case 'csv':
      header('Content-Type: text/csv; charset=UTF-8');
      header('Content-Transfer-Encoding:UTF-8');
      header('Content-disposition: attachment; filename=' . $name . '.csv');
      $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Csv($ss);
      $objWriter->setDelimiter(';');
      $objWriter->setEnclosure('"');
      $objWriter->setLineEnding("\r\n");
      break;
    case 'xl':
    case 'xls':
      header('Content-Type: application/vnd.ms-excel');
      header('Content-Disposition: attachment;filename=' . $name . '.xls');
      $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xls($ss);
      break;
    case 'xl07':
    case 'xlsx':
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename=' . $name . '.xlsx');
      $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
      break;
  }
  $objWriter->save('php://output');
  exit(0);
}

function loadIni($refresh=false) {
  if(!isset($GLOBALS['TZR']) || $refresh=true) {
    $GLOBALS['TZR_INI']=new \Seolan\Core\Ini();
    return $GLOBALS['TZR']=$GLOBALS['TZR_INI']->load();
  } else {
    $GLOBALS['TZR_INI']=$_SESSION['TZR_INI'];
    return $GLOBALS['TZR']=$_SESSION['TZR'];
  }
}
// affiche d'un var_dump raw
function tzr_var_dump($mixed){
  ob_start();
  var_dump($mixed);
  $ret = ob_get_contents();
  ob_end_clean();
  return $ret;
}
// affichage d'un message de debug dans le fichier de log
//
function debug($msg) {
   if(!is_string($msg)) 
     $msg=var_export($msg,true);
   \Seolan\Core\Logs::debug($msg);
}

function checkIfUrlIsSecure($url) {
  $exprs=array('/etc/','proc/','../..','GLOBALS','mosConfig');
  foreach($exprs as $foo) {
    if(strstr($url, $foo)!=FALSE) {
        \Seolan\Library\Security::alert('url is not secure (rule_url1:'.$foo.')');
    }
  }
  if(preg_match_all('/[^a-z](schema|chr|null|all|union|select|char|concat|from|sleep|\(|\))[^a-z]/i',$url, $matches)) {
    if(!empty($matches) && count($matches[1])>=3) {
      \Seolan\Library\Security::alert('checkIfUrlIsSecure1');
    }
  }
}

function checkIfTemplateIsSecure(&$t,$security2=false) {
  if(is_array($t)) {
    foreach($t as $i=>&$t1)
      checkIfTemplateIsSecure($t1,$security2);
    return;
  }
  if(empty($t)) return;
  if (strpos($t, 'application:') === 0){
      $t2 = substr($t, strlen('application:'));
      checkIfTemplateIsSecure($t2);
      $t = 'application:'.$t2;
      return true;
  }
  if(preg_match('@^(https:|http:|ftp:|[0-9]{1,3}|/)@i',$t)) {
    \Seolan\Library\Security::alert('template is not secure (rule1)');
  }
  // : pour avoir le protocole (xxx:yyyy.html)
  if(!preg_match('@^([:_a-z0-9\./-]+)$@i',$t) || (strpos($t,'..')!== false)) {
    \Seolan\Library\Security::warning('template <'.$t.'> is not secure (rule2)');
  }
  if($security2 && \Seolan\Core\Shell::admini_mode() && \Seolan\Core\User::isNobody() && !in_array($t, $GLOBALS['TZR_PUBLIC_TEMPLATES'])) {
    \Seolan\Library\Security::warning("template '$t' is not secure (rule0) ".implode(',', $GLOBALS['TZR_PUBLIC_TEMPLATES']));
    if(!empty($_REQUEST['_ajax'])) header("HTTP/1.1 401 Unauthorized");
    else header('Location: /admin');
    exit(0);
  }
  if(preg_match('@^dir/([a-z0-9]+)/@i',$t,$eregs)) {
    $GLOBALS['TEMPLATES_DIR']=dirname($GLOBALS['TEMPLATES_DIR']).'/'.$eregs[1].'/';
    $t=ereg_replace('^/dir/([A-Za-z0-9]+)/','',$t);
  }
  return true;
}
function backtrace() {
  $output = "<div style='text-align: left; font-family: monospace;'>\n";
  $output .= "<b>Backtrace:</b><br />\n";
  $backtrace = debug_backtrace();

  foreach ($backtrace as $bt) {
    $args = '';
    foreach ($bt['args'] as $a) {
      if (!empty($args)) {
	$args .= ', ';
      }
      switch (gettype($a)) {
      case 'integer':
      case 'double':
	$args .= $a;
	break;
      case 'string':
	$a = htmlspecialchars(substr($a, 0, 64)).((strlen($a) > 64) ? '...' : '');
	$args .= "\"$a\"";
	break;
      case 'array':
	$args .= 'Array('.count($a).')';
	break;
      case 'object':
	$args .= 'Object('.get_class($a).')';
	break;
      case 'resource':
	$args .= 'Resource('.strstr($a, '#').')';
	break;
      case 'boolean':
	$args .= $a ? 'True' : 'False';
	break;
      case 'NULL':
	$args .= 'Null';
	break;
      default:
	$args .= 'Unknown';
      }
    }
    $output .= "<br />\n";
    $output .= "<b>file:</b> {$bt['line']} - {$bt['file']}<br />\n";
    $output .= "<b>call:</b> {$bt['class']}{$bt['type']}{$bt['function']}($args)<br />\n";
  }
  $output .= "</div>\n";
  return $output;
}
function backtrace2()
{
  $backtrace = array_reverse(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
  $output="";
  for ($i=0; $i<count($backtrace)-1; $i++) {
    $output .= "{$backtrace[$i]["class"]}#".$backtrace[$i+1]["line"]."{$backtrace[$i]["type"]}{$backtrace[$i]["function"]}() > ";
  }
  return $output;
}

// fonction de nettoyage d'emails, qui supprime les caracteres non attendus,
// les blancs et autres anomalies dans l'adresses email
//
function emailClean($email) {
  $email=str_replace('mailto:','',$email);
  $email=trim($email);
  if(preg_match('/^([^@]+@[^.]+.*)$/i',$email, $res)) {
    return $res[1];
  }
  return NULL;
}
// rend vrai si la variable a la syntaxe d'un email
//
function isEmail($email) {
  return preg_match('@'.str_replace('@', '\@', TZR_EMAIL_REGEXP).'@i', $email);
}
function getEmailFromString($str) {
  $toRet = array();
  $emails2=array();
  if(!empty($str)) $emails2=preg_split('@[\ ,;]@',$str);
  foreach($emails2 as &$v){
    $v = emailClean($v);
    if(isEmail($v)) $toRet[] = $v;
  }
  return $toRet;
}
// change filename to produce unique filename in directory
function uniqueFileNameInDirectory($directory, $filename){
  $tofilename = $directory.$filename;
  while(file_exists($tofilename)){
    $infos = pathinfo($tofilename);
    $ext = '';
    if ($infos['extension']){
      $ext = '\.'.$infos['extension'];
    } 
    $exp = '/^(.*)(|\(([0-9]+)\))'.$ext.'$/U';
    $r = preg_match($exp, $infos['basename'], $parts);
    if ($r && isset($parts[3])){
      $num = $parts[3]+1;
      $filename = $parts[1];
    } else {
      $filename = $infos['filename'];
      $num = 1;
    }
    $tofilename = $infos['dirname'].'/'.$filename.'('.$num.')'.str_replace('\.', '.', $ext);
  }
  return $tofilename;
}
function cleanFilename($filename) {
  $filename=removeaccents($filename);
  return preg_replace("/([^0-9A-Za-z@_.-]+)/",'_',$filename);
}
/*
* Supprime le prefix (nom de table) d'un oid 
*/
function cleanOid($oid) {
  return preg_replace('@([^:]*:)@','',$oid);
}

function groupCacheActive() {
  return (isset($_REQUEST['TZR_USE_GROUP_CACHE']) && $_REQUEST['TZR_USE_GROUP_CACHE']==1) ||
         (!isset($_REQUEST['TZR_USE_GROUP_CACHE']) && TZR_USE_GROUP_CACHE);
}


// gestion des sessions
//
function sessionActive($cluster = NULL) {
  return !empty($_SESSION[$cluster ?? '_TZR']);
}
function issetSessionVar($n, $cluster = NULL) {
  return isset($_SESSION[$cluster ?? '_TZR'][$n]);
}
function setSessionVar($n, $v, $cluster = NULL) {
  $_SESSION[$cluster ?? '_TZR'][$n]=$v;
}
function setUserRoot() {
  setSessionVar('root', true);
  setSessionVar('UID', TZR_USERID_ROOT);
}
function getSessionVar($n, $cluster = NULL) {
  if (isset($_SESSION[$cluster ?? '_TZR']) && isset($_SESSION[$cluster ?? '_TZR'][$n]))
    return $_SESSION[$cluster ?? '_TZR'][$n];
  return NULL;
}
function clearSessionVar($n, $cluster = NULL) {
  unset($_SESSION[$cluster ?? '_TZR'][$n]);
}
function removeSessionVar($n, $cluster='_TZR') {
  $ret=NULL;
  if(isset($_SESSION[$cluster]) && isset($_SESSION[$cluster][$n])){
    $ret = $_SESSION[$cluster][$n];
    unset($_SESSION[$cluster][$n]);
  }
  return $ret;
}
function unsetSession($cluster = NULL) {
  unset($_SESSION[$cluster ?? '_TZR']);
}
function mergeSessionVar($var_name, $value_to_merge, $cluster = NULL) {
  $cluster = $cluster ?? '_TZR';
  $value = getSessionVar($var_name, $cluster);
  if (is_null($value))
    $value = $value_to_merge;
  elseif (is_array($value))
    $value = array_merge_recursive($value, $value_to_merge);
  elseif (is_object($value))
    $value = (object) array_merge((array) $value, (array) $value_to_merge);
  elseif (is_string($var))
    $value .= $value_to_merge;
  elseif (is_numeric($var))
    $value += $value_to_merge;
  setSessionVar($var_name, $value, $cluster);
}
function sessionStart() {
  global $SESSION_CACHE_LIMITER;
  session_name(TZR_SESSION_NAME);
  // récupration de la session si elle est passée sur l'url
  if ((defined('TZR_JSON_MODE') || TZR_ADMINI) && isset($_GET[TZR_SESSION_NAME])) {
    session_id($_GET[TZR_SESSION_NAME]);
  }
  if (!empty($_SERVER['HTTPS']))
    session_set_cookie_params(get_session_lifetime(), TZR_SESSION_COOKIE_PARAM, ini_get('session.cookie_domain'), TRUE, TRUE);
  else
    session_set_cookie_params(get_session_lifetime(), TZR_SESSION_COOKIE_PARAM, ini_get('session.cookie_domain'), FALSE, TRUE);

  session_cache_limiter($SESSION_CACHE_LIMITER);
  session_start();
  if(sessionActive()) {
    $sessionlength = (!empty($_SESSION['TZR_TS']))?(time()-$_SESSION['TZR_TS']):0;
    if($sessionlength>TZR_SESSION_DURATION){
      // Session expirée
      unsetSession();
    }else{
      // Verification que la session correspond bien au navigateur en cours
      // Si ce n'est pas le cas, on genere un nouvel identifiant et on le vide
      $cliinf = getSessionVar('CLIENT_INFO');
      if(!empty($_SERVER['HTTP_USER_AGENT']) 
	 && substr($_SERVER['HTTP_USER_AGENT'],0,6)!="Smarty" 
	 && substr($_SERVER['HTTP_USER_AGENT'],0,6)!="Prince" 
	 && !empty($cliinf['AGENT']) 
	 && $cliinf['AGENT']!=$_SERVER['HTTP_USER_AGENT']) {
        session_regenerate_id();
        unsetSession();
        unset($_REQUEST[session_name()]);
      }
    }
    $_SESSION['TZR_TS']=time();
  }

  $session_backup = \Seolan\Core\SessionBackup::getInstance();
  if ($session_backup->hasBackup()) {
    $session_backup->restore();
  }
}

/**
 * Met en place le nécessaire (cookie) pour prolonger la durée de lasession
 * @global int $SESSION_LIFETIME_REMEMBER_ME
 */
function set_up_remember_me(){
  global $SESSION_LIFETIME_REMEMBER_ME;
  setcookie("rememberme", True, time()+$SESSION_LIFETIME_REMEMBER_ME);
}

/**
 * Retourne la durée de la session a appliquer a cette éxecution
 * @global int $SESSION_LIFETIME_REMEMBER_ME
 * @global int $SESSION_LIFETIME
 * @return boolean
 */
function get_session_lifetime(){
  if (!\Seolan\Core\Shell::admini_mode() && filter_input(INPUT_COOKIE, "rememberme")) {
    global $SESSION_LIFETIME_REMEMBER_ME;
    return $SESSION_LIFETIME_REMEMBER_ME;
  }
  global $SESSION_LIFETIME;
  return $SESSION_LIFETIME;
}

function sessionClose() {
  $session_backup = \Seolan\Core\SessionBackup::getInstance();
  try {
    $session_backup->store();
  } catch (\Seolan\Core\Exception\SessionAlreadyStored $ex) {
    \Seolan\Core\Logs::critical(__METHOD__, $ex->getMessage());
  }
  session_commit();
}


/// rend vrai si la chaine $str contient un mot qui est un mot clé sql
function containsNoSQLKeyword($str) {
  $separators='() ;,.-+/?!+';
  $tok = strtok($str, $separators);
  while ($tok !== false) {
    if(isOrderSQLKeywordProhibited($tok)) {
      return false;
    }
    $tok = strtok($separators);
  }
  return true;
}
function isSQLOperator($k){
  //https://dev.mysql.com/doc/refman/5.5/en/non-typed-operators.html
  static $operators = ['AND','&&','=',':=','BETWEEN','BINARY','&','~','|','^','CASE','DIV','/','=','<=>','>','>=','IS','IS NOT','IS NOT NULL','IS NULL','<<','<','<=','LIKE','-','%','MOD','NOT','!','NOT BETWEEN','!=','<>','NOT LIKE','NOT REGEXP','||','OR','+','REGEXP','>>','RLIKE','SOUNDS LIKE','*','-','XOR'];
  return in_array(strtoupper($k),$operators);
}
function isSQLKeyword($k) {
  // MySQL reserved words from https://dev.mysql.com/doc/refman/5.7/en/keywords.html + POSITION
  static $keywords = array('ACCESSIBLE', 'ADD', 'ALL', 'ALTER', 'ANALYZE',
    'AND', 'AS', 'ASC', 'ASENSITIVE', 'BEFORE', 'BETWEEN', 'BIGINT', 'BINARY',
    'BLOB', 'BOTH', 'BY', 'CALL', 'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER',
    'CHECK', 'COLLATE', 'COLUMN', 'CONDITION', 'CONSTRAINT', 'CONTINUE', 'CONVERT',
    'CREATE', 'CROSS', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP',
    'CURRENT_USER', 'CURSOR', 'DATABASE', 'DATABASES', 'DAY_HOUR',
    'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE',
    'DEFAULT', 'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DETERMINISTIC',
    'DISTINCT', 'DISTINCTROW', 'DIV', 'DOUBLE', 'DROP', 'DUAL', 'EACH', 'ELSE',
    'ELSEIF', 'ENCLOSED', 'ESCAPED', 'EXISTS', 'EXIT', 'EXPLAIN', 'FALSE', 'FETCH',
    'FLOAT', 'FLOAT4', 'FLOAT8', 'FOR', 'FORCE', 'FOREIGN', 'FROM', 'FULLTEXT',
    'GENERATED', 'GRANT', 'GROUP', 'HAVING', 'HIGH_PRIORITY', 'HOUR_MICROSECOND',
    'HOUR_MINUTE', 'HOUR_SECOND', 'IF', 'IGNORE', 'IN', 'INDEX', 'INFILE', 'INNER',
    'INOUT', 'INSENSITIVE', 'INSERT', 'INT', 'INT1', 'INT2', 'INT3', 'INT4',
    'INT8', 'INTEGER', 'INTERVAL', 'INTO', ', ', 'IS', 'ITERATE', 'JOIN', 'KEY',
    'KEYS', 'KILL', 'LEADING', 'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 'LINEAR', 'LINES',
    'LOAD', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT',
    'LOOP', 'LOW_PRIORITY', 'MASTER_SSL_VERIFY_SERVER_CERT', 'MATCH', 'MAXVALUE',
    'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT', 'MIDDLEINT', 'MINUTE_MICROSECOND',
    'MINUTE_SECOND', 'MOD', 'MODIFIES', 'NATURAL', 'NOT', 'NO_WRITE_TO_BINLOG',
    'NULL', 'NUMERIC', 'ON', 'OPTIMIZE', 'OPTIMIZER_COSTS', 'OPTION', 'OPTIONALLY',
    'OR', 'ORDER', 'OUT', 'OUTER', 'OUTFILE', 'POSITION', 'PRECISION', 'PRIMARY',
    'PROCEDURE', 'PURGE', 'RANGE', 'READ', 'READS', 'READ_WRITE', 'REAL',
    'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPEAT', 'REPLACE', 'REQUIRE',
    'RESIGNAL', 'RESTRICT', 'RETURN', 'REVOKE', 'RIGHT', 'RLIKE', 'SCHEMA',
    'SCHEMAS', 'SECOND_MICROSECOND', 'SELECT', 'SENSITIVE', 'SEPARATOR', 'SET',
    'SHOW', 'SIGNAL', 'SMALLINT', 'SPATIAL', 'SPECIFIC', 'SQL', 'SQLEXCEPTION',
    'SQLSTATE', 'SQLWARNING', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS',
    'SQL_SMALL_RESULT', 'SSL', 'STARTING', 'STORED', 'STRAIGHT_JOIN', 'TABLE',
    'TERMINATED', 'THEN', 'TINYBLOB', 'TINYINT', 'TINYTEXT', 'TO', 'TRAILING',
    'TRIGGER', 'TRUE', 'UNDO', 'UNION', 'UNIQUE', 'UNLOCK', 'UNSIGNED', 'UPDATE',
    'USAGE', 'USE', 'USING', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'VALUES',
    'VARBINARY', 'VARCHAR', 'VARCHARACTER', 'VARYING', 'VIRTUAL', 'WHEN', 'WHERE',
    'WHILE', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL');
  return in_array(strtoupper($k), $keywords);
}

function isOrderSQLKeywordProhibited($k) {
  if (in_array(strtoupper($k), array('ASC', 'DESC')))
    return false;
  return isSQLKeyword($k);
}

function noSqlKeywordRegexp() {
}

function isTZRKeyword($k) {
  static $keywords=
    array('TPLENTRY','_INPUTS','_OPTIONS','_FILTER','OPTIONS','MESSAGE','_FIELDS','FUNCTION','_FUNCTION',
          'OID','KOID','MOID','CLASS','TEMPLATE','INSIDEFILE','NEXT','_NEXT','ORDER','ID',
          // Autres noms pouvant poser problème pour le JavaScript
          'OBJECT','ARRAY','DATE','REGEXP','MATH','PROTOTYPE');
  return in_array(strtoupper($k),$keywords);
}

 // conversion du fichier de nom $filename ou du contenu xml  de $content
 // vers PDF en utilisant PRINCE. La fonction rend le nom du fichier
 // contenant le pdf en cas de succes.
 //
 function princeXML2PDF($filename=NULL,$content="", $option="", $multi=false) {
  $tmpname=TZR_TMP_DIR.uniqid();
  $destfilename=$tmpname.".pdf";

  if(!empty($option) && ltrim($option) == $option){
    $option = " ".$option; //On rajoute l'espace nécessaire à l'execution de la commande
  }
  if(empty($content) && !empty($filename)) $content=@file_get_contents($filename);
  if ($multi) {
    $tmpnames = "";
    $tmpfiles = [];
    $xml_doc = new DOMDocument();

    foreach($content as $key => $value) {
      convert_charset($content[$key],TZR_INTERNAL_CHARSET,'UTF-8');
      @file_put_contents($tmpname.".xml",$content[$key]);

      $name = $tmpname.".xml";
      $xml_doc->validateOnParse = true;

      if (file_exists($tmpname.".xml")) {
        $xml_doc->load($name);
        $body = $xml_doc->getElementsByTagName('body');

        foreach ($body as $item) {
          if(!empty(trim($item->nodeValue))) {
            $tmpnames .= escapeshellarg($tmpname . ".xml") . " ";

            array_push($tmpfiles, $tmpname.".xml");

            $tmpname=TZR_TMP_DIR.uniqid();
          }
        }
      }
    }
    if(defined('TZR_PRINCE2_PATH') && file_exists(TZR_PRINCE2_PATH)) {
      if ($tmpnames!="") {
        $cmd = TZR_PRINCE2_PATH.$option." --insecure --verbose --input=xhtml --prefix=\"".TZR_PRINCE2_LIB."\" ".$tmpnames." -o " .
          $destfilename ." 2>&1";
        \Seolan\Core\Logs::debug(__METHOD__.$cmd);
        exec($cmd, $ret);
      }
      else {
        $destfilename = "";
        \Seolan\Core\Logs::debug("princeXML2PDF failed : file without content");
      }
    } else {
      \Seolan\Core\Logs::critical("princeXML2PDF failed : PRINCE package absent");
    }
  }
  else {
    convert_charset($content,TZR_INTERNAL_CHARSET,'UTF-8');
    @file_put_contents($tmpname.".xml",$content);
    if(defined('TZR_PRINCE2_PATH') && file_exists(TZR_PRINCE2_PATH)) {
      $cmd = TZR_PRINCE2_PATH.$option." --insecure --verbose --input=xhtml --prefix=\"".TZR_PRINCE2_LIB."\" ".escapeshellarg("$tmpname.xml")." 2>&1";
      \Seolan\Core\Logs::debug(__METHOD__.$cmd);
      exec($cmd, $ret);
    } else {
      \Seolan\Core\Logs::critical("princeXML2PDF failed : PRINCE package absent");
    }
  }
  foreach($ret as $line){
      \Seolan\Core\Logs::debug($line);
  }
  if ($multi) {
    foreach ($tmpfiles as $file) {
      unlink($file);
    }
  }
  else unlink($tmpname.".xml");
  if(!file_exists($destfilename) && !empty($ret)) {
    \Seolan\Core\Logs::critical('princeXML2PDF failed : ' . implode("\n", $ret));
    exit(0);
  }
  return $destfilename;
}

///Tidyse la chaine $content
function tidyString(&$content,$ar=array()) {
  $ar=array_merge(array('quote-nbsp'=>'no', 'wrap'=>0,'indent'=>true,'drop-empty-paras'=>true,'output-xhtml'=>true),$ar);
  if(function_exists('tidy_repair_string')) {
    if(TZR_INTERNAL_CHARSET!='UTF-8') 
        convert_charset($content,TZR_INTERNAL_CHARSET,'UTF-8');
    $content = tidy_repair_string($content,$ar,'utf8');
    if(TZR_INTERNAL_CHARSET!='UTF-8') 
        convert_charset($content,'UTF-8',TZR_INTERNAL_CHARSET);
  } 
}

/// tentative de suppression du contenu javascript dans une chaine de texte en html
function removeJavascript($text) {
  return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $text);
}

// conversion tidysée du fichier de nom $filename ou du contenu xml de $content
// vers PDF en utilisant PRINCE. La fonction rend le nom du fichier
// contenant le pdf en cas de succes.
function princeTidyXML2PDF($filename=NULL,$content="",$tidyopt=NULL, $option=NULL, $multi=false) {
  if ($multi) {
    foreach ($content as $key => $value) {
      if($tidyopt)
	tidyString($content[$key],$tidyopt);
      else
	tidyString($content[$key]);
    }
  }
  else {
    if($tidyopt)
      tidyString($content,$tidyopt);
    else
      tidyString($content);
  }
  $tmpname=princeXML2PDF($filename,$content, $option, $multi);
  return $tmpname;
}

function OutputIsXHTML() {
  return (substr(TZR_DEFAULT_OUTPUT,0,5)=='XHTML');
}
function OutputIsHTML() {
  return (substr(TZR_DEFAULT_OUTPUT,0,4)=='HTML');
}
function isInteger($i) {
  return is_int($i) || ctype_digit($i);
}

function removeaccents($string) {
   $tab=array('À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
              'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
              'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
              'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
              'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
              'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
              'Ç'=>'c','ç'=>'c',
              'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
              'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
              'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
              'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
              'ÿ'=>'y',
              'Ñ'=>'n','ñ'=>'n'
              );
  return strtr($string, $tab);
}

/**
 * Retourne la valeur simplifiée d'une chaine de caractère (pour les alias de page par exemple)
 * @param string $alias : chaîne à transformer
 * @param bool $lowerize string : le tout en minuscule
 * @param bool $characters_only : supprime également les - et les _
 * @return string : valeur simplifiée de la chaine passée en paramètre
 */
function rewriteToAscii($alias, $lowerize=true, $characters_only=false) {
  $alias=removeaccents(trim($alias));
  if($lowerize) $alias=strtolower($alias);
  if($characters_only) {
    return preg_replace('@([^A-Za-z0-9]+)@', '', $alias);
  }
  return preg_replace(['/[\.]+/', '/[^a-zA-Z0-9_]+/', '/^-|-$/'], ['_', '-', ''], $alias);
}

/**
 * Retourne la valeur simplifiée d'une chaine de caractère pour les noms de fichier
 * @param $alias string : chaîne à transformer
 * @param $lowerize bool : met le tout en minuscule
 * @return string valeur simplifiée de la chaine passée en paramètre
 */
function rewriteToFilename($alias,$lowerize=false,$removePointsAndWhites=false) {
  $alias=removeaccents(trim($alias));
  if($lowerize) $alias=strtolower($alias);
  $alias=preg_replace('/([[:space:]]{2,10})/','',$alias);
  $alias=preg_replace('/([\'\/]+)/','-',$alias);
  $alias=preg_replace('@([^a-z0-9_\.[:space:]-]+)@i','',$alias);
  if($removePointsAndWhites) $alias=preg_replace('@([\.[:space:]]+)@','-',$alias);
  return $alias;
}

/**
 * Retourne la valeur simplifiée d'une chaine de caractère pour les URL
 * @param $alias string : chaîne à transformer
 * @param $remove_stop_words bool : exclut les mots à ignorer selon la langue (Français: le, la, un, de... / English: the, a, an...)
 * @param $lang string : langue à utiliser pour les mots à ignorer (par défaut prend la langue en cours)
 */
function rewriteToUrl($alias, $remove_stop_words = true, $lang = null) {
  $alias = strtolower(removeaccents(trim($alias)));
  if ($remove_stop_words) {
    $stop_words = preg_split('/[^\w]+/', \Seolan\Core\Lang::getLocaleProp('stop_words', $lang));
    $alias = '-'.$alias.'-';
    $alias_words = preg_split('/[^\w]+/', $alias);
    foreach ($alias_words as $word) {
      if (in_array($word, $stop_words)) {
        $alias = preg_replace('/[^\w]'.$word.'([^\w])/','$1', $alias);
      }
    }
  }
  return preg_replace('@(^-|-$)@','',preg_replace('@[^\w]+@', '-', $alias));
}

function getBytes($size){
  switch(substr(strtolower($size),-1)){
  case 'g':
    return (int)$size*1073741824;
  case 'm':
    return (int)$size*1048576;
  case 'k':
    return (int)$size*1024;
  default:
    return $size;
  }
}

function getStringBytes($size){
  $u='o';
  if($size>1073741824){
    $size=$size/1073741824;
    $u='Go';
  }elseif($size>1048576){
    $size=$size/1048576;
    $u='Mo';
  }elseif($size>1024){
    $size=$size/1024;
    $u='Ko';
  }
  return round($size,2).' '.$u;
}

function decode_unicode_url($str) {
  $res='';
  $i=0;
  $max=strlen($str);
  while ($i <= $max) {
    $character = $str[$i];
    if ($character == '%' && $str[$i + 1] == 'u') {
      $value=hexdec(substr($str, $i + 2, 4));
      $i+=6;

      if ($value < 0x0080) // 1 byte: 0xxxxxxx
	$character=chr($value);
      else if ($value < 0x0800) // 2 bytes: 110xxxxx 10xxxxxx
	$character=chr((($value & 0x07c0) >> 6) | 0xc0).chr(($value & 0x3f) | 0x80);
      else // 3 bytes: 1110xxxx 10xxxxxx 10xxxxxx
	$character=chr((($value & 0xf000) >> 12) | 0xe0).chr((($value & 0x0fc0) >> 6) | 0x80).chr(($value & 0x3f) | 0x80);
    }
    else
      $i++;
    $res.= $character;
  }
  return $res;
}


function cp1252_replace(&$text) {
  $from=array("\r\n","\x82","\x83","\x84","\x85","\x88","\x89","\x8B","\x8C","\x91","\x92","\x93","\x94","\x95",
              "\x96","\x97","\x98","\x99","\x9B","\x96");
  $to=array("\n",",","f",",,","...","^","\"","<","Oe","`", "'","\"","\"","*","-","--","~","(tm)",">","oe");
  $text=str_replace($from, $to, $text);
}
function utf8_cp1252_replace(&$text,$addslashes=false) {
  $from=array('’','“','”');
  if($addslashes) $to=array("\'",'\"','\"');
  else $to=array("'",'"','"');
  $text=str_replace($from, $to, $text);
}

function dumps_cp1252_replace(&$text) {
  $from=array("\r\n","\x82","\x83","\x84","\x85","\x88","\x89","\x8B","\x8C","\x91","\x92","\x93","\x94","\x95",
	      "\x96","\x97","\x98","\x99","\x9B","\x96");
  $to=array("\n",",","f",",,","...","^","\"","<","Oe","`", "\\'","\"","\"","*","-","--","~","(tm)",">","oe");
  $text=str_replace($from, $to, $text);
}

function &cleanStringForXML(&$text){
  $ret=preg_replace('/\x{ffff}/u','',$text);
  return $ret;
}

function convert_charset(&$text, $cssrc, $csdst, $suffix = "//TRANSLIT") {
  if($text && $cssrc && $csdst && $cssrc!=$csdst) {
    $text2=iconv($cssrc,$csdst.$suffix, $text);
    if(!$text2) {
      $text2 = mb_convert_encoding($text, $csdst, $cssrc);
    }
    $text = $text2;
  }
}

// conversion d'un tableau par exmploration recursive du tableau
//

function array_convert_charset(&$v,$key, $arcs) {
  $cssrc = $arcs[0];
  $csdst = $arcs[1];
  if($cssrc == $csdst) return;
  if(empty($cssrc)) return;
  convert_charset($v, $cssrc, $csdst);
}

// Enrichi le decodage html par defaut
function html_entity_decode_utf8($txt){
  if(strpos($txt,'&')===false) return $txt;
  $trans_tbl = array();
  $trans_tbl['&rsquo;']='\'';
  $trans_tbl['&ldquo;']='"';
  $trans_tbl['&rdquo;']='"';
  $trans_tbl['&hellip;']='...';
  $trans_tbl['&bull;']='- ';
  $txt = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $txt);
  return html_entity_decode(strtr($txt, $trans_tbl), ENT_COMPAT | ENT_HTML401, 'UTF-8');
}
function getTextFromHTML($text){
  return strip_tags(preg_replace('@<br>|<br/>|<script[^>]*?.*?</script>|\[<a.*</a>\]@siuU', ' ', html_entity_decode_utf8($text)));
}

function newPassword() {
  \Seolan\Core\System::loadVendor('password_generator/Password.php');
  $pwd = Password::getInstance()->setAlphabet(array('a','e','i','o','u','y'),
		  array('b','c','d','f','g','h','j','k','l','m','n','p',
			'q','r','s','t','v','w','x','z'))->
    generate(array(TZR_PASSWORD_NBLETTERS,TZR_PASSWORD_NBDIGITS),1,Password::RANDOM);
  $symbols = array(',', ';', ':', '?', '%', '@', '&');
  $char_at = mt_rand(0, TZR_PASSWORD_NBLETTERS + TZR_PASSWORD_NBDIGITS - 1); 
  $pwd[$char_at] = $symbols[mt_rand(0, count($symbols)-1)]; 
  return $pwd;
}

function &getTextContentFrom($mime, $filename) {
  $empty="";
  if(!file_exists($filename) || empty($mime)) return $empty;
  if(in_array($mime,array('application/msword','application/vnd.msword','application/vnd.ms-word')))
    return getTextContentFromWord($filename);
  if(in_array($mime,array('application/msexcel','application/vnd.msexcel','application/vnd.ms-excel')))
    return getTextContentFromExcel($filename);
  if(in_array($mime,array('application/vnd.oasis.opendocument.spreadsheet','application/x-vnd.oasis.opendocument.spreadsheet')))
    return getTextContentFromODS($filename);
  if(in_array($mime,array('application/vnd.oasis.opendocument.text','application/x-vnd.oasis.opendocument.text')))
    return getTextContentFromODT($filename);
  if($mime=='application/pdf') return getTextContentFromPDF($filename);
  if($mime=='text/plain') return getTextContentFromTxt($filename);
  return $empty;
}

function &getTextContentFromPDF($filename) {
  $f='tzr-'.uniqid();
  system("pdftotext -layout -nopgbrk -enc UTF-8 ".escapeshellarg($filename).' '.escapeshellarg(TZR_TMP_DIR.$f));
  $text=file_get_contents(TZR_TMP_DIR.$f);
  unlink(TZR_TMP_DIR.$f);
  return $text;
}

function &getTextContentFromWord($filename) {
  return getTextWithOpenOffice($filename,'.doc');
}
function &getTextContentFromExcel($filename) {
  return getTextWithOpenOffice($filename,'.xls','.csv');
}
function &getTextContentFromODS($filename) {
  return getTextWithOpenOffice($filename,'.ods','.csv');
}
function &getTextContentFromODT($filename) {
  return getTextWithOpenOffice($filename,'.odt');
}
function &getTextWithOpenOffice($filename,$ext,$out='.txt'){
  if(file_exists(TZR_OPENOFFICE_SPOOL_DIR.'in')) {
    $g=uniqid();
    $source=TZR_OPENOFFICE_SPOOL_DIR.'in/'.$g.$ext;
    $format=TZR_OPENOFFICE_SPOOL_DIR.'in/'.$g.$ext.'.toconvert';
    $destination=TZR_OPENOFFICE_SPOOL_DIR.'out/'.$g.$out;
    copy($filename, $source);
    file_put_contents($format, substr($out,1));
    $t=0;
    while(!file_exists($destination) && ($t<30)) {
      sleep(1);
      $t++;
    }
    if($t==30){
      unlink($source);
      unlink($format);
      $text='';
      return $text;
    }
    $a=array();
    $filetype=exec("file -ib $destination");
    if(strpos($filetype,'charset=utf-8')===FALSE && strpos($filetype,'charset=us-ascii')===FALSE) {
      rename($destination, TZR_TMP_DIR.$g.$out.'.1');
      $destination=TZR_TMP_DIR.$g.$out;
      system("iconv -f latin1 -t utf8 ".escapeshellarg($destination.'.1').">".escapeshellarg($destination));
      unlink($destination.'.1');
    }
    $text=file_get_contents($destination);
    unlink($destination);
    return $text;
  } else {
    \Seolan\Core\Logs::critical(__METHOD__, 'legacy converter');
    $f='tzr-'.uniqid().'.txt';
    $f2='tzr-'.uniqid().'2.txt';
    $f3='tzr-'.uniqid().'3'.$ext;
    copy($filename, TZR_TMP_DIR.$f3);
    system("python ".$GLOBALS['LIBTHEZORRO']."DocumentConverter.py '".TZR_TMP_DIR.$f3."' '".TZR_TMP_DIR.$f."'");
    system("iconv -f latin1 -t utf8 '".TZR_TMP_DIR."$f'>".TZR_TMP_DIR.$f2);
    unlink(TZR_TMP_DIR.$f);
    unlink(TZR_TMP_DIR.$f3);
    $text=file_get_contents(TZR_TMP_DIR.$f2);
    unlink(TZR_TMP_DIR.$f2);
    sleep(1);
    return $text;
  }
}

function &getTextContentFromTxt($filename) {
  $charset=getFileCharset($filename);
  $text=file_get_contents($filename);
  convert_charset($text, $charset, 'UTF-8');
  return $text;
}


/**
 * Conversion d'un document du type PDF en image.
 * @param filename Chemin du fichier source
 * @param page Numéro de la page à récupérer
 * @param out Extension du fichier de sortie
 * @param unlink Si oui, retourne le contenu du fichier temporaire après l'avoir supprimé, sinon le chemin du fichier temporaire
 * @return string Si unlink, le contenu du fichier généré, sinon le chemin du fichier généré
 */
function &getImageFromPdfDocument($filename, $page, $out='.png', $unlink=true){
  $tmpFilename = 'viewer'.uniqid();
  $imageFileNameTmp = TZR_TMP_DIR.$tmpFilename.($page?'-'.$page:'');
  $imageFileName = $imageFileNameTmp.$out;

  if (file_exists($filename) === false || empty($out)) {
    return false;
  }

  $regenerate = true;
  if (file_exists($imageFileName) === true) {
    $regenerate = filemtime($filename) > filemtime($imageFileName) ? true : false;  
  }

  if ($regenerate) {
    $res = exec("pdftoppm -png -f ".escapeshellarg($page)." -singlefile ".escapeshellarg($filename)." ".escapeshellarg($imageFileNameTmp), $output, $retval);
    if ($res or !file_exists($imageFileName))
        return false;
  }

  if ($unlink) {
    $content = file_get_contents($imageFileName);
    unlink($imageFileName);
    return $content;
  }
  return $imageFileName;
}

/**
 * Conversion d'un document du type bureautique lisible par libreoffice en image.
 * @param filename Chemin du fichier source
 * @param page Numéro de la page à récupérer
 * @param out Extension du fichier de sortie
 * @param unlink Si oui, retourne le contenu du fichier temporaire après l'avoir supprimé, sinon le chemin du fichier temporaire
 * @return string : si unlink, le contenu du fichier généré, sinon le chemin du fichier généré
 */
function &getImageFromOpenOfficeDocument($fileName, $page, $out='.png', $unlink=true){
  $tmpFileName = 'viewer'.uniqid();
  $imageFileNameTmp = TZR_TMP_DIR.$tmpFileName.$out;
  $imageFileName = TZR_TMP_DIR.$tmpFileName.($page?'-'.$page:'').$out;

  if (file_exists($fileName) === false || empty($out)) {
    return false;
  }

  $regenerate = true;
  if (file_exists($imageFileName) === true) {
    $regenerate = filemtime($fileName) > filemtime($imageFileName) ? true : false;
  }

  if ($regenerate) {
    if(\Seolan\Field\File\Viewer\Viewer::$csxofficeconverterIsOk) {
      $source = TZR_VIEWER_SPOOL_DIR.'in/'.$tmpFileName;
      $format = TZR_VIEWER_SPOOL_DIR.'in/'.$tmpFileName.'.toconvert';
      $err = TZR_VIEWER_SPOOL_DIR.'in/'.$tmpFileName.'.err';
      $destination = TZR_VIEWER_SPOOL_DIR.'out/'.$tmpFileName.$out;

      copy($fileName, $source);
      file_put_contents($format, '#XSOC'.PHP_EOL, FILE_APPEND | LOCK_EX);
      file_put_contents($format, 'ext='.substr($out,1).PHP_EOL, FILE_APPEND | LOCK_EX);
      file_put_contents($format, 'page='.$page.PHP_EOL, FILE_APPEND | LOCK_EX);
      file_put_contents($format, '#'.TZR_VIEWER_CONVERTER.PHP_EOL, FILE_APPEND | LOCK_EX);
      $t=0;
      while(!file_exists($destination) && ($t<30)) {
        if(file_exists($err)) {
          $t = PHP_INT_MAX;
          break;
        }
        sleep(1);
        $t++;
      }
      if($t>=30){
        unlink($source);
        unlink($format);
        return false;
      }

      rename($destination, $imageFileName);
    } else {
      $res = exec('python3 '.TZR_VIEWER_CONVERTER.' '.escapeshellarg($fileName)." ".escapeshellarg($imageFileNameTmp)." export $page ".escapeshellarg(TZR_VIEWER_TMP_DIR)." debug ".TZR_VIEWER_CONVERTER_PORT, $output, $retval);
    }
  }

  if ($unlink) {
    $content = file_get_contents($imageFileName);
    unlink($imageFileName);
    return $content;
  }
  
  return $imageFileName;
}

function getFileCharset($filename){
  $res='';
  exec('file -i '.escapeshellarg($filename),$res);
  return substr($res[0],strpos($res[0],'charset=')+8);
}

function cryptAndSignFile($filename,$destfile=NULL){
  global $TZR_GPGCRYPT_KEY_FILES;
  global $TZR_GPGSIGN_KEY_FILES;

  if(empty($destfile)) $destfile=$filename;
  if(!empty($TZR_GPGCRYPT_KEY_FILES) || !empty($TZR_GPGSIGN_KEY_FILES)){
    $gnupg=new gnupg();
    $pubk=$privk=array();
    foreach($TZR_GPGCRYPT_KEY_FILES as $pkey){
      $pub=file_get_contents($pkey);
      $act=$pubk[]=$gnupg->import($pub);
      $gnupg->addencryptkey($act['fingerprint']);
    }
    foreach($TZR_GPGSIGN_KEY_FILES as $pkey){
      $priv=file_get_contents($pkey);
      $act=$privk[]=$gnupg->import($priv);
      $gnupg->addsignkey($act['fingerprint']);
    }
    $content=file_get_contents($filename);
    if(!empty($TZR_GPGCRYPT_KEY_FILES) && !empty($TZR_GPGSIGN_KEY_FILES)){
      file_put_contents($destfile,$gnupg->encryptsign($content));
    }elseif(!empty($TZR_GPGCRYPT_KEY_FILES)){
      file_put_contents($destfile,$gnupg->encrypt($content));
    }else{
      file_put_contents($destfile,$gnupg->sign($content));
    }
    foreach($pubk as $key) $gnupg->deletekey($key['fingerprint']);
    foreach($privk as $key) $gnupg->deletekey($key['fingerprint'],true);
  }
}

function &decyptAndVerifyFile($filename,$destfile=NULL){
  global $TZR_GPGDECRYPT_KEY_FILE;
  global $TZR_GPGVERIFY_KEY_FILE;

  $gnupg=new gnupg();
  if(!empty($TZR_GPGDECRYPT_KEY_FILE)){
    $privk=$gnupg->import(file_get_contents($TZR_GPGDECRYPT_KEY_FILE));
    $gnupg->adddecryptkey($privk['fingerprint'],'');
  }
  if(!empty($TZR_GPGVERIFY_KEY_FILE)){
    $pubk=$gnupg->import(file_get_contents($TZR_GPGVERIFY_KEY_FILE));
  }

  if(!empty($TZR_GPGDECRYPT_KEY_FILE) && !empty($TZR_GPGVERIFY_KEY_FILE)){ // Decryptage + signature
    $content='';
    $gret=$gnupg->decryptverify(file_get_contents($filename),$content);
    $ret=false;
    if(!empty($gret)){
      foreach($gret as $key){
	if($pubk['fingerprint']==$key['fingerprint']) $ret=true;
      }
    }
  }elseif(!empty($TZR_GPGDECRYPT_KEY_FILE)){ // Decryptage seulement
    $content=$gnupg->decrypt(file_get_contents($filename));
    if($content!==false) $ret=true;
    else $ret=false;
  }else{ // Signature seulement
    $content='';
    $gret=$gnupg->verify(file_get_contents($filename),false,$content);
    $ret=false;
    foreach($gret as $key){
      if($pubk['fingerprint']==$key['fingerprint']) $ret=true;
    }
  }
  if(!empty($privk)) $gnupg->deletekey($privk['fingerprint'],true);
  if(!empty($pubk)) $gnupg->deletekey($pubk['fingerprint']);

  if(!$ret) return $ret;
  else{
    if($destfile) file_put_contents($destfile,$content);
    return $content;
  }
}

/// Retourne un code de validation pour outre-passer la securite du downloader
function getDownloaderToken($filename,$mime){
  return sha1($filename.'-'.$mime);
}

/// Recherche les informations associees aux fichiers attaches a un display
function &getFilesDetails(&$d,$withsize=false) {
  $files=array();
  $k=0;
  if(is_array($d['fields_object'])) {
    foreach($d['fields_object'] as $i=>&$v) {
      if($v->fielddef instanceof \Seolan\Field\File\File) {
        if(!$v->fielddef->multivalued){
          if(!empty($v->filename)){
            $files[$k]['field'] = $v->field;
            $files[$k]['url']=$v->url;
            $files[$k]['shortfilename']=$v->shortfilename;
            $files[$k]['filename']=$v->filename;
            $files[$k]['name']=$v->originalname;
            $files[$k]['title'] = $v->title;
            $files[$k]['mime']=$v->mime;
            if($withsize) $files[$k]['filesize']=filesize($v->filename);
            $files[$k]['crypt']=$v->fielddef->crypt;
            $k++;
          }
        } else {
          foreach($v->catalog as $j=>&$e) {
            if(!empty($e->filename)){
              $files[$k]['field'] = $v->field;
              $files[$k]['url']=$e->url;
              $files[$k]['shortfilename']=$e->shortfilename;
              $files[$k]['filename']=$e->filename;
              $files[$k]['name']=$e->originalname;
              $files[$k]['title'] = $e->title;
              $files[$k]['mime']=$e->mime;
              if($withsize) $files[$k]['filesize']=filesize($e->filename);
              $files[$k]['crypt']=$v->fielddef->crypt;
              $k++;
            }
          }
        }
      }
    }
  }
  return $files;
}
/// Recupere tout le contenu des fichiers d'un display
function &getFilesContent(&$d, $fields=NULL, $sizelimit=-1 /* 1 format acceptable par getBytes */){
  $files=getFilesDetails($d,($sizelimit!=-1));
  $text='';
  if ($sizelimit != -1)
    $sizelimit = getBytes($sizelimit);
  foreach($files as $file){
    if (isset($fields) && !in_array($file['field'], $fields))
      continue;
    if ($sizelimit != -1 && $file['filesize'] > $sizelimit)
      continue;
    if($file['crypt']){
      $fn=TZR_TMP_DIR.uniqid();
      $ret=decyptAndVerifyFile($file['filename'],$fn);
      if($ret===false) continue;
    }else{
      $fn=$file['filename'];
    }
    $text.=getTextContentFrom($file['mime'],$fn).' ';
    if($file['crypt']) @unlink($fn);
  }
  return $text;
}

function mysqlDateToTS($mdate) {
  $exploded=explode('-',$mdate);
  return mktime(0, 0, 0,$exploded[1],$exploded[2],$exploded['0']);
}

function getExcelReader($file,$ext=''){
  if($ext) $info=pathinfo($ext);
  else $info=pathinfo($file);
  if($info['extension']=='csv'){
    $reader=new PHPExcel_Reader_CSV();
    $reader->setDelimiter(';');
    $reader->setEnclosure('"');
  }elseif($info['extension']=='xls'){
    $reader=new PHPExcel_Reader_Excel5();
  }elseif($info['extension']=='xlsx'){
    $reader=new PHPExcel_Reader_Excel2007();
  }
  return $reader->load($file);
}

function getCPUCores() {
  return (int) exec('nproc');
}

/// rend vrai si le fichier $filename sur le serveur $ftp semble avoir une taille stable
function waitForFileCompletion($ftp,$filename,$wait=2000) {
  $s=ftp_size($ftp,$filename);
  usleep($wait);
  if($s!=ftp_size($ftp,$filename)) return false;
  else return true;
}
/// ajoute aun suffix au nom d'un fichier avant l'extension
function addFileSuffix($filename,$suffix){
  $pos = strrpos($filename,'.');
  if($pos===false)
    return $filename.$suffix;
  $part1 = substr($filename,0,$pos);
  $part2 = substr($filename,$pos);
  return $part1.$suffix.$part2;
} 
/// fonction de download traitant le byte-range
function download($file, $mime) {
  header('Last-Modified: '.gmdate('D, d M Y H:i:s T', filemtime($file)));
  header('Accept-Ranges: bytes');
  if (isset($_SERVER['HTTP_RANGE'])) {
    \Seolan\Core\System::loadVendor('ByteServing/byteserving.php');
    byteserve($file, $mime);
  } else {
    header('Content-type: '.$mime);
    header('Content-Length: '.filesize($file));
    readfile($file);
  }
}
/// Retourne vrai si le paramètre est vide ou si il contient TZR_unchanged
function emptyOrUnchanged(&$o){
  if(empty($o)) return true;
  if(is_string($o) && $o===TZR_UNCHANGED) return true;
  return false;
}


// Local version of escapeshellarg() with this property:
// - not munging UTF8 strings despite locale not being UTF8
//   (see http://foldoc.org/munge )
// Other properties:
// - maps empty string to '' (well, current implementation of escapeshellarg() also does it)
// - UNIX-specific
//
// Return string
function single_quote_into_sh_arg ($s) {
  // Chars
  $backslash = "\\";
  $single_quote = "'";
  
  // Regexp
  $delimiter = '/';
  
  $regexp = $delimiter . $single_quote . $delimiter;
  
  // Replacement
  $backslash_quoted_for_replacement
    = $backslash     // quote next...
    . $backslash     // ... hence literal
    ;
  
  $replacement
    = $single_quote      // close
    . $backslash_quoted_for_replacement  // quote next...
    . $single_quote      // ... hence literal
    . $single_quote      // open
    ;
  
  // Go !
  
  return $single_quote     // open
    . preg_replace ($regexp, $replacement, $s)
    . $single_quote;     // close
  
  // TODO handle preg_replace() returning null
}

/// recherche de tous les ancetres d'une classe
function getAncestors ($class) {
     for ($classes[] = $class; $class = get_parent_class ($class); $classes[] = $class);
     return $classes;
}

if (!function_exists('mb_trim')) {
  /** 
   * JUG 20133110 FROM http://php.net/manual/en/ref.mbstring.php discussion
   * 
   * Trim characters from either (or both) ends of a string in a way that is 
   * multibyte-friendly. 
   * 
   * Mostly, this behaves exactly like trim() would: for example supplying 'abc' as 
   * the charlist will trim all 'a', 'b' and 'c' chars from the string, with, of 
   * course, the added bonus that you can put unicode characters in the charlist. 
   * 
   * We are using a PCRE character-class to do the trimming in a unicode-aware 
   * way, so we must escape ^, \, - and ] which have special meanings here. 
   * As you would expect, a single \ in the charlist is interpretted as 
   * "trim backslashes" (and duly escaped into a double-\ ). Under most circumstances 
   * you can ignore this detail. 
   * 
   * As a bonus, however, we also allow PCRE special character-classes (such as '\s') 
   * because they can be extremely useful when dealing with UCS. '\pZ', for example, 
   * matches every 'separator' character defined in Unicode, including non-breaking 
   * and zero-width spaces. 
   * 
   * It doesn't make sense to have two or more of the same character in a character 
   * class, therefore we interpret a double \ in the character list to mean a 
   * single \ in the regex, allowing you to safely mix normal characters with PCRE 
   * special classes. 
   * 
   * *Be careful* when using this bonus feature, as PHP also interprets backslashes 
   * as escape characters before they are even seen by the regex. Therefore, to 
   * specify '\\s' in the regex (which will be converted to the special character 
   * class '\s' for trimming), you will usually have to put *4* backslashes in the 
   * PHP code - as you can see from the default value of $charlist. 
   * 
   * @param string 
   * @param charlist list of characters to remove from the ends of this string. 
   * @param boolean trim the left? 
   * @param boolean trim the right? 
   * @return String 
   */ 
  function mb_trim($string, $charlist='\\\\s', $ltrim=true, $rtrim=true){ 
    $both_ends = $ltrim && $rtrim; 
    $char_class_inner = preg_replace(array( '/[\^\-\]\\\]/S', '/\\\{4}/S' ), 
				     array( '\\\\\\0', '\\' ), 
				   $charlist 
				     ); 
    $work_horse = '[' . $char_class_inner . ']+'; 
    $ltrim && $left_pattern = '^' . $work_horse; 
    $rtrim && $right_pattern = $work_horse . '$'; 
    if($both_ends){ 
      $pattern_middle = $left_pattern . '|' . $right_pattern; 
    }elseif($ltrim){ 
      $pattern_middle = $left_pattern; 
    }else{
      $pattern_middle = $right_pattern;
    }
    return preg_replace("/$pattern_middle/usSD", '', $string) ; 
 } 
}

/**
 * @author Bastien Sevajol (bastien.sevajol@xsalto.com)
 * @desc Lance une exception si la constante DEBUG === True, sinon procède
 * a un exit(0)
 * @param string $message Message en cas de lancement de l'exception
 * @param string $header Definis un header avant d'éxecuter exit(0), permet par
 * exemple de personaliser la page d'erreur avec Apache.
 * @throws \Exception
 */
function critical_exit($message = "Critical error, please read trace for debug", $header = 'HTTP/1.1 500 Internal Server Error', $disp = False)
{
	
  if (is_int($header)) $header = get_header_for_code($header);
  if ($header) header($header);
  if ($disp) echo $message;
  throw new \Exception($message);
  exit(0);
}

function get_header_for_code($code)
{
  switch ($code) {
    case 404:
    case 5404:
      return 'HTTP/1.1 404 Not Found';
    case 403:
    case 5403:
      return 'HTTP/1.1 403 Forbidden';
    case 500:
      return 'HTTP/1.1 500 Internal Server Error';
    default:
      return '';
  }
}

/// Redirige sur un url avec un code donné
function redirecTo($url,$code='302'){
  header('Location:'.htmlspecialchars_decode($url),true,$code);
  exit(0);
}

/// obtenir l'url de la page
function getCurrentPageUrl() {
  $url  = @( $_SERVER['HTTPS'] != 'on' ) ? 'http://'.@$_SERVER['SERVER_NAME'] :  'https://'.@$_SERVER['SERVER_NAME'];
  $url .= in_array(@$_SERVER['SERVER_PORT'], array( '80' , '443' )) ? ':' . @$_SERVER['SERVER_PORT'] : '';
  $url .= ($_SERVER['REQUEST_URI']??'');
  return $url;
}

/// Insere un element dans un tableau apres un index donné
function array_insert_after(&$array,$key,$new_key,$new_value){
  if(array_key_exists($key,$array)){
    $new=array();
    foreach($array as $k=>$value) {
      $new[$k]=$value;
      if($k===$key) {
        $new[$new_key]=$new_value;
      }
    }
    $array=$new;
  }
}
/// Retourne l'erreur json
function jsonError(){
  switch (json_last_error()) {
  case JSON_ERROR_NONE:
    return 'Aucune erreur';
    break;
  case JSON_ERROR_DEPTH:
    echo 'Profondeur maximale atteinte';
    break;
  case JSON_ERROR_STATE_MISMATCH:
    return 'Inadéquation des modes ou underflow';
    break;
  case JSON_ERROR_CTRL_CHAR:
    return 'Erreur lors du contrôle des caractères';
    break;
  case JSON_ERROR_SYNTAX:
    return 'Erreur de syntaxe ; JSON malformé';
    break;
  case JSON_ERROR_UTF8:
    return 'Caractères UTF-8 malformés, probablement une erreur d\'encodage';
    break;
  default:
    return 'Erreur inconnue';
    break;
  }
}
/// Retourne une réponse de type json
function returnJson($ar){
  header("Content-Type: application/json");
  echo json_encode($ar);
  exit(0);
}

/// Verification de l'existence du processus dont l'identifiant est $pid
function checkPid($pid) {
  if (empty($pid))
    return false;
  return file_exists('/proc/'.$pid);
}

/// Detecte si le script courant est exécuté depuis un appel ajax
function isAjaxContext(){
  return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])=='xmlhttprequest');
}


/// mocker le strip_tags car celui de smarty ne repond pas au probleme (pas de prise en compte de l'argument tags)
function mock_striptags($s1, $tags) {
  return strip_tags($s1, $tags);
}

/// Teste si un champ est présent dans un tableau et est non vide
function fieldIsInArray($field,&$array){
    return ((!empty($array[$field]) || $array[$field] === '0') && $array[$field]!=array('0'=>'') && $array[$field]!=array('0'=>'Foo'));
}
 
/// generation d'une chaine aleatoire avec un masque
function generateRandomString( $length=10, $mask='**********' ) {
  $type = [
    'A' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    'a' => 'abcdefghijkmnopqrstuvwxyz',
    '9' => '23456789',
    '*' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz',
    '-' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'];
  for( $i = 0; $i < $length; $i++ ) {
    $size = strlen( $type[$mask[$i]] );
    $str .= $type[$mask[$i]][rand( 0, $size - 1 ) ];
  }
  
  return $str;
}
// génération d'un QRCode
function QRCode($string, $size=4, $ecc=2) {
  \Seolan\Core\System::loadVendor('QR/class.qrcode.php');
  $tmpfile = TZR_TMP_DIR . uniqid();
  QRcode::png($string, $tmpfile, $ecc, $size);
  $img =  '<img src="data:image/png;base64,' . base64_encode(file_get_contents($tmpfile)) . '" alt="' . $string . '" />';
  unlink($tmpfile);
  return $img;
}

function getFieldTypeFromV8($fieldtype){
  include('src/Core/upgrades/Shell/fileslist.php');
  if($fields && array_key_exists($fieldtype,$fields)){
    return $fields[$fieldtype];
  }
  return $fieldtype;
}

/// correction des variables d'environnement du SERVER pour prendre en compte le proxy
function checkSERVEREnvVariables() {
  if(function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
  }
  else {
    $headers = $_SERVER;
  }
  if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $_SERVER['REMOTE_ADDR']=$_SERVER['HTTP_X_FORWARDED_FOR'];
  if(isset($_SERVER['HTTP_X_FORWARDED_HOST'])) $_SERVER['HTTP_HOST']=$_SERVER['HTTP_X_FORWARDED_HOST'];
  if (@$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || (array_key_exists('X-Forwarded-Proto', $headers) && strtolower($headers['X-Forwarded-Proto']) == 'https')) {
    $_SERVER['REQUEST_SCHEME'] = 'https';
    $_SERVER['HTTPS']='on';
    $_SERVER['SERVER_PORT']='443';
  }
  if (!empty($_SERVER['REQUEST_URI'])) {
    $pattern = '(_|_?nocache|_cachepolicy|forcecache|cacheoid|sessionid)=[^&]+';
    $_SERVER['REQUEST_URI'] = preg_replace([
      "/&amp;/",
      "/\?$pattern&/",
      "/\?$pattern/",
      "/&$pattern/",
      "/(\?|&)$/"
      ], [
      '&',
      '?',
      '',
      '',
      ''
      ], $_SERVER['REQUEST_URI']
    );
  }
}
function getRemoteAddr() {
  return $_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR'];
}
function makeUrl($url, $addParameters){
  $u = parse_url($url);
  $p = [];
  if(!empty($u['query'])) parse_str($u['query'], $p);
  $p = array_merge($p, $addParameters);
  $url = (isset($u['scheme'])?$u['scheme'].'://':'').($u['host']??'').(isset($u['port'])?':'.$u['port']:'').$u['path'].'?'.http_build_query($p);
  return $url;
}

/**
 * Wrapper pour récupérer facilement la traduction d'un libellé
 * @see \Seolan\Core\Labels::getText
 */
function __() {
  return call_user_func_array(["\Seolan\Core\Labels", 'getText'], func_get_args());
}

/**
 * Wrapper pour récupérer facilement la traduction d'un libellé par sa variable
 * @see \Seolan\Core\Labels::get
 */
function _v() {
  try{
    return call_user_func_array(["\Seolan\Core\Labels", 'get'], func_get_args());
  }catch(\Exception $e){
    return "'".func_get_args()[0]."'";
  }
}

/**
 * Wrapper pour récupérer facilement la traduction d'un libellé singulier ou pluriel
 * @see \Seolan\Core\Labels::getSingleOrPluralText
 */
function _n() {
  return call_user_func_array(["\Seolan\Core\Labels", 'getSingleOrPluralText'], func_get_args());
}

// Chronometre une fonction.
// Utilisation : chrono($obj, 'nomFonction', array($param1, $param2 ...)).
// ex :
// Avant : $ofieldvalue[$myi]=$v->pquery($initval, $opts1);
// Après : $ofieldvalue[$myi]=chrono($v, "pquery", array($initval, $opts1));
function chrono($obj, $func, $params) {
  $message = '';
  if(!$obj || !is_object($obj)) {
    $start = microtime(true);
    $ret = call_user_func_array($func, $params);
    $end = microtime(true);
    $message = "Fonction $func : ";
  }
  else {
    $start = microtime(true);
    $ret = call_user_func_array(array($obj, $func), $params);
    $end = microtime(true);
    if    (is_a($obj, "\Seolan\Core\DataSource\DataSource")) $message = "Datasource " . $obj->getTable() . " - $func : ";
    elseif(is_a($obj, "\Seolan\Module\Table\Table"))         $message = "Module " . $obj->table . " - $func : ";
    elseif(is_a($obj, "\Seolan\Core\Field\Field"))           $message = "Champ " . $obj->field . " (" . $obj->table . ") - $func : ";
    else                                                     $message = "Objet " . get_class($obj) . " - $func : ";
  }

  \Seolan\Core\Logs::critical('Chrono ', $message . ($end-$start) . 's.');

  return $ret;
}
/**
 * fichiers sécurisés : tous, par table, par champ
 * sauf exceptions fichiers 'publics'
 */
function isSecureField($table, $field){
  return (empty($GLOBALS['TZR_SECURE']['_public'][$table][$field])) 
    && (!empty($GLOBALS['TZR_SECURE']['_all']) || !empty($GLOBALS['TZR_SECURE'][$table][$field])
	|| (!empty($GLOBALS['TZR_SECURE'][$table]) && $GLOBALS['TZR_SECURE'][$table] == '_all'));
}

function myUrl2cdn($url, $filename = '') {
  if(empty($GLOBALS['CDN_ID'])) return $url;
  if($url[0] == '/') $url = \Seolan\Core\Session::makeDomainName() . $url;
  return url2cdn($GLOBALS['CDN_ID'], $url, $filename);
}

/// rend vrai si le fichier est ok ou ne peux pas être examiné, faux s'il y a un virus
function clamScanFile($filename) {
  $output='';
  $return_code=0;
  exec("clamdscan --no-summary --fdpass ".escapeshellarg($filename) . ' 2>/dev/null', $output, $return_code);
  if($return_code==1) return false;
  else return true;
}

if(!function_exists('geoip_record_by_name')) {
  function geoip_record_by_name($ip) {
    global $LIBTHEZORRO;
    if(!function_exists('geoip_open') || !function_exists('geoip_record_by_addr')) {
      \Seolan\Core\System::loadVendor('geoip/geoip/src/geoip.inc');
      \Seolan\Core\System::loadVendor('geoip/geoip/src/geoipcity.inc');
    }
    $gi = geoip_open($LIBTHEZORRO.'Vendor/geoip/GeoLiteCity.dat', GEOIP_STANDARD);
    $record = geoip_record_by_addr($gi, $ip);

    return json_decode(json_encode($record), true);
  }
}
