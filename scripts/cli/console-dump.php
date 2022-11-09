<?php
$options = getopt('C:hcdntzm::', [], $ind);
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

$tmpdir=TZR_TMP_DIR;
$helpmess=<<<EOF

USAGE :
=======

php-seolan10[version] console-mysqldump.php [options] [dumpfilename]

default dumpfilename 'databasename_yyyymmdd_his'

options: 
  -h print this message
  -c create tzr check point
  if -c :
     dumpfilename will be ignored
     -d with datadir (default : false)
     -n without logs table (default true)
     -m comment
  else (single sql dump) :
     -t dump file in TZR_TMP_DIR '$tmpdir'
     -n do not dump log table
     -z zip dump file

EOF;

if (isset($options['h'])){
  echo($helpmess);
  exit(0);
}

use \Seolan\Module\Management\Management;


if (isset($options['c'])){ // create checkpoint
  $comment='';
  $ar = [
    'withdatabase'=>true,
    'withdatadir'=>false,
    'withlogstable'=>true
  ];
  if (isset($options['m']))
    $comment = $options['m'];
  
  if (isset($options['d']))
    $ar['withdatadir'] = true;
  if (isset($options['n']))
    $ar['withlogstable'] = false;
  
  $checkpointDate =  Management::createTZRCheckpoint($ar, $comment);
  if (isset($checkpointDate)){
    $dir = TZR_VAR2_DIR.'checkpoints/'.$checkpointDate.'/';
    exec("ls -ogh $dir", $lines);
    echo("\ncheckpoint created : $dir\n".implode("\n", $lines)."\n\n");
  } else {
    echo("\nError while creating checkpoint files");
  }

  
} else { // dump single file
  if ($ind == count($argv)){
    $filename = $GLOBALS['DATABASE_NAME'].'_'.date('Ymd_His').'.sql.dump';
  } else {
    $filename = array_pop($argv);
  }
  
  if (isset($options['t'])){
    $dumpfilename = $tmpdir.$filename;
  } else {
    $dumpfilename = "$filename";
  }

  $ar=[
    'no_logs'=>isset($options['n']),
    'file'=>$dumpfilename,
    'zip'=>isset($options['z'])
  ];

  Management::createSQLDump($ar);

  exec("ls -ogh $dumpfilename", $lines);
  echo("\ndump created : \n".implode("\n", $lines)."\n\n");

  echo("\n\n");
  
}


