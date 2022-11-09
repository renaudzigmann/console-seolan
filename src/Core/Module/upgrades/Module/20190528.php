<?php
/**
 * Transformation en json des champs specs de la table OPTS et les champs spec de la table IMPORTS
 */
function Module_20190528() {
  /*
     récuper les champs specs étant en serialized et les convertir en json
  */
  function upgradeOPTS($koid, $spec){ 
    if($value=unserialize($spec)) {
      $value=unserialize($spec);
      $json=json_encode($value);
      getDB()->execute('UPDATE OPTS set UPD=UPD, specs=? where KOID=? ',array($json, $koid));
    }    
  }
  /*
     récuperer les champs spec étant en XML et les convertir en json
  */
  function jval($el, $type='string'){
    if ($type == 'boolean')
      return in_array(strtolower($el),['true', 1]);
    if ($type == 'string')
      return (string)$el;
    return $s;
  }
  function convertNode($node){
    
    static $bools = null;
    if ($bools == null)
      $bools = getBools();
    $tagName = $node->getName();
    $children = $node->children(); 
    //    echo("\ntn : $tagName ".$node->count());
    $value = [];
    $hasChildren = $hasAttributes = false;
    foreach($node->attributes() as $aname => $avalue){
      $type = in_array($aname, $bools)?'boolean':'string';
      $value[$aname] = jval($avalue, $type);
      $hasAttributes = true;
    }
    if ($children->count()>0){
      foreach($children as $cnode){
	$hasChildren = true;
	list($ctag, $cvalue) = convertNode($cnode);
	$value[$ctag] = $cvalue;
      }
    }
    if (!$hasChildren && !$hasAttributes){
      $value = jval($node, 'string');
    }
    
    return [$tagName, $value];
    
  }
  function getBools(){
    return ['merge', 'create', 'unique','fieldsinheader','clearbefore','updateifexists','notdeletefile'];
  }
  function upgradeImport($koid, $data){ 
    $o = simplexml_load_string($data);
    if($o) {
      $n = ['general'=>[],
	    'action'=>null,
	    'catalog'=>null
	    ];

      list($tag, $value) = convertNode($o->general[0]);
      $n[$tag] = $value;
      // correction linestoskip
      if (isset($n['general']['linestoskip']['value'])){
	$n['general']['linestoskip'] = $n['general']['linestoskip']['value'];
      }
      
      if ($o->action->children()->count() > 0){
	list($tag, $value) = convertNode($o->action[0]);
	$n[$tag] = $value;
      }
      
      // bidouille pour keys
      unset($n['general']['key']);
      if ($o->general[0]->key->count() > 0){
	$keys = [];
	foreach($o->general->key as $key){
	  list($tag, $value) = convertNode($key);
	  $keys[] = $value;
	}
	// nettoyage
	$n['general']['keys'] = array_filter($keys);
	if (empty($n['general']['keys']))
	  $n['general']['keys'] = null;
      }
      // les champs (bidouille aussi)
      foreach($o->catalog->field as $field){
	list($tag, $value) = convertNode($field);
	$n['catalog']['fields'][] = $value;
      }
      $newData = json_encode($n, JSON_PRETTY_PRINT);
      getDB()->execute('UPDATE IMPORTS set UPD=UPD, spec=? where KOID=? ',array($newData, $koid));
    } else {
      \Seolan\Core\Logs::upgradeLog("unable to load xml : ".substr($data, 0, 120),defined('TZR_BATCH'));
    }
    
  }
  
  $majOpts = getDB()->query("SELECT specs, KOID FROM OPTS"); 
  
  foreach($majOpts as $parmetre) {
    // certaines specs sont déjà en json les subs par exemple
    if(substr($parmetre['specs'],0,2)!='{"' && substr($parmetre['specs'],0,1)!='[') {   
      upgradeOPTS($parmetre['KOID'], $parmetre['specs']);
    }
  }
  
  $maj_Import=getDB()->query("SELECT spec, KOID FROM IMPORTS");
  foreach($maj_Import as $parmetre) {
    
    if(substr($parmetre['spec'],0,2)!='{"' && substr($parmetre['spec'],0,1)!='[') {
      upgradeImport($parmetre['KOID'], $parmetre['spec']);
    }
  }
  
}
