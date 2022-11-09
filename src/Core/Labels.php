<?php
namespace Seolan\Core;

class Labels {
  public $labs;
  public static $LABELS=array();
  public static $SQLLABELS=array();

  function __construct() {
    $this->labs = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LABELS');
    \Seolan\Core\Labels::createAMsg();
  }
  
  /// Charge les libellés du module passé en paramètre. Ne sont pas rechargés plusieurs fois sauf si $force=true
  public static function loadLabels(string $module, $force=false) {
    $module=self::checkPath($module);
    $module=preg_replace('/[^.a-z0-9_-]/i','',$module);
    if($module && (empty(\Seolan\Core\Labels::$LABELS[$module]) || $force)) {
      // recherche de la langue utilisateur en cours
      $lang_code=strtoupper(\Seolan\Core\Shell::getLanguser());
      $system=@\Seolan\Core\Lang::$locales[$lang_code]['system'];
      if(!$system) {
        if(isset(\Seolan\Core\Lang::$locales[$lang_code]['subst'])) $lang_code=\Seolan\Core\Lang::$locales[$lang_code]['subst'];
        else $lang_code=TZR_DEFAULT_LANG;
      }

      // recherche du code iso de la langue "systeme"
      $infos=decodeClassname($module,'_',false);
      $lang_code=strtolower($GLOBALS['TZR_LANGUAGES'][$lang_code]);

      @include_once($infos['dir'].'/locale/'.$lang_code.'/'.$infos['file']);

      if(file_exists($_SERVER['DOCUMENT_ROOT'].'../tzr/locale/'.$module.'.'.$lang_code.'.php')) {
        @include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/locale/'.$module.'.'.$lang_code.'.php');
      }
    }
  }

  /// Recupere le code langue des labels
  public static function getLangCode() {
    $lang_code=$GLOBALS['TZR_LANGUAGES'][\Seolan\Core\Shell::getLangUser()];
    $lang_code=strtolower($lang_code);
    return $lang_code;
  }

  /// Recharge tous les messages déjà chargés, par exemple en cas de changement de langue en cours de route
  public static function reloadLabels() {
    foreach(\Seolan\Core\Labels::$LABELS as $l=>$foo) {
      if(is_array($foo)) {
	\Seolan\Core\Labels::loadLabels($l,true);
      }
    }
  }

  /// Rend une image avec le A HREF autour et les alt et title correctement positionnes
  function getIconWithLink($path, $label, $url, $title) {
    $img=self::getSysLabel($path, $label);
    return '<a href="'.$url.'" title="'.$title.'">'.$img.'</a>';
  }

  /// Nettoie le path (quand on passe le nom de la classe par exemple). Remplace les backslashes par des _
  static function checkPath($path){
    return trim(str_replace('\\','_',$path),'_');
  }

