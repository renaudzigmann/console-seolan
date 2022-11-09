<?php
namespace Seolan\Field\Tag;

/// Champ de gestion de tags
class Tag extends \Seolan\Core\Field\Field {
  public $indexable=true;
  public $minCharNum=2;
  public $displayModList=false;
  public static $TAG_PREFIX = '#';
  public static $LIST_SEPARATOR = ' ';


  function __construct($obj=NULL) {
    parent::__construct($obj);
  }

  function initOptions() {
    parent::initOptions();
    $editgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','min_char_num'), 'minCharNum', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','display_mod_list'), 'displayModList', 'boolean');
  }
  
  function sqltype() {
    return 'text';
  }

  function my_display(&$value,&$options,$genid=false) {
    $r=parent::my_display($value,$options,$genid);
    $r->html = $this->format_tags($r->raw);
    $r->text = $this->text_tags($r->raw);
    return $r;
  }

  function my_browse(&$value,&$options,$genid=false) {
    $r=parent::my_browse($value,$options,$genid);
    $r->html = $this->format_tags($r->raw);
    $r->text = $this->text_tags($r->raw);
    return $r;
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field.'['.$o.']';	
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
    } else {
      $fname=$this->field;
    }
     $value .= ' '; // FIX: Ajout d'un espace pour que le champ caché fonctionne correctement lors de la suppression du tag de fin de liste
    $hiddenname = $fname."_hidden";
    $textname = $fname."_text";
    $inputname = $fname."_input";
    $previewname = $fname."_preview";
    $url = TZR_AJAX8.'?class=_Seolan_Field_Tag_Tag&function=tag_autocomplete';
    $r->raw = $value;
    $title = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'delete');
    $onclick = "return delete_tag(this, '$hiddenname')";
    $r->html = '<div class="taginput" id="'.$inputname.'">';
    $fmt = "";
    if ($this->compulsory)
      $fmt .= " required";
    $r->html .= '<input id="'.$textname.'" type="hidden" autocomplete="off">';
    $r->html .= '<input id="_INPUT'.$textname.'" type="text" autocomplete="off"'.$fmt.'>';
    $r->html .= '<input type="hidden" id="'.$hiddenname.'" class="tag" name="'.$fname.'" value="'.$value.'">';
    $r->html .= '</div>';
    $r->html .= '<div class="tagpreview" id="'.$previewname.'">';
    $r->html .= $this->format_tags($value, "", $title, $onclick);
    $r->html .= '</div>';
    $r->html.='<script type="text/javascript" language="javascript">jQuery("#_INPUT'.$textname.'").data("autocomplete", {url:"'.$url.'", callback:add_tag, params:{id:"'.$textname.'", title:"'.$title.'"}});TZR.addAutoCompleteTag("'.$textname.'", {count:'.$this->minCharNum.', list:'.($this->displayModList==1?'true':'false').',textskey:"'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'"});</script>';
    return $r;
  }
  /**
   * @param $title : titre de l'action ?
   */
  function format_tags($raw, $url = NULL, $title = NULL, $onclick = NULL, $tagparam = NULL, $selectedtag = NULL, $alttitle = NULL) {
    $html = "";
    $param = "";
    if ($onclick)
      $onclick = ' onclick="'.$onclick.'"';
    else {
      if (!$title)
        $title = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'query');
    }
    $tags = explode(\Seolan\Field\Tag\Tag::$TAG_PREFIX, $raw);
    $tagusermoid = \Seolan\Core\Module\Module::getMoid(XMODTAGUSER_TOID);
    foreach ($tags as $tag) {
      $tag = trim($tag);
      $tagtitle = $title;
      if ($tag) {
        $tagUrl = $url;
        if ($url) {
          if ($tag == $selectedtag) {
            $param = "";
            $cssclass = " cv8-ajaxlink tag_selected";
            if ($alttitle) {
              $tagtitle = $alttitle;
            }
          } else {
            if ($tagparam) {
              $param = sprintf($tagparam, $tag);
            }
            $cssclass = " cv8-ajaxlink";
          }
        } elseif (!$onclick && $tagusermoid) {
          $tagUrl = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$tagusermoid.'&function=searchTag&template=Module/TagUser.result.html&tplentry=br&tag='.$tag;
          $cssclass = " cv8-ajaxlink";
        }
        $html .= '<span class="tag"><a class="tag'.$cssclass.'" title="'.$tagtitle.'" href="'.$tagUrl.$param.'"'.$onclick.'>'.$tag.'</a></span>';
      }
    }
    return $html;
  }

  function text_tags($raw) {
    $text = "";
    $sep = "";
    $tags = explode(\Seolan\Field\Tag\Tag::$TAG_PREFIX, $raw);
    foreach ($tags as $tag) {
      $tag = trim($tag);
      if ($tag) {
        $text .= $sep.$tag;
        $sep = \Seolan\Field\Tag\Tag::$LIST_SEPARATOR;
      }
    }
    return $text;
  }

  /// Prepare la recherche sur le champ
  function my_query($value,$options=NULL) {
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $url = TZR_AJAX8.'?class=_Seolan_Field_Tag_Tag&function=tag_autocomplete';
    if(isset($value)) $t1=htmlspecialchars($value);
    else $t1='';
    $r=$this->_newXFieldVal($options,true);
    $r->html = '<input type="text" id="'.$fname.'" name="'.$fname.'" size="'.($this->fcount>30?30:$this->fcount).'" value="'.$t1.'" autocomplete="off"/>';
    $r->html .= '<script type="text/javascript" language="javascript">jQuery("#'.$fname.'").data("autocomplete", {url:"'.$url.'", callback:set_search_tag, params:{id:"'.$fname.'"}});TZR.addAutoCompleteTagSearch("'.$fname.'", {count:'.$this->minCharNum.', list:'.($this->displayModList==1?'true':'false').',textskey:"'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'", init : true});</script>';
    $r->raw=$value;

    return $r;
  }

  /// Prepare la recherche sur le champ
  function my_quickquery($value,$options=NULL) {
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $url = TZR_AJAX8.'?class=_Seolan_Field_Tag_Tag&function=tag_autocomplete&no_add=1';
    if(is_array($value)) $value=implode($this->separator[0],$value);
    if(isset($value)) $t1=htmlspecialchars($value);
    else $t1='';
    $r=$this->_newXFieldVal($options,true);
    $r->html = '<input type="hidden" value="'.$fname.'" name="_FIELDS['.$fname.']">';
    $r->html .= '<input type="text" id="'.$fname.'" name="'.$fname.'" size="'.($this->fcount>30?30:$this->fcount).'" value="'.$t1.'" autocomplete="off"/>';
    $r->html .= '<script type="text/javascript" language="javascript">jQuery("#'.$fname.'").data("autocomplete", {url:"'.$url.'", callback:set_search_tag, params:{id:"'.$fname.'"}});TZR.addAutoCompleteTagSearch("'.$fname.'", {count:'.$this->minCharNum.', list:'.($this->displayModList==1?'true':'false').',textskey:"'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'", init : true});</script>';
    $r->raw=$value;

    return $r;
  }

  function my_import($value,$specs=null){
    if($value) {
      $valueslist = explode(\Seolan\Field\Tag\Tag::$LIST_SEPARATOR, $value);
      $value = \Seolan\Field\Tag\Tag::$TAG_PREFIX.implode(\Seolan\Field\Tag\Tag::$LIST_SEPARATOR.\Seolan\Field\Tag\Tag::$TAG_PREFIX, $valueslist);
    }
    return array('message'=>'','value'=>$value);
  }

  function my_export($value) {
    $valueslist = explode(\Seolan\Field\Tag\Tag::$LIST_SEPARATOR, $this->text_tags($value));
    $value = implode(\Seolan\Field\Tag\Tag::$LIST_SEPARATOR, $valueslist);
    return $value;
  }

}
  
