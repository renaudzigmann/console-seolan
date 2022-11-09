<?php
function Module_20200715(){

  if (\Seolan\Core\Shell::getMonoLang())
    return;
  $data = Module_Data_20200715();
  foreach($data as list($line, $infos)){

    $ok = (count($infos['ds'])==1 && $infos['translatable'][0]==1);
    
    if ($ok){
      if ($infos['langs'] == 1 && $infos['levels']==1 && count($infos['raw'])==1){
	// une règle dans une langue : on duplique sur les autres
	Module_dup_20200715($infos['raw'][0]);
      } else if (count($infos['raw'])>=1){
	// 1 ou plusieurs règles, 1 ou plusieurs plusieurs langues
	// on duplique la langue de base sur autres langues ou la première langue trouvée sur les autres
	$rawlang = null;
	foreach($infos['raw'] as $src){
	  if ($src['ALANG'] == TZR_DEFAULT_LANG){
	    $rawlang = $src;
	    break;
	  }
	}
	if ($rawlang == null){
	  $rawlang = $infos['raw'][0];
	}
	Module_dup_20200715($rawlang);
      } else { // erreur
      }
    } else {
      \seolan\Core\Logs::upgradeLog("impossible de déterminer le status de traduction du champ");
    }
    
  }
}
function Module_dup_20200715($src){
  foreach(array_keys($GLOBALS['TZR_LANGUAGES']) as $code){
    if ($code != $src['ALANG']){
      $aoid = md5($code.$src['AOID'].$src['AKOID'].$src['AMOID'].$src['AGRP'].$src['AFUNCTION']);
      $params = [$src['AOID'],
		 $src['AMOID'],
		 $src['AKOID'],
		 $src['AGRP'],
		 $src['AFUNCTION'],
		 $code,
		 1,
		 ''
      ];
      $q = 'insert into ACL4 (aoid, amoid, akoid, agrp, afunction, alang, ok, acomment) values(?,?,?,?,?,?,?,?)';

      //si la règle existe dans la langue, on passe, quelque soit la fonction
      $exists = getDB()->fetchRow('select 1 from ACL4 where alang=? and amoid=? and akoid=? and agrp=?', [$code,
													  $src['AMOID'],
													  $src['AKOID'],
													  $src['AGRP']]);
      if (!$exists){
	\Seolan\Core\Logs::upgradeLog($q." ".print_r($params, true));
	getDB()->execute($q, $params);
      } else {
	\Seolan\Core\Logs::upgradeLog("règle existe  ".print_r([$code,
								$src['AMOID'],
								$src['AKOID'],
								$src['AGRP']],
							       true));
      }
    }
  }
}

function Module_Comment_20200715(){
  if (\Seolan\Core\Shell::getMonoLang()){
    return 'Patch sans objet : console mono langue';
  }
  $html = '';
  $styles = '<style type="text/css">tr.warning20200715>td{font-weight:bold;color:red;}</style>';
  $data = Module_Data_20200715();

  foreach($data as list($line, $infos)){
    if ($html == ''){
      $html.=$styles;
      $html.='<table class="table table-condensed table-striped"><tr>';
      foreach(array_keys($line) as $fn){
	$html.="<th>{$fn}</th>";
      }
      $html.='</tr>';
    }

    if ($infos['langs']>1 && $infos['levels']>1){
      $html.='<tr class="warning20200715">';
    } else {
      $html.='<tr>';
    }
    
    foreach($line as $fv){
      $html.="<td>{$fv}</td>";
    }
    if ($infos['langs']>1 || $infos['levels']>1){
      $html.="<tr class='warning20200715'><td colspan='3'>Problème potentiel ?!</td><td>{$infos['levelcodes']}</td><td>{$infos['langcodes']}</td></tr>";
    }
    $html.='</tr>';

  }
  $html.='</table>';
  return "Correction des droits sur les champs par langue<br>$html";    
}
function Module_Data_20200715(){
  $data = [];
  
  foreach(getDB()->select("select module, substr(akoid,8) as 'fieldname', akoid, amoid,  agrp, count(distinct afunction) as 'levels', count(distinct alang) as 'langs' from ACL4,MODULES where amoid=moid and akoid like '_field-%' group by 1,2,3,4,5 order by amoid, akoid")
    as $infos){

    $line = [];
    
    $infos['ds'] = [];
    $infos['translatable'] = [];
    
    $mod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$infos['amoid'],
						      'interactive'=>false,
						      'tplentry'=>TZR_RETURN_DATA]);
    
    foreach($mod->usedTables() as $tabname){
      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($tabname);
      if ($ds->fieldExists($infos['fieldname'])){
	$infos['ds'][] = $ds->getTable();
	$infos['translatable'][] = $ds->getTranslatable();
      }
    }

    $infos['raw'] = getDB()->fetchAll('select * from ACL4 where amoid=? and akoid=? and agrp=?', [$infos['amoid'],
												  $infos['akoid'],
												  $infos['agrp']
    ]);

    $line['Module']=$infos['module'];
    $line['Field']=$infos['fieldname'];

        if (substr($infos['agrp'], 0, 3) == 'GRP')
      $line['User/Group'] = getDB()->fetchOne('select grp from GRP where koid=?', [$infos['agrp']]);
    else
      $line['User/Group'] = getDB()->fetchOne('select fullnam from USERS where koid=?', [$infos['agrp']]);
    
    $line['Levels']=$infos['levels'];
    $line['Langs']=$infos['langs'];
    
    
    $line['Field'].=" (".implode(',', $infos['ds']).")";

    if (empty($line['User/Group']))
      $line['User/Group'] = $infos['agrp'];

    if ($infos['langs']>1){
      $infos['langcodes'] = getDB()->fetchOne('select group_concat(alang) from ACL4 where akoid=? and amoid=? and agrp=?', [$infos['akoid'],$infos['amoid'],$infos['agrp']]);
    }
    
    if ($infos['levels']>1){
      $infos['levelcodes'] = getDB()->fetchOne('select group_concat(afunction) from ACL4 where akoid=? and amoid=? and agrp=?', [$infos['akoid'],$infos['amoid'],$infos['agrp']]);
    }
    
    $data[] = [$line, $infos];
    
  }
  return $data;
}