  /// Recupère un label système
  static function getSysLabel(string $path, ?string $label=null, ?string $type='both', $failoverpath=NULL, $failoverlabel=NULL) {
    if(!isset(\Seolan\Core\Labels::$LABELS[$path])) {
      \Seolan\Core\Labels::loadLabels($path);
    }

    $label=strtolower($label);
    switch($type) {
    case 'both':
      if(isset(\Seolan\Core\Labels::$LABELS[$path][$label.'_fontclass'])) {
        return '<span class="glyphicon '.\Seolan\Core\Labels::$LABELS[$path][$label.'_fontclass'].'" aria-hidden="true"></span>';
      } elseif(isset(\Seolan\Core\Labels::$LABELS[$path][$label.'_ico'])) {
	if(is_array(\Seolan\Core\Labels::$LABELS[$path][$label.'_ico'])) {
	  $title=@\Seolan\Core\Labels::$LABELS[$path][$label];
	  $urlname=TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$path][$label.'_ico']['src'];
	  $height=\Seolan\Core\Labels::$LABELS[$path][$label.'_ico']['height'];
	  $width=\Seolan\Core\Labels::$LABELS[$path][$label.'_ico']['width'];
	  return '<img class="tzr-picto" src="'.$urlname.'" ALT="'.
	    $title.'" height="'.$height.'" width="'.$width.'"/>';
	} else {
	  $title=@\Seolan\Core\Labels::$LABELS[$path][$label];
	  $urlname=TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$path][$label.'_ico'];
	  return  '<img class="tzr-picto" src="'.$urlname.'" ALT="'.$title.'" />';
	}
      } elseif(!empty($failoverpath)) {
        if(isset(\Seolan\Core\Labels::$LABELS[$failoverpath][$failoverlabel.'_fontclass'])) {
          return  '<span class="glyphicon '.\Seolan\Core\Labels::$LABELS[$failoverpath][$failoverlabel.'_fontclass'].'" aria-hidden="true"></span>';
        } elseif(isset(\Seolan\Core\Labels::$LABELS[$failoverpath][$failoverlabel.'_ico'])) {
          $urlname=TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$failoverpath][$failoverlabel.'_ico'];
          $title=@\Seolan\Core\Labels::$LABELS[$path][$label];
          return '<img class="tzr-picto" src="'.$urlname.'" ALT="'.$title.'" />';
        }
      }
      break;
    case 'csico':
      if(isset(\Seolan\Core\Labels::$LABELS[$path][$label.'_fontclass'])) {
	return  '<span class="glyphicon '.\Seolan\Core\Labels::$LABELS[$path][$label.'_fontclass'].'"></span>';
      } elseif(isset(\Seolan\Core\Labels::$LABELS[$path][$label.'_ico'])) {
	$urlname=TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$failoverpath][$failoverlabel.'_ico'];
	$title=@\Seolan\Core\Labels::$LABELS[$path][$label];
	return  '<img class="tzr-picto" src="'.$urlname.'" ALT="'.$title.'" />';
      }
      break;
    case 'url':
      if(isset(\Seolan\Core\Labels::$LABELS[$path][$label.'_fontclass'])) {
        return '<span class="deprecated-icon-url glyphicon '.\Seolan\Core\Labels::$LABELS[$path][$label.'_fontclass'].'" aria-hidden="true"></span>';
      } elseif(isset(\Seolan\Core\Labels::$LABELS[$path][$label.'_ico'])) {
	return TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$path][$label.'_ico'];
      }
      break;
    }

    return \Seolan\Core\Labels::$LABELS[$path][$label] ?? $label;
  }

  /// Recupère un label système
  static function getTextSysLabel(string $path, ?string $label) {
    if(!isset(\Seolan\Core\Labels::$LABELS[$path])) {
      \Seolan\Core\Labels::loadLabels($path);
    }
    $label=strtolower($label);
    return \Seolan\Core\Labels::$LABELS[$path][$label] ?? $label;
  }

  /// Recupère un label système
  static function getTextSysLabelFromClass(string $path, ?string $label) {
    $path=self::checkPath($path);
    if(!isset(\Seolan\Core\Labels::$LABELS[$path])) {
      \Seolan\Core\Labels::loadLabels($path);
    }
    $label=strtolower($label);
    return \Seolan\Core\Labels::$LABELS[$path][$label] ?? $label;
  }
  /// Recupère tout les labels système déjà chargé
  static function &getSysLabels($type='both') {
    $r=array();
    foreach(\Seolan\Core\Labels::$LABELS as $pack => &$packset) {
      foreach($packset as $code => &$label) {
	if($type=="both") {
          if(isset(\Seolan\Core\Labels::$LABELS[$pack][$code.'_fontclass'])) {
            $r[$pack][$code]= '<span class="glyphicon '.\Seolan\Core\Labels::$LABELS[$pack][$code.'_fontclass'].'" aria-hidden="true"></span>';
          } elseif(isset(\Seolan\Core\Labels::$LABELS[$pack][$code.'_ico'])) {
	    $title=@\Seolan\Core\Labels::$LABELS[$pack][$code];
	    if(is_array(\Seolan\Core\Labels::$LABELS[$pack][$code.'_ico'])) {
	      $urlname=TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$pack][$code.'_ico']['src'];
	      $height=\Seolan\Core\Labels::$LABELS[$pack][$code.'_ico']['height'];
	      $width=\Seolan\Core\Labels::$LABELS[$pack][$code.'_ico']['width'];
	      $r[$pack][$code]= '<img class="tzr-picto" src="'.$urlname.'" alt="'.$title.'" '.
		'height="'.$height.'" width="'.$width.'"/>';
	    } else {
	      $urlname=TZR_ICO_URL.\Seolan\Core\Labels::$LABELS[$pack][$code.'_ico'];
	      $r[$pack][$code]= '<img class="tzr-picto" src="'.$urlname.'" alt="'.$title.'" />';
	    }
	  }
	}
	if(!isset($r[$pack][$code])) $r[$pack][$code]=$label;
	$r[$pack][$code."_text"]=$label;
      }
    }
    return $r;
  }

  /// Recupère un label de la table LABELS
  function get_label($ar=array('textonly'=>false)) {
    static $labels = [];
    $selectors=@$ar['selectors'];
    if(empty($selectors)) $selectors='global';
    if(!empty($ar['oid'])) $cond="KOID='".$ar['oid']."'";
    $variable=$ar['variable'];
    if(!empty($variable)) $cond="VARIABL='$variable'";
    $lang_user=\Seolan\Core\Shell::getLangUser();
    $filefield=@$this->labs->desc['AFILE'];
    $imagefield=@$this->labs->desc['PICTO'];
    if(!$this->labs->getTranslatable()) $lang_user = TZR_DEFAULT_LANG;
    if (isset($labels[$cond.$selectors.$lang_user])) {
      $ors = $labels[$cond.$selectors.$lang_user];
    } else {
      $ors = $labels[$cond.$selectors.$lang_user] =
        getDB()->fetchRow('select * from LABELS where SELECTO=? and LANG=? and '.$cond, array($selectors,$lang_user));
    }
    $fooparam=array('_charset'=>@$_REQUEST['_charset']);
    $res=NULL;
    if($ors) {
      $KOID=$ors['KOID'];
      $label=$this->labs->desc['LABEL']->display($ors['LABEL'],$fooparam);
      $file=@$ors['AFILE'];
      $picto=@$ors['PICTO'];
      if(isset($file)) {
	$res[$variable]=$label->html;
	$res[$variable.'_file']=$filefield->display($file,$fooparam);
      }elseif($picto!='' && \Seolan\Core\Ini::get('icons') && $picto!=TZR_UNCHANGED) {
	$opicto=$imagefield->display($picto,$fooparam);
	$res[$variable]=$opicto->html;
	$res[$variable.'_file']=$opicto;
      }else{
	$res[$variable]=$label->html;
      }
      $res[$variable.'_text']=$label->text;
    }
    if(!empty($ar['textonly'])) return $res[$variable.'_text'];
    return $res;
  }

  /**
   * Retourne un texte de label
   * @param String $label_name
   * @return String
   */
  public function get_label_text($label_name){
    return $this->get_label(array(
      'textonly' => True,
      'variable' => $label_name
    ));
  }

  /// insertion de nouveaux libelles. $force=true: le libelle est force a la
  /// valeur meme s'il existe deja
  function set_labels($ar, $selector='global', $force=false) {
    $retarray=array();
    foreach($ar as $code=>$label) {
      if(!$exist=getDB()->fetchOne('select KOID from LABELS where VARIABL=? AND SELECTO=? AND LANG=?', [$code, $selector, TZR_DEFAULT_LANG])) {
	$oid=$this->labs->getNewOid();
      } else {
	$oid=$exist;
      }
      $retarray[$code]=$oid;
      // traitement de la langue par defaut
      $ar1=array('_local'=>1);
      $ar1['VARIABL']=$code;
      $ar1['LANG_DATA']=TZR_DEFAULT_LANG;
      $ar1['SELECTO']=$selector;
      $ar1['LABEL']=$label[TZR_DEFAULT_LANG];
      $ar1['TITLE']=$label['TITLE'];
      // Autorise l'insertion de code HTML depuis le front-office
      $ar1['options']['LABEL']['raw'] = true;
      if (!$exist) {
	$ar1['newoid']=$oid;
	$ret=$this->labs->procInput($ar1);
      } elseif ($force) {
        $ar1['oid']=$oid;
        $ret=$this->labs->procEdit($ar1);
      }
      foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$iso) {
	if($lang!=TZR_DEFAULT_LANG) {
	  $ar1=array('_local'=>1);
	  $ar1['VARIABL']=$code;
	  $ar1['LANG_DATA']=$lang;
	  $ar1['SELECTO']=$selector;
	  $ar1['LABEL']=$label[$lang];
	  $ar1['TITLE']=$label['TITLE'];
	  
	  $ar1['oid']=$oid;
          if (!$this->labs->getAutoTranslate()) {
            $exist = getDB()->fetchExists('select KOID from LABELS where VARIABL=? AND SELECTO=? AND LANG=?', [$code, $selector, $lang]);
          }
	  if(!$exist || $force) {
	    $ret=$this->labs->procEdit($ar1);
	  }
	}
      }
    }
    return $retarray;
  }

  /// Ajoute un label à la table LABELS
  public function set_label($variable, $text, $selector = 'global', $title = '', $force = false) {
    return $this->set_labels([$variable=>[
      TZR_DEFAULT_LANG => $text,
      'TITLE' => empty($title) ? 'Seolan: '.$text : $title,
    ]], $selector, $force);
  }

  /// Recupère tous les labels de la table LABELS pour une liste de selecteur
  function get_labels($ar=NULL) {
    static $labels = [];
    $selectors=$ar['selectors'];
    $lang_user=\Seolan\Core\Shell::getLangUser();
    $filefield=@$this->labs->desc['AFILE'];
    $imagefield=@$this->labs->desc['PICTO'];
    $labelfield=@$this->labs->desc['LABEL'];
    $fooparam=@array('_charset'=>$_REQUEST['_charset']);
    $res=NULL;
    if(!$this->labs->getTranslatable()) $lang_user = TZR_DEFAULT_LANG;
    $key = implode($selectors) . $lang_user;
    if (isset($labels[$key])) {
      return $labels[$key];
    }
    foreach($selectors as $i=>$selector){
      $rs=getDB()->select('select * from LABELS where SELECTO=? and LANG=?',array($selector,$lang_user));
      while($ors=$rs->fetch()) {
	$KOID=$ors['KOID'];
	$variable=$ors['VARIABL'];
	if(isset($res[$variable])) unset($res[$variable]);
	if(isset($res[$variable.'_text'])) unset($res[$variable.'_text']);
	if(isset($res[$variable.'_file'])) unset($res[$variable.'_file']);
	$label=NULL;
	$label=$labelfield->display($ors['LABEL'],$fooparam);
	$file=@$ors['AFILE'];
	$picto=@$ors['PICTO'];
	if(isset($file)) {
	  $res[$variable]=$label->html;
          $res[$variable.'_file']=$filefield->display($file,$fooparam);
        }elseif($picto!='' && \Seolan\Core\Ini::get('icons') && $picto!=TZR_UNCHANGED) {
	  $opicto=$imagefield->display($picto,$fooparam);
	  $res[$variable]=$opicto->html;
	  $res[$variable.'_file']=$opicto;
	}else{
	  $res[$variable]=$label->html;
	}
	$res[$variable.'_text']=$label->text;
      }
    }
    return $labels[$key] = $res;
  }

  /// Recupere un label systeme personnalisable via la table des labels
  function getCustomSysLabel($syspath,$syslab='',$selectors='global', $type='text'){
    $lab=$syspath.(!empty($syslab)?'.'.$syslab:'');
    $labtmp=$this->get_label(array('variable'=>$lab,'selectors'=>$selectors));
    if (!empty($labtmp[$lab]) && $type == 'url') {
      $text = $labtmp[$lab.'_file']->resizer;
    } elseif(!empty($labtmp[$lab]))
      $text = str_replace('<br />','',$labtmp[$lab]);
    else
      $text=\Seolan\Core\Labels::getSysLabel($syspath,$syslab,$type);
    if(empty($text)) $text=$syspath;
    return $text;
  }
  function del($lab) {
    getDB()->execute("DELETE FROM LABELS where VARIABL='$lab'");
  }

  static function &getAMsg($id,$lang=NULL,$nl2br=true) {
    static $msgs=array();
    if(empty($msgs)) {
      $rs1=getDB()->fetchAll('SELECT * FROM AMSG');
      foreach($rs1 as $ors1) {
	$msgs[$ors1['MOID']][$ors1['MLANG']]= $nl2br ? nl2br($ors1['MTXT']) : $ors1['MTXT'];
      }
      $msgs['foo']=1;
      unset($rs1);
    }
    if(empty($lang)) {
      
      return $msgs[$id];
    } else {
      static $empt='';
      if(isset($msgs[$id][$lang])) return $msgs[$id][$lang];
      else return $empt;
    }
  }
  static function updateAMsg($id, $txt, $lang=NULL) {
    if(empty($lang) && is_array($txt)) {
      foreach($txt as $lang => $txt1) {
	\Seolan\Core\Labels::updateAMsg($id, $txt1, $lang);
      }
    } elseif(empty($lang)) {
      foreach($GLOBALS['TZR_LANGUAGES'] as $lang => $iso) {
	\Seolan\Core\Labels::updateAMsg($id, $txt, $lang);
      }
    } else {
      $cnt=getDB()->count('SELECT COUNT(*) FROM AMSG WHERE MOID="'.$id.'" AND MLANG="'.$lang.'"');
      if($cnt) {
	getDB()->execute('UPDATE AMSG SET MTXT=? WHERE MOID=? AND MLANG=?',array($txt,$id,$lang));
      } else {
	getDB()->execute('INSERT INTO AMSG SET MTXT=?, MOID=?, MLANG=?',array($txt,$id,$lang));
      }
    }
  }
  static function deleteAMsg($id) {
    getDB()->execute('DELETE FROM AMSG WHERE AMOID="'.$id.'"');
  }
  static function createAMsg() {
    if(!\Seolan\Core\System::tableExists('AMSG')) {
      getDB()->execute("CREATE TABLE `AMSG` (".
                       "`MOID` varchar(40) NOT NULL default '',".
                       "`MLANG` char(2) NOT NULL default '',".
                       "`MTXT` varchar(250) default NULL,".
                       "PRIMARY KEY  (`MOID`,`MLANG`)".
                       ");");
    }
  }

  /**
   * Retourne l'objet labels du Shell
   *
   * @return Labels Objet Labels construit
   * @throws Exception si la propriété labels n'est pas initialisée
   */
  public static function getInstance() {
    if (!isset($GLOBALS['XSHELL']->labels))
      throw new \Exception('Too early to get a custom label: $XSHELL->labels not set');
    return $GLOBALS['XSHELL']->labels;
  }

  /**
   * Récupère facilement un label ou un syslabel via son nom de variable ou son path
   *
   * @param string $variable Variable du label avec path si désiré
   *   Exemples : 'mon_label_perso' ou 'xsession.passwordrequest_sent'
   * @param string $selector Sélecteur permettant de regrouper les labels (par défaut global est toujours chargé en front et en back)
   * @param array $tokens Tableau de tokens de remplacement dans le libellé de la forme ['%token1' => 'Texte 1', '%token2' => 'Texte 2', ...]
   * @param string $type text|html|url Type du retour (text par défaut)
   * @return string Chaine traduite
   * @todo : si on fait ____('mot', 'un selecteur') on obtient une variable égale au label mais le throw est nécessaire pour obtenir la création du label
   */
  public static function get($variable, $selectors = 'global', $tokens = [], $type = 'text') {
    $label = self::getInstance()->getCustomSysLabel($variable, '', $selectors, $type);
    if ($label === $variable) {
      throw new \Exception('Undefined label variable: '.$variable);
    }
    return self::applyTokens($label, $tokens);
  }

  /**
   * Récupére la traduction d'un texte
   *
   * @param string $text Texte à traduire
   * @param string $selectors string Sélecteur permettant de regrouper les labels (par défaut global est toujours chargé en front et en back)
   * @param array $tokens Tableau de tokens de remplacement dans le libellé de la forme ['token1' => 'Texte 1', 'token2' => 'Texte 2', ...] pour un texte '... %token1 ... %token2 ...'
   * @param boolean $force Force le raffraichissement du label si demandé
   * @return string Chaine traduite
   */
  public static function getText($text, $selector = 'global', $tokens = [], $force = false) {
    $variable = self::getVariableFromText($text);
    try {
      $insert = false;
      $get_text = self::get($variable, $selector);
    } catch (\Exception $e) {
      $insert = true;
    }
    if ($insert || $force) {
      self::getInstance()->set_label($variable, $text, $selector, '', $force);
      $get_text = $text;
    }
    return self::applyTokens($get_text, $tokens);
  }

  /**
   * Récupère la traduction d'un texte singulier ou pluriel selon le nombre passé en paramètre
   *
   * @param string $single Texte à traduire si le nombre est <= 1
   * @param string $plural Texte à traduire si le nombre est > 1
   * @param int $number Nombre à comparer pour choisir quel texte renvoyer
   * @param string $selectors string Sélecteur permettant de regrouper les labels (par défaut global est toujours chargé en front et en back)
   * @param array $tokens Tableau de tokens de remplacement dans le libellé de la forme ['%token1' => 'Texte 1', '%token2' => 'Texte 2', ...]
   * @param boolean $force Force le raffraichissement du label si demandé
   * @param array $tokens Permet de remplacer les signes %token par une valeur selon un tableau associatif
   * @return string Chaine traduite
   */
  public static function getSingleOrPluralText($single, $plural, $number, $selector = 'global', $tokens = [], $force = false) {
    return self::getText($number > 1 ? $plural : $single, $selector, $tokens, $force);
  }

  /**
   * Permet de remplacer les %nomdevariable du texte du label par les valeurs passées via les paramètres
   *
   * @param string $get_text Texte traduit
   * @param array $tokens Tableau des tokens à remplacer
   * @param string $prefix Préfixe des tokens dans le texte traduit ("%" par défaut)
   * @return string Texte traduit avec tokens insérés
   */
  public static function applyTokens($get_text, $tokens, $prefix = '%') {
    foreach ($tokens as $token => $value) {
      $get_text = str_replace($prefix.$token, $value, $get_text);
    }
    return $get_text;
  }

  /**
   * Retourne le nom de variable automatiquement créé à partir d'un texte
   *
   * @param string $text Texte à convertir
   * @see smarty_function_label
   */
  public static function getVariableFromText($text) {
    return substr(preg_replace(['/[^\w]+/','/(^_|_$)/'], ['_',''], strtolower(removeaccents($text))), 0, 40);
  }
}
