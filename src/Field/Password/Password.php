<?php
namespace Seolan\Field\Password;
use \Seolan\Core\Labels;

// Champ de gestion d'un mot de passe
class Password extends \Seolan\Core\Field\Field {
  // ?= : assertions (match without consumming)
  public const PASSWORD_FORMAT = '^(?=.{12,}$)(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*\W)(?![.\\n\\t\\r]).*$';
  public const PASSWORD_FORMAT_UNCHANGED = '(^'.TZR_UNCHANGED.'$)';
  public $edit_format = self::PASSWORD_FORMAT;
  public $edit_format_text = '';
  public $cryptmd5=true;
  public $with_confirm=false;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL) {
    parent::__construct($obj) ;
    if($this->cryptmd5) $this->fcount=64;
  }
  function initOptions() {
    parent::initOptions();
    $edit_format_list = array(
      "labels" => array(
        Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format_strong'),// default value
        Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format_medium'),
      ),
      "values" => array(
        self::PASSWORD_FORMAT,// default value
        "^(?=.{10,}$)(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?![.\\n\\t\\r]).*$",
      )
    );
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format'), "edit_format", "list", $edit_format_list, self::PASSWORD_FORMAT);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format_text'), "edit_format_text", "ttext", null,['FR'=>Labels::getTextSysLabel('Seolan_Core_SessionMessages','rgpd_password_format_text')]);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','with_strength'), 'with_strength', 'boolean', NULL, false);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','crypt'), 'cryptmd5', 'boolean', NULL, true);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','with_confirmation'), 'with_confirm', 'boolean', NULL, false);
    $this->_options->setComment(sprintf('RGPD format : '.self::PASSWORD_FORMAT_UNCHANGED.'|(%s)"',self::PASSWORD_FORMAT), 'edit_format');
  }
  function my_export($value) {
    return str_repeat('*',10);
  }
  function my_display(&$value,&$options,$genid=false) {
    $r=parent::my_display($value,$options,$genid);
    $r->raw=$r->html=str_repeat('*',10);
    if(!$this->cryptmd5) $r->raw2=$value;
    return $r;
  }
  /// hashage d'un mot de passe pour comparaison ou stockage
  static function hash($value) {
    return hash('sha256', $value);
  }

  function my_browse(&$value,&$options,$genid=false) {
    $r=parent::my_browse($value,$options,$genid);
    $r->raw=$r->html=str_repeat('*',10);
    if(!$this->cryptmd5) $r->raw2=$value;
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field."[$o]";
      $hfname = $this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hfname = $this->field.'_HID';
    } else {
      $fname=$this->field;
      $hfname = $this->field.'_HID';
    }	
    // si value n'est pas vide on est dans le cadre d'un nouveau de mot passe.
    // on ajoute alors le format pour unchanged
    if( !empty($value)){
      $this->edit_format = self::PASSWORD_FORMAT_UNCHANGED."|(".$this->edit_format.")";
    }
    $uniqId = uniqid();
    $containerId = "container".$uniqId;
    $varid='v'.$uniqId;
    $color=\Seolan\Core\Ini::get('error_color');
    $jsreg='new RegExp(\'^\'+document.getElementById(\''.$varid.'\').value+\'$\')'; 
    $js='if(typeof(TZR)!="undefined") {TZR.addValidator(["'.$varid.'",/'.$this->edit_format.'/,"'.$this->label.'","'.$color.'","\Seolan\Field\Password\Password",null,"'.($this->with_confirm || @$options['with_confirm'] == 1).'"]);}';

    // le null ci-dessus : 5 a un sens autres dans les validateurs
    if ($this->compulsory){
      $js .= 'if(typeof(TZR)!="undefined") {TZR.addValidator(["'.$varid.'",/(.+)/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);}';
    }
    $fmt=' onblur="if(typeof(TZR)!=\'undefined\') {TZR.isIdValid(\''.$varid.'\');}" ';
    $r=$this->_newXFieldVal($options);
    $size=($this->fcount>30?30:$this->fcount);
    $maxlength=$this->fcount;
    if(isset($options["size"])) $size=$options["size"];
    if(isset($options["maxlength"])) $size=$options["maxlength"];
    $r->varid=$varid;
    $r->raw=$value;
    $placeholder="";
    if (@$options['labelin']) {
      $placeholder = ' placeholder="'.$this->label.'"';
    }
    $class = '';
    if ($this->compulsory)
      $class .= 'tzr-input-compulsory';
    if (@$this->error)
      $class .= ' error_field';
    if ($class)
      $class = " class=\"$class\"";

    if($value!='') 
      $value=TZR_UNCHANGED;

    $fieldPattern = static::getHtmlPattern($this);
    // le title permet d'avoir un message d'invalidité du champ personnalisé
    $title='';
    if (!empty($fieldPattern)){
      $title = 'title="'.addslashes($this->edit_format_text).'"';
    } 

    $required = ($this->compulsory)?'required':'';

    $r->html = '<div id="'.$containerId.'" class="passwd-edit">';

    $r->html .= '<div class="form-group">';

    $r->html .= '<div class="input-group">';

    $r->html.="<input {$title} {$required} type='password' {$class} name='{$fname}' size='{$size}' maxlength='{$maxlength}' value='{$value}' id='{$varid}' {$fmt} {$placeholder} {$fieldPattern}/>";

    $r->html .= "<button id='{$varid}_show' class='btn btn-default btn-md btn-inverse passwd_hide hidden'><span class='glyphicon csico-visibility hidden'></span><span class='glyphicon csico-visibility-off'></span></button>";

    $js .= "jQuery('#{$varid}').one('change', function(e) { jQuery('#{$varid}_show').removeClass('hidden'); });
      jQuery('#{$varid}_show').on('click', function(e) {
        e.preventDefault();
        type = jQuery(e.currentTarget).hasClass('passwd_hide') ? 'text' : 'password';
        jQuery('input[id^={$varid}]').attr('type',type);
        jQuery('button#{$varid}_show').toggleClass('passwd_hide').children('span').toggleClass('hidden');
      });";
      
    if ($this->with_confirm || @$options['with_confirm'] == 1) {
      $title = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','field_psswd_confirm_text');
      $confirmPlaceholder = str_replace(['(',')'], '', \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','field_psswd_confirm')); 
      $fmthid_wc=' onblur="if(typeof(TZR)!=\'undefined\') {TZR.isIdValid(\''.$varid.'_HID\');}" ';
      if ($class == ''){
	$class .= 'class="csx-input-confirm"';
      } else {
	$class = substr($class,0, -1).' csx-input-confirm"';
      }
      $r->html.="<input type='password' placeholder='{$confirmPlaceholder}' {$required}  {$class} name='{$hfname}' size='{$size}' maxlength='{$maxlength}' value='{$value}' id='{$varid}_HID' {$fmthid_wc} title='".$title."' data-pattern-error-message='".$title."'/>";
      $js.="if(typeof(TZR)!='undefined'){TZR.addValidator(['{$varid}_HID',null,'{$this->label}','{$color}','Confirm','{$varid}']);}";
    }

    $r->html .= '</div>';// fermeture form-group

    // composant pour l'affichage de la force du mot de passe
    if($this->with_strength){
      $strengthOptions = array(
        "forceValues" => array(
          "weak"=>\Seolan\Core\Labels::getSysLabel("Seolan_Core_Field_Field","strength_weak"),
          "normal"=>\Seolan\Core\Labels::getSysLabel("Seolan_Core_Field_Field","strength_normal"),
          "medium"=>\Seolan\Core\Labels::getSysLabel("Seolan_Core_Field_Field","strength_medium"),
          "strong"=>\Seolan\Core\Labels::getSysLabel("Seolan_Core_Field_Field","strength_strong"),
          "veryStrong"=>\Seolan\Core\Labels::getSysLabel("Seolan_Core_Field_Field","strength_veryStrong"),
        ),
        "containerId" => $containerId,
        "strengthId" => "strength".$uniqId,
        "messageId" => "message".$uniqId,
        "strengthErrorRegex" => $this->edit_format_text,
        "edit_format" => $this->edit_format,
      );
      $js .= 'jQuery.getScript("/csx/VendorJS/pwstrength/dist/pwstrength-bootstrap.min.js",
      function(){ TZR.Fields.Password.pwstrengthInit("'.$varid.'",'.json_encode($strengthOptions).'); });';
      $r->html .= '
        <div class="form-group passw strength">
          <label>'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','password_strength').'</label>
          <div id="'.$strengthOptions['strengthId'].'"></div>
        </div>
        <div id="'.$strengthOptions['messageId'].'" class="alert alert-danger" style="display:none">&nbsp;</div>';
      $r->html .= '<script  src="/csx/src/Field/Password/public/js/Password.js"></script>';
    }
    $r->html.='<script type="text/javascript">'.$js.'</script>';
    if (@$this->errorEquals){
      $r->html .= '<div class="error-field-comment">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','field_equals').'</div>';
    }
    $r->html .= '</div>';
    return $r;
  }
  function my_query($value, $options=NULL) {
    $r = $this->_newXFieldVal($options);
    return $r;
  }
  function sqltype() {
    return "varchar(".$this->fcount.")";
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    $r->raw=$value;
    if(($value!=TZR_UNCHANGED) && !empty($value)) {
      if($this->with_confirm && $value!=$options[$this->field.'_HID']) $r->raw=TZR_UNCHANGED;
      elseif($this->cryptmd5) $r->raw=self::hash($value);
      $this->trace(NULL,$r, '[*****] -> [*****]');
    }
    return $r;
  }
}
