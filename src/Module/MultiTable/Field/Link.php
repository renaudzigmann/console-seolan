<?php
namespace Seolan\Module\MultiTable\Field;
/**
 * surcharge des champs liens utilisée dans le multitable pour gérer la suppression
 * -> on fait le SQL avec la prop. _mttCloneFieldName positionnées par le multitable
 * les noms de champs dans le multitable n'étant pas standard (ttttt_ffff pour tttt.ffff sql)
 */
class Link extends \Seolan\Field\Link\Link{

  /**
   *  la prop. "field" étant surchargée dans les champs utilisés dans le multitable ...
   */
  function _removeOidInLink($target, $oid) {
    if (empty($this->table) || is_a(\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->table), '\Seolan\Model\DataSource\View\View'))
      return;
    $tolog=false;
    if(!empty($target))
      $tolog=($target->toLog() && $this->table!='LOGS');
    if (isset($this->_mttCloneFieldName))
      $field = $this->_mttCloneFieldName;
    else
      $field = $this->field;
    $rs=getDB()->select('select KOID from '.$this->table.' where '.$field.' like ? OR '.$field.' = ?', array('%|'.$oid.'|%', $oid));
    if($tolog) {
      while($rs && ($ors=$rs->fetch())) \Seolan\Core\Logs::update('update',$ors['KOID'],'remove oid '.$oid.' from '.$field);
    }
    if($rs->rowCount()>0) {
      getDB()->execute('update '.$this->table.' set UPD=UPD,'.$field.'=REPLACE('.$field.',"||'.$oid.'||","||") '.
		       'where '.$field.' like "%|'.$oid.'|%"');
      getDB()->execute('update '.$this->table.' set UPD=UPD,'.$field.'="" '.
		       'where '.$field.' ="'.$oid.'"');
    }
  }
}