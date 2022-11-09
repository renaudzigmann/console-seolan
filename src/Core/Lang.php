<?php
/// Classe de manipulation des codes langues (voir table LANG)
namespace Seolan\Core;

class Lang {
  public $nbLang = 0;
  static $locales = array();
  static $price_suffix = '&nbsp;&euro;';
  public $codes = NULL;
  private $long = NULL;
  private $charset = NULL;
  protected static $optionalLocalesProperties = array('decimal_point', 'thousands_sep');

  function __construct() {
    /*
     constantes relatives aux langues
     TZR_LANGUAGES
     TZR_LANG_FREELANG
     TZR_LANG_BASEDLANG
     */
    self::$locales=\Seolan\Core\Config::load('locale',true)->toArray();
    /// @todo : restreindre le traitement aux langues definies dans TZR_LANGUGES ?
    foreach(self::$locales as $l=>&$params){
      $params['smarty_date_format']=str_replace(array('F','d','Y','y','M','m'),
						array('%B','%d','%Y','%y','%b','%m'),
						$params['date_format']
						);
      // ajout des props
      // !!! dans Lang::get, code devient iso_code et $l code
      if (!isset($params['locale_code'])){
	$params['locale_code'] = strtolower($params['code']).'_'.strtoupper($l);
      }
      if (!isset($params['country_code'])){
	$params['country_code'] = strtoupper($l);
      }
      if (!isset($params['lang_code'])){
	$params['lang_code'] = strtoupper($params['code']);
      }
      if ($l != 'GB'){
	foreach(static::$optionalLocalesProperties as $optionalProperty){
	  if (!isset($params[$optionalProperty])){
	    $params[$optionalProperty] = self::$locales['GB'][$optionalProperty];
	  }
	}
      }
    }
    //VC : Si on charge les labels des langues, alors le chargement se fera dans la langue de base et non dans la bonne langue
    //\Seolan\Core\Labels::loadLabels('Seolan_Core_Lang');
  }

  /**
   * Recupere l'ensemble des propriétés d'une langue
   * @todo : voir getLocalePro : propriétés non définies
   */
  public static function getLocale($lang=NULL) {
    if(empty($lang)) $lang=\Seolan\Core\Shell::getLangData();
    return self::$locales[$lang];
  }
  /// Recupere une propriété d'une langue
  public static function getLocaleProp($name,$lang=NULL){
    if(empty($lang)) $lang=\Seolan\Core\Shell::getLangData();
    if(empty(Lang::$locales[$lang][$name])) return @Lang::$locales[TZR_DEFAULT_LOCALE_PROPERTY_CODE][$name];
    else return @Lang::$locales[$lang][$name];
  }

  public static function setLocale($lang=NULL) {
    if(empty($lang)) $lang=\Seolan\Core\Shell::getLangData();
    $locale=self::$locales[$lang]["locale_code"].".".strtoupper(self::$locales[$lang]["charset"]);
    \Locale::setDefault($locale);
    // permet les tris des caractères accentués
    setlocale(LC_COLLATE, $locale);
    return self::$locales[$lang];
  }
  /// Liste des codes langues
  public static function getCodes($select=NULL,$sort=false) {
    global $XLANG;
    $langs= $XLANG->_getCodes($sort);
    if($select) return $langs[$select];
    else return $langs;
  }
  private function _getCodes($sort=false) {
    $nbsys = 0;
    if(!isset($this->codes)) {
      $this->codes=array();
      $this->iso_codes=array();
      $this->system=array();
      $this->long=array();
      $this->text=array();
      $this->charset=array();
      $this->countries_codes = array();
      $this->locales_codes = array();
      $myi=0;
      global $TZR_LANGUAGES;
      
      foreach($TZR_LANGUAGES as $l=>$li) {
        $this->codes[$myi]=$l;
        $this->iso_codes[$myi]=self::$locales[$l]['code'];
        $this->system[$myi]=self::$locales[$l]['system'];
	if($this->system[$myi]==true) $nbsys++;	
        $this->long[$myi]=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Lang',$l);
        $this->text[$myi]=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Lang',$l);
        $this->charset[$myi]=Lang::$locales[$l]['charset'];
        $this->locales_codes[$myi]=Lang::$locales[$l]['locale_code'];
        $this->countries_codes[$myi]=Lang::$locales[$l]['country_code'];
        $myi++;
      }
      $this->nbLang=count($this->codes);
    }
    $ret=array('code'=>$this->codes,
	       'iso_code'=>$this->iso_codes,
	       'long'=>$this->long,
	       'text'=>$this->text,
	       'charset'=>$this->charset,
	       'system'=>$this->system,
	       'locale_code'=>$this->locales_codes,
	       'country_code'=>$this->countries_codes);
    if($sort) array_multisort($ret['text'],$ret['code'],$ret['iso_code'],$ret['long'],$ret['charset']);
    $ret['nb_lang_system'] = $nbsys;
    return $ret;
  }

