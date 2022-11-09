<?php

// envoi d'un message
function msg($m, $l=1) {
  if($l<=$GLOBALS['_opt_verbose']) {
    print $l.":".$m."\n";
  }
}
function newBOID(&$db) {
  $res=& $db->query("select BOID from BASEBASE order by BOID desc");
  $o =& $res->fetchRow();
  return $o->BOID+1;
}
function newMOID(&$db) {
  // Determine le prochain MOID dans la table MSG
  while(true) {
    $moid=md5(uniqid(""));
    $r1=&$db->query("select * from MSG where MOID='$moid'");
    if($r1->numRows()<=0)
      return $moid;
  }
}

function drop_table($table) {
  msg("Dropping $table",1);
  $x = new \Seolan\Library\Database;
  $x->query("drop table $table");
  $x->query("delete from DICT where DTAB='$table'");
  $x->query("delete from MSGS where MTAB='$table'");
  $x->query("delete from BASEBASE where BTAB='$table'");
}
function rename_table($old_name, $new_name) {
  msg("Renaming $old_name to $new_name",1);
  $x = new \Seolan\Library\Database;
  $x->query("rename table $old_name to $new_name");
  $x->query("update DICT set DTAB='$new_name' where DTAB='$old_name'");
  $x->query("update MSGS set MTAB='$new_name' where MTAB='$old_name'");
  $x->query("update BASEBASE set BTAB='$new_name' where BTAB='$old_name'");
  $x->query("update $new_name set KOID=replace(KOID,'$old_name:','$new_name:')");
  if(file_exists($GLOBALS['DATA_DIR'].$old_name)) {
    rename($GLOBALS['DATA_DIR'].$old_name, $GLOBALS['DATA_DIR'].$new_name);
  }
  $x->query("select * from DICT where FTYPE='lien' and TARGET='$old_name'");
  $y = new \Seolan\Library\Database;
  while($o=$x->next_record()) {
    $field=$o->FIELD;
    $table=$o->DTAB;
    $y->query("update DICT set TARGET='$new_name' where DTAB='$old_name' and FIELD='$field'");
    $y->query("update $table set $field=replace($field,'$old_name:','$new_name:')");
  }
  msg("$old_name renamed as $new_name. Restart the procedure.",0);
}
function rename_field($table, $old_name, $new_name, $def, $ty=NULL,$ta=NULL) {
  if(fieldExists($table, $old_name)) {
    msg("Renaming $table:$old_name to $table:$new_name",1);
    $x = new \Seolan\Library\Database;
    $x->query("alter table $table change $old_name $new_name $def");
    $x->query("update DICT set FIELD='$new_name' where FIELD='$old_name' and DTAB='$table'");
    if($ty) {
      $x->query("update DICT set FTYPE='$ty' where FIELD='$old_name' and DTAB='$table'");
      if($ta) {
	$x->query("update DICT set TARGET='$ta' where FIELD='$old_name' and DTAB='$table'");
      }
    }
    $x->query("update MSGS set FIELD='$new_name' where FIELD='$old_name' and MTAB='$table'");
    $x->query("update SETS set FIELD='$new_name' where FIELD='$old_name' and STAB='$table'");
    if(file_exists($GLOBALS['DATA_DIR'].$table."/".$old_name)) {
      rename($GLOBALS['DATA_DIR'].$table."/".$old_name, $GLOBALS['DATA_DIR'].$table."/".$new_name);
    }
  } 
}
function convert_url_field($table, $name) {
  if(!fieldExists($table, $name)) {
    msg("Converting url field $table:$name",1);
    $x = new \Seolan\Library\Database;
    $x->query("alter table $table add $name varchar(250)");
    $x->query("update $table set $name=CONCAT(".$name."_0,';',".$name."_1)");
    $x->query("alter table $table drop ".$name."_0");
    $x->query("alter table $table drop ".$name."_1");
  } 
}


