<?php
function smarty_function_getvar($params, &$smarty)
{
  if (!isset($params['var'])) {
    $smarty->trigger_error("get: missing 'name' parameter");
    return;
  }
  if($params['var'] == '') {
    return;
  }
  $name=$params['var'];  
  if (isset($smarty->tpl_vars[$name])){
    // cas de base : récupération de la variable
    $value = $smarty->tpl_vars[$name]->value;
  } else if (strpos($name, '(') !== false) {
    // pas d'appel de fonction
    \Seolan\Core\Logs::debug(__FUNCTION__." skipping insecure usage '{$name}'");
    $value = null;
    \Seolan\Library\Security::alert(__FUNCTION__." insecure usage '{$name}'");
  } else {
    // évaluation d'une expression 'plus complexe'
    $pos1=strpos($name,'[');
    $pos2=strpos($name,'->');
    if($pos1===false) $pos1=9999;
    if($pos2===false) $pos2=9999;
    $pos=($pos1<$pos2)?$pos1:$pos2;
    $name="['".substr($name,0,$pos)."']->value".substr($name,$pos);
    \Seolan\Core\Logs::debug(__FUNCTION__." evaluating '{$name}'");
    $value = eval("return  \$smarty->tpl_vars{$name};");
  }
  
  if(!empty($params['assign'])){
    $smarty->assignByRef($params['assign'], $value);
  } else {
    return $value;
  }
  
}
?>
