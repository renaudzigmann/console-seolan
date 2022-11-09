<?php
use \Seolan\Core\Module\Module;
use \Seolan\Core\Logs;
/**
 * si un module issu d'un projet est présent dans le menu gauche (corbeille comprise)
 * on le passe en 'home=false', pour qu'il apparaisse ensuite dans 'autres modules'
 */
function Project_20220131(){
  Logs::upgradeLog(Project_comment_20220131(),true);
  foreach(Project_20220131_toMove() as list($moid, $mod, $ptitle, $modpLabel)){
    $mod->procEditProperties(['_options'=>['local'=>1],
			      'options'=>['home'=>false]]);
  }
  Logs::upgradeLog(Project_comment_20220131(),true);
}
function Project_comment_20220131(){
  $ret  =   "Modules à traiter :";
  $ret .= "\n===================";
  $list = '';
  foreach(Project_20220131_toMove() as list($moid, $mod, $ptitle, $modpLabel)){
    $someone=true;
    $list .= "\n- Module projet '{$modpLabel}',  projet '{$ptitle}' : $moid {$mod->getLabel()} ({$mod->home})";
  }
  if (empty($list))
    $list = "\n\tAucun module à traiter";
  $ret .= $list;
  $title = "Mise à jour à false de la prop. 'home' des modules de projets présents dans le menu gauche.<br>";
  return "{$title}<pre>\n{$ret}\n</pre>";
}
function Project_20220131_toMove(){
  
  $toMove = [];

  foreach(getDB()->select("select moid, module from MODULES where toid=?", [8006]) as $line){

    list($moid, $modname) = array_values($line);
  
    $modp = Module::objectFactory(['moid'=>$moid,
				   'interactive'=>false,
				   'tplentry'=>TZR_RETURN_DATA]);
    
    foreach(getDB()->select("select title, prefix, descr, amods from {$modp->table}",[],false,\PDO::FETCH_NUM)
	    as list($ptitle, $prefix, $descr, $amods)){
      
      $amoids = preg_split( '/\|\|/', $amods, -1, PREG_SPLIT_NO_EMPTY);
      
      foreach($amoids as $amoid){
	$inMenu = getDB()->fetchOne("select 1 from CS8SEC where modid=?", [$amoid]);
	if ($inMenu){

	  $amod = Module::objectFactory(['moid'=>$amoid,
					 'interactive'=>false,
					 'tplentry'=>TZR_RETURN_DATA]);
	  $toMove[] = [$amoid,
		       $amod,
		       $ptitle,
		       $modp->getLabel()
		       ];
	}
      }
      
    }
  }
  return $toMove;
}
	

