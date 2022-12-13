<?php
namespace Seolan\Field\Country;
class Country extends \Seolan\Field\Link\Link {

  public $geoLocation=true;
  public $nbFavorites=5;

 
  function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->checkbox = 0;
    $this->autocomplete_limit = 300;
  }
  function initOptions() {
   parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','geoLocation'), "geoLocation", "boolean", array(),true);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','nbFavorites'), "nbFavorites", "text", 5);
  }
  
  
  // Edition du champ sous la forme d'une liste déroulante
  function getSelect(&$value,&$options,&$r, &$rs/*contient la requete*/,$fname,$hiddenname,$myliste) {
    if(!\Seolan\Core\Shell::admini_mode() && !$value && $this->geoLocation){
      // geoip.php peut-être inclu dans php a sa compilation
      if(!function_exists('geoip_open')){
          \Seolan\Core\System::loadVendor('geoip/geoip.inc');
      }
      
      $ip = $_SERVER['REMOTE_ADDR'];
      $gi   = geoip_open($GLOBALS['LIBTHEZORRO'].'Vendor/geoip/GeoIP.dat',GEOIP_STANDARD);
      $code  = geoip_country_code_by_addr($gi, $ip);
      $value = 'COUNTRYISO:'.$code;
    }
    
    // Si on doit afficher en tête un certains nombre de pays favorits
    if($this->nbFavorites>0)
      // Récupération des pays les plus utilisés (Stocker dans _VARS (alimenter 1 fois/jour) par chk())
      $fav = \Seolan\Core\DbIni::get('xmodcountry:favoritesList'.$this->table.$this->field, 'val');
    if (@$fav)
      $result = array_merge($fav, $rs->fetchAll());
    else
      $result = $rs->fetchAll();

    $varid='';
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')) return;
    }
    $qf=@$options['query_format'];
    $oidcollection=$collection=array();
    $edit=$my_previousOid='';
    $opts=array('_published'=>'all');
    $i=0;
    if ($this->grouplist) { // la liste est groupée sur le premier champ du lien
      if (count($myliste) <= 1)
        $this->grouplist = false;
      else {
        $fields = array_keys($myliste);
        $groupField = $fields[0];
        $groupListe[$groupField] = array_shift($myliste);
      }
    }
    $used_values=null;
    if($qf==\Seolan\Core\Field\Field::QUICKQUERY_FORMAT && !\Seolan\Core\Shell::admini_mode()){
      $used_values=$this->_getUsedValues(null,null,$options);
    }
    
    foreach($result as $ors) {
      $i++;
      $koid=$ors['KOID'];
      if($used_values!==null && !$used_values[$koid]) 
	continue;
      if(!empty($mod) && $mod->object_sec && !$mod->secure($koid,':ro'))
	continue;
      if((is_array($value) && isset($value[$koid])) || ($koid==$value)) 
	$selected=' selected';
      else 
	$selected='';
      if ($this->grouplist && $currentGroup != $ors[$groupField]) {
        $currentGroup = $ors[$groupField];
        $groupLabel = $this->format_display($groupListe,$ors,$opts,null,'text');
        $edit .= '<optgroup label="'.$groupLabel.'">';
	$i++;
      }
      $display=$this->format_display($myliste,$ors,$opts,null,'text');
      $edit.='<option value="'.$koid.'"'.$selected.'>'.$display.'</option>';
      if($selected) 
	$r->text=$display;
      $oidcollection[]=$koid;
      $collection[]=$display;
	
      if(!empty($selected) && empty($first))
	$first='<option value="'.$koid.'">'.$this->label.' : '.$display.'</option>';
    }
    if($qf){
      $labelin=@$options['labelin'];
      $format=@$options['fmt'];
      if(empty($format)) 
	$format=@$options['qfmt'];
      if(empty($format)) 
	$format=$this->query_format;
      if(empty($labelin)) 
	$first='<option value="">----</option>';
      elseif(empty($first) || $format=='listbox') 
	$first='<option value="">'.$this->label.'</option>';
      else{
	$first.='<option value="">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','n/a').'</option>';
	$edit=str_replace('" selected>','">',$edit);
      }
      if (($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options))){
        $first = '';
      }
      $i++;
      if($i<2 || $format=='listbox-one') 
	$edit='<select '.($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'" id="'.$fname.'">'.$first.$edit.'</select>';
      else 
	$edit='<select '.($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'[]" id="'.$fname.'" size="'.min($i,$this->boxsize).'" multiple>'.$first.$edit.'</select>';
    }else{
      if(!$this->compulsory || !$this->usedefault) {
	$edit='<option value="">----</option>'.$edit;
	$i++;
      }
      $varid=getUniqID('v');
      if($this->multivalued)
	$cplt='name="'.$fname.'[]" size="'.min($i,$this->boxsize).'" multiple';
      else 
	$cplt='name="'.$fname.'"';
	
      $class = '';
      if($this->compulsory)
	$class = "tzr-input-compulsory";
      if (@$this->error)
	$class .= " $color";
      if ($class)
	$class = " class=\"$class\"";
	
      $edit='<select '.$cplt.' '.$class.' id="'.$varid.'" onblur="TZR.isIdValid(\''.$varid.'\');">'.$edit.'</select>';
      if($this->compulsory) {
	$edit.='<script type="text/javascript">TZR.addValidator(["'.$varid.'","compselect","'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'",'.
	  '"\Seolan\Field\Link\Link"]);</script>';
      }
    }
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->varid=$varid;
    $r->html=$edit;
  }
  
  /// Récupère la liste des pays les plus utilisés
  public function chk(&$msg) {
    $mostUseCountries=getDB()->select('SELECT '.$this->field.', count(*) as nb FROM '.$this->table.' GROUP BY '.$this->field.' ORDER BY nb DESC LIMIT '.$this->nbFavorites.'')->fetchAll();
    $lang = null;
    $lang = \Seolan\Core\Shell::getLangUser();
    if(empty($lang)){
       $lang = TZR_DEFAULT_LANG;
    }
    $countries = array();
    $i = 0;
    foreach($mostUseCountries as $country){
      $countries[$i]['KOID'] = $country[$this->field];
      $countries[$i]['title'] = self::getCountryName($country[$this->field], $lang);
      $i++;
    }
    \Seolan\Core\DbIni::set('xmodcountry:favoritesList'.$this->table.$this->field, $countries);
  }

  /// Retourne le nom d'un pays à partir de l'oid
  public static function getCountryName($koid, $lang = TZR_DEFAULT_LANG){
    return getDB()->select('SELECT title FROM COUNTRYISO WHERE KOID=? AND LANG=?', array($koid, \Seolan\Core\Shell::getLangUser()))->fetch(\PDO::FETCH_COLUMN);
  }
  /// Retourne le code d'un pays à partir d'un nom
  public static function getCountryCode($name, $code='ISO2') {
    return getDB()->select("SELECT $code FROM COUNTRYISO WHERE title=?", array($name))->fetch(\PDO::FETCH_COLUMN);
  }
}
?>