  static public function get($lang) {
    return array('iso'=>Lang::$locales[$lang]['code'],
		 'long'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_Lang',$lang),
		 'text'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Lang',$lang),
		 'charset'=>Lang::$locales[$lang]['charset'],
		 'country_code'=>Lang::$locales[$lang]['country_code'],
		 'locale_code'=>Lang::$locales[$lang]['locale_code'],
		 'code'=>$lang);
  }

  public static function getCharset($l=NULL){
    //si on a demande un charset particulier d'une langue
    if( $l!==NULL && array_key_exists($l,self::$locales) ){
      return self::$locales[$l]['charset'];
    }else{ //on veut le charset en cours
      if(!empty($_REQUEST['_charset'])){//il est definit dans l'url
	return $_REQUEST['_charset'];
      }else{
        if(isAjaxContext()){
          return 'UTF-8';
        }elseif(\Seolan\Core\Shell::admini_mode()){
	  return TZR_ADMINI_CHARSET;
	}else{
	  $lang_data=\Seolan\Core\Shell::getLangData();
	  return self::$locales[$lang_data]['charset'];
	}
      }
    }
  }
  /**
   * Nombre formaté selon la langue en cours
   * @param {float|string} $number Nombre à formater
   * @param {int} $decimals Nombre de décimales à afficher
   * @return {string} Nombre formaté
   */
  public static function number_format($number, $decimals) {
    return number_format(self::floatval($number), $decimals, self::getLocaleProp('decimal_point'), self::getLocaleProp('thousands_sep'));
  }

  /**
   * Prix formaté selon la langue en cours
   * @param {float} $number Nombre à formater
   * @param {string} $suffix Suffix à ajouter. Ex: &nbsp;&euro; sinon prend celui par défaut
   * @return {string} Prix formaté
   */
  public static function price_format($number, $suffix = '') {
    return self::number_format($number, 2).($suffix ?: self::$price_suffix);
  }

  /**
   * Nombre au format float selon la langue en cours
   * @param {float|string} $number Nombre à formater
   * @return {float} Nombre formaté
   */
  public static function floatval($number) {
    // Pour une chaine on force la conversion du nombre au format anglais
    if (is_string($number)) {
      $number = str_replace(self::getLocaleProp('thousands_sep'), '', $number); 
      $number = str_replace(self::getLocaleProp('decimal_point') , '.', $number); 
    }
    return floatval($number);
  }

  /**
   * Liste formatée selon la langue en cours
   * @param {array} $list Tableau de termes
   * @param {string} $separator Séparateur à placer entre les termes
   * @param {string} $last_separator Séparateur à placer entre le dernier et l'avant dernier terme
   * @return {string} Liste formatée
   */
  public static function join(array $list, $separator = ', ', $last_separator = ', ') {
    $last = array_pop($list);
    if ($list) {
      return implode($separator, $list) . ' ' . $last_separator . ' ' . $last;
    }
    return $last;
  }

  /**
   * Liste formatée selon la langue en cours avec un "et" avant le dernier terme
   * @see XLang::join()
   */
  public static function join_and(array $list, $separator = ', ') {
    return self::join($list, $separator, self::getLocaleProp('and'));
  }

  /**
   * Liste formatée selon la langue en cours avec un "ou" avant le dernier terme
   * @see XLang::join()
   */
  public static function join_or(array $list, $separator = ', ') {
    return self::join($list, $separator, self::getLocaleProp('or'));
  }
}

