<?php
namespace Seolan\Module\Media\Collection;
class Collection extends \Seolan\Module\Table\Table{
  function __construct($ar=NULL){
    parent::__construct($ar);
  }

  /// Liste des groupes de droits valides pour ce module
  static function getRoList() {
    return \Seolan\Module\Media\Media::getRoList();
  }
}
?>