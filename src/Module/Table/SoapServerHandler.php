<?php
namespace Seolan\Module\Table;
class  SoapServerHandler extends \Seolan\Core\Module\SoapServerHandler {
  function browse($context,$params){
    $soapmod = $this->module;
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    \Seolan\Core\Logs::debug("SOAPRequest function browse LANG:$LANG_DATA filter:".$params->filter." fields:".$params->fields);
    $soapmod->SOAPContext($context,'browse');
    $ar=array('tplentry'=>TZR_RETURN_DATA,'pagesize'=>999999);
    if(!empty($params->fields)){
      if($params->fields=='all' || $params->fields=='*') $ar['selectedfields']='all';
      else $ar['selectedfields']=explode(',',$params->fields);
    }
    if(!empty($params->filter)){
      $translatable = $soapmod->xset->getTranslatable();
      
      if(!$translatable) $LANG_DATA=TZR_DEFAULT_LANG;
      $ar['select']='select * from '.$soapmod->table.' where LANG="'.$LANG_DATA.'" AND ('.str_ireplace(' select ','',$params->filter).')';
    }
    $ar['nocount'] = 1;
    foreach($ar['selectedfields'] as $fieldname){
      $ar['options'][$fieldname]['nofollowlinks']=1;
    }
    $br=$soapmod->browse($ar);
    $lines=array();
    foreach($br['lines_oid'] as $i=>$oid){
      $line=array('oid'=>$oid);
      foreach($br['header_fields'] as $j=>&$f){
	$line[$f->field]=$br['lines_o'.$f->field][$i]->getSoapValue();
      }
      $lines[]=$line;
    }
    return array('line'=>$lines);
  }
  function display($context,$params){
    $soapmod = $this->module;
    \Seolan\Core\Logs::debug("SOAPRequest function display oid:".$params->oid);
    $soapmod->SOAPContext($context,'display',$params->oid);
    $ar=array('tplentry'=>TZR_RETURN_DATA,'oid'=>$params->oid);
    $br=$soapmod->display($ar);
    $ret=array('oid'=>$br['oid']);
    foreach($br['fields_object'] as $j=>&$f){
      $ret[$f->field]=$br['o'.$f->field]->getSoapValue();
    }
    return $ret;
  }
}
