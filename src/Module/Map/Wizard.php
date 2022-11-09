<?php
namespace Seolan\Module\Map;
/*
  wizard du module cartographie
*/
class Wizard extends \Seolan\Core\Module\Wizard {
  private static $deftablename = 'MAPSCFG';
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Map_Map');
  }

  function istep1() {
    parent::istep1();
    $this->_module->tablename = self::$deftablename;
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'table', 'text'), 'tablename', 'text', NULL);
  }

  function iend($ar=NULL) {
    $tablename = trim($this->_module->tablename);
    if (empty($tablename) || strlen($tablename)>7)
      $this->_module->tablename = self::$deftablename;
    $this->createStructure();
    self::verifyStructures($this->_module->tablename, 'MAPSRS');
    parent::iend();
  }
  /**
   * autres champs et tables
   */
  public static function verifyStructures($table1, $table2){
    // champ type de coordonnées dans la couche
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table1);
    if(!fieldExists($table1, 'coordsrs')) {
      $ds->createField('coordsrs','Système Spatial de Référence', '\Seolan\Field\Link\Link','80','','0','0','0','0','0','0', $table2);
      $ds->procEditField(array('tplentry'=>TZR_RETURN_DATA,
            'field'=>'coordsrs',
            '_todo'=>'save',
            'options'=>array('fgroup'=>array('FR'=>'Général'),
                  'comment'=>'',
                  'acomment'=>'Système Spatial de Référence (SRS), par defaut WGS84')
            ));
    }
  }
  /**
   * table de base des couches
   */
  private function createStructure() {
    $newtable=$this->_module->tablename;
    $lg = TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="0";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$newtable;
    $ar1["bname"][$lg]="Gestion des cartes";
    if (\Seolan\Core\System::tableExists($newtable) || \Seolan\Core\DataSource\DataSource::sourceExists($newtable))
    return;
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    //                                                                    cnt   ord obl que bro tra mul pub 
    $x->createField('name','Nom de la couche','\Seolan\Field\ShortText\ShortText',            '54' ,'3' ,'1','1','1','0','0','1');
    $x->createField('ftable','Table des données','\Seolan\Field\DataSource\DataSource',              ''   ,'4' ,'1','1','1','0','0','1');
    $x->createField('fname','Champ coordonnées','\Seolan\Field\ShortText\ShortText',          '7'  ,'5' ,'1','1','1','0','0','1');
    $x->createField('ezoom','Zoom par défaut','\Seolan\Field\Real\Real',                 '7'  ,'6' ,'1','1','0','0','0','0');
    $x->createField('elatlng','Position par défaut','\Seolan\Field\ShortText\ShortText',      '54' ,'7' ,'1','1','0','0','0','0');
    $x->createField('fgcauto','Geocodage automatique','\Seolan\Field\Boolean\Boolean',         ''   ,'8' ,'0','1','1','0','0','0');
    $x->createField('fgcaddr','Champs adresse','\Seolan\Field\ShortText\ShortText',           '124','9' ,'0','1','0','0','0','0');
    $x->createField('fgczipc','Champ code postal','\Seolan\Field\ShortText\ShortText',        '7'  ,'10','0','1','0','0','0','0');
    $x->createField('fgccntr','Champ pays','\Seolan\Field\ShortText\ShortText',               '7'  ,'11','0','1','0','0','0','0');
    $x->createField('fgctown','Champ ville','\Seolan\Field\ShortText\ShortText',              '7'  ,'12','0','1','0','0','0','0');
    $x->createField('rmoid','Module','\Seolan\Field\Module\Module',                        ''   ,'12','0','1','0','0','0','0');
    $x->createField('ricon1','Icone','\Seolan\Field\Image\Image',                         ''   ,'14','0','0','1','0','0','0');
    $x->createField('rshad1','Ombre','\Seolan\Field\Image\Image',                         ''   ,'15','0','0','1','0','0','0');
    $x->createField('rcicon','Icone des groupes','\Seolan\Field\Image\Image',             ''   ,'16','0','0','0','0','0','0');
    $x->createField('rcshad','Ombre des groupes','\Seolan\Field\Image\Image',             ''   ,'17','0','0','0','0','0','0');
    $x->createField('rfilter','Filtre SQL','\Seolan\Field\ShortText\ShortText',               '124','18','0','0','0','0','0','0');
    $x->createField('rtitlef','Champ titre','\Seolan\Field\ShortText\ShortText',              '7'  ,'19','0','1','0','0','0','0');
    $x->createField('rdescrf','Champs description','\Seolan\Field\ShortText\ShortText',       '124','20','0','1','0','0','0','0');
    $x->createField('rkmltpl','Template des kml','\Seolan\Field\File\File',              ''   ,'21','0','0','0','0','0','0');
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'name',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Général'),
                'comment'=>'',
                'acomment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'ftable',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Général'),
                'comment'=>'',
                'acomment'=>'Table portant la couche')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'fname',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Général'),
                'acomment'=>array('FR'=>'Champ contenant les coordonnées géodésiques'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'ezoom',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=> 'Valeurs par défaut'),
                'acomment'=>array('FR'=>'Zoom par défaut en edition et visualisation (voir google maps API)'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'elatlng',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Valeurs par défaut'),
                'acomment'=>array('FR'=>'Position par défaut sur la carte en édition (latitude;longitude en degrés décimaux)'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'fgcauto',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Géocodage'),
                'acomment'=>array('FR'=>'Si oui, calcul automatique des coordonnées. Il faut alors remplir les autres champs du groupe'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'fgcaddr',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Géocodage'),
                'acomment'=>array('FR'=>'Liste des champs qui forment une adresse, séparateur : un espace '),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'fgczipc',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Géocodage'),
                'acomment'=>array('FR'=>'Le champ du code postal'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'fgccntr',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Géocodage'),
                'acomment'=>array('FR'=>'Le champ qui contient le pays'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'fgctown',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Géocodage'),
                'aomment'=>array('FR'=>'Le champ qui contient la ville'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rmoid',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'comment'=>array('FR'=>'Le module (Il sera fait un browse dessus)'),
                'acomment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'ricon1',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Icone 32x32'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rshad1',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Ombre pour cette icone'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rcicon',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Icone des groupes'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rcshad',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Ombre des groupes'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rfilter',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'comment'=>array('FR'=>'Filtre supplémentaire (sql) qui sera appliqué pour cette couche'),
                'acomment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rtitlef',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Champ qui formera le titre dans une infobulle'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rdescrf',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Champs qui formeront le contenu dans une infobulle'),
                'comment'=>'')
          ));
    $x->procEditField(array('tplentry'=>TZR_RETURN_DATA,
          'field'=>'rkmltpl',
          '_todo'=>'save',
          'options'=>array('fgroup'=>array('FR'=>'Paramètres de restitution - Couche'),
                'acomment'=>array('FR'=>'Template pour former le titre et le contenu dans une infobulle'),
                'comment'=>'')
          ));
    $this->_module->table = $this->_module->tablename;
  }
}

?>
