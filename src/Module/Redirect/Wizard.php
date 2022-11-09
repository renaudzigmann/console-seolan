<?php
namespace Seolan\Module\Redirect;

use Seolan\Core\DataSource\DataSource;
use Seolan\Core\Module\Module;
use Seolan\Model\DataSource\Table\Table;

class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar = NULL) {
    return parent::__construct($ar);
  }

  function istep1() {
    return parent::istep1();
  }

  function istep2() {
    if($this->_module->createstructure) {
      $this->_options->setOpt('Gestionnaire de rubrique', 'infotree', 'module', array('toid' => XMODINFOTREE_TOID));
    }
    return parent::istep2();
  }

  function iend($ar = NULL) {
    if($this->_module->createstructure) {
      $this->_module->createstructure = false;
      $ar1 = array();
      $ar1['translatable'] = $this->_module->translatable;
      $ar1['auto_translate'] = $this->_module->auto_translate;
      $ar1['trackchanges'] = $this->_module->trackchanges;
      $ar1['btab'] = $this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG] = $this->_module->bname;
      $ret = Table::procNewSource($ar1);
      if($ret['error']) {
        $this->_step = 2;
        $this->_module->createstructure = true;
        $GLOBALS['XSHELL']->tpldata["wd"]['message'] = $ret['message'];
        $this->irun($ar);
        return;
      }

      // Ajout des champs
      $modInfoTree = Module::objectFactory($this->_module->infotree);
      $x = DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=' . $this->_module->btab);
      $x->createField('source_type',      'Source type',      '\Seolan\Field\StringSet\StringSet', '0',   '3',  '1', '1', '1', '0', '0', '');
      $x->createField('source_url',       'Source url',       '\Seolan\Field\ShortText\ShortText', '255', '4',  '0', '1', '1', '0', '0', '1');
      $x->createField('source_url_is',    'Source url is',    '\Seolan\Field\StringSet\StringSet', '0',   '5',  '1', '1', '1', '0', '0', '0');
      $x->createField('source_page',      'Source page',      '\Seolan\Field\Thesaurus\Thesaurus', '0',   '6',  '0', '1', '1', '0', '0', '1', $modInfoTree->table);
      $x->createField('target_type',      'Target type',      '\Seolan\Field\StringSet\StringSet', '0',   '7',  '1', '1', '1', '0', '0', '0');
      $x->createField('target_url',       'Target url',       '\Seolan\Field\ShortText\ShortText', '255', '8',  '0', '1', '1', '0', '0', '0');
      $x->createField('target_page',      'Target page',      '\Seolan\Field\Thesaurus\Thesaurus', '0',   '9',  '0', '1', '1', '0', '0', '0', $modInfoTree->table);
      $x->createField('redirection_mode', 'Redirection mode', '\Seolan\Field\StringSet\StringSet', '0',   '10', '1', '1', '1', '0', '0', '0', '', ['default' => 'header_location']);
      $x->createField('http_code',        'HTTP code',        '\Seolan\Field\StringSet\StringSet', '0',   '11', '1', '1', '1', '0', '0', '0');

      // Ajout des valeurs des stringSet
      $f = $x->getField('source_type');
      $f->newString('Url', 'url');
      $f->newString('Page', 'page');
      $f = $x->getField('source_url_is');
      $f->newString('Equal', 'equal');
      $f->newString('Part of', 'like');
      $f->newString('Regular expression', 'regex');
      $f = $x->getField('target_type');
      $f->newString('Url', 'url');
      $f->newString('Page', 'page');
      $f = $x->getField('redirection_mode');
      $f->newString('Content replacement', 'content_replacement');
      $f->newString('Redirection to', 'header_location');
      $f->newString('End of script', 'end');
      $f = $x->getField('http_code');
      $f->newString('200 OK', '200');
      $f->newString('301 Moved Permanently', '301');
      $f->newString('302 Moved temporarily', '302');
      $f->newString('401 Unauthorized', '401');
      $f->newString('403 Forbidden', '403');
      $f->newString('404 Not Found', '404');
      $f->newString('410 Gone', '410');

      // Modification des options
      $id1 = uniqid();
      $id2 = uniqid();
      $id3 = uniqid();
      $optionsPage = $optionsUrl = array('dependency' => array(
        'f' => 'source_type',
        'op' => [$id1 => '=', $id2 => '!='],
        'dval' => [$id1 => 'url', $id2 => 'url'],
        'style' => [$id1 => '', $id2 => 'hidden'],
        'nochange' => [$id1 => '1', $id2 => '1'],
      ));
      $optionsPage['dependency']['dval'] = [$id1 => 'page', $id2 => 'page'];

      $x->procEditField(array('field' => 'source_url', 'options' => $optionsUrl));
      $x->procEditField(array('field' => 'source_url_is', 'options' => $optionsUrl));
      $x->procEditField(array('field' => 'source_page', 'options' => $optionsPage));

      $optionsUrl['dependency']['f'] = 'target_type';
      $optionsPage['dependency']['f'] = 'target_type';

      $x->procEditField(array('field' => 'target_url', 'options' => $optionsUrl));
      $x->procEditField(array('field' => 'target_page', 'options' => $optionsPage));

      $optionsCode = array(
        'dependency' => array(
          'f' => 'redirection_mode',
          'op' => [$id1 => '=', $id2 => '=', $id3 => '='],
          'dval' => [$id1 => 'header_location', $id2 => 'content_replacement', $id3 => 'end'],
          'val' => [$id1 => '301', $id2 => '200', $id3 => '404'],
        ),
        'default' => '301'
      );
      $x->procEditField(array('field' => 'http_code', 'options' => $optionsCode));

      // On doit reload la datasource pour ajouter le flabel et le fparent sur les thésaurus
      DataSource::clearCache();
      $x = DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=' . $this->_module->btab);
      $x->procEditField(array('field' => 'source_page', 'options' => ['flabel' => 'title', 'fparent' => 'linkup']));
      $x->procEditField(array('field' => 'target_page', 'options' => ['flabel' => 'title', 'fparent' => 'linkup']));

      $this->_module->table = $this->_module->btab;
    }
    return parent::iend();
  }
}

?>
