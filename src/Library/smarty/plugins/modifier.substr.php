<?php
/*
 * Smarty plugin
 *
*/
function smarty_modifier_substr($string,$start=0,$end=NULL)
{
  if (!$string) return $string;
  if(empty($end)) return substr($string,$start);
  else return substr($string,$start,$end);
}

/* vim: set expandtab: */

?>
