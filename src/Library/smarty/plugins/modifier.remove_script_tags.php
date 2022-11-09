<?php
/*
 * Smarty plugin
 *
*/
function smarty_modifier_remove_script_tags($string)
{
  if (!$string) return $string;
  return preg_replace('@<script[^>]*?>.*?</script>@si', '', $string);
}

/* vim: set expandtab: */

?>
