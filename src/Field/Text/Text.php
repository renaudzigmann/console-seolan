<?php
namespace Seolan\Field\Text;

class Text extends \Seolan\Core\Field\Field {
  public $separator="\n";
  public $arrow2link=false;
  public $longtext=false;
  public $enabletags=false;
  public $indexable=true;
  public $tagglobalsearch=false;
  public $interpretsmileys=false;
  public $usertag_table='USERS';
  public $usertag_id_field='alias';
  public $usertag_name_field='fullnam';
  public $usertag_mail_field='email';
  public static $USERTAG_PREFIX = '@'; // not yet parameterized in js
  public static $USERTAG_SPACE = '_';
  protected $tags_allowed = null;
  protected $_usermoid = null;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function initOptions() {
    parent::initOptions();
    $editgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','longtext'), 'longtext', 'boolean', [], false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','arrow2link'), 'arrow2link', 'boolean', [], false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','interpretsmileys'), 'interpretsmileys', 'boolean', [], false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','enabletags'), 'enabletags', 'boolean', [], false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','tagglobalsearch'), 'tagglobalsearch','boolean', [], false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format'),'edit_format','text');
    $querygroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','query');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','indexable'), 'indexable', 'boolean', NULL, true, $querygroup);

  }
  function my_quickquery($value,$options=NULL) {
    $r=$this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($this->separator,$value);
    if(isset($value)) $value=htmlspecialchars($value);
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $r->html.='<input '.($this->isFilterCompulsory($options) ? 'required' : '').' type="text" name="'.$this->field.'" size="30" value="'.$value.'">';
    return $r;
  }
  function _mklinks(&$text) {
    $t=$text;
    static $expr=
      array("([_[:alnum:]-]{1,50})[ ]*-&gt;[ ]*([[:alnum:]\-_]+)",
	    "\"([^\"]{1,50})\"[ ]*->[ ]*([[:alnum:]\-_]+)",
	    "&quot;([^,><\.]{1,50})&quot;[ ]*-&gt;[ ]*([[:alnum:]\-_]+)",
	    "\"([^\"]{1,50})\"[ ]*-&gt;[ ]*([[:alnum:]\-_]+)",
	    "([[:alnum:]]{1,50})[ ]*->[ ]*([[:alnum:]\-_]+)");
    foreach($expr as $i => $ex) {
      $regs=array();
      while(preg_match('/'.$ex.'/',$t,$regs)) {
	if(\Seolan\Core\Alias::checkAlias($regs[2])) {
	  $t=str_replace($regs[0],'<a href="'.\Seolan\Core\Alias::mklink($regs[2]).'">'.
			 $regs[1].'</a>',$t);
	} else {
	  if(\Seolan\Core\Shell::admini_mode()) {
	    $t=str_replace($regs[0],
			   '<font style="color:white;background-color:red;">'.$regs[1].'</font>',$t);
	  } else {
	    $t=str_replace($regs[0], $regs[1],$t);
	  }
	}
      }
    }
    static $expr2=array("(HREF|href)=\"\[([_[:alnum:]-]+)\]");;
    foreach($expr2 as $i => $ex) {
      $regs=NULL;
      while(preg_match('@'.$ex.'@',$t,$regs)) {
	if(\Seolan\Core\Alias::checkAlias($regs[2])) {
	  $t=str_replace($regs[0],' href="'.\Seolan\Core\Alias::mklink($regs[2]),$t);
	} else {
	  if(\Seolan\Core\Shell::admini_mode()) {
	    $t=str_replace($regs[0],
			   ' style="color:white;background-color:red;" href="'.$regs[2],$t);
	  } else {
	    $t=str_replace($regs[0],' foo="',$t);
	  }
	}
      }
    }
    return $t;
  }
  protected function _interpretSomeSmileys(&$text) {
    $text=str_replace([':)',':('],
		      ['<span class="glyphicon csico-smilley-happy" aria-hidden="true"></span>',
		       '<span class="glyphicon csico-smilley-sad" aria-hidden="true"></span>'],$text);
    return $text;
  }
  
  function _mklinks2(&$t) {
    $expr2="(HREF|href)=\n?\"\[([0-9]+),([_[:alnum:]\:-]+)\]";
    $regs=NULL;
    while(preg_match('@'.$expr2.'@',$t,$regs)) {
      if(\Seolan\Core\Kernel::isAKoid($regs[3])) {
	$t=str_replace($regs[0],' href="'.\Seolan\Core\Alias::mklink2($regs[2],$regs[3]),$t);
      } else {
	if(\Seolan\Core\Shell::admini_mode()) {
	  $t=str_replace($regs[0],' style="color:white;background-color:red;" href="'.$regs[2],$t);
	} else {
	  $t=str_replace($regs[0],' foo="',$t);
	}
      }
    }
    return $t;
  }
  function _mklinks3(&$t) {
    $expr2="(HREF|href)=\n?\"\[([0-9]+),([_[:alnum:]\:-]+)\]";
    $regs=NULL;
    while(preg_match('@'.$expr2.'@',$t,$regs)) {
      if(\Seolan\Core\Kernel::isAKoid($regs[3]) && \Seolan\Core\Alias::checkRep($regs[2],$regs[3])) {
	$t=str_replace($regs[0],'href="['.\Seolan\Core\Alias::checkRep($regs[2],$regs[3]).']',$t);//"
      } else {
	if(\Seolan\Core\Shell::admini_mode()) {
          $t=str_replace($regs[0],' style="color:white;background-color:red;" href="'.$regs[2],$t);
	} else {
	  $t=str_replace($regs[0],' foo="',$t);
	}
      }
    }
    return $t;
  }
  /// transofmation des notations ->
  function _normalizearrowlinks(string &$text, ?bool $backslashed=false){
    static $expr = [
        "([_[:alnum:]-]{1,80})[ ]*-&gt;[ ]*([[:alnum:]\-_]+)",
        "\"([^\"]{1,80})\"[ ]*->[ ]*([[:alnum:]\-_]+)",
        "&quot;([^,><\.]{1,80})&quot;[ ]*-&gt;[ ]*([[:alnum:]\-_]+)",
        "\"([^\"]{1,80})\"[ ]*-&gt;[ ]*([[:alnum:]\-_]+)",
        "([[:alnum:]]{1,80})[ ]*->[ ]*([[:alnum:]\-_]+)"
    ];
    $t = $text;
    foreach($expr as $ex){
      $regs = array();
      while(preg_match('@' . $ex . '@', $t, $regs)){
        if (($rep = \Seolan\Core\Alias::getInternalRep($regs[2], $this->aliasmodule))
            && is_array($rep) && count($rep)>=2
            && !empty($rep[0])  && !empty($rep[1])){
          $t = str_replace($regs[0], '<a href="[' . $rep[0] . ',' . $rep[1] . 'oo]">' . $regs[1] . '</a>', $t);
        }else{
          if (\Seolan\Core\Shell::admini_mode()){
            $t = str_replace($regs[0], '<a href="[' . $regs[2] . ']">' . $regs[1] . '</a>', $t);
          }else{
            $t = str_replace($regs[0], $regs[1], $t);
          }
        }
      }
    }
    $text = $t;
  }
  /// résolution d'un lien : notation -> et alias en moid,oid
  function _normalizelinks(&$text, $backslashed=false) {
    if (empty($text))
      return $text;
    $this->_normalizearrowlinks($text, $backslashed);
    static $expr2 = [
        "(HREF|href)=\"\[([_[:alnum:]-]+)\]",
        "(HREF|href)=\"https?\:\/\/\[([_[:alnum:]-]+)\]",
        "(HREF|href)=\"https?\:\/\/([_[:alnum:]-]+)\""
    ];
    foreach($expr2 as $ex){
      $regs = NULL;
      while(preg_match('@' . $ex . '@', $text, $regs)){
        if (($rep = \Seolan\Core\Alias::getInternalRep($regs[2], $this->aliasmodule)) && is_array($rep)){
          $text = str_replace($regs[0], ' class="tzr-internallink" href="[' . $rep[0] . 'aa,' . $rep[1] . ']', $text);
        }else{
          $text = str_replace($regs[0], ' class="tzr-errorlink" href="#" ', $text);
        }
      }
    }
    if ($backslashed)
      $text = addslashes($text);
    return $text;
  }

  /// transform usertags
  function _normalizetags(&$text) {
    $text = preg_replace_callback('/\B'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.'([^\s<]+)/', function ($matches) {return $this->replace_user($matches);}, $text);
    return $text;
  }
  
  function replace_user($matches) {
    if (preg_match('/([^\(]*)\(([^\)]+)\)/', $matches[1], $fields)) {
      $userid = $fields[1];
      $username = $fields[2];
    } else {
      $userid = $matches[1];
      $username = '???';
    }
    if ($userid) {
      $usertag_table = $this->usertag_table ? $this->usertag_table : 'USERS';
      $usertag_id_field = $this->usertag_id_field ? $this->usertag_id_field : 'alias';
      $usertag_name_field = $this->usertag_name_field ? $this->usertag_name_field : 'fullnam';
      $usertag_mail_field = $this->usertag_mail_field;
      $extra = '';
      if ($usertag_mail_field) $extra = ','.$usertag_mail_field;
      $lang = \Seolan\Core\Shell::getLangUser();
      $query = "select KOID, $usertag_name_field$extra from $usertag_table where LANG=? and $usertag_id_field=?";
      $user = getDB()->fetchRow($query, array($lang, $userid));
    }
    if ($user) {
      return \Seolan\Field\Text\Text::$USERTAG_PREFIX.$userid.'('.$user['KOID'].'|'.$user[$usertag_name_field].')';
    } else {
      return \Seolan\Field\Text\Text::$USERTAG_PREFIX.$userid.'(|'.$username.')';
    }
  }
  /**
   * transform user/tags in links AND collect uid
   */
  function _userlinks($r) {
    if (!isset($r->subscollection))
	$r->subscollection = [];
    $r->html = preg_replace_callback('/\B'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.'([^\(]*)\(([^\|]*)\|([^\)]+)\)/', function ($matches) use($r) {return $this->replace_user_link($matches, $r);}, $r->html);
    $r->html = preg_replace_callback('/\B'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'([^\s<]+)/', function ($matches) {return $this->replace_tag_link($matches);}, $r->html);
  }
  
  /// transform usertags in text
  function _usertexts($text) {
    return  preg_replace_callback('/\B'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.'([^\(]*)\(([^\|]*)\|([^\)]+)\)/', function ($matches) {return $this->replace_user_text($matches);}, $text);
  }
  /**
   * replace user @tag and collect uid
   */
  function replace_user_link($matches, $r) {

    if ($matches[2]) {
      $r->subscollection[] = $matches[2];
      if ($this->_usermoid === null){
	$mods = \Seolan\Core\Module\Module::modulesUsingTable($this->usertag_table);
	if (count($mods) > 0){
	  $this->_usermoid = array_keys($mods)[0];
	} else {
	  $this->_usermoid = false;
	}
      }
      if ($this->_usermoid !== false){
	$url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_usermoid.'&function=display&template=Module/Table.view.html&tplentry=br&oid='.$matches[2];
	return '<a class="cv8-ajaxlink ckeusertag" href="'.$url.'" title="'.$matches[3].'">'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.$matches[1].'</a>';
      } else {
	return '<span class="ckeusertag" title="'.$matches[3].'">'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.$matches[1].'</span>';
      }
    } else {
      $username = str_replace(\Seolan\Field\Text\Text::$USERTAG_SPACE, " ", $matches[3]);
      return \Seolan\Field\Text\Text::$USERTAG_PREFIX.$matches[1].'('.$username.')';
    }
  }

  function replace_tag_link($matches) {
    $tumoid = \Seolan\Core\Module\Module::getMoid(XMODTAGUSER_TOID);
    $wallmoid = \Seolan\Core\Module\Module::getMoid(XMODWALL_TOID);
    if ($tumoid) {
      if ($this->tagglobalsearch && $wallmoid) {
        $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$tumoid.'&function=searchTag&template=Module/TagUser.result.html&tplentry=br&tag='.$matches[1];
      } else {
        $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$wallmoid.'&function=displayWall&template=Module/Wall.displayWall.html&tplentry=br&tag='.$matches[1];            
      }
      return '<a class="cv8-ajaxlink cketag" href="'.$url.'" title="'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'query').'">'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.$matches[1].'</a>';
    } else {
      return $matches[0];
    }
  }
  function replace_user_text($matches) {
    if ($matches[2]) {
      $usertag_table = $this->usertag_table ? $this->usertag_table : 'USERS';
      $usertag_id_field = $this->usertag_id_field ? $this->usertag_id_field : 'alias';
      $usertag_name_field = $this->usertag_name_field ? $this->usertag_name_field : 'fullnam';
      $usertag_mail_field = $this->usertag_mail_field;
      
      $lang = \Seolan\Core\Shell::getLangUser();
      $query = "select $usertag_id_field, $usertag_name_field from $usertag_table where LANG=? and KOID=?";
      $user = getDB()->fetchRow($query, array($lang, $matches[2]));
      if ($user) {
        return \Seolan\Field\Text\Text::$USERTAG_PREFIX.$user[$usertag_id_field];
      } else {
        return \Seolan\Field\Text\Text::$USERTAG_PREFIX.$matches[1].'('.$matches[3].')';
      }
    } else {
      return \Seolan\Field\Text\Text::$USERTAG_PREFIX.$matches[1].'('.$matches[3].')';
    }
  }

  function my_import($value, $specs=null) {
      // Contrôle du format d'édition si renseigné dans les options de champ. On saute la ligne si skiponbadformat = true dans les specs d'import
      $msg = '';
      $ret = $value;
      $edit_format = '/'.$this->edit_format.'/';
      if(!empty($edit_format)) {
          $match = preg_match($edit_format, $value);
          if($match === false)
              $msg = '<u>Warning</u> : edit format can\'t be checked<br/>';
          elseif($match === 0) {
              $msg = '<u>Warning</u> : value doesn\'t match the edit format constraint<br/>';
              $ret = '';
          }
      }
      return array('message'=>$msg,'value'=>$ret);
  }
  
  function my_export($value) {
    return '<![CDATA['.nl2br($value)."]]>\n";
  }
  
  function my_display(&$value,&$options,$genid=false) {
    $r=$this->_newXFieldValDeferred($value,$options,$genid,'my_display');
    return $r;
  }
  function my_display_deferred(&$r){
    $r->html=nl2br($r->raw);
    if ($this->arrow2link) $this->_mklinks2($r->html);
    if ($this->interpretsmileys) $this->_interpretSomeSmileys($r->html);
    if ($this->enabletags) $this->_userlinks($r);
    if(\Seolan\Core\Shell::admini_mode()) {
      // en mode admini on supprime les codes js pour qu'ils n'interragissent pas avec ceux du BO
      $r->html= removeJavascript($r->html);
    }
    return $r;
  }
  
  function my_browse_deferred(&$r){
    $html = trim($r->raw);
    if(!is_a($this,'\Seolan\Field\RichText\RichText')) $html = nl2br($html);

    if(@$r->options['context']=='export') $browse_format='full';
    else $browse_format=$this->browse_format;

    if ($this->arrow2link) $this->_mklinks2($html);

    if (!\Seolan\Core\Shell::admini_mode()){
      $r->html = $html;
      if ($this->enabletags) $this->_userlinks($r);
    }else{
      if(@$r->options['context']!='export') {
	// en mode admini on supprime les codes js pour qu'ils n'interragissent pas avec ceux du BO
        $html= removeJavascript($html);
      }
      $picto = '<a data-html="true" data-trigger="" tabindex="0" role="button" title="'.htmlspecialchars($this->label).'" data-toggle="popover" data-content="'.htmlspecialchars($html).'">'. \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'more', 'csico').'</a>';
      switch ($browse_format) {
        case 'extract' :
          $lines = explode("\n", wordwrap(trim(strip_tags($html)), 50));
          $r->html = $lines[0];
	  if ($this->enabletags) $this->_userlinks($r);
          if (isset($lines[1])) {
            $r->html .= ' ... ' . $picto;
          }
          break;
        case 'picto' :
          $r->html = empty($html) ? '' : $picto;
          break;
        default : // 'full'
          $r->html = $html;
          if ($this->enabletags) $this->_userlinks($r);
      }

    }
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    $r=$this->_newXFieldVal($options,true);
    $r->varid=uniqid('v');
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field.'['.$o.']';	
      $hiddenname=$this->field.'_HID['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }	
    $cols=$this->fcount;
    $t=$value;
    if(isset($options['rows'])) $rows=$options['rows'];
    if(isset($options['cols'])) $cols=$options['cols'];
    $rows=min(strlen($t)/$cols,50);
    $rows=floor(max(4,$rows));
    $t=$this->_mklinks3($t);
    if ($this->enabletags) 
      $t=$this->_usertexts($t);
    $fmt1=$this->edit_format;
    $js = '';
    if(!empty($fmt1) || $this->compulsory){
      $color=\Seolan\Core\Ini::get('error_color');	
      $class = '';
      if ($this->compulsory)
	$class = "tzr-input-compulsory";
      if (@$this->error)
	$class .= " $color";
      if ($class)
	$class = " class=\"$class\"";
      if(!empty($fmt1))
        $js.='TZR.addValidator(["'.$r->varid.'",/'.$fmt1.'/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);';
      if($this->compulsory && $fmt1!='(.+)')
        $js.='TZR.addValidator(["'.$r->varid.'",/(.+)/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);';
      $html='<textarea required onblur="TZR.isIdValid(\''.$r->varid.'\');" id="'.$r->varid.'" '.$class.' name="'.$fname.'" '.
	'cols="'.$cols.'" rows="'.$rows.'" wrap="soft">'.$t.'</textarea><script>'.$js.'</script>';
    } else {
      $html='<textarea id="'.$r->varid.'" name="'.$fname.'" cols="'.$cols.'" rows="'.$rows.'" wrap="soft">'.$t.'</textarea>';
    }
    if ($this->enabletags){
      list($tagsuser, $tagstext) = $this->allowedTags();
      if ($tagsuser || $tagstext){
	$url = TZR_AJAX8.'?class=_Seolan_Field_Tag_Tag&function=tag_autocomplete&add_prefix=1';
	$url2 = TZR_AJAX8.'?class=_Seolan_Field_Text_Text&function=user_autocomplete';
	$url2 .= '&usertag_table='.$this->usertag_table;
	$url2 .= '&usertag_id_field='.$this->usertag_id_field;
	$url2 .= '&usertag_name_field='.$this->usertag_name_field;
	$url2 .= '&usertag_mail_field='.$this->usertag_mail_field;
	$title = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'delete');
	// callback : add_input_tag
	$html .= '<script>jQuery("#'.$r->varid.'").data("autocomplete", {url:"'.$url.'", url2:"'.$url2.'", callback:TZR.tagAutoCompleteCallback(), params:{id:"'.$r->varid.'", title:"'.$title.'"}});TZR.addAutoCompleteTagInput("'.$r->varid.'",'.json_encode(['userskey'=>static::$USERTAG_PREFIX,'users'=>$tagsuser, 'texts'=>$tagstext]).');</script>';
      }
    }
    $r->html=$html;
    $r->raw=$value;
    return $r;
  }

  function my_query($value, $options=NULL) {
    $p=new \Seolan\Core\Param($options);
    $format=$p->get('fmt');
    if(empty($format)) $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;
    $labelin=$p->get('labelin');
    $r=$this->_newXFieldVal($options,true);
    if(is_array($value)) $value=implode($value);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $t=htmlspecialchars($value);
    $r->html='<input type="text" name="'.$fname.'" id="'.$fname.$r->varid.'" value="'.$t.'">';
    if(!empty($labelin)){
      $r->html.='<script type="text/javascript">inputInit("'.$fname.$r->varid.'","'.$this->label.'");</script>';
    }
    $r->raw=$value;
    return $r;
  }

  /// Traitement apres formulaire sur les champs calculés
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    if (is_string($value)){
      $value = trim($value); //$value peut être un array ! et ça le vide !
      if($this->arrow2link) $this->_normalizelinks($value);
      if($this->enabletags) $this->_normalizetags($value);
    }
    if(!empty($this->exif_source) && empty($value)){
      $r->raw=$this->getMetaValue($fields_complement);
    }else{
      $r->raw=$value;
    }
    utf8_cp1252_replace($r->raw);
    // suppression des codes html et js eventuel lorsqu'on n'est pas en BO
    if(!\Seolan\Core\Shell::admini_mode() && empty($options['raw']))
      $r->raw=strip_tags($r->raw);
    if(@$options['toxml']){
      \Seolan\Core\System::array2xml($r->raw,$xml);
      $r->raw=$xml;
    } 
    $this->trace(@$options['old'],$r);
    return $r;
  }
  function post_edit_dup($value,$options) {
    $r=$this->_newXFieldVal($options);
    $r->raw=$this->_normalizelinks($value);
    return $r;
  }
  function sqltype() {
    return 'mediumtext';
  }
  function my_getJSon($o, $options) {
    if (isset($options['property']))
      return $o->{$options['property']};
    return $o->html;
  }
  protected function allowedTags(){
    \Seolan\Core\Logs::debug(__METHOD__.' '.$this->field.' '.$this->table);
    if ($this->tags_allowed == null){
      $this->tags_allowed = [false, false]; // user, text
      if(\Seolan\Core\Shell::admini_mode()
	 && \Seolan\Core\User::isNobody()){
	\Seolan\Core\Logs::debug(__METHOD__.' no tag ');
	return $this->tags_allowed;
      }
      // modules accessibles utilisant la table
      $mods = \Seolan\Core\Module\Module::modulesUsingTable($this->usertag_table);
      if (count($mods)>0){
	$this->tags_allowed[0] = true;
      }
      // module des tags
      $modtag = \Seolan\Core\Module\Module::singletonFactory(XMODTAG_TOID);
      $this->tags_allowed[1] = (isset($modtag) && $modtag->secure('', ':list'));
    }
    return $this->tags_allowed;
  }
}

function user_autocomplete($php=false) {
  $q = $_REQUEST['q'];
  $q = normalize_user($q);
  $usertag_table = $_REQUEST['usertag_table'] ? $_REQUEST['usertag_table'] : 'USERS';
  $usertag_id_field = $_REQUEST['usertag_id_field'] ? $_REQUEST['usertag_id_field'] : 'alias';
  $usertag_name_field = $_REQUEST['usertag_name_field'] ? $_REQUEST['usertag_name_field'] : 'fullnam';
  $usertag_mail_field = $_REQUEST['usertag_mail_field'];
  $lang = \Seolan\Core\Shell::getLangUser();
  $query = "select $usertag_id_field, $usertag_name_field from $usertag_table where LANG=? and $usertag_name_field like ? order by $usertag_name_field";
  $res = getDB()->fetchAll($query, array($lang, '%'.$q.'%'));
  header('Content-Type:application/json; charset=UTF-8');
  foreach($res as $ores)  {
    $data[] = array('value' => '@'.$ores[$usertag_id_field], 'label' => $ores[$usertag_name_field].' ('.$ores[$usertag_id_field].')');
  }
  die(json_encode($data));
}

function normalize_user($value) {
  return str_replace('@','',trim($value));
}
