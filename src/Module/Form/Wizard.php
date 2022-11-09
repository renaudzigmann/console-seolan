<?php
namespace Seolan\Module\Form;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), 'createstructure', 'boolean');
  }

  function istep2(){
    if(!$this->_module->createstructure){
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','answerstable'), 'atable', 'table');
    }else{
      $this->_module->bname=$this->_module->modulename;
      $this->_module->btab=\Seolan\Model\DataSource\Table\Table::newTableNumber();
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name'), 'bname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code'), 'btab', 'text');
      $this->_module->aname=$this->_module->bname.' : Réponses choix multiples';
      $this->_module->atab=\Seolan\Model\DataSource\Table\Table::newTableNumber('T',1);
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','answerstable'), 'aname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code'), 'atab', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','translate'), 'translatable', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','auto_translate'), 'auto_translate', 'boolean');
    }
  }

  function iend($ar=NULL) {
    if($this->_module->createstructure) $this->createStructure();
    return parent::iend();
  }
  
  private function createStructure() {
    $this->_module->createstructure=false;
    $ar1=array();
    $ar1['translatable']=$this->_module->translatable;
    $ar1['auto_translate']=$this->_module->auto_translate;
    $ar1['btab']=$this->_module->btab;
    $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->bname;
    $ar1['publish']=false;
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
    if ($x->fieldExists('TAG')){
      $x->delField(['field'=>'TAG','action'=>'OK']);
    }
    //                                                                   size ord  obl que bro tra mul pub tar
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                    '255','3', '1','1','1','0','0','1');
    $x->createField('intro','Texte d\'introduction','\Seolan\Field\RichText\RichText',      '70','4', '0','1','0','0','0','0');
    $x->createField('outro','Texte de bas de page','\Seolan\Field\RichText\RichText',       '70','5', '0','1','0','0','0','0');
    $x->createField('dtstart','Date d\'ouverture','\Seolan\Field\Date\Date',             '0','6', '1','1','1','0','0','0');
    $x->createField('dtend','Date de clotûre','\Seolan\Field\Date\Date',                 '0','7', '1','1','1','0','0','0');
    $x->createField('qmod','Module des réponses','\Seolan\Field\Module\Module',           '0','8', '0','1','0','0','0','0');
    $x->createField('qtable','Table des réponses','\Seolan\Field\DataSource\DataSource',            '0','9', '0','1','0','0','0','0');
    $x->createField('amulti','Réponses multiples','\Seolan\Field\Boolean\Boolean',             '0','10', '0','1','0','0','0','0');
    $x->createField('reedit','Possibilité de réédition','\Seolan\Field\Boolean\Boolean',       '0','11','0','1','0','0','0','0');
    $x->createField('isopen','Accès public','\Seolan\Field\Boolean\Boolean',                   '0','12','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'isopen',
			    '_todo'=>'save',
			    'options'=>[
					'acomment'=>[TZR_DEFAULT_LANG=>'Formulaire ouvert à tout utilisateur ayant accès à la page']]
			    ));
    $fieldName = 'directaccess';
    $ar2 = [];
    $ar2['field'] = $fieldName;
    $ar2['ftype'] = '\Seolan\Field\Boolean\Boolean';
    $ar2['fcount'] = 64;
    $ar2['forder'] = 13;
    $ar2['label'] = [TZR_DEFAULT_LANG=>'Accès direct,par token'];
    $ar2['target'] = null;
    $ar2['compulsory'] = 0;
    $ar2['browsable'] = 0;
    $ar2['queryable'] = 1;
    $ar2['translatable'] = 0;
    $ar2['multivalued'] = 0;
    $ar2['published'] = 0;
    $x->procNewField($ar2);
    $x->procEditField(array('field'=>$fieldName,
			     '_todo'=>'save',
			     'options'=>[
					 'acomment'=>[TZR_DEFAULT_LANG=>'Autorise l\'accès par token, sans login, aux destinataires internes.']]
			     ));

    $x->createField('lastanswer','Dernière réponse','\Seolan\Field\DateTime\DateTime',       '0','12','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'lastanswer',
			    '_todo'=>'save',
			    'options'=>['readonly'=>1]
			    ));
    $x->createField('dest','Destinataires internes','\Seolan\Field\User\User',           '0','13','0','1','0','0','1','0','USERS');
    $x->createField('destm','Destinataires externes','\Seolan\Field\Text\Text',         '70','14','0','1','0','0','1','0');
    $x->createField('invitok','Invitations envoyées','\Seolan\Field\Boolean\Boolean',          '0','15','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'invitok','table'=>$this->_module->btab,'_todo'=>'save','options'=>array('readonly'=>true)));

    $fieldName = 'dtsend';
    $ar2 = [];
    $ar2['field'] = $fieldName;
    $ar2['ftype'] = '\Seolan\Field\Date\Date';
    $ar2['fcount'] = 64;
    $ar2['forder'] = 16;
    $ar2['label'] = [TZR_DEFAULT_LANG=>'Date d\'envoi'];
    $ar2['target'] = null;
    $ar2['compulsory'] = 0;
    $ar2['browsable'] = 0;
    $ar2['queryable'] = 1;
    $ar2['translatable'] = 0;
    $ar2['multivalued'] = 0;
    $ar2['published'] = 0;
    $x->procNewField($ar2);
    $x->procEditField(array('field'=>$fieldName,
			     '_todo'=>'save',
			     'options'=>['readonly'=>1,
					 'acomment'=>[TZR_DEFAULT_LANG=>'Date d\'envoi des invitations.']]
			     ));

    $x->createField('accesurl','URL d\'accès','\Seolan\Field\ShortText\ShortText',          '255','17','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'accesurl','table'=>$this->_module->btab,'_todo'=>'save','options'=>array('readonly'=>true)));
    $x->createField('savelabel','Libelle du bouton','\Seolan\Field\ShortText\ShortText',          '30','18','1','0','0','0','0','0');


    $fieldName = 'questionsproperties';
    $ar2 = [];
    $ar2['field'] = $fieldName;
    $ar2['ftype'] = '\Seolan\Field\Serialize\Serialize';
    $ar2['fcount'] = 64;
    $ar2['forder'] = 18;
    $ar2['label'] = [TZR_DEFAULT_LANG=>'Propriétés des questions'];
    $ar2['target'] = null;
    $ar2['compulsory'] = 0;
    $ar2['browsable'] = 0;
    $ar2['queryable'] = 0;
    $ar2['translatable'] = 0;
    $ar2['multivalued'] = 0;
    $ar2['published'] = 0;
    $x->procNewField($ar2);
    $x->procEditField(array('field'=>$fieldName,
			    '_todo'=>'save',
			    'options'=>['hidden'=>true,
					'acomment'=>[TZR_DEFAULT_LANG=>'Propriétés spécifiques des quesions.']]
			    ));
    


    $this->_module->table=$this->_module->btab;

    $ar1=array();
    $ar1['translatable']=$this->_module->translatable;
    $ar1['auto_translate']=$this->_module->auto_translate;
    $ar1['btab']=$this->_module->atab;
    $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->aname;
    $ar1['publish']=$ar1['own']=false;
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->atab);
    if ($x->fieldExists('TAG')){
      $x->delField(['field'=>'TAG','action'=>'OK']);
    }
    //                                                                   size ord  obl que bro tra mul pub tar
    $x->createField('dtable','Table','\Seolan\Field\DataSource\DataSource',                          '0','3', '1','1','1','0','0','0');
    $x->createField('dfield','Champ','\Seolan\Field\ShortText\ShortText',                   '255','4', '1','1','1','0','0','0');
    $x->createField('ord','Ordre','\Seolan\Field\Real\Real',                             '3','5', '1','1','1','0','0','0');
    $x->createField('title','Libellé','\Seolan\Field\ShortText\ShortText',                  '255','6', '1','1','1','1','0','1');
    $x->createField('score','Score','\Seolan\Field\Real\Real',                           '3','7', '0','1','0','0','0','0');
    $this->_module->atable=$this->_module->atab;
  }
}
?>
