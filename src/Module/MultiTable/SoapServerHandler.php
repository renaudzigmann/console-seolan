<?php
namespace Seolan\Module\MultiTable;
class  SoapServerHandler extends \Seolan\Module\Table\SoapServerHandler {
  function display($context,$params){
    $soapmod = $this->module;
    \Seolan\Core\Logs::debug("SOAPRequest function display oid:".$params->oid);
    $soapmod->SOAPContext($context,'display',$params->oid);
    $ar=array('tplentry'=>TZR_RETURN_DATA,'oid'=>$params->oid);
    $br=$soapmod->display($ar);
    $ret=array('oid'=>$br['oid']);
    foreach($br['fields_object'] as $j=>&$f){
      $key = $f->field;
      if($f->table != $soapmod->table)
	$key = $f->table.'_'.$f->field;
	
      $ret[$key]=$br['o'.$f->field]->getSoapValue();
    }
    return $ret;
  }
}
