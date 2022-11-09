<?php
namespace Seolan\Module\DocumentManagement\DocumentTypes;
/**
gestion des types des bases documentaires
-> EF sur _TYPES
*/
class DocumentTypes extends \Seolan\Module\Table\Table{
  function __construct($ar=NULL){
    parent::__construct($ar);
  }
  // edit surcharge : champ modele
  //
  function &edit($ar){
    parent::edit($ar);
    $p = new \Seolan\Core\Param($ar, array());
    $tpl = $p->get('tplentry');
    // recherche des modeles possibles
    $scr = \Seolan\Core\Shell::from_screen($tpl);
    $modid = $scr['omodid']->raw;
    $mod = \Seolan\Core\Module\Module::objectFactory($modid);
    $patterns = $mod->getPatterns();
    // ajout de la liste des modeles - idealement, un champ de type xfolderlink a creer ...
    $o = $scr['opattern'];
    $o->html = '<select name="pattern"><option value="">--</option>';
    foreach($patterns as $i=>&$doc){
      $s = ($doc->oid == $o->raw)?'selected':'';
      $o->html .= '<option '. $s.' value="'.$doc->oid.'">'.$doc->title.'</option>';    
    }
    $o->html .= '</select>';
    $scr['opattern'] = $o;
  }
  // le champ pattern n'est pas accessible
  //
  function &insert($ar){
    $this->xset->desc['pattern']->readonly = true;
    parent::insert($ar);
  }
  // le champ pattern est un oid en readonly dans un tc 40
  //
  function display($ar){
    return parent::display($ar);
  }
}
?>
