<?php
namespace Seolan\Field\Table;
/// Champ permettant le stockage de tableaux de donnees
class Table extends \Seolan\Core\Field\Field {
  public $cols=3;
  public $rows=10;
  public $table_labels='';
  public $table_labels_ro=false;
  public $cannot_add_row=false;
  public $cannot_add_col=false;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  
  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','cols'), 'cols', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','rows'), 'rows', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','labels'), 'table_labels', 'ttext');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','labels_ro'), 'table_labels_ro', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','cannot_add_row'), 'cannot_add_row', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','cannot_add_col'), 'cannot_add_col', 'boolean');
  }

  function my_display_deferred(&$r){
    if($r->raw=='') return $r;
    if(!empty($r->options['_charset'])) $charset=$r->options['_charset'];
    else $charset=\Seolan\Core\Lang::getCharset();
    $table = substr($r->raw,0,2)=='a:' ? unserialize($r->raw) : json_decode($r->raw, true);

    if(!is_array($table)) return $r;

    $tableid=uniqid('v');
    if($this->fcount>100) $retval='<div style="width:'.$this->fcount.'px; overflow-x:auto;">';
    else $retval='<div>';
    $retval.='<table class="tzr-tablefield cv8_tablesorter" id="'.$tableid.'">';
   
    if($this->table_labels_ro) $labels=explode(',',$this->table_labels);
    else $labels=$table['_labels'];
    $retval.='<thead><tr>';
    foreach($labels as $j=>$c){
      if(empty($c)) $c='&nbsp;';
      $retval.='<th>'.$c.'</th>';
    }
    $retval.='</tr></thead>';

    if($this->table_has_rlabels){
      if($this->table_rlabels_ro) $rlabels=explode(',',$this->table_rlabels);
      else $rlabels=$table['_rlabels'];
    }
    unset($table['_labels']);
    unset($table['_rlabels']);

    $nblines=0; 
    $retval.='<tbody>';
    foreach($table as $i => &$l) {
      $l = $this->checkLine($l);
      $line='';
      $line.='<tr>';
      if($this->table_has_rlabels) $line.='<th>'.$rlabels[$i].'</th>';
      foreach($l as $j=>$c) {
	if(empty($c) && $c !== "0") $c='&nbsp;';
	$line.='<td>'.$c.'</td>';
	if(isEmail($c)) $r->emails.=';'.emailClean($c);
      }
      $line.='</tr>';
      $retval.=$line;
    }
    $retval.='</tbody></table></div>';
    $retval.='<script type="text/javascript">TZR.XTableSorter("'.$tableid.'");</script>';
    $r->html=$retval;
    $r->alltable=$table;
    $r->thead=$labels;
    $r->trhead=$rlabels;
    $r->rows=count($table);
    $r->cols=count($table[0]);
    return $r;
  }

  /// Generation du champ pour modification du fichier
  function my_edit(&$value, &$options, &$fields_complement = NULL) {
    $r = $this->_newXFieldVal($options);

    $table = substr($value,0,2)=='a:' ? unserialize($value) : json_decode($value, true);
    // Pas de valeur, création d'un tableau vide et des entetes par defaut
    if (empty($table['_labels'])) {
      // Entetes de colonne
      if (!empty($this->table_labels)) {
	// Entetes specifiées
        $table['_labels'] = explode(',', $this->table_labels);
        while(count($table['_labels']) < $this->cols) {
          $table['_labels'][] = "";
      }
      } else {
        // Entetes par defaut
        $table['_labels'] = array();
        for ($i = 0; $i < $this->cols; $i++) {
          $table['_labels'][] = 'Column ' . ($i + 1);
	}
      }
    }
    $cols = count($table['_labels']);
    $rows = $this->rows;
    if (empty($table)) {
      // Création du tableau de donnée
      for ($j = 0; $j < $rows; $j++) {
        for ($i = 0; $i < $cols; $i++) {
          $table[$j][$i] = '';
	}
      }
    }
    else {
      foreach ($table as $k => &$v) {
        foreach ($v as $k1 => &$v1) {
          $v1 = htmlspecialchars($v1);
        }
      }
    }

    $tableLabels = $table['_labels'];
    unset($table['_labels']);

    // Si aucune ligne, on en créée une vide
    if (empty($table)) {
      foreach ($tableLabels as $i => $c)
        $table[0][$i] = '';
    }

    $configTemplate = defined('TZR_SLICKGRID_TEMPLATE') ? TZR_SLICKGRID_TEMPLATE : $GLOBALS['TEMPLATES_DIR'].'Pack/SlickGrid/public/default_config.html';

    $fname = (isset($options['fieldname']) ? $options['fieldname'] : $this->field);
    $xt = new \Seolan\Core\Template($configTemplate);
    $theTplData = null;
    $theRawData = array(
        'table' => $table,
        'tableLabels' => $tableLabels,
        'table_labels_ro' => $this->table_labels_ro,
        'cannot_add_row' => $this->cannot_add_row,
        'cannot_add_col' => $this->cannot_add_col,
        'width' => $this->fcount,
        'fname' => $fname
    );

    $r->html = $xt->parse($theTplData, $theRawData);
    $r->raw = $value;
    $r->table = $table;
    $r->thead = $tableLabels;
    $r->rows = count($table);
    $r->cols = count($table[0]);
    return $r;
  }

  /// 'normalise' une ligne
  function checkLine($l){
    if (!empty($this->cols) && ($nbcol = count($l)) < $this->cols){
      for($nc = $nbcol; $nc < $this->cols; $nc++){
	$l[$nc] = '';
      }
    }
    return $l;
  }
  /// Retourne le contenu d'une cellule (row et col peuvent etre un numero ou une entete (attention à la langue))
  function getCellValue(&$r,$row,$col){
    if(!is_numeric($row)) $row=array_search($row,$r['trhead']);
    if(!is_numeric($col)) $col=array_search($col,$r['thead']);
    if($row!==false && $col!==false) return $r->table[$row][$col];
  }

  function sqltype() {
    return 'text';
  }

  // Traitement apres saisie
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    if(is_array($value)) {
      $table=$value; // _labels[c], [_rlabels][r], [r][c]
      // On supprime toutes les lignes en FIN (et seulement en fin) de tableau qui sont vides
      $row=count($table)-2; 
      if(!empty($table['_rlabels'])) $row--;
      for($i=$row;$i>=0;$i--){
	// un label de ligne renseigné; si rlabels_ro un label est toujours renseigné et les lignes gardées
	if(!empty($table['_rlabels'][$i])) break;
	// au moins une colonne
	foreach($table[$i] as $k)
	  if(!empty($k)) break 2;
	unset($table[$i],$table['_rlabels'][$i]);
      }
      $r->raw=json_encode($table);  //enregister le tableau en json
      if (isset($options['old']) && ($r->raw != $options['old']->raw)){
	$this->trace($options['old'], $r, '['.$options['old']->html.'] -> ['.$this->display($r->raw, $options)->html.']');
      }
    } elseif (false !== json_decode($value) xor $value == json_decode(false)) {
      $r->raw = $value;
    }
    return $r;
  }

  function my_query($value,$options=NULL) {
    $r=$this->_newXFieldVal($options);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $r->html= '<input name="'.$fname.'" size="20" value="'.$value.'">';  
    return $r;
  }

  function my_quickquery($value,$options=NULL) {
    $r = $this->_newXFieldVal($options);
    if (is_array($value))
      $value = implode($this->separator[0], $value);
    $r->html= '<input '.($this->isFilterCompulsory($options) ? 'required' : '').' type="text" name="'.$this->field.'" size="20" value="'.$value.'"><input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    return $r;
  }

  /// Recupere le type du champ dans un webservice (name : type xml, descr : description du type pour l'ajour d'une type complexe)
  function getSoapType(){
    return array('name'=>'tns:table',
		 'descr'=>array('stringArray'=>array(array('name'=>'value','minOccurs'=>0,'maxOccurs'=>'unbounded','type'=>'xsd:string')),
				'table'=>array(array('name'=>'label','minOccurs'=>0,'maxOccurs'=>1,'type'=>'tns:stringArray'),
					       array('name'=>'rlabel','minOccurs'=>0,'maxOccurs'=>1,'type'=>'tns:stringArray'),
					       array('name'=>'row','minOccurs'=>0,'maxOccurs'=>'unbounded','type'=>'tns:stringArray'))));
  }
  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    return array('label'=>$r->thead,'rlabel'=>$r->trhead,'row'=>$r->table);
  }
}

