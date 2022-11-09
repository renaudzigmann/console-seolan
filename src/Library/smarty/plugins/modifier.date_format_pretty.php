<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage actindo_plugins
 */

/**
 * Include the {@link shared.make_timestamp.php} plugin
 */

/**
 * Smarty date_format_pretty modifier plugin
 * 
 * Type:     modifier<br>
 * Name:     date_format_pretty<br>
 * Purpose:  format datestamps via strftime<br>
 * Input:<br>
 *         - string: input date string
 *         - lang: Locale to use (de_DE,en_US, etc. null for default)
 *         - default_date: default date if $string is empty
 * @author Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 1.4 $
 * @param string
 * @param string
 * @param string
 * @return string|void
 * @uses smarty_make_timestamp()
 */
function smarty_modifier_date_format_pretty($string, $lang=null, $default_date=null)
{
  require_once (SMARTY_DIR.'/plugins/shared.make_timestamp.php');
  if( $string != '' && $string != '0000-00-00' )
    $date = smarty_make_timestamp( $string );
  elseif( isset($default_date) && $default_date != '' )
    $date = smarty_make_timestamp( $default_date );
  else
    return;

  $save_lang = setlocale( LC_TIME, 0 );
  if( !isset($lang) )
    $l = setlocale( LC_TIME, 0 );
  else
  {
    setlocale( LC_TIME, $lang );
    $l = $lang;
  }

  $langs = array(
    'de' => array( 'Gestern', 'Vorgestern' ),
    'en' => array( 'yesterday', '' ),
    'C' => array( 'yesterday', '' ),
  );
  $l = explode( '_', $l );

  if( $date > strtotime('today 00:00:00') )
    $d = strftime( '%H:%M', $date );
  elseif( $date > strtotime('yesterday 00:00:00') )
    $d = $langs[$l[0]][0].' '.strftime( '%H:%M', $date );
  elseif( $date > strtotime('-2 days 00:00:00') && $l[0] == 'de' )   // only for de_* locales
    $d = $langs[$l[0]][1].' '.strftime( '%H:%M', $date );
  elseif( $date > strtotime('-1 week 00:00:00') )
    $d = strftime( '%A, %H:%M', $date );
  elseif( $date > strtotime('-1 year 00:00:00') )
    $d = strftime( '%d. %b, %H:%M', $date );
  else
    $d = strftime( '%d. %b %Y, %H:%M', $date );

  if( isset($lang) )
    setlocale( LC_TIME, $save_lang );

  return $d;
}
?>