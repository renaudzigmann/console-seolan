<?php
/*
 * Smarty plugin
 *
*/
function smarty_modifier_asciify($string)
{
  if (!$string) return $string;
  return rewriteToAscii($string);
}

/* vim: set expandtab: */

?>
