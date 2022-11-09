<?php
/**
 * transformation des evts 'remove oid' de type 'update' en evts de type 'autoupdate'
 */
use \Seolan\Core\Logs;
function Link_20220629(){

  $no = __FUNCTION__;

  \Seolan\Core\Logs::upgradeLog("$no start");

  \Seolan\Core\Logs::upgradeLog("$no".Link_comment_20220629());


  getDB()->execute("update LOGS set UPD=UPD, etype='autoupdate' where etype='update' and comment rlike 'remove oid [A-Za-z0-9_]+:[a-z0-9]+ from [A-Za-z0-9_]+'");

  $nb = getDB()->fetchOne("select ROW_COUNT()");

  \Seolan\Core\Logs::upgradeLog("$no done {$nb} updated");

}
function Link_comment_20220629(){

  $tot = getDB()->fetchOne("select count(*) from LOGS where etype='update' and comment rlike 'remove oid [A-Za-z0-9_]+:[a-z0-9]+ from [A-Za-z0-9_]+'");

  $details = "";

  foreach(getDB()->select("select regexp_substr(l.object, '[a-zA-Z0-9_]+'), ifnull(b.bname,'Unknown table'), regexp_substr(l.comment, 'oid ([a-zA-Z0-9_]+)'), count(*) from LOGS l left outer join BASEBASE b on b.btab=regexp_substr(l.object, '[a-zA-Z0-9_]+') where l.etype='update' and l.comment rlike 'remove oid [A-Za-z0-9_]+:[a-z0-9]+ from [A-Za-z0-9_]+' group by 1,2,3 order by 1,2,3;", [], null, \PDO::FETCH_NUM) as list($btab, $bname, $target, $nb)){
    $target = str_replace('oid ', '', $target);
    $details .= "\n\t\t-{$bname} ({$btab}) : {{$target}} -> $nb";
  }
  return "<pre>Transformation des lignes de LOG 'remove oid' de type 'update' en ligne de type 'autoupdate' (champ etype)\n\nLignes à corriger : {$tot}\n\n{$details}\n</pre>";

}
