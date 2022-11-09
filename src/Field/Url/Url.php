<?php
namespace Seolan\Field\Url;
// classe permet de gérer le type de données URL, qui prend en charge
// un libellé et une url, mailto ou http. La classe cherche à ajouter
// http ou mailto dans l'adresse fournie, si possible.
//
class Url extends \Seolan\Core\Field\Field {
  public $usealt=false;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','basicdisplay'), 'usealt', 'boolean', false);
  }
  function my_search($value,$options) {
    return '';
  }
  function my_export($value) {
    $p = new \Seolan\Core\Param($options, array());
    if ( $value[1] == '' ) return '';

    $res = '<URL>'.$value[1]."</URL>\n<LABEL>".$value.'</LABEL>';
    return $res;
  }
  function post_edit_dup($value,$options){
    $r = $this->_newXFieldVal($options);
    if(!is_array($value) && !empty($value)) $value=array('url'=>$value);
    if(preg_match("/^\[([_[:alnum:]-]+)\]$/", $value['url'], $regs)) {
      if(($rep=\Seolan\Core\Alias::getInternalRep($regs[1],$this->aliasmodule)) && is_array($rep)) {
	$value['url']='['.$rep[0].','.$rep[1].']';
      }
    }
    $value['url']=urlencode($value['url']);
    $value['label']=urlencode(stripslashes($value['label']));
    $v1= $value['label'].';'.$value['url'].';'.$value['target'];
    $r->raw=$v1;
    return $r;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    if(!is_array($value))
      $value=array('url'=>$value,'label'=>'','target'=>'');
    $value['label']=urlencode(stripslashes($value['label']??''));
    if(preg_match("/^\[([_[:alnum:]-]+)\](.*)/", $value['url'], $regs)) {
      if(($rep=\Seolan\Core\Alias::getInternalRep($regs[1],$this->aliasmodule)) && is_array($rep)) {
	$value['url']='['.$rep[0].','.$rep[1].']';
      }
      $value['url']=urlencode($value['url']).$regs[2];
    } else {
      $value['url']=urlencode($value['url']);
    }
    $v1= $value['label'].';'.$value['url'].';'.($value['target']??'');
    $r->raw=$v1;
    $this->trace($options['old']??null,$r);
    return $r;
  }
  function my_quickquery($value,$options=NULL) {
    $r=$this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($value);
    if(isset($value)) $value=htmlspecialchars($value);
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $r->html.='<input '.($this->isFilterCompulsory($options) ? 'required' : '').' type="text" name="'.$this->field.'" size="30" value="'.$value.'">';
    return $r;
  }

  function my_display_deferred(&$r){
    $options=&$r->options;
    $value=&$r->raw;
    $t2 = (empty($options["target"])?NULL:$options["target"]);
    $lang = \Seolan\Core\Shell::getLangUser();
    @list($title,$url,$target) = explode(";",$value,3);
    $title=urldecode($title);
    $url=urldecode($url);

    $starget='';
    if(!empty($t2)) $target=$t2;
    if(!empty($target)) $starget="target=\"$target\"";

    $alert=false;
    if(preg_match('/^\[(.*)\](.*)/',$url,$regs)) {
      if(preg_match("/\[([0-9]+),([_[:alnum:]\:-]+)\]/",$url,$regs2)) {
	$nurl=\Seolan\Core\Alias::mklink2($regs2[1],$regs2[2]).$regs[2];
        $alias = \Seolan\Core\Alias::checkRep($regs2[1],$regs2[2]);
	if(empty($title)) $title=\Seolan\Core\Alias::checkRep($regs2[1],$regs2[2]);
        $r->alias = $alias;
        $r->aliasoid=$regs2[2];
        $r->aliasmoid=$regs2[1];
        if(\Seolan\Core\Shell::admini_mode()) {
          $starget.=' class="cv8-ajaxlink"';
        }
      }

      if(empty($nurl)) $alert=true;
      else $url=$nurl;

    }
    if(preg_match('/^\&(.*)$/',$url,$regs)) {
	$url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().$url;
    }
    if(empty($title)) $title=$url;

    // on essaie de déterminer automatiquement le type de la donnée
    if($alert) $title='<span style="background-color:red">'.$title.'</span>';
    if(empty($url)) $res=$title;
    elseif(preg_match('/^\[(.*)\]/',$url))
      $res=$title;
    elseif(preg_match('/^(https|http|ftp|javascript):/',$url))
      $res = "<a href=\"$url\" $starget>$title</a>";
    elseif(preg_match('@(^/.*)@',$url)) {
      $res = "<a href=\"$url\" $starget>$title</a>";
    } elseif(preg_match('/(^mailto:)/',$url)) {
      $res = "<a href=\"$url\" $starget>$title</a>";
      $r->emails = emailClean($url);
    } elseif(preg_match('/([^@]+@[^@.]+\..*)/',$url)) {
      $res = "<a href=\"mailto:$url\" $starget>$title</a>";
      $url="mailto:".$url;
      $r->emails = emailClean($url);
    } elseif(preg_match('@(^www.*)@',$url)) {
      $res = "<a href=\"http://$url\" $starget>$title</a>";
      $url="http://".$url;
    } else {
      $res = "<a href=\"$url\" $starget>$title</a>";
    }

    $r->html=$res;
    $r->url=$url;
    $r->title=$title;
    $r->target=$target;
    return $r;
  }
  /**
   * Function isEmpty
   * @return true si le champ n'est pas remplit
   */
  public function isEmpty($r){
    return empty($r->url);
  }

  function my_getJSon($o, $options) {
    if (isset($options['property']))
      return $o->{$options['property']};
    $osimplified=(object)null;
    $osimplified->title=$o->title;
    $osimplified->url=$o->url;
    $osimplified->target=$o->target;

    return $osimplified;
  }

  /// Ecriture dans un fichier excel
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=null) {
    if($value->url){
      $lab=$value->toText();
      if(empty($lab)) $lab='Link';
      convert_charset($lab,TZR_INTERNAL_CHARSET,'UTF-8');
      $xl->setCellValueByColumnAndRow($j,$i,$lab);
      $xl->getCellByColumnAndRow($j,$i)->getHyperlink()->setUrl($GLOBALS['HOME_ROOT_URL'].$value->url);
      if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
    }
  }

  /// Sous fonction pour l'import de données vers une table
  function my_import($value, $specs=null){
    $ret['label']='';
    $ret['url']=$value;
    $ret['target']='_blank';
    return array('message'=>'','value'=>$ret);
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p = new \Seolan\Core\Param($options,array());
    $lang = \Seolan\Core\Shell::getLangUser();

    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field."[$o]";
      $hiddenname=$this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field."_HID";
    }

    $r=$this->_newXFieldVal($options);
    $r->display=$this->display($value,$options);
    @list($title,$url,$target)=@explode(';',$value,3);
    $title=urldecode($title);
    $url=urldecode($url);
    $selectedOid='';
    if(preg_match('/^\[(.*)\](.*)/',$url,$regs)) {
      $nurl=NULL;
      if(preg_match('/\[([0-9]+),([_[:alnum:]\:-]+)\]/',$url,$regs2)) {
        $selectedMoid = $regs2[1];
        $selectedOid  = $regs2[2];
	$nurl='['.\Seolan\Core\Alias::checkRep($selectedMoid, $selectedOid).']'.$regs[2];
      }
      if(!empty($nurl)) $url=$nurl;
    }

    $varid=uniqid('v');
    $color=\Seolan\Core\Ini::get('error_color');
    $class = '';
    if ($this->compulsory)
      $class = "tzr-input-compulsory";
    if (@$this->error)
      $class .= " $color";
    if ($class)
      $class = " class=\"$class\"";
	
    if ($this->usealt){
      $edit='<input type="hidden" name="'.$fname.'[label]" id="label'.$varid.'" value=""/>';
      $edit.='<input type="hidden" name="'.$fname.'[target]" id="target'.$varid.'" value="_self"/>';
      $edit.='<input type="text" name="'.$fname.'[url]" '.$class.' size="40" max="100" value="'.$url.'" id="url'.$varid.'" onblur="TZR.isIdValid(\'url'.$varid.'\')" data-selected-oid="'.$selectedOid.'" /> '.$this->getInfoTreeLink($fname,$varid);
    } else {
      $edit='<table><tr><td><label>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','label').'</label>';
      $edit.='</td><td><input type="text" name="'.$fname.'[label]" '.$class.' size="40" max="80" id="label'.$varid.'" value="'.$title.'"/> <button type="button" class="btn btn-default" onclick="TZR.getUrlTitle(\''.$varid.'\',\''.$color.'\'); return false;">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xurl_gettitle').'</button></td></tr>';
      $edit.='<tr><td><label>Url</label></td><td><input type="text" name="'.$fname.'[url]" size="40" max="100" value="'.$url.'" '.
	'id="url'.$varid.'" onblur="TZR.isIdValid(\'url'.$varid.'\');" data-selected-oid="'.$selectedOid.'" /> '.$this->getInfoTreeLink($fname,$varid).'</td></tr>';
      $edit.='<tr><td><label>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','target').'</label></td><td>';
      $edit.='<select '.$class.' name="'.$fname.'[target]">';
      $a1=array('_self'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xurl_currentwindow'),'_top'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xurl_mainwindow'),
		'_blank'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xurl_newwindow'));
      if(empty($target)) $target="_self";
      foreach($a1 as $opt => $lab) {
	if($opt==$target) $edit.='<option selected value="'.$opt.'">'.$lab.'</option>';
	else $edit.='<option value="'.$opt.'">'.$lab.'</option>';
      }
      $edit.='</select></td></tr>';
      $edit.='</table>'; 
    }

    $js="TZR.addValidator(['url$varid',/^(()|(\[[A-Za-z0-9-_]+\].*)|(http.+)|(\/.*)|((mailto:)?([^@]+@[^.]+.*))|(((?!mailto)(?!\[)).+))$/,'".addslashes($this->label)."','$color','\Seolan\Field\ShortText\ShortText']);";
    if($this->compulsory) $js.="TZR.addValidator(['url$varid',/(.+)/,'".addslashes($this->label)."','$color','\Seolan\Field\ShortText\ShortText']);";
    $edit.='<script type="text/javascript">'.$js.'</script>';
    $r->varid=$varid;
    $r->html=$edit;
    $r->raw=$value;
    return $r;
  }

  /// Génère un lien pour aller chercher l'alias dans le gestionnaire de rubrique lié
  function getInfoTreeLink($fieldname, $varid) {
    if (!is_numeric($this->aliasmodule) || empty($this->aliasmodule))
      return '';
    return ' <button type="button" class="btn btn-default" onclick="'.$fieldname.'_getAliasFromInfoTree(); return false;">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','selectatopic').'</button>'.
     '<script>
     '.$fieldname.'_getAliasFromInfoTree = function() {
          var selectedOid = jQuery(\'input[name="'.$fieldname.'[url]"]\').data("selected-oid");
          TZR.selectTopic('.$this->aliasmodule.', selectedOid, function() {
            if (window.selectedTopic != null){
               var selectedTopic = window.selectedTopic;
               jQuery(\'#url'.$varid.'\').val("["+selectedTopic.alias+"]").data("selected-oid", selectedTopic.oid);
               jQuery(\'#label'.$varid.'\').val(selectedTopic.title);
            }
          });
        }
      </script>';
  }

  function my_query($value, $options=NULL) {
    $r = $this->_newXFieldVal($options);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $edit = "<table><tr><td><label>".\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','label')."</label></td>";
    $edit .= "<td><input type=\"text\" name=\"".$fname."_0\" size=\"40\" max=\"80\" ".
      " value=\"$value\"/></td></tr>";
    $edit = $edit."<tr><td><label>".\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','target')."</label></td>".
      "<td><input type=\"text\" name=\"".$fname."_1\" size=\"40\" max=\"100\" ".
      " value=\"$value[1]\"/></td></tr></table>";
    $r->html=$edit;
    return $r;
  }

  function sqltype() {
    return "text";
  }

  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    return $r->url;
  }

}

/// Recupere le titre d'une page
function xurldef_getPageTitle(){
  $url=$_REQUEST['url'];
  if(substr($url,0,1)=='/') $url=$GLOBALS['HOME_ROOT_URL'].$url;
  if(strpos($url,'http')!==0) $url='http://'.$url;
  $c=@file_get_contents($url);
  if(empty($c)) die('error');
  $ret=array();
  if(preg_match('@;[ ]?charset=([a-zA-Z0-9-_]+)["\']@i',$c,$cs) ){
    $charset = $cs[1];
  }else $charset = TZR_INTERNAL_CHARSET;
  if(preg_match('@<title>(.*?)</title>@is',$c,$ret)){
    $tit = trim($ret[1]);
    convert_charset($tit,$charset,TZR_INTERNAL_CHARSET);
    $tit = strip_tags(html_entity_decode($tit,ENT_COMPAT,TZR_INTERNAL_CHARSET));
    die($tit);
  }else
    die('');
}
?>
