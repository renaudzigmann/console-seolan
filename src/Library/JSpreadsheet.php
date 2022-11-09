<?php
namespace Seolan\Library;
/**
* traitements pour jspreadsheet
* -> correspondances de types
* -> analyse d'une requete de mise à jour
*/
use \Seolan\Core\{Logs,Param};
use \Seolan\Module\Table\Table;
use \Seolan\Core\Field\Value as FieldValue;
class JSpreadsheet {
  protected const FIELD_MAPPING = [
    '\Seolan\Field\Chrono\Chrono'                              =>'',
    '\Seolan\Field\Chrono2\Chrono2'                            =>'',
    '\Seolan\Field\Boolean\Boolean'                            =>'checkbox',
    '\Seolan\Field\Color\Color'                                =>'color',
    '\Seolan\Field\Date\Date'                                  =>'calendar',
    '\Seolan\Field\Time\Time'                                  =>'',
    '\Seolan\Field\Url\Url'                                    =>'text',
    '\Seolan\Field\Link\Link'                                  =>'',
    '\Seolan\Field\User\User'                                  =>'...',
    '\Seolan\Field\Thesaurus\Thesaurus'                        =>'...',
    '\Seolan\Field\Password\Password'                          =>'',
    '\Seolan\Field\StringSet\StringSet'                        =>'...',
    '\Seolan\Field\File\File'                                  =>'',
    '\Seolan\Field\File\ConfidentialData'                      =>'',
    '\Seolan\Field\Document\Document'                          =>'',
    '\Seolan\Field\ExternalImage\ExternalImage'                =>'',
    '\Seolan\Field\Image\Image'                                =>'image',
    '\Seolan\Field\DateTime\DateTime'                          =>'',
    '\Seolan\Field\Timestamp\Timestamp'                        =>'',
    '\Seolan\Field\Order\Order'                                =>'',
    '\Seolan\Field\Real\Real'                                  =>'numeric',
    '\Seolan\Field\Text\Text'                                  =>'text',
    '\Seolan\Field\Expression\Expression'                      =>'',
    '\Seolan\Field\ShortText\ShortText'                        =>'text',
    '\Seolan\Field\RichText\RichText'                          =>'html',
    '\Seolan\Field\DataSource\DataSource'                      =>'', // on peut avoir la liste
    '\Seolan\Field\Table\Table'                                =>'', // idem
    '\Seolan\Field\Query\Query'                                =>'text',
    '\Seolan\Field\Module\Module'                              =>'',
    '\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates'    =>'',
    '\Seolan\Field\DataSourceField\DataSourceField'            =>'',
    '\Seolan\Field\Rating\Rating'                              =>'numeric',
    '\Seolan\Field\Options\Options'                            =>'',
    '\Seolan\Field\DependentLink\DependentLink'                =>'',
    '\Seolan\Field\GmapPoint\GmapPoint'                        =>'',
    '\Seolan\Field\GmapPoint2\GmapPoint2'                      =>'',
    '\Seolan\Field\Serialize\Serialize'                        =>'',
    '\Seolan\Field\Country\Country'                            =>'...',
    '\Seolan\Field\Interval\Interval'                          =>'',
    '\Seolan\Field\Tag\Tag'                                    =>'',
    '\Seolan\Field\Lang\Lang'                                  =>'dropdown', // todo
    '\Seolan\Field\Icon\Icon'                                  =>'',
    '\Seolan\Field\Label\Label'                                =>'',
    '\Seolan\Field\Phone\Phone'                                =>'text',
    '\Seolan\Field\MarkdownText\MarkdownText'                  =>'text'];

  static public function getInstance(){
    return new JSpreadsheet();
  }

