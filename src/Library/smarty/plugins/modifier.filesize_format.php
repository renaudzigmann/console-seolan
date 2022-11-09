<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage actindo_plugins
 */


/**
 * Smarty filesize_format modifier plugin
 *
 * Type:     modifier<br>
 * Name:     filesize_format<br>
 * Purpose:  format strings via sprintf
 * @author Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 1.4 $
 * @param string
 * @param string
 * @return string
 */
function smarty_modifier_filesize_format( $size )
{
  if( is_null($size) || $size === FALSE || $size == 0 )
    return $size;

  if( $size > 1024*1024*1024 )
    $size = sprintf( "%.1f GB", $size / (1024*1024*1024) );
  if( $size > 1024*1024 )
    $size = sprintf( "%.1f MB", $size / (1024*1024) );
  elseif( $size > 1024 )
    $size = sprintf( "%.1f kB", $size / 1024 );
  elseif( $size < 0 )
    $size = '&nbsp;';
  else
    $size = sprintf( "%d B", $size );

  return $size;
}

/* vim: set expandtab: */

?>
