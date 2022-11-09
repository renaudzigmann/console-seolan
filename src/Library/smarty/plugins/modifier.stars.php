<?php

/**
 * Smarty plugin
 * @package Smarty
 * @subpackage actindo_plugins
 */

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     modifier
 * Name:     stars
 * Purpose:  convert chars to stars, excluding N suffix chars
 * -------------------------------------------------------------
 * @author Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 1.5 $
 */
function smarty_modifier_stars($string, $suffix = 0, $char = '*')
{
  $_prefix_len = strlen($string) - $suffix;

	if($suffix == 0)
		return str_repeat($char, $_prefix_len);

  if($_prefix_len > 0) 
    return str_repeat($char, $_prefix_len) . substr($string, - $suffix);
  else
    return $string;
}

?>