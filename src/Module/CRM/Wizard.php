<?php

namespace Seolan\Module\CRM;

use Seolan\Core\Labels;

class Wizard extends \Seolan\Core\Module\Wizard {

  public function __construct($ar = null) {
    parent::__construct($ar);
    $this->_module->table = 'CRMCONTACTS';
    $this->_module->group = 'CRM';
    $this->_module->modulename = Labels::getSysLabel('Seolan_Module_CRM_CRM', 'modulename', 'text');
    $this->_module->comment = Labels::getSysLabel('Seolan_Module_CRM_CRM', 'comment', 'text');
    $this->_module->trackchanges = 0;
    $this->_module->available_in_display_modules = 0;
  }

  public function istep1() {
    $tagMoid = \Seolan\Core\Module\Module::getMoid(XMODTAG_TOID);
    if (empty($tagMoid)) {
      \Seolan\Core\Shell::alert('Le module TAG n`est pas installé', 'info');
    }
    parent::istep1();
  }

  public function iend($ar = NULL) {
    $this->createStructure();
    $moid = parent::iend($ar);
    \Seolan\Core\Shell::setNext("moid=$moid&function=editProperties&template=Core/Module.admin/editprop.html&tplentry=props");
  }

  public function createStructure() {
    if (!\Seolan\Core\System::tableExists($this->_module->table)) {
      \Seolan\Model\DataSource\Table\Table::procNewSource([
        'translatable' => 0,
        'btab' => $this->_module->table,
        'bname' => [TZR_DEFAULT_LANG => 'CRM - Contacts'],
        'publish' => false,
        'own' => false,
        'cread' => true,
        'tag' => false,
      ]);
    }
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->_module->table);
    $ds->createField('email', 'Email', '\Seolan\Field\ShortText\ShortText', 80, '', 1, 1, 1, 0, 0, 1, '', ['readonly' => 1]);
    $ds->createField('Marketing', 'Marketing', '\Seolan\Field\Boolean\Boolean', '', '', 1, 1, 1, 0, 0, 0, '',
      ['readonly' => 1, 'default' => 2, 'comment' => ['FR' => 'Accèpte les NL']]);
    $ds->createField('Commercial', 'Commercial', '\Seolan\Field\Boolean\Boolean', '', '', 1, 1, 1, 0, 0, 0, '',
      ['readonly' => 1, 'default' => 2, 'comment' => ['FR' => 'Contact ayant accepté des cgv indiquant l\'utilisation possible de leur mail']]);
    $ds->createField('Technic', 'Technique', '\Seolan\Field\Boolean\Boolean', '', '', 1, 1, 1, 0, 0, 0, '',
      ['readonly' => 1, 'default' => 2, 'comment' => ['FR' => 'Client actuellement sous contrat']]);
    $ds->createField('Sources', 'Sources', '\Seolan\Field\Link\Link', '', '', 0, 1, 0, 0, 1, 0, '%', ['readonly' => 1, '']);
    $ds->createField('Tags', 'Tags', '\Seolan\Field\Tag\Tag', '', '', 0, 1, 1, 0, 0, 0, '', []);
    $ds->createField('MarketingDeny', 'Refus marketing', '\Seolan\Field\Boolean\Boolean', 0, '', 1, 1, 0, 0, 0, 0, '',
      ['readonly' => 1, 'default' => 2]);
    $ds->createField('CommercialDeny', 'Refus commercial', '\Seolan\Field\Boolean\Boolean', 0, '', 1, 1, 0, 0, 0, 0, '',
      ['readonly' => 1, 'default' => 2]);
    $ds->createField('Archive', 'Archive', '\Seolan\Field\Boolean\Boolean', 0, '', 1, 1, 0, 0, 0, 0, '',
      ['readonly' => 1, 'default' => 2]);
    // template unsubscribe
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('TEMPLATES');
    $filename = TZR_TMP_DIR . 'unsubscribe.html';
    $filenameEdit = TZR_TMP_DIR . 'unsubscribe-edit.html';
    file_put_contents($filename, '<%include file="Module/CRM.unsubscribe.html"%>');
    file_put_contents($filenameEdit, '<%include file="Module/CRM.unsubscribe-edit.html"%>');
    $tab = getDB()->fetchOne('select tab, count(*) from TEMPLATES group by 1 order by 2 desc limit 1');
    $ds->procInput([
      'newoid' => 'TEMPLATES:CRMUNSUBSCRIBE',
      'title' => 'CRM désincription',
      'gtype' => 'page',
      'disp' => $filename,
      'edit' => $filenameEdit,
      'tab' => $tab,
    ]);
    $labels = new Labels();
    $t = [
      'crm_marketing_unsubscribe' => [
      'TITLE' => 'CRM désinscription Marketing',
      'FR' => 'Je souhaite ne plus recevoir de newsletter.',
      'GB' => 'I no longer wish to receive newsletter.',
      'DE' => 'Ich möchte den Newsletter nicht mehr erhalten..',
      'ES' => 'No deseo seguir recibiendo el newsletter.',
      'IT' => 'Non desidero più ricevere la newsletter.'],
    'crm_commercial_unsubscribe' => [
      'TITLE' => 'CRM désinscription Commercial',
      'FR' => 'Je souhaite ne plus recevoir d\'information commerciale.',
      'GB' => 'I no longer wish to receive commercial information.',
      'DE' => 'Ich möchte keine kommerziellen Informationen mehr erhalten.',
      'ES' => 'Ya no deseo recibir información comercial.',
      'IT' => 'Non desidero più ricevere informazioni commerciali.'],
    ];
    $labels->set_labels($t, 'global', FALSE);
  }

}
