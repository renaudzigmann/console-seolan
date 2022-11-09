<?php

/**
 * Smarty plugin
 * @package Smarty
 * @subpackage actindo_plugins
 */

function smarty_function_url2cloud($params, &$smarty) {
  return myUrl2cdn($params['url'], $params['name']);
}
