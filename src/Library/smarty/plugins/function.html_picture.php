<?php
/*
 * Smarty plugin
 *
-------------------------------------------------------------
/**
* html_picture
* @param resizer resizer for the image
* @param alt html attribute
* @param title html attribute
* @param srcsetId key of the global sourcesets array, associated value is an 
*        array of <media-query> => <resize parameters>
* @return an html picture Tag for the image's resizer with different source tags
*
-------------------------------------------------------------
*/


function smarty_function_html_picture(array $params, $smarty) {

  return \Seolan\Field\File\File::buildPictureTag($params);
}
/* vim: set expandtab: */
