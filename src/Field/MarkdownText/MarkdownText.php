<?php
namespace Seolan\Field\MarkdownText;
use \Seolan\Core\Logs;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Labels;
/**
 * Texte dont la saisie est effectuée en markdown
 * Post-edit : génération du html avec pandoc
 * Preview : pandoc -> avoir qqchose de cohérent
 * Contenus non 'markdown'  (par ex: texte enrichi)
 * sont gardés et l'utilisateur notifié
 */
class MarkdownText extends \Seolan\Field\Text\Text {
  public $interpretsmileys = false;
  public $enabletags = false;
  public $edit_format = false;
  public $tagglobalsearch = false;
  public $aliasmodule = null;
  public $arrow2link = false;
  public static $multivaluable = false;
  function initOptions(){
    parent::initOptions();
    $this->_options->delOpt('interpretsmileys');
    $this->_options->delOpt('enabletags');
    $this->_options->delOpt('edit_format');
    $this->_options->delOpt('tagglobalsearch');
    $this->_options->delOpt('exif_source');
    //$this->_options->delOpt('aliasmodule');
    //$this->_options->delOpt('arrow2link');
  }
  /// on deferre le decodage
  function my_browse(&$value,&$options,$genid=false){
    return $this->_newXFieldValDeferred($value,$options,$genid, 'my_browse');
  }
  /// comme le parent fonctionne avec le ->raw; on refait
  function my_browse_deferred(&$r){
    $decoded = json_decode($r->raw);
    if (empty($decoded) && !empty($r->raw))
      $r->raw = $r->markdown = $r->raw;
    else {
      $r->markdown = $decoded->markdown;
      $r->preview = $decoded->preview;
    }
    if ($this->arrow2link && isset($this->aliasmodule))
      $this->_mklinks2($r->preview);
    $picto = '<a data-html="true" data-trigger="" tabindex="" role="button" title="'.htmlspecialchars($this->field).'" data-toggle="popover" data-content="'.htmlspecialchars($r->preview).'">'. \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'more', 'csico').'</a>';
    switch ($this->browse_format) {
    case 'extract' :
      $lines = explode("\n", wordwrap(trim(strip_tags($r->preview)), 50));
      $r->html = $lines[0];
      if (isset($lines[1])) {
	$r->html .= ' ... ' . $picto;
      }
      break;
    case 'picto' :
      $r->html = empty($r->preview) ? '' : $picto;
      break;
    default : // 'full'
      $r->html = $r->preview;
      if ($this->enabletags) $this->_userlinks($r);
    }
    return $r;
  }
  /// ne retourner que le markdown
  function getSpreadSheetCellValue($value, $options=null){
    return $value->markdown;
  }
  /**
   * ne chercher que dans le markdown
   */
  function post_query($o, $ar){
    $ar['jsonQueryPath'] = '$.markdown';
    return parent::post_query($o, $ar);
  }
  /// affichage
  function my_display(&$value, &$options, $genid=false){
    $r = $this->_newXFieldVal($options, true);
    $decoded = json_decode($value);
    $info='';
    if (!is_object($decoded) ){
      $r->raw = $r->markdown = $r->preview = $value;
      if (!empty(trim($value)))
	$info = '<div class="alert alert-warning">Attention, le contenu ne semble pas être du texte encodé en Markdown</div>';
    } else {
      $r->raw = $value;
      $r->markdown = $decoded->markdown;
      $r->preview = $decoded->preview;
      if ($this->arrow2link && isset($this->aliasmodule)){
	$this->_mklinks2($r->preview);
      }
    }
    if(\Seolan\Core\Shell::admini_mode()){
      $r->html = "{$info}<div class='Field_Markdown' id='{$r->varid}'>{$r->preview}</div>";
    } else {
      $r->html = $r->preview;
    }
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL){
    $r = $this->_newXFieldVal($options,true);
    $r->raw = $value;
    $r->markdown = '';
    $r->preview = '';
    list($fname, $hiddenname) = $this->editFieldNames($options);
    list($cols, $rows) = $this->dimensions($options, $value);
    if (!empty($value)){
      $decoded = json_decode($value);
      if (empty($decoded) && !empty($value)){
	$r->raw = $r->markdown = $r->preview = $value;
	if(\Seolan\Core\Shell::admini_mode())
	  $info = '<div class="alert alert-warning">Attention, le contenu ne semble pas être du texte encodé en Markdown</div>';
      } else {
	$r->markdown = $decoded->markdown;
	if ($this->arrow2link && isset($this->aliasmodule))
	  $this->_mklinks3($r->markdown);
	$r->preview = $decoded->preview; // à voir
      }
    }
    $r->html="{$info}<textarea style='width:100%' name='{$fname}' rows='{$rows}' cols='{$cols}' wrap='soft' class='markdown-text $classname' id='{$r->varid}'>{$r->markdown}</textarea>";
    $this->setTabs($r, $options);
    return $r;
  }
  function my_input(&$value,&$options,&$fields_complement=NULL){
    return $this->my_edit($value, $options, $fields_complement);
  }

  /**
   * nettoyer si FO
   * ! certains éditeurs et la spec (?) autorisent du html 
   * générer le html
   */
  function post_edit($value,$options=NULL,&$fields_complement=NULL){
    $r = $this->_newXFieldVal($options, false);
    // suppression des balises si on est pas en BO
    if(!\Seolan\Core\Shell::admini_mode() && empty($options['raw']))
      $value=strip_tags($value);
    if ($this->arrow2link && isset($this->aliasmodule))
      $this->_normalizelinks($value);
    $r->raw = json_encode(['markdown'=>$value, 'preview'=>$this->generatePreview($value)]);
    return $r;
  }
  public function generatePreview($value){
    $format='html5';
    $tmpfilein = TZR_TMP_DIR.uniqid()."{$this->field}.md";
    $tmpfileout = TZR_TMP_DIR.uniqid()."{$this->field}.html";
    file_put_contents($tmpfilein, $value);
    $cmd = "pandoc --from=markdown --to={$format} --output={$tmpfileout} {$tmpfilein}";
    exec($cmd, $res);
    if (!empty($res))
      Logs::critical(__METHOD__, 'error while pandoc format conversion: '.implode("\n", $res));
    $preview = '<div class="Field_Markdown">'.file_get_contents($tmpfileout).'</div>';
    unlink($tmpfilein);
    unlink($tmpfileout);
    return $preview;
  }
	
  function sqltype() {
    return 'mediumtext';
  }
  /**
   * lien markdown : [un texte](url)
   * ou [un texte](url "un title")
   * [un texte] (url) compte pas
   * on memorise l'alias en 3me pour pouvoir refaire une edition
   * pour les alias qui existent pas (plus)
   */
  function _normalizelinks(&$text, $backslashed=false) {
    $exp = "\[.+\]\(\[([a-zA-Z0-9-_]+)\]( \".*\")?\)";
    $res=[];
    while(preg_match('@'.$exp.'@',$text,$res)) {
      if(($rep=\Seolan\Core\Alias::getInternalRep($res[1],$this->aliasmodule)) && isset($rep[1])){
	$text=preg_replace("@\]\(\[{$res[1]}@","]([{$rep[0]},{$rep[1]},{$res[1]}",$text);
      } else {
	$text=preg_replace("@\]\(\[{$res[1]}@","]([{$rep[0]},x,{$res[1]}",$text);
      }
      unset($res);
    }
  }
  /**
   * transformations des liens internes en url 
   */
  function _mklinks2(&$text) {
    // href="[moid,koid,alias]" est encodé dans le preview
    $exp = '<a +href="%5B([0-9]+),([a-zA-Z0-9:]+),([a-zA-Z0-9-_]+)%5D"';
    $res=null;
    $i=0; 
    while(preg_match('@'.$exp.'@',$text,$res) && ($i++<=200)) {
      list($all, $moid, $koid, $alias) = $res;
      $linkNorm = "{$moid},{$koid},{$alias}";
      if(\Seolan\Core\Kernel::isAKoid($koid)) {
	$url = \Seolan\Core\Alias::mklink2($moid,$koid);
	$text=str_replace("%5B{$linkNorm}%5D",$url,$text);
      } else {
	if(\Seolan\Core\Shell::admini_mode()) {
	  $text=str_replace("{$linkNorm}","<mark class='error-alias'>{$linkNorm}</mark>",$text);
	} else {
	  $text=str_replace("%5B{$linkNorm}%5D","",$text);
	}
      }
    }
    if ($i>2000){
      Logs::notice(__METHOD__,"error decoding link : '{$linkNorm}', text '{$text}'");
    }
  }
  /**
   * affichage des liens en edition : 
   * [xxx]([moid,oid,alias] "kjkjkj") -> [xxx]([alias] "kjkjkj") 
   * [xxx]([moid,oid],alias) -> [xxx]([alias]) 
   * l'alias de l'encodage est là pour info au cas ou l'oid existe plus
   */
  function _mklinks3(&$text){
    $exp = "\[.+\]\(\[([0-9]+),([a-zA-Z0-9:]+),([a-zA-Z0-9-_]+)\]( \".*\")?\)";
    $res =NULL;
    while(preg_match('@'.$exp.'@',$text,$res)) {
      if(\Seolan\Core\Kernel::isAKoid($res[2]) && ($alias = \Seolan\Core\Alias::checkRep($res[1],$res[2]))) {
	$text=str_replace("[{$res[1]},{$res[2]},{$res[3]}]","[{$alias}]",$text);
      } else {
	$text=str_replace("[{$res[1]},{$res[2]},{$res[3]}]","[{$res[3]}]",$text);
	if(\Seolan\Core\Shell::admini_mode()) {
	  Logs::critical(__METHOD__,"error decoding normalized link : '{$res[1]},{$res[2]}' '{$text}'");
	} 
      }
    }
  }
  protected function dimensions($options, $value){
    $cols=$this->fcount;
    if(isset($options['rows'])) $rows=$options['rows'];
    if(isset($options['cols'])) $cols=$options['cols'];
    $rows=min(strlen($value)/$cols,50);
    $rows=floor(max(4,$rows));
    return [$cols, $rows];
  }
  protected function editFieldNames($options){
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
    return [$fname, $hiddenname];
  }
  protected function setTabs($r, $options){
    if (!isset($options['fmoid']))
      return;
    $edit = Labels::getInstance()->getSysLabel('Seolan_Core_General', 'edit', 'text');
    $preview = Labels::getInstance()->getSysLabel('Seolan_Core_General', 'preview', 'text');
    $r->html = <<<EOF
      <div>
      <ul class="nav nav-tabs" role="tablist">
      <li role="presentation" class="active"><a href="#{$r->varid}edit" aria-controls="{$r->varid}edit" role="tab" data-toggle="tab">{$edit}</a></li>
      <li role="presentation"><a href="#{$r->varid}preview" aria-controls="{$r->varid}preview" role="tab" data-toggle="tab">{$preview}</a></li>
      </ul>
      <div class="tab-content">
      <div role="tabpanel" class="tab-pane active" id="{$r->varid}edit">{$r->html}</div>
      <div role="tabpanel" class="tab-pane" id="{$r->varid}preview"></div>
      </div>
      </div>
      <script>
EOF;
    $url=TZR_AJAX8.'?'.http_build_query(
      ['class'=>'\Seolan\Field\MarkdownText\MarkdownText',
       'moid'=>$options['fmoid'],
       'table'=>$r->fielddef->table,
       'field'=>$r->fielddef->field,
       'function'=>"MarkdownText_getPreview"]);
    $r->html .= <<<EOF
      jQuery('a[href="#{$r->varid}preview"]').on('show.bs.tab', function(e){
	  var content = jQuery("#{$r->varid}").val();
	  console.log(content);
	  var obj = {
	  mode:'post',
	  cache:false,
	  dataType:"text/html",
	  url:"{$url}",
	  data:{c:content},
          overlay:"{$r->varid}preview",
	  cb_args : ["{$r->varid}"],
	  overlay:'none',
	  cb:function(varid, responseText, status, xhrObject){
            jQuery("#"+varid+"preview").html(responseText);
           }
	  }
	  TZR.jQueryAjax(obj);
	});
      </script>									      
EOF;
  }
  /// génére un preview à partir d'un texte markdown
  function ajaxPreview($text){
    if ($this->arrow2link && isset($this->aliasmodule)){
      $this->_normalizelinks($text);
    }
    return $this->generatePreview($text);
  }
}
  
function MarkdownText_getpreview() {
  activeSec();
  $moid = $_REQUEST['moid'];    // Module depuis lequel on fait l'autocomplete
  $table = $_REQUEST['table'];  // Table contenant le champ
  $field = $_REQUEST['field'];
  if (empty($moid) || empty($field) || empty($table)){
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  $mod = \Seolan\Core\Module\Module::objectFactory($moid);
  if ($mod->object_sec)
    $ok = $mod->secure('', ':list');
  else
    $ok = $mod->secure('', ':ro');
  //if (!$ok || !$mod->usesTable($table)){
  if (!$ok){ // usesTable pose pb avec mod infotree
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  $ds = DataSource::objectFactoryHelper8($table);
  $ofield= $ds->getField($field);
  
  $oid = @$_REQUEST['oid'];
  $contents = $_POST['c'];
  header('Content-Type:text/html');
  echo($ofield->ajaxPreview($contents));
  die();
}

