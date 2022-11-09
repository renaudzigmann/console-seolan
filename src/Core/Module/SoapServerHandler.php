<?php
namespace Seolan\Core\Module;
/**
 * classe de base des 'actions' soap des modules
 */
class SoapServerHandler {
  protected $module = null;
  function __construct($module){
    $this->module = $module;
  }
  function auth($ar){
    \Seolan\Core\Logs::notice(__METHOD__,'auth');
    $c=$GLOBALS['TZR_SESSION_MANAGER'];
    $sess=new $c();
    $ar=(array)$ar;
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$sess->procAuth($ar);
    if($ret) return session_id();
    else throw new \SoapFault('BADAUTHINFO','Bad login and/or password');
  }
  function close($context){
    \Seolan\Core\Logs::notice(__METHOD__,'close');
    session_commit();
    session_id($context->sessid);
    session_start();
    session_destroy();
    return 1;
  }
}