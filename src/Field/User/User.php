<?php
namespace Seolan\Field\User;
use \Seolan\Core\DataSource\DataSource;
class User extends \Seolan\Field\Link\Link {
  public $activeGroups = NULL;
  public $multivalued = true;
  function __construct($obj=NULL) {
    parent::__construct($obj);
    if (empty($this->target) || $this->target == TZR_DEFAULT_TARGET)
      $this->target = 'USERS';
  }
  public function getTypeStringAnnotation() {
    return "";
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Groupes actifs', 'activeGroups', 'text', NULL, NULL);
    foreach(['doublebox','doubleboxorder','autocomplete','autocomplete_minlength','autocomplete_limit',
    'checkbox','checkbox_limit','checkbox_cols', 'browsesourcemodule','edit_query','sourcemodule','boxsize','grouplist']
      as $n){
      $this->_options->delOpt($n);
    }
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL){
    if (!$this->multivalued && is_array($value)){
      foreach($value as $v){
	if (!empty($v)){
	  $value = $v;
	  break;
	}
      }
    }
    return parent::post_edit($value, $options, $fields_complement);
  }
  function my_query($value,$options=NULL){
    $format=$options['fmt']??$options['qfmt']??$this->query_format;
    if ($format !== 'autocomplete')
      return parent::my_query($value, $options);
    $options['querymode'] = true;
    $r = $this->my_edit($value, $options);
    if($this->get_multivalued()){
      $textid = $fname.'_id';
      $op=$options['op'];
      $r->html = '<select name="'.$this->field.'_op">
        <option value="AND"'.($op==='AND'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</option>
        <option value="OR"'.($op==='OR'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</option>
        <option value="NONE"'.($op==='NONE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_noterm').'</option>
        <option value="EXCLUSIVE"'.($op==='EXCLUSIVE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_onlyterms').'</option>
        </select>' . $r->html;
    }
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL){
    if ($this->treeview){
      $options['_autocomplete'] = ['class'=>'_Seolan_Field_User_User',
				   'method'=>'xuserdef_autocomplete'];
      return parent::my_edit($value, $options, $fields_complement);
    }
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
    $querymode = $options['querymode']??false;
    $inputname=$fname;
    $r=$this->_newXFieldVal($options, true);
    if (!$this->multivalued && !is_array($value))
      $usersoids = [$value];
    else
      $usersoids = array_keys($value);
    $usersoids = array_filter($usersoids);

    // Liste des users/groupes !! sourcemodule
    list($bru, $brg)=\Seolan\Core\User::getUsersAndGroups(true,false, $this->sourcemodule??null);
    
    // groupes dont un user au moins est actuellement sélectionné
    // et groupes des users
    $usedgroups = [];
    $usergroups = [];
    foreach($bru['lines_oid'] as $i=>$userid){
      if (in_array($userid, $usersoids)){
	$usedgroups = array_unique(array_merge($usedgroups, $bru['lines_oGRP'][$i]->oidcollection));
      }
    }
    $html = '';
    $html .= "<input id=\"{$r->varid}baseinput\" type=\"hidden\" name=\"{$inputname}[]\" value=\"\">";
    $selected = '';
    $old_generate = $this->generate_link;
    $this->generate_link = false;
    $dispOptions = [
      'target_fields'=>['fullnam'],// idem Core\User::getUsersAndGroups, Module/Group::getGroupTree (ajax)
    ]; 
    
    foreach($usersoids as $oid){
      $label = htmlspecialchars($this->display($oid, $dispOptions)->text, ENT_QUOTES);
      
      $button =  <<<EOF
	<button onclick="TZR.UserSelector.unSelectUserFromList.call(TZR.UserSelector, jQuery(this).parents('ul.selectedusers'), '{$oid}');" class="btn btn-default btn-md btn-inverse" type="button"><span class="glyphicon csico-delete" aria-hidden="true"></span></button>
EOF;
      $input = <<<EOF
       <input name="{$this->field}[]" type="hidden" value="{$oid}">
EOF;
      $selected .= <<<EOF
	<div data-oid="{$oid}">{$button} {$label} {$input}</div>
EOF;
    }
    $this->generate_link = $old_generate;
    // Ajout d'un input d'autocompletion pour une saisie directe
    $varidautocomplete = $r->varid."autocomplete";
    $autocomplete=<<<EOF
    <ul class="selectedusers">{$selected}</ul>
    <input autocomplete="off" id="_INPUT{$varidautocomplete}" size="30" type="text" value="" class="tzr-link">
EOF;
    // cas ou "pseudo champ" 
    $ffm = $options['ffm']??null;
    $urlautocomplete=TZR_AJAX8
		    .'?'
		    .http_build_query(['class'=>'_Seolan_Field_User_User',
				       'function'=>'xuserdef_autocomplete',
				       '_silent'=>1,
				       'target_fields'=>'fullnam',
				       'query_format'=>@$options['query_format'],
				       'oid'=>$options['oid'],
				       'ffm'=>$ffm,
		    ]);
    $autocomplete.='<script type="text/javascript">jQuery("#_INPUT'.$varidautocomplete.'").data("autocomplete", {url:"'.$urlautocomplete.'", params:{multivalued:"'.$this->multivalued.'",moid:"'.$options['fmoid'].'", table:"'.$this->table.'", field:"'.$this->field.'", varid:"'.$varidautocomplete.'",id:"'.$varidautocomplete.'"},callback:TZR.autoCompleteUsers});TZR.addAutoComplete("'.$varidautocomplete.'");</script>';
    $html.=$autocomplete;
    $html.=<<<EOF
    <ul id="{$r->varid}"
    class="simpleTree userSelector"
    data-params='{"varid":"{$r->varid}","multivalued":"{$this->multivalued}"}'>
    <li class="root"><div>{$this->label}</div><ul>
EOF;
    foreach($brg['lines_oid'] as $ig=>$group) {
      if (isset($this->activeGroups) && !in_array($group, $this->activeGroups)){
        continue;
      }
      $usersgroupoid=\Seolan\Module\Group\Group::users(array($group), true);
      $countusersgroupoid = count($usersgroupoid);
      $html.='<li id="'.$r->varid.'-'.$inputname.'-ajax-'.$group.'" x-value="'.$group.'" x-name="'.$inputname.'[]" x-type="folder" x-nbusers="'.$countusersgroupoid.'">';
      $html.='<span><span class="'.(in_array($group, $usedgroups)?'hselected':'unselected').'">'.$brg['lines_oGRP'][$ig]->raw.'</span></span>';
      $groupcontenturl = http_build_query(['function'=>'xmodgroup_getGroupTree',
					   'class'=>'_Seolan_Module_Group_Group',
					   'grp'=>$group,
					   'directorymodule'=>$this->sourcemodule??'',
					   'name'=>$inputname.'[]']);
      $html.="<ul class=\"ajax\"><li>{url:\"/csx/scripts-admin/ajax8.php?{$groupcontenturl}\"}</li></ul></li>";
    }
    $html.='</ul></li></ul>';
    $html.= '<script type="text/javascript">TZR.UserSelector.activateField.call(TZR.UserSelector, jQuery("#'.$r->varid.'"));';
    if ($this->compulsory && !$querymode){
      $errcolor = \Seolan\Core\Ini::get('error_color');
      $html.="TZR.addValidator(['{$r->varid}baseinput',/(.+)/,'".addslashes($this->label)."','$errcolor','\Seolan\Field\User\User', '{$r->varid}']);"; 
    }
    $html.= '</script>';
    $r->raw=$value;
    $r->html=$html;
    return $r;
  }
  /**
   * ajout d'un filtre : user actif, voir Module/Group getGroupTree
   */
  function getFilter(){
    $cond = [];
    $filter = parent::getFilter();
    if (!empty($filter))
      $cond[] = $filter;

    $ds = DataSource::objectFactoryHelper8($this->target);

    if($ds->fieldExists('DATEF') && $ds->fieldExists('DATET')) {
      $cond[]='DATET>="'.date('Y-m-d').'"';
      $cond[]='DATEF<="'.date('Y-m-d').'"';
    }
    if($ds->fieldExists('PUBLISH'))
      $cond[]='ifnull(PUBLISH,"")=1';
    
    return implode(' AND ', $cond);
    
  }
  
}
function xuserdef_autocomplete($php=false){
  activeSec();
  if (!$php && isset($_REQUEST['ffm'])){
    $ffm = $_REQUEST['ffm'];
    $mod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$ffm['moid'],
                                                      'interactive'=>false,
                                                      'tplentry'=>TZR_RETURN_DATA]);
    $m = $ffm['f'];
    $ofield = $mod->$m($moid, $ffm['o']);
  }
  // droits de parcour au moins sur le module des utilisateurs
  $moduser = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
  if (!$moduser->secure('', ':ro')){
    throw new \Exception(__METHOD__." moid '{$moduser->_moid}' invalid acl");
  }

  if (!isset($ofield)){
    $table = $_REQUEST['table'];
    $field = $_REQUEST['field'];
    $ds = DataSource::objectFactoryHelper8($table);
    if (is_object($ds) && $ds->fieldExists($field)){
      $ofield = $ds->getField($field);
    }
  }
  
  $ret = \Seolan\Field\Link\xlinkdef_autocomplete(true, $ofield??null, true);
  if ($php)
    return ['field' => $ret['field'], 'suggestions' => $ret['values']];
  
  header('Content-Type:application/json; charset=UTF-8');
  if (count($ret['suggestions']) == 0)
    die(json_encode([['value'=>'*no_result*', 'label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'no_result')]]));
  foreach ($ret['suggestions'] as $koid => $value) {
    $data[] = ['value' => $koid, 'label' => $value];
  }
  if ($ret['state'] == 'toomuch')
    $data[] = ['value' => '', 'label' => \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'too_many_results')];
  die(json_encode($data));
  
}