  static public function getTypeFromTzrType(\Seolan\Core\Field\Field $fielddef):string{

    Logs::debug(__METHOD__."{$fielddef->ftype}");
    return static::FIELD_MAPPING[$fielddef->ftype];

  }
  /**
   * transformation des prop. champ en prop. colonne JSpreadsheet
   */
  public function getColumnDesc($fielddef, $defaults){
    // infos de base communes
    $type = static::FIELD_MAPPING[$fielddef->ftype];
    $encodedLabel = addslashes($fielddef->get_label());
    if (empty($type)){
      $type = $default['type']??'text';
    }
    $width = $defaults['width']??'null';
    // voir jsuites.js masks, tokens
    $align = $decimalSign = $source = $mask = 'null';
    $align = "'left'"; // on préfère à gauche par défaut
    $mv = $autocomp = $stripHtml = 'null';
    $ro = null;
    // sous types et types qu'on veut en ro (pas d'éditeur dispo / fait)
    if (!empty($type)){
      if ($fielddef instanceof \Seolan\Field\StringSet\StringSet){
	$type = 'dropdown';
	// voir doc on peut aussi mettre une url : [{id:'...',name:'...'},
	$source = '';
	foreach($fielddef->value_set as $soid=>$slabel){
	  if (!empty($source))
	    $source.=',';
	  $slabel = addslashes($slabel);
	  $source.="{id:'{$soid}',name:'{$slabel}'}";
	}
	$source = "[{$source}]";
      } elseif ($fielddef instanceof \Seolan\Field\Link\Link){
	$type = 'dropdown'; // existe aussi autocomplete qui a l'air de faire pareil ?
	// à voir complètement
	$foo = null;
	$fielddef->autocomplete = false;
	$fielddef->autocomplete_limit = 9999999;
	$fv = $fielddef->edit($foo);
	$items = [];
	foreach($fv->oidcollection as $i=>$oid){
	  $name = addslashes($fv->collection[$i]);
	  $items[] = "{id:'{$oid}',name:'{$name}'}";
	}
	$source = '['.implode(',', $items).']';
	$mv = $fielddef->multivalued?'true':'false';
	$autocomp = 'true';
      } elseif ($fielddef->ftype == '\Seolan\Field\Real\Real'){
	$ent = '0';
	$align = '\'right\'';
	if ($fielddef->decimal != 0){
	  $dec = ','.implode('', array_fill(0,$fielddef->decimal,'0'));
	  $decimalSign="','";
	} else {
	  $dec = '';
	}
	$mask = "'{$ent}{$dec}'";
      } elseif ($fielddef instanceof \Seolan\Field\Image\Image && !$fielddef->multivalued){
	$type = 'text';
	$ro = 'true';
	$stripHtml = 'false';
      }
    } else {
      $ro = true;
      \Seolan\Core\Logs::notice(__METHOD__,"unknown mapping for {$fielddef->ftype}");
    }
    if ($ro == null)
      $ro = $fielddef->readonly?'true':'false';
    $classes = '\'customjspreadsheetcell-'.rewriteToAscii($fielddef->fgroup).'\'';
    $desc = <<<EOF
      name:'{$fielddef->field}',
      type:'{$type}',
      title:'{$encodedLabel}',
      width:'{$width}',
      mask:{$mask},
      decimal:{$decimalSign},
      readOnly:{$ro},
      // cas des dropdown
      multiple:{$mv},
      source:{$source},
      autocomplete:{$autocomp},
      stripHTML:{$stripHtml},
      align:{$align},
      classes:{$classes}
EOF;
    return $desc;
  }
  /**
   * groupes et colspan correspondants
   */
  public function getGroups(array $fields):array{
    $groups = [];
    foreach($fields as $field){
      if (empty($field->fgroup))
	$g = ' ';
      else
	$g = $field->fgroup;
      if (!isset($groups[$g]))
	$groups[$g] = ['title'=>$g,
		       'fields'=>[],
		       'colspan'=>0];
      $groups[$g]['fields'][] = $field->field;
      $groups[$g]['colspan']++;
    }
    if (count($groups) == 1)
      return [];
    return $groups;
  }
  /**
   * mise en forme d'une requete de mise à jour
   * -> si koid like '__newline__:[0-9]{1,4}' ou vide => insertion
   * -> sinon => update
   * ! on reçoit tout le tableau et les oid des lignes modifiées
   */
  public function procEditAnalyse(Param $p, Table $targetModule):array{

    $data = $p->get('spreadsheetData');
    $updated = $p->get('_updated');

    $procEditAr = $procInsertAr = [
      'editfields'=>[],
      'count'=>0
    ];

    $procEditAr['oid'] = [];

    foreach($data as &$line){
      if (preg_match('/_newlineoid_:[0-9]{1,4}/', $line['oid']))
        $line['oid']='';
      if (isset($line['oid']) && $line['oid']!=='' && in_array($line['oid'], $updated)){
	       $procEditAr['oid'][] = $line['oid'];
	        $this->procEditLineAnalyse($targetModule, $line, $procEditAr);
      } elseif(empty($line['oid'])) {
	       $this->procEditLineAnalyse($targetModule, $line, $procInsertAr);
      }
    }

    return [$procEditAr, $procInsertAr];

  }
  /**
   * mise en forme d'une ligne de mise à jour
   */
  protected function procEditLineAnalyse(Table $targetModule, array $line, array &$goodAr){
    foreach($line as $k=>$v){
      if (!$targetModule->xset->fieldExists($k))
	       continue;
      $fielddef = $targetModule->xset->getField($k);
      if ($fielddef->readonly)
	        continue;
      if (!isset($goodAr[$k])){
	       $goodAr[$k] = [];
	       $goodAr['editfields'][] = $k;
      }
      if ($fielddef instanceof \Seolan\Field\Link\Link && $fielddef->multivalued){
	       $v = array_filter(explode(';', $v));
      }
      $goodAr[$k][] = $v;
    }
    $goodAr['count']++;
  }
  /**
   * passer une valeur raw/html à une valeur accéptée par JSpreadsheet
   * retourne la valeur et év. des metas
   */
  public function preEdit(FieldValue $fv):array{
    $value = '';
    $meta  = [];
    switch($fv->fielddef->ftype){
      case '\Seolan\Field\Boolean\Boolean':
	if ($fv->raw == $fv->fielddef->TRUE)
	  $value = 'true';
	else
	  $value = 'false';
	break;
      case '\Seolan\Field\StringSet\StringSet':
	$value = "'$fv->raw'";
	break;
      case '\Seolan\Field\Link\Link':
	// à voir les MV ? mettre un array ?
	if ($fv->fielddef->multivalued){
	  $value = '"'.implode(';', array_filter(explode('||', $fv->raw))).'"';
	} else {
	  $value = "'$fv->raw'";
	}
	break;
      case '\Seolan\Field\Date\Date':
	$value = !empty($fv->raw && $fv->raw != '0000-00-00')?"'$fv->raw'":"''";
	break;
      case '\Seolan\Field\Image\Image':
	if (!$fv->fielddef->multivalued){
	  // les seules données supportées : data:image voir jexcel.js ln ~8485
	  // à terme, passer l'url et via fonction js la lire et faire le base 64
	  // dans l'immédiat : url façcon l'exemple +/- type dédié ?
	  // $imgbinary = fread(($fd = fopen($thumb, "r")), filesize($fv->filename));
	  // fclose($fd);
          // $value = 'data:image/' . $fv->mime . ';base64,' . base64_encode($imgbinary);
	  $value = "<img src='{$fv->resizer}'>";
	} else
	  $value = addslashes($fv->text);
	$value = "\"{$value}\"";
	break;
      default:
	$value = '\''.addslashes($fv->text).'\'';
    }
    if (isset($fv->fielddef->edit_format)
	&& !empty($fv->fielddef->edit_format)
	&& $fv->fielddef->edit_format != '(.*)'){
      $meta[]='edit_format:'.'"'.addslashes($fv->fielddef->edit_format).'"';
    }
    return ['value'=>$value, 'meta'=>'{'.implode(',',$meta).'}'];
  }
}