function check_file($koid, $prefix=NULL, $convert=false) {
  msg("Checking $prefix $koid",5);
  if($koid=="TZR_unchanged") return false;
  global $DATA_DIR;
  if(empty($prefix)) $dir=$DATA_DIR; else $dir=$prefix;
  if(!file_exists($dir)) return false;
  if ($dh = opendir($dir)) {
    while (($file = readdir($dh)) !== false) {
      $filename=$prefix."/".$file;
      $filetype=filetype($filename);
      if($file=='.') continue;
      if($file=='..') continue;
      if($filetype=="file") {
	if($file==$koid) { closedir($dh); return $filename; }
	if(eregi("(.*).($file)$",$koid)) {closedir($dh);return $filename;}
      }
      if($filetype=="dir") {
	$r=check_file($koid,$filename);
	if($r) {closedir($dh);return $r;}
      }
    }
    closedir($dh);
    return false;
  }
}

// création d'un ensemble de champs
//
function create_fields(&$x, $ar1) {
  for($i=0;$i<count($ar1);$i++) {
    $ar=array();
    $ar["field"]=$ar1[$i][0];
    if(!fieldExists($x->table,$ar["field"])) {
      msg("Creating ".$x->table.":".$ar["field"],1);
      $ar["ftype"]=$ar1[$i][1];
      $ar["fcount"]=$ar1[$i][2];
      $ar["forder"]=($i+1);
      $ar["compulsory"]=$ar1[$i][3];
      $ar["queryable"]=$ar1[$i][4];
      $ar["browsable"]=$ar1[$i][5];
      $ar["translatable"]=$ar1[$i][6];
      $ar["multivalued"]=$ar1[$i][7];
      $ar["published"]=$ar1[$i][8];
      $lg = TZR_DEFAULT_LANG;
      $ar["label"][$lg]=$ar1[$i][9];
      $ar["label"]['GB']=$ar1[$i][10];
      $ar["target"]=$ar1[$i][11];
      $ar['_todo']="save";
      $x->procNewField($ar);
    }
  }
}

function recurse_chown_chgrp($mypath, $uid, $gid,$metoo=true) {
  $d = opendir ($mypath) ;
  while(($file = readdir($d)) !== false) {
    if ($file != "." && $file != "..") {
      
      $typepath = $mypath . "/" . $file ;
      
      if (filetype ($typepath) == 'dir') {
	recurse_chown_chgrp ($typepath, $uid, $gid,false);
      }
      chown($typepath, $uid);
      chgrp($typepath, $gid);
    }
  }
  if($metoo) {
    chown($mypath, $uid);
    chgrp($mypath, $gid);
  }
}

function myReadlineShow($set) {
  $answer=NULL;
  if(!empty($set)) {
    foreach($set as $t=>$lis) {
      if($t!=$lis)
	echo "$lis ($t)\n";
      else 
	echo $lis.",";
    }
    echo "\n";
  }
}
function myReadline($prompt, $default=NULL,$set=NULL) {
  if(empty($default)) {
    myReadlineShow($set);
    $answer=readline("$prompt > ");
    while(empty($answer) || (!empty($set)&&!(in_array($answer,$set)))) {
      myReadlineShow($set);
      $answer=readline("$prompt > ");
    }
  } else {
    myReadlineShow($set);
    $answer=readline("$prompt (default: $default) > ");
    while(!empty($answer) && !empty($set) && !in_array($answer,$set) ) {
      myReadlineShow($set);
      $answer=readline("$prompt (default: $default) > ");
    }
    if(empty($answer)) $answer=$default;
  }
  if(!empty($set)) return array_search($answer, $set);
  return $answer;
}
function myConfirm($prompt, $default="Y") {
  $answer=readline("$prompt (default: $default) > ");
  while(!empty($answer) && !stristr('yn',$answer)) {
    $answer=readline("$prompt (default: $default) > ");
  }
  if(empty($answer)) $answer=$default;
  return ($answer=="Y")||($answer=="y");
}


// copie récursive d'une répertoire
function copyr($source, $dest) {
  // Simple copy for a file
  if (is_file($source)) {
    return copy($source, $dest);
  }
 
  // Make destination directory
  if (!is_dir($dest)) {
    mkdir($dest);
  }
 
  // Loop through the folder
  $dir = dir($source);
  while (false !== $entry = $dir->read()) {
    // Skip pointers
    if ($entry == '.' || $entry == '..') {
      continue;
    }
 
    // Deep copy directories
    if ($dest !== "$source/$entry") {
      copyr("$source/$entry", "$dest/$entry");
    }
  }
 
  // Clean up
  $dir->close();
  return true;
}


?>
