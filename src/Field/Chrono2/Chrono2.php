<?php
namespace Seolan\Field\Chrono2;
/// Gestion des champs chrono
class Chrono2 extends \Seolan\Field\ShortText\ShortText {
  public $chrono_format = '{Date:Y}{chrono:5}';
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL) {
    parent::__construct($obj) ;
    $this->readonly=true;
    $this->multivalued=false;
    $this->translatable=false;
  }
  function initOptions() {
    parent::initOptions();
    $this->readonly=true;
    $editgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','chrono_format'),'chrono_format','text',array(), NULL, $editgroup);
    $this->_options->delOpt('edit_format_text');
    $this->_options->delOpt('edit_format');
    $this->_options->delOpt('listbox');
    $this->_options->delOpt('boxsize');
    $this->_options->delOpt('listbox_limit');
  }

  function input(&$value,&$options=array(),$fields_complement=null) {
    $options['value']=$value=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','computed');
    return parent::input($value, $options, $fields_complement);
  }
  function post_input($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);

    // substitution des chaines contenant des codes de dates
    $root = preg_replace_callback('/{Date:([^}]+)}/i', function ($matches) { return date($matches[1]);}, $this->chrono_format);

    /// création du préfixe
    $rootprefix = preg_replace_callback('/{Chrono:([^}]+})/i', function ($matches) { return '';}, $root);

    // on met un lock pour éviter de faire des chronos en double
    if($lock=\Seolan\Library\Lock::getLock('Chrono2-'.$this->table.'-'.$this->field)) {
      $chrono=\Seolan\Core\DbIni::get('Chrono::'.$this->table.'::'.$this->field);
      $chronoFmt=\Seolan\Core\DbIni::get('ChronoFmt::'.$this->table.'::'.$this->field);
      
      // recherche de la taille du chrono, par exemple sur 5 positions
      $sizePreg = preg_match('/{Chrono:([^}]+)}/i', $this->chrono_format, $matches);
      if(empty($matches[1])) $size=5;
      else $size=$matches[1];
      $chrono=$chrono[0];
      $chronoFmt=$chronoFmt[0];

      // si le chrono n'est pas positionné dans _VARS on le calcule à partir de la table
      if(!isset($chrono)||($chronoFmt!=$root)) {
	// création de la chaine pour interrogation de la base de données
	$rootquery = preg_replace_callback('/{Chrono:([^}]+})/i', function ($matches) { return '%';}, $root);
	// calcul d'un nouveau chrono si nécessaire
	$max=getDB()->fetchOne('select max('.$this->field.') from '.$this->table.' where '.$this->field.' like ?',array($rootquery),false);
	if(empty($max)) $chrono=1;
	else $chrono=substr($max, -$size)+1;
      }
      $chrono = sprintf($rootprefix.'%0'.$size.'d', $chrono);
      \Seolan\Core\DbIni::set('Chrono::'.$this->table.'::'.$this->field,$chrono);
      \Seolan\Core\DbIni::set('ChronoFmt::'.$this->table.'::'.$this->field,$rootquery);
      $r->raw=$chrono;

      \Seolan\Library\Lock::releaseLock($lock);
      $this->trace(@$options['old'],$r);
      return $r;
    } else {
      \Seolan\Core\Logs::critical("warning","Could not get lock on Chrono2 field : ".$this->table.'::'.$this->field);
      throw new \Exception('Chrono2 Error '.$this->table.'::'.$this->field);
    }
  }
  
  
  public function post_edit_dup($values, $options) {
    $p = new \Seolan\Core\Param($options,array());
    $oidsrc = $p->get('oidsrc');
    $options['oid']=$oidsrc;
  
    return $this->post_input(null, $options);
  }
}
?>
