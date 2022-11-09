<?php
namespace Seolan\Module\Moodle;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
    if(!\Seolan\Core\Module\Module::getMoid(XMODMOODLE_TOID)) {
      $this->_module->createstructure = true;
      $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Moodle_Moodle','modulename');
      $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
      $this->_module->comment[TZR_DEFAULT_LANG] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Moodle_Moodle','comment');
    }
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), 'createstructure', 'boolean');

  }
  function istep2(){
    if(!$this->_module->createstructure){
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table');
    }else{
      $this->_module->bname=$this->_module->modulename;
      if(!\Seolan\Core\Module\Module::getMoid(XMODMOODLE_TOID)) {
        $this->_module->btab="MOODLE";
      }
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name'), 'bname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code'), 'btab', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','translate'), 'translatable', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','auto_translate'), 'auto_translate', 'boolean');
      $this->_module->trackchanges = true;
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','trackchanges'), 'trackchanges', 'boolean');
    }
  }
  function iend($ar=NULL) {
    if($this->_module->createstructure){
      $this->_module->createstructure=false;
      $ar1=array();
      $ar1['translatable']=$this->_module->translatable;
      $ar1['auto_translate']=$this->_module->auto_translate;
      $ar1['trackchanges']=$this->_module->trackchanges;
      $ar1['btab']=$this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->bname;
      $ar1['tag']=0;
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
      $ord = 3;
      $x->createField('moodle_table','Table Moodle','\Seolan\Field\ShortText\ShortText','255',$ord++,'1','1','1','0','0','1');
      $x->createField('moodle_field','Champ Moodle','\Seolan\Field\ShortText\ShortText','255',$ord++,'1','1','1','0','0','1');
      $x->createField('seolan_table','Table Seolan','\Seolan\Field\ShortText\ShortText','255',$ord++,'1','1','1','0','0','1');
      $x->createField('seolan_field','Champ Seolan','\Seolan\Field\ShortText\ShortText','255',$ord++,'1','1','1','0','0','1');
      $x->createField('tfunc','Fonction de transformation','\Seolan\Field\ShortText\ShortText','255',$ord++,'0','1','1','0','0','1');
      $x->createField('pkey','Clé primaire','\Seolan\Field\Boolean\Boolean','0',$ord++,'1','1','1','0','0','1');

      $this->_module->table=$this->_module->btab;
      // insert default roles
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,NOW(),1)',
                       array('login',
                             'mdl_user',
                             'username',
                             'USERS',
                             'alias',
                             1,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,NOW(),1)',
                       array('firstname',
                             'mdl_user',
                             'firstname',
                             'USERS',
                             'firstname',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,NOW(),1)',
                       array('lastname',
                             'mdl_user',
                             'lastname',
                             'USERS',
                             'lastname',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,NOW(),1)',
                       array('email',
                             'mdl_user',
                             'email',
                             'USERS',
                             'email',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, tfunc, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,?,NOW(),1)',
                       array('lang',
                             'mdl_user',
                             'lang',
                             'USERS',
                             'LANG',
                             'lowercase',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, tfunc, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,?,NOW(),1)',
                       array('password',
                             'mdl_user',
                             'password',
                             'USERS',
                             'passwd',
                             'pwd_not_cached',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, tfunc, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,?,NOW(),1)',
                       array('auth',
                             'mdl_user',
                             'auth',
                             'USERS',
                             'alias',
                             'auth_cas',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, tfunc, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,?,NOW(),1)',
                       array('confirmed',
                             'mdl_user',
                             'confirmed',
                             'USERS',
                             'alias',
                             'confirmed',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );
      getDB()->execute('INSERT INTO '.$this->_module->table.' (KOID, moodle_table, moodle_field, seolan_table, seolan_field, tfunc, pkey, OWN, LANG, UPD, PUBLISH) values (?,?,?,?,?,?,?,?,?,NOW(),1)',
                       array('mnethostid',
                             'mdl_user',
                             'mnethostid',
                             'USERS',
                             'alias',
                             'confirmed',
                             2,
                             TZR_USERID_ROOT,
                             TZR_DEFAULT_LANG
                             )
                       );

    }
    return parent::iend();
  }
}
?>