function tag_autocomplete() {
  activeSec();
  $modtag = \Seolan\Core\Module\Module::singletonFactory(XMODTAG_TOID);
  if(isset($modtag) && $modtag->secure('', ':list')){
    $q=$_REQUEST['q'];
    $q=normalize_tag($q);

    if ($_REQUEST['add_prefix']) {
      $prefix = \Seolan\Field\Tag\Tag::$TAG_PREFIX;
    } else {
      $prefix = "";
    }
    $lang = \Seolan\Core\Shell::getLangUser();
    $query = "select tag from TAGS where  LANG=? and ( tag like binary ? or tag like ?) order by UPD desc /*{$_REQUEST['q']}*/";
    $res=getDB()->fetchAll($query, array($lang, '%'.$q.'%', $q.'%'));

    header('Content-Type:application/json; charset=UTF-8');
    $gotit =false;
    foreach($res as $ores)  {
      $data[] = array('value' => $prefix.$ores['tag'], 'label' => $ores['tag']);
      $gotit = (!$gotit && $ores['tag'] == $q);
    }
    if (!empty($q)) {
      if (!$_REQUEST['no_add'] && !$gotit && $modtag->secure('', ':rw')) {
        $title = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'add');
        $data[] = array('value' => $prefix.$q, 'label' => $title.' "'.$q.'"');
      }
    }
    die(json_encode($data));
  } else {
    header("HTTP/1.1 500 Seolan Server Error : access level configuration error");
    return null;
  }
}

function normalize_tag($value) {
  $value = preg_replace('/^'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'/','',trim($value));
  $words = preg_split('/[\s\']+/', $value);
  $nvalue = "";
  $accents = array
    (
     "à" => 'À',
     'â' => 'Â',
     'ä' => 'Ä',
     'é' => 'É',
     'è' => 'È',
     'ê' => 'Ê',
     'ë' => 'Ë',
     'î' => 'Î',
     'ï' => 'Ï',
     'ô' => 'Ô',
     'ö' => 'Ö',
     'ù' => 'Ù',
     'û' => 'Û',
     'ü' => 'Ü',
     'ç' => 'Ç'
     );

  foreach ($words as $word) {
    if (strlen($word)) {
      $first = substr($word,0,1);
      $last = substr($word,1);

      if ($first == "\xc3") {
        $first .= substr($last,0,1);
        $last = substr($last,1);
      }
        
      if ($accents[$first])
        $first = $accents[$first];
      else
        $first = strtoupper($first);
      $nvalue .= $first.$last;
    }
  }
  return $nvalue;
}

?>
