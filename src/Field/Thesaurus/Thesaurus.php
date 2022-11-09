<?php
namespace Seolan\Field\Thesaurus;
class Thesaurus extends \Seolan\Field\Link\Link{
  public $query_formats=array('classic', 'listbox-one');
  public $quickadd=true;
  public $flabel=NULL;
  public $fparent=NULL;
  public $indexable=NULL;
  public $generate_link=false;
  public $ajax_search=true;
  public $optimizewith='';

  function __construct($ar=NULL){
    parent::__construct($ar);
  }

  /**
   * Renvoie le filtre sur les données du thésaurus
   * @return string
   */
  public function getFilter(){
    if ($this->sourcemodule) {
      $mod = \Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if ($mod instanceof \Seolan\Module\InfoTree\InfoTree) {
        return 'KOID!="'.$mod->getTrashOid().'"';
      }
    }
    // si le champ est en lecture on peut appliquer les filtres SQL
    if ($this->get_readonly() && !empty($this->filter)){
        return parent::getFilter();
    }
    return '';
  }

  function getOrder($default="") {
    if($this->sourcemodule) {
      $sourcemod = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$this->sourcemodule, 'tplentry'=>TZR_RETURN_DATA));
      if($sourcemod && $sourcemod->order) {
        return $sourcemod->order;
      }
    }
    if($this->thesorder) {
      return $this->thesorder;
    }
    return $default;
  }

  function initOptions() {
    \Seolan\Core\Field\Field::initOptions();
    $this->_options->delOpt('generate_link');
    $this->_options->delOpt('aliasmodule');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','filter'), 'filter', 'text',array('rows'=>2,'cols'=>60));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','sourcemodule'),'sourcemodule','module',array('emptyok'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xthesaurus_label'),"flabel","field",
			    array('table'=>$this->target,'compulsory'=>false,'type'=>'\Seolan\Field\ShortText\ShortText'));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xthesaurus_order'),"thesorder","field",
                            array('table'=>$this->target,'compulsory'=>false));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xthesaurus_parent'),"fparent","field",
			    array('table'=>$this->target,'compulsory'=>true,'type'=>\Seolan\Core\Field\Field::getLinkTypes()));
    $this->_options->setOpt('Optimiser en fonction du champ',"optimizewith","field",
			    array('table'=>$this->table,'compulsory'=>false,'type'=>array('\Seolan\Field\Link\Link', '\Seolan\Field\Thesaurus\Thesaurus', '\Seolan\Field\ShortText\ShortText')));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xthesaurus_quickadd'),"quickadd","boolean");
    $querygroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','query','text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','indexable'), 'indexable', 'boolean', NULL, true, $querygroup);
  }

  /// Recupere la liste des ascendants d'un oid
  function &getParents($value,$options=null,$gethtml=true){
    $lang_data=\Seolan\Core\Shell::getLangData(@$options['lang_list']);
    $parents=array();
    if($gethtml) list($myliste,$my_flist,$first)=$this->getFieldList($options);
    if(empty($value)) return array();
    elseif(!is_array($value)) $v=array($value);
    else $v=$value;
    $filter = $this->getFilter();
    do {
      $where = array(
        'KOID IN ("'.implode('","',$v).'")',
        'LANG="'.$lang_data.'"',
        $filter
      );
      $rs=getDB()->select('SELECT * FROM '.$this->target.' WHERE '.implode(' AND ', array_filter($where)));
      $v=array();
      while($rs && $ors=$rs->fetch()){
	$parents[$ors['KOID']]=($gethtml?$this->format_display($myliste,$ors,$p1):true);
	// si on est pas sur une racine
	if (!empty($ors[$this->fparent]))
          $v[]=$ors[$this->fparent];
      }
    }while(!empty($v));
    $parents=array_reverse($parents);
    return $parents;
  }
  
  /// Recupere la liste des descendants d'un ou pls oid
  function &getSons($value,$options,$gethtml=true){
    $lang_data=\Seolan\Core\Shell::getLangData();
    $sons=array();
    if($gethtml) list($myliste,$my_flist,$first)=$this->getFieldList($options);
    if(empty($value)) return array();
    elseif(!is_array($value)) $v=array($value);
    else $v=$value;
    $filter = $this->getFilter();
    do{
      $where = array(
        $this->fparent.' IN ("'.implode('","',$v).'")',
        'LANG="'.$lang_data.'"',
        $filter
      );
      $rs=getDB()->select('SELECT * FROM '.$this->target.' WHERE '.implode(' AND ', array_filter($where)));
      $v=array();
      while($rs && $ors=$rs->fetch()){
	$sons[$ors['KOID']]=($gethtml?$this->format_display($myliste,$ors,$p1):true);
	$v[]=$ors['KOID'];
      }
    }while(!empty($v));
    return $sons;
  }

  public function my_display_deferred(&$r){
    if($this->target==TZR_DEFAULT_TARGET || !\Seolan\Core\DataSource\DataSource::sourceExists($this->target) || empty($this->fparent)) return $r;
    parent::my_display_deferred($r);
    if(!empty($r->raw)){
      $parents=$this->getParents($r->raw,$r->options);
      if(!empty($r->title))  $parents[$r->raw]=$r->title;
      $r->html=implode(' > ',$parents);
    }
    return $r;
  }

  function my_import($value, $specs=null){
    if(empty($specs->srcField)){
      $specs->srcField=$this->flabel;
    }

    if($value && strpos($value, " > ") !== false) {
      $separator=$specs->separator;
      if(empty($separator)) {
        $separator = TZR_IMPORT_SEPARATOR;
      }
      if(is_array($separator)) {
        $value = str_replace($separator, $separator[0], $value);
        $valueslist = explode($separator[0], $value);
      }
      else {
        $valueslist = explode($separator, $value);
      }
      foreach($valueslist as $i => $val) {
        if($val && strpos($val, " > ") !== false) {
          $valueslist[$i] = substr(strrchr($val, ">"), 2);
        }
      }
      $sep = is_array($separator) ? $separator[0] : $separator;
      $value = implode($sep, $valueslist);
    }

    return parent::my_import($value,$specs);
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL){
    $lang_data=\Seolan\Core\Shell::getLangData();
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    if($this->target==TZR_DEFAULT_TARGET || !\Seolan\Core\DataSource\DataSource::sourceExists($this->target) || empty($this->fparent)) {
      $r->html='';
      return $r;
    }
    $mod=NULL;
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$this->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
      if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')) return $r;
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

    // Liste des champs de la cible
    list($myliste,$my_flist,$first)=$this->getFieldList($options);

    // Ligne de base
    $varid=uniqid('v');
    $delico=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
    $edit='<div id="div'.$varid.'">';
    if($this->get_multivalued() && $options['query_format']){
      $edit.='<select name="'.$fname.'_op">
        <option value="AND"'.($options['op']==='AND'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</option>
        <option value="OR"'.($options['op']==='OR'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</option>
        <option value="NONE"'.($options['op']==='NONE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_noterm').'</option>
      </select><br>';
    }
    $edit.='<input type="hidden" id="'.$varid.'"/><table id="table'.$varid.'"><tr class="none model"><td><a href="#" class="xthesaurus-del" onclick="TZR.removeThesaurusValue(\''.$varid.'\',this); return false;">'.$delico.'</a><input type="hidden" name="'.$fname.($this->multivalued?'[]':'').'" value=""></td><td></td></tr>';

    // Affiche les valeurs en cours
    if(!empty($value)){
      if(!is_array($value)) $value=array($value=>'1');
      $order = $this->getOrder($first);
      $rs=getDB()->select('SELECT DISTINCT '.$my_flist.' FROM '.$this->target.' WHERE KOID IN ("'.implode('","',array_keys($value)).'") '.
			  'AND LANG="'.$lang_data.'"'.($order?' ORDER BY '.$order:''));
      while($ors=$rs->fetch()){
	if($mod && $mod->object_sec && !$mod->secure($ors['KOID'],':list')) continue;
	$parents=$this->getParents($ors['KOID'],$options);
	$parents[$ors['KOID']]=$this->format_display($myliste,$ors,$p1);
	$display=implode(' > ',$parents);
	if($mod && $mod->object_sec && !$mod->secure($ors['KOID'],':ro')) {
	  /* dans le cas d'une rubrique mère en mode traverser, on peut voir le nom dela rubrique mais rien modifier */
	  $edit.='<tr><td><input type="hidden" name="'.$fname.($this->multivalued?'[]':'').'" value="'.$ors['KOID'].'"></td><td>'.$display.'</td></tr>';
	} else {
	  $edit.='<tr><td><a href="#" onclick="TZR.removeThesaurusValue(\''.$varid.'\',\''.$ors['KOID'].'\');return false;">'.$delico.'</a><input type="hidden" name="'.$fname.($this->multivalued?'[]':'').'" value="'.$ors['KOID'].'"></td><td>'.$display.'</td></tr>';
	}
      }
    }

    $edit.='</table>';
    // Ajout d'un input d'autompletion pour une saisie directe
    if ($this->ajax_search) {
      $edit.='<input autocomplete="off" id="_INPUT'.$varid.'" size="30" type="text" value="" class="tzr-link">';
    }
    $url=TZR_AJAX8.'?class=_Seolan_Field_Thesaurus_Thesaurus&function=xthesaurusdef_autocomplete&_silent=1&query_format='.@$options['query_format'].'&oid='.$options['oid'];
    $edit.='<script type="text/javascript" language="javascript">jQuery("#_INPUT'.$varid.'").data("autocomplete", {url:"'.$url.'", params:{moid:"'.$options['fmoid'].'", table:"'.$this->table.'", field:"'.$this->field.'", id:"'.$varid.'"},callback:TZR.autocompleteThesaurus});TZR.addAutoComplete("'.$varid.'");</script>';
    // calcul de l'arbo
    if (@$options['query_format'] == \Seolan\Core\Field\Field::QUICKQUERY_FORMAT) {
      $rw = 0;
      $quickquery = 1;
      $tree = array('htmlFiltered' => '', 'html' => '
        <ul class="simpleTree quickquery treefiltered">
          <li class="root close" onclick="TZR.thesaurusClick(\''.$varid.'\', event)">
            <div>'.$this->label.'</div>
            <ul style="display:none" id="top'.$varid.'"></ul>
          </li>
          <li class="fin_float"></li>
        </ul>
      ');
    } else {
      $rw = $this->isAutorizedToAdd($options);
      $quickquery = 0;
      $tree = $this->getTree($value, $options, $varid, $mod, $rw, $fields_complement);
    }
    $edit.='<div id="treecontainer'.$varid.'" class="thesaurustree">'.$tree['htmlFiltered'].$tree['html'].'</div>';
    $edit.='<script type="text/javascript">
      jQuery("#treecontainer'.$varid.'").data({varid:"'.$varid.'", moid:"'.$options['fmoid'].'", xtable:"'.$this->table.'", field:"'.$this->field.'", rw:'.(int)$rw.', multivalued:'.(int)$this->multivalued.', optimizewith:"'.$this->optimizewith.'",optimizevalues:"'.implode(' ',(array) $fields_complement[$this->optimizewith]).'",quickquery:'.$quickquery.', reloadUrl:"'.TZR_AJAX8.'?class=_Seolan_Field_Thesaurus_Thesaurus&function=xthesaurusdef_loadtree&moid='.$options['fmoid'].'&table='.$this->table.'&field='.$this->field.'&varid='.$varid.'&LANG_DATA='.$lang_data.'"});';
    if ($this->optimizewith)
      $edit.='TZR.activeThesaurus("'.$varid.'","'.$this->optimizewith.'");';
    if((!@$options['query_format'] && $this->compulsory) || (@$options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options))){
      $color=\Seolan\Core\Ini::get('error_color');
      $edit.='TZR.addValidator(["'.$varid.'","","'.addslashes($this->label).'","'.$color.'","\Seolan\Field\Thesaurus\Thesaurus"]);';
    }
    $edit.='</script></div>';
    $r->varid=$varid;
    $r->html=$edit;
    return $r;
  }

  /// Calcule le menu
  function getTree($value, $options, $varid, $mod=null, $rw, $fields_complement=array()) {
    $lang_data = \Seolan\Core\Shell::getLangData();
    $where = array(
      'LANG="'.$lang_data.'"',
      $this->getFilter()
    );
    $order = $this->getOrder($this->flabel);
    // regression requete lors du merge de juillet : plus de ? pour la langue
    $stmt = getDB()->select('select distinct '.$this->fparent.', KOID, '.$this->flabel.' from '.$this->target.' where '.implode(' AND ', array_filter($where)).' order by '.$order);

    if (!$stmt)
      return;
    $items = $stmt->fetchAll(\PDO::FETCH_GROUP);
    $nbItems = $stmt->rowCount();
    $used_values = null;
    // dans le cas d'une contrainte
    if ($this->optimizewith) {
      // si on a une valeur dans le champ qui contraint
      if (isset($fields_complement[$this->optimizewith])) {
        if (is_array($fields_complement[$this->optimizewith])) // champ multivalué déjà my_edité
          $filteringValues = array_keys($fields_complement[$this->optimizewith]);
        else
          $filteringValues = preg_split('/\|\|/', $fields_complement[$this->optimizewith], 0, PREG_SPLIT_NO_EMPTY);
      }
      // on l'utilise pour calculer la partie filtrée
      if (!empty($filteringValues)) {
        foreach ($filteringValues as $filteringValue)
          $_filter[] = 'instr('.$this->optimizewith.', "'.addcslashes($filteringValue,'"').'")';
        $filter = '(' . implode(' or ', $_filter)  . ')';
        $used_values = $this->_getUsedValues($filter, null, $options);
      }
      // s'il y a un filtre global, on restreint les valeurs
      if (isset($options['fmoid'])) {
        $owFilter = \Seolan\Core\User::getDbCacheData($this->optimizewith . 'Filter' . $options['fmoid']);
        if ($owFilter) {
          $options['restrictTo'] = $this->_getUsedValues($owFilter, null, $options);
        }
      }
    }
    // calcul des droits du module source
    if ($mod && $mod->object_sec && !\Seolan\Core\Shell::isRoot()) {
      $modLevels = $GLOBALS['XUSER']->getObjectAccess($mod, \Seolan\Core\Shell::getLangData());
      $_secObjects = $mod->getObjectsWithSec(\Seolan\Core\User::get_current_user_uid());
      if($_secObjects===true) $_secObjects = $mod->xset->browseOids();
      $rights = $GLOBALS['XUSER']->getObjectsAccess($mod, \Seolan\Core\Shell::getLangData(), $_secObjects);
      foreach ($_secObjects as $i => $oid)
        $secObjects[$oid] = $rights[$i];
      $secObjects['default'] = $modLevels[0];
    } else
      $secObjects = null;
    $options['withSiblings'] = \Seolan\Core\Shell::admini_mode() && !@$options['quickquery'];
    $tree = $this->_getTree(@$options['top'], $items, $secObjects, $value, $used_values, $options, $varid);

    // html de l'arbo
    $htmlFiltered='';
    $_htmlFiltered=NULL;
    if (@$options['top'])
      return array('html' => $this->_makeHtml($tree, $varid, 20));
    if (!@$options['justFiltered'])
      $htmlFull = $this->_makeHtml($tree, $varid, ($nbItems > 500) ? 1 : 10);
    if ($this->optimizewith)
      $_htmlFiltered = $this->_makeHtmlFiltered($tree, $varid, 10);
    if ($this->optimizewith) {
      $htmlFiltered = '
        <ul class="simpleTree treefiltered" '.($_htmlFiltered ? '' : ' style="display:none"').'>
          <li class="root close" onclick="TZR.thesaurusClick(\''.$varid.'\', event)">
            <div>'.$this->label.' - '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','filtered').'</div>
            <ul style="display:none" id="topfiltered'.$varid.'">'.
              $_htmlFiltered . '
            </ul>
          </li>
          <li class="fin_float"></li>
        </ul>';
    }
    $html = '';
    if (@$options['quickquery']) {
      $html = $htmlFull;
    } elseif (!@$options['justFiltered']) {
      $html = '
        <ul class="simpleTree full">
          <li class="root close" onclick="TZR.thesaurusClick(\''.$varid.'\', event)">
            <div>'.$this->label.'</div>
            <ul style="display:none" id="top'.$varid.'">'.
              $htmlFull . '
            </ul>
          </li>
          <li class="fin_float"></li>
        </ul>';
    }
    return array('html' => $html, 'htmlFiltered' => $htmlFiltered);
  }

  /// Construit l'arborescence
  private function _getTree($oid, &$items, $secObjects, $value, $used_values, $options) {
    $default = $secObjects['default'];
    $ro = is_array($default) && (in_array('ro1', $default) || in_array('ro2', $default) || in_array('ro3', $default) || in_array('ro', $default));
    $tree = ['oid' => $oid, 'items' => [], 'activeItems' => [], 'active' => false, 'live' => false];
    foreach ($items[$oid] as $i => $item) {
      $item_oid = $item['KOID'];
      $item['live'] = false;
      // Filtre sur les droits du module source
      // est ce qu'il y a au moins un droit de lecture
      $roi = isset($secObjects[$item_oid]['ro1']) ||isset($secObjects[$item_oid]['ro2']) ||isset($secObjects[$item_oid]['ro3']) ||isset($secObjects[$item_oid]['ro']);
      if ($secObjects && isset($secObjects[$item_oid]) && !$roi) continue;
      elseif ($secObjects && !isset($secObjects[$item_oid]) && !$ro) continue;

      // les items dans le nom commencent par un . sont cachés
      if (substr($item[$this->flabel],0,1)=='.') 
        continue;

      $item['selected'] = isset($value[$item_oid]);
      // marque les noeuds pour le htmlFiltered
      $item['active'] = $item['selected'] || isset($used_values[$item_oid]);
      // marque les noeuds pour le filtre global
      if (!empty($options['restrictTo']))
        $item['live'] = $item['selected'] || isset($options['restrictTo'][$item_oid]);
      if (isset($items[$item_oid])){
        $item['subtree'] = $this->_getTree($item_oid, $items, $secObjects, $value, $used_values, $options);
        $item['active'] |= $item['subtree']['active'];
        $item['live'] |= $item['subtree']['live'];
      }
      // prendre les fils des noeuds selectionnés
      if ($item['selected'] && $item['subtree'] && !$item['subtree']['active']) { 
        $item['subtree']['getAll'] = true;
      }
      $tree['items'][] = $item;
      if ($item['active'])
        $tree['activeItems'][] = $item;
      if ($item['live'])
        $tree['liveItems'][] = $item;
      // prendre les freres des feuilles actives
      if ($options['withSiblings'] && $item['active'] && !isset($item['subtree'])) { 
        $tree['getAll'] = true;
      }
      $tree['active'] |= @$item['active'];
      $tree['live'] |= @$item['live'];
    }
    return $tree;
  }

  /// Génère le html
  private function _makeHtml(&$tree, $varid, $depth=0) {
    if (empty($tree) || !$depth)
      return '';
    $html = '';
    if (isset($tree['liveItems']))
      $items = 'liveItems';
    else
      $items = 'items';
    $last = count($tree[$items]) -1;
    foreach ($tree[$items] as $i => &$item) {
      $html .= ' <li class="line">&nbsp;</li><li tzroid="'.$item['KOID'].'" class="'.(isset($item['subtree']) ? 'folder close' : 'doc').($i == $last?' last':'').'"><div style="position:absolute;"><div class="ico"></div></div><img class="trigger" src="/csx/public/jtree/spacer.gif" border="0" style="float:left;_float:none"><span class="text"><span'.($item['selected'] ? ' class="selected"' : '').'>'.$item[$this->flabel].'</span></span>';
      if (isset($item['subtree'])) {
        $html .= '<ul style="display:none">'.$this->_makeHtml($item['subtree'], $varid, $depth-1).'</ul>';
      }
      $html .= '</li>';
    }
    unset($item);
    $html .= '<li class="line-last">&nbsp;</li>';
    return $html;
  }
  /// Génère le html des entrées filtrées
  private function _makeHtmlFiltered($tree, $varid, $depth=0) {
    if (empty($tree))
      return '';
    $html = '';
    if (isset($tree['activeItems'])) {
      $last = count($tree['activeItems']) -1;
      foreach ($tree['activeItems'] as $i => $item) {
        if (!empty($item['subtree'])) {
          if ($item['subtree']['getAll'])
            $subHtml = $this->_makeHtml($item['subtree'], $varid, $depth-1);
          else
            $subHtml = $this->_makeHtmlFiltered($item['subtree'], $varid, $depth-1);
        } else
          $subHtml = '';
        $html .= ' <li class="line">&nbsp;</li><li tzroid="'.$item['KOID'].'" class="'.($item['subtree']['activeItems'] ? 'folder close' : 'doc').($i == $last?' last':'').'"><div style="position:absolute;"><div class="ico"></div></div><img class="trigger" src="/csx/public/jtree/spacer.gif" border="0" style="float:left;_float:none"><span class="text"><span'.($item['selected'] ? ' class="selected"' : '').'>'.$item[$this->flabel].'</span></span>';
        if ($item['subtree']) {
          $html .= '<ul style="display:none">'.($depth>0 ? $subHtml :'').'</ul>';
        }
        $html .= '</li>';
      }
    }
    if ($html)
      return $html . '<li class="line-last">&nbsp;</li>';
    else 
      return '';
  }

  /// Retourn vrai si l'utilisateur peut créer de nouveau mots clé à la volée
  function isAutorizedToAdd(&$options){
    if(!empty($options['query_format']) || empty($this->flabel) || !$this->quickadd) return false;
    return parent::isAutorizedToAdd($options);
  }

  function my_quickquery($value,$options=NULL) {
    $r=$this->my_query($value,$options);
    $r->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    return $r;
  }
  
  function my_query($value,$options=NULL){
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $hiddenname=$fname.'_HID';
    $options['fieldname']=$fname;
    $qf = ((isset($options['query_format']) && $options['query_format']!='quick')?$options['query_format']:$this->query_format);

    if(is_array($value)){
      $keys=array_keys($value);
      if(!\Seolan\Core\Kernel::isAKoid($keys[0])) $value=array_flip($value);
    }
    $optimizeValues=array();
    if ($this->optimizewith) {
      foreach ($options['fields_complement']['_FIELDS'] as $id => $fieldname)
        if ($fieldname == $this->optimizewith) {
          $idx = $id;
          break;
        }
      if ($idx)
        $optimizeValues = @array_filter($options['fields_complement'][$idx]);
    }
    if ($qf == 'listbox-one'){
      return $this->listbox_query($value,$options, array($this->optimizewith => $optimizeValues));
    } else {
      $o=array($this->optimizewith => $optimizeValues);
      $multi=$this->get_multivalued();
      $this->set_multivalued(true);
      $ret=$this->my_edit($value,$options, $o);
      $this->set_multivalued($multi);
      return $ret;
    }
  }
  /*
   * maquette ....
   * @TODO : -> voir gettree ?
   */
  function listbox_query($value,$options, $optimizeValues){
    
    $r=$this->_newXFieldVal($options, true);
    
    if($this->target==TZR_DEFAULT_TARGET || !\Seolan\Core\DataSource\DataSource::sourceExists($this->target) || empty($this->fparent)) {
      $r->html='';
      return $r;
    }
    $sourcemod = NULL;
    if($this->sourcemodule){
      $sourcemod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$this->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
      // @todo vérifier que c'est nécessaire ici
      if ($sourcemod->object_sec && !$sourcemod->secure('',':list') || !$sourcemod->object_sec && !$sourcemod->secure('',':ro')) 
        return $r;
    }
    if ($sourcemod && !$sourcemod->object_sec)
      $sourcemod = null;
      
    if(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
    } else {
      $fname=$this->field;
    }
    
    $limitlevel=(isset($options['limitlevel'])?$options['limitlevel']:4);;
    $filter = (isset($options['filter'])?$options['filter']:NULL);

    $usedvalues = false;
    if (isset($options['usedvalues'])){
      $usedvalues = array_keys($this->_getUsedValues($filter, NULL, $options));
      // ajouter les parents des noeuds possibles pour avoir une arbo complete
      $usedvalues = array_merge($usedvalues, array_keys($this->getParents($usedvalues, array(), false)));
    }

    if (!is_array($value))
      $value = array($value=>$value);
    $value = array_keys($value);
    $lang_data = \Seolan\Core\Shell::getLangData();

    $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
    $condpublish = ' AND 1 ';
    if($xst->publishedMode(new \Seolan\Core\Param(NULL, array())) == 'public')
      $condpublish.=' AND '.$xst->getTable().'.PUBLISH="1" ';

    $condapp = '';
    if(TZR_USE_APP && $xst->fieldExists('APP') && !\Seolan\Core\Shell::isRoot()) {
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      if($bootstrapApplication && $bootstrapApplication->oid) {
        $condapp .= ' AND '.$xst->getTable().'.APP="'.$bootstrapApplication->oid.'" ';
      }
    }

    $size = isset($options['size'])?$options['size']:6;
    $r->raw=$value;

    $parent = '';
    $pil = array();
    $list = array();
    $level = 0;
    $levelmax = 0;
    $labels = array();
    $levels = array();

    // restriction aux valeurs utilisées
    // ajouter les valeurs des parents !
    if ($usedvalues !== false){
      $condval = 'KOID in("'.implode('","', $usedvalues).'") and ';
    } else {
      $condval = '';
    }

    // Filtre sur les valeurs du champ
    $filter = $this->getFilter();
    if (!empty($filter)) {
      $condval.= $filter.' and ';
    }
    
    // tri
    $order = $this->getOrder($this->flabel.' desc');
    if (isset($options['orderfield'])){
      $order = 'ifnull('.$options['orderfield'].', "") desc, '.$order;
    }
    do{
     
      // desc -> liste alpha 
      $rs = getDB()->select('SELECT * FROM '.$this->target.' WHERE '.$condval.' ifnull('.$this->fparent.', "") = "'.$parent.'" AND LANG="'.$lang_data.'" '.$condpublish.$condapp.' order by '.$order);
      while($rs && ($ors = $rs->fetch())){
        // niveau max atteint
	if($level<=$limitlevel){
	  $pil[] = array($level, $ors);
        }
        // objet non visible : ignoré @todo : droits traverser ... ?
        if (isset($sourcemod) && !$sourcemod->secure($ors['KOID'], ':ro')){
          array_pop($pil);
        }
      }

      $next = array_pop($pil);

      if (!empty($next)){
	$list[] = array('level'=>$next[0], 'label'=>$next[1][$this->flabel], 'oid'=>$next[1]['KOID']);
	$levels[] = $level;
	$labels[] = $next[1][$this->flabel];
	$parent = $next[1]['KOID'];
	$level = $next[0]+1;
      }

      if ($level>$levelmax)
	$levelmax = $level;

      $done = (count($pil) == 0 && empty($next));

    } while(!$done);

    if (($nb = count($list)) < $size)
      $size = $nb+1;
    if (!isset($options['operator'])){
      $r->html = '<div class="radio"><label><input class="radio" type="radio" name="'.$fname.'_op" value="AND" id="'.$varid.'-AND"'.((empty($options['op']) || $options['op']=='AND')?' checked':'').'>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</label></div><br>'.
	'<div class="radio"><label><input class="radio" type="radio" name="'.$fname.'_op" value="OR" id="'.$varid.'-OR"'.($options['op']=='OR'?' checked':'').'>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</label></div><br>'.
	'<div class="radio"><label><input class="radio" type="radio" name="'.$fname.'_op" value="NONE" id="'.$varid.'-NONE"'.($options['op']=='NONE'?' checked':'').'>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_noterm').'</label></div><br>';
    } else {
      $r->html .= '<input type="hidden" value="'.$options['operator'].'" name="'.$fname.'_op">';
    }
    $r->html .= '<select id="'.$r->varid.'" size="'.$size.'" multiple class="thesaurus" name="'.$fname.'[]">';
    $r->html .= '<option class="item-level0" value="">'.(isset($options['labelin'])?$this->label:'----').'</option>';

    foreach($list as $il=>$item){

      $group = ($list[$il+1]['level']>$item['level'])?'group':'';
      $list[$il]['group'] = ($group=='group');
      $pad = implode('&nbsp;' , array_fill(0, $item['level']+1, '&nbsp;'));
      $r->html .= '<option data-level="'.$item['level'].'" data-group="'.$group.'" '.(in_array($item['oid'], $value)?'selected':'').' class="item-level'.$item['level'].' '.$group.'" value="'.$item['oid'].'">'.$pad.$item['label'].'</option>';

    }

    $r->list = $list;
    $r->html .= '</select>';

    return $r;
  }
  function post_query($o,$options=NULL){
    if(is_array($o->value)){
      $values=$o->value;
    } else {
      $values=[$o->value];
    }
    $v1op=$o->op;
    if($v1op!='OR' && $v1op!='NONE') $v1op='AND';
    $rq=array();
    foreach($values as $v){
      if(empty($v)) continue;
      $oids=$this->getSons($v,$options,false);
      $oids[$v]='';
      $rq2=array();
      foreach($oids as $oid=>$foo){
	$tmp=(object)array('op'=>'OR','value'=>$oid,'field'=>$o->field);
	parent::post_query($tmp,$options);
	$rq2[]=$tmp->rq;
      }
      $rq[]='('.implode('OR',$rq2).')';
    }
    if (empty($rq))
      $o->rq='';
    elseif ($v1op == 'NONE')
      $o->rq='(!'.implode(' AND !',$rq).')';
    else
      $o->rq='('.implode($v1op,$rq).')';
    return $o;
  }
}
function xthesaurusdef_loadtree() {
  activeSec();
  $moid=$_REQUEST['moid'];
  $table=$_REQUEST['table'];
  $field=$_REQUEST['field'];
  if(empty($moid) || empty($table) || empty($field)) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xds->getField($field);
  if($ofield->sourcemodule){
    $smod=$mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$ofield->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
  } else {
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
  }
  $ok=$mod->secure('',':list');
  if(!$ok) die('');
  if (isset($_REQUEST['value']))
    $value = array_flip($_REQUEST['value']);
  else
    $value = array();
  if (isset($_REQUEST['filter']))
    $fields_complement = array($ofield->optimizewith => array_flip($_REQUEST['filter']));
  else
    $fields_complement = array();
  $options=array('fmoid'=>$moid, 'justFiltered' => (@$_REQUEST['justfiltered'] && @$_REQUEST['filter']));
  $rw = $ofield->isAutorizedToAdd($options);
  $options['top'] = @$_REQUEST['top'];
  $options['quickquery'] = @$_REQUEST['quickquery'];
  $tree=$ofield->getTree($value,$options,$_REQUEST['varid'],$smod,$rw,$fields_complement);
  if ($options['justFiltered'])
    die($tree['htmlFiltered']);
  else
    die($tree['html']);
}
function xthesaurusdef_delvalue(){
  activeSec();
  $moid=$_REQUEST['moid'];
  $table=$_REQUEST['table'];
  $field=$_REQUEST['field'];
  $oid=$_REQUEST['oid'];
  if(empty($moid) || empty($table) || empty($field) || empty($oid)) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xds->getField($field);
  if($ofield->sourcemodule){
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$ofield->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure($oid,':rw');
  }else{
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure('',':rwv');
  }
  if(!$ok) die(json_encode(''));
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ofield->target);
  $rs=getDB()->select('select KOID from '.$ofield->target.' where KOID=? or '.$ofield->fparent.'=?', [$oid, $oid]);
  while($rs && $ors=$rs->fetch()) $xds->del(array('oid'=>$ors['KOID'],'tplentry'=>TZR_RETURN_DATA));
  die('ok');
}
function xthesaurusdef_editvalue(){
  activeSec();
  $moid=$_REQUEST['moid'];
  $table=$_REQUEST['table'];
  $field=$_REQUEST['field'];
  $oid=$_REQUEST['oid'];
  $value=$_REQUEST['value'];
  if(empty($moid) || empty($table) || empty($field) || empty($oid)) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xds->getField($field);
  if($ofield->sourcemodule){
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$ofield->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure($oid,':rw');
  }else{
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure('',':rwv');
  }
  if(!$ok) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ofield->target);
  $xds->procEdit(array('oid'=>$oid,$ofield->flabel=>$value,'tplentry'=>TZR_RETURN_DATA));
  die('ok');
}
function xthesaurusdef_addvalue(){
  activeSec();
  $moid=$_REQUEST['moid'];
  $table=$_REQUEST['table'];
  $field=$_REQUEST['field'];
  $parentoid=$_REQUEST['parentoid'];
  $value=$_REQUEST['value'];
  if(empty($moid) || empty($table) || empty($field) || empty($value)) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xds->getField($field);
  if($ofield->sourcemodule){
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$ofield->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure('',':rw');
  }else{
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure('',':rwv');
  }
  if(!$ok){
    \Seolan\Library\Security::alert("invalid  parameters or ACL configuration");
    exit(0);
  }
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ofield->target);
  $ret=$xds->procInput([$ofield->fparent=>$parentoid,$ofield->flabel=>$value,'tplentry'=>TZR_RETURN_DATA]);
  die($ret['oid']);
}
function xthesaurusdef_copyvalue(){
  activeSec();
  $moid=$_REQUEST['moid'];
  $table=$_REQUEST['table'];
  $field=$_REQUEST['field'];
  $parentoid=$_REQUEST['parentoid'];
  $value=$_REQUEST['value'];
  if(empty($moid) || empty($table) || empty($field) || empty($value)) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xds->getField($field);
  if($ofield->sourcemodule){
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$ofield->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure($value,':rw');
  }else{
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure('',':rwv');
  }
  if(!$ok) die();
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ofield->target);
  $ret=$xds->duplicate(array('oid'=>$value));
  getDB()->execute('update '.$ofield->target.' set '.$ofield->fparent.'=? where KOID=?',[$parentoid,$ret]);
  die($ret);
}
function xthesaurusdef_cutvalue(){
  activeSec();
  $moid=$_REQUEST['moid'];
  $table=$_REQUEST['table'];
  $field=$_REQUEST['field'];
  $parentoid=$_REQUEST['parentoid'];
  $value=$_REQUEST['value'];
  if(empty($moid) || empty($table) || empty($field) || empty($value)){
    \Seolan\Library\Security::alert("Invalids parameters or ACL configuration");
    die();
  }
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xds->getField($field);
  if($ofield->sourcemodule){
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$ofield->sourcemodule,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure($value,':rw');
  }else{
    $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    $ok=$mod->secure('',':rwv');
  }
  if(!$ok){
    \Seolan\Library\Security::alert("Invalids parameters or ACL configuration");
    exit(0);
  }
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ofield->target);
  getDB()->execute('update '.$ofield->target.' set '.$ofield->fparent.'=? where KOID=?',[$parentoid, $value]);
  die($value);
}
function xthesaurusdef_autocomplete() {
  $r = \Seolan\Field\Link\xlinkdef_autocomplete(true);
  $ofield = $r['field'];
  $suggestions = $r['suggestions'];

  header('Content-Type:application/json; charset=UTF-8');
  if (count($suggestions) == 0)
    die(json_encode(array(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'no_result'))));
  foreach ($suggestions as $koid => $value) {
    $parents = $ofield->getParents($koid);
    $parents[$koid] = $value;
    $data[] = array('value' => $koid, 'label' => implode(' > ', $parents));
  }

  if ($ret['state'] == 'toomuch')
    $data[] = array('value' => '', 'label' => \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'too_many_results'));
  die(json_encode($data));
}

?>
