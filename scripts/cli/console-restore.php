<?php
$options = getopt('C:hctzifde', [], $ind);
define('TZR_ADMINI', 1);
define('TZR_SCHEDULER', 1);

if (empty($options['C'])) {
  $options['C'] = getenv('HOME') . '/../tzr/local.php';
}

if (false === include_once($options['C'])) {
  echo('include local.php failed');
  exit(1);
}
if (false === include_once($LIBTHEZORRO . 'bootstrap.php')) {
  echo('include bootstrap.php failed');
  exit(1);
}
if (false === include_once($LIBTHEZORRO . 'contribs/lib2.php')) {
  echo('include lib2 failed');
  exit(1);
}

$tmpdir=TZR_TMP_DIR;
$helpmess=<<<EOF

USAGE :
=======

php-seolan10[version] console-restore.php [options] [checkpoint name|sqldumpfilename]

options: 
  -h print this message
  -c restore named checkpoint
  if -c :
   -i select checkpoint to restaure in current list
  else
   -t assume file located in $tmpdir
   -z dumpfile is gzipped
   -d drop tables before import
   -e expunge definers before import
EOF;

if (isset($options['h'])){
  echo($helpmess);
  exit(0);
}

use \Seolan\Module\Management\Management;
use \Seolan\Library\MimeTypes;

$fileOrName = null;
if ($ind < count($argv))
  $fileOrName = array_pop($argv);

if (isset($options['c'])){ 

  if (isset($options['i'])){
    // to do liste + selection
  }
  
  if ($fileOrName == null){
    echo("\nno checkpoint name provided nor selected\n\n");
    exit(0);
  }
  list($fileok, $versionok, $mess) = Management::checkTZRCheckpointVersion($fileOrName);
  if (!$fileok){
    echo("\n{$mess}\n\n");
    exit(0);
  }
  if (!$versionok && !isset($options['f'])){
    echo("\n{$mess}\nuse -f option\n\n");
    exit(0);
  }
  echo("\n\trestoring checkpoint {$fileOrName}\n\n");
  Management::restoreTZRCheckpoint($fileOrName);

  echo("\n\trestoration done\n\n");
  
} else { 
  if ($fileOrName == null){
    echo("\nplease provide dump file name\n\n");
    exit(0);
  }
  $dumpfilename = $fileOrName;
  if (isset($options['t'])){
    $dumpfilename = $tmpdir.$dumpfilename;
  }
  if (!file_exists($dumpfilename)){
    echo("\nfile {$dumpfilename} not found\n\n");
    exit(0);
  }

  $ar = ['file'=>$dumpfilename,
	 'zip'=>isset($options['z']),
	 'del'=>isset($options['d']),
	 ];
  
  $mime = MimeTypes::getInstance()->get_file_type($dumpfilename);
  if (in_array($mime, ['application/zip', 'application/gzip']))
    $ar['zip'] = true;

  $expungedFileName = null;
  if (isset($options['e'])){
    if ($ar['zip']===true){
      echo("\n-e with compressed file not planned at this time, unzip the file {$dumpfilename}\n");
      exit(0);
    }
    $expungedFileName = $tmpdir.uniqid('expungedfile');
    $cmd="sed -E -e 's/CREATE DEFINER[^ ]+ (VIEW|PROCEDURE|FUNCTION|TRIGGER)/CREATE \\1/' -e 's/DEFINER[ ]*=[ ]*[^*]*\*/\*/' $dumpfilename > $expungedFileName";
    exec($cmd, $linesres);
    $ar['file'] = $expungedFileName;
  }

  echo("\nrestoring file : {$dumpfilename}\n\n");
  
  Management::loadSQLDump($ar);
  
  if ($expungedFileName != null){
    unlink($expungedFileName);
  }
  
  echo("\nrestoration done\n\n");
}


