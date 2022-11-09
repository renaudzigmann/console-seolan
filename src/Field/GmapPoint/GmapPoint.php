<?php
namespace Seolan\Field\GmapPoint;
/*
Coordonnees google (v3)
compatible \Seolan\Field\GeodesicCoordinates\GeodesicCoordinates
pas de modmap nécessaire
Structures des données brutes
latitude (float);longitude (float);M/A(type);accuracy;UPD
Avec : 
- type M manuel, A Automatique
- accuracy : niveau de precision de la reponse du geocodeur pour les champs automatiques, quand celui ci la fournit
TODO: 
  - infowindow
*/
class GmapPoint extends \Seolan\Core\Field\Field {
  
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  private $googleGeoStatusV3 = array(
    'OK' => 'No errors occurred; the address was successfully parsed and at least one geocode was returned.',
    'ZERO_RESULTS' => 'The geocode was successful but returned no results. This may occur if the geocode was passed a non-existent address or a latlng in a remote location.',
    'OVER_QUERY_LIMIT' => 'Over your quota.',
    'REQUEST_DENIED' => 'Request was denied, generally because of lack of a sensor parameter.',
    'INVALID_REQUEST' => 'The query (address or latlng) is missing.',
    'UNKNOWN_ERROR' => 'Request could not be processed due to a server error. The request may succeed if you try again.'
  );
//   private $googleLocationTypes = array(
//     'APPROXIMATE' => 'The returned result is approximate.',
//     'GEOMETRIC_CENTER' => 'The returned result is the geometric center of a result such a line (e.g. street) or polygon (region).',
//     'RANGE_INTERPOLATED' => 'The returned result reflects an approximation (usually on a road) interpolated between two precise points (such as intersections). Interpolated results are generally returned when rooftop geocodes are unavailable for a street address.',
//     'ROOFTOP' => 'The returned result reflects a precise geocode.'
//   );
  private $googleComponentTypes = array(
    'country' => array('1', 'indicates the national political entity, and is typically the highest order type returned by the Geocoder.'),
    'administrative_area_level_1' => array('2', 'indicates a first-order civil entity below the country level. Within the United States, these administrative levels are states. Not all nations exhibit these administrative levels'),
    'administrative_area_level_2' => array('3', 'indicates a second-order civil entity below the country level. Within the United States, these administrative levels are counties. Not all nations exhibit these administrative levels'),
    'political' => array('4', 'indicates a political entity. Usually, this type indicates a polygon of some civil administration.'),
    'locality' => array('4', 'indicates an incorporated city or town political entity.'),
    'sublocality' => array('4', 'indicates an first-order civil entity below a locality.'),
    'postal_code' => array('5', 'indicates a postal code as used to address postal mail within the country.'),
    'post_box' => array('5', 'indicates a specific postal box.'),
    'route' => array('6', 'indicates a named route (such as "US 101").'),
    'intersection' => array('7', 'indicates a major intersection, usually of two major roads.'),
    'premise' => array('7', 'indicates a named location, usually a building or collection of buildings with a common name'),
    'street_address' => array('8', ' indicates a precise street address.'),
    'street_number' => array('9', 'indicates the precise street number.'),
    'floor' => array('9', 'indicates the floor of a building address.'),
    'room' => array('9', 'indicates the room of a building address.')
  );
  public $displayMap = false;

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Position par défaut', 'defaultLocation', 'text', null, '44.090454,6.22605');
    $this->_options->setOpt('Zoom', 'defaultZoom', 'text', null, '8');
    $this->_options->setOpt('Géométrie carte', 'mapGeometry', 'text', null, '400x300');
    $this->_options->setOpt('Champs de l\'adresse (séparateur ,)', 'addrFields', 'text', array('rows' => 3, 'cols' => 40), '');
    $this->_options->setOpt('Géocodage auto', 'autogc', 'boolean', false);
    $this->_options->setOpt('Précision minimale', 'minaccuracy', 'text', null, '4');
    $this->_options->setOpt('Google Maps key', 'gmaps_key', 'text', null,'');
    $this->_options->setOpt('Afficher la carte dans la fiche', 'displayMap', 'boolean', null,false);
    $this->_options->setOpt('Type de carte', 'mapType', 'list', array('values'=>['gmap', 'osm'],'labels'=>['Google Map', 'Open Street Map']),false);
    $this->_options->setOpt('URL tuiles OSM', 'osmTiles', 'text', null,'https://osmtiles.xsalto.com/osm_tiles/{z}/{x}/{y}.png');
    $this->_options->setOpt('URL service geocoding', 'osmGeocodingUrl', 'text', null,'https://nominatim.openstreetmap.org/');
    $this->_options->setOpt('OSM activer le zoom scroll', 'osmScrollWheelZoom', 'boolean', null,false);
  }

  function my_browse(&$value,&$options,$genid=false) {
    return parent::my_browse($value,$options,true);
  }
  
  function my_browse_deferred(&$r){
    $value=&$r->raw;
    $uniqid = $r->varid;
    $r->text = $value;
    if (!empty($value)) {
      list($r->lat, $r->lng, $r->type, $r->accuracy, $r->upd) = explode(';', $value);
      if ($this->autogc && $r->type == 'A' && $r->accuracy <= $this->minaccuracy)
        $style = 'style="color:#ff0000"';
      else
        $style = '';

      if ($r->lat && $r->lng) {
        $txt = sprintf('%.2f, %.2f', $r->lat, $r->lng);
        $localize=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'comment');
	$title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
        $jsFunction = 'TZR.localize';
        if ($this->mapType === 'osm') {
          $jsFunction = 'TZR.localizeOSM';
        }
        $r->html = <<<EOT
        <span {$style}>{$txt}</span> <a href="#" onclick="{$jsFunction}({id:'{$r->varid}', title:'{$title}', zoom:{$this->defaultZoom}, defaultLocation:'{$r->lat},{$r->lng}', mapGeometry: '{$this->mapGeometry}', tilesURL: '{$this->osmTiles}', geocodingUrl: '{$this->osmGeocodingUrl}', scrollWheelZoom : '{$this->osmScrollWheelZoom}'});return false;" id="{$r->varid}-loc">$localize</a>
EOT;
      }
    }
    return $r;
  }
  
  function my_display(&$value,&$options,$genid=false) {
    return parent::my_display($value,$options,true);
  }

  function my_display_deferred(&$r){
    $uniqid = $r->varid;
    $r->text = $r->raw;
    if (!empty($r->raw)) {
      list($r->lat, $r->lng, $r->type, $r->accuracy, $r->upd) = explode(';', $r->raw);
      $accuraciesLevels = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapaccuracieslevels');
      if ($this->autogc/* && isset($accuraciesLevels[$r->accuracy])*/){
        if ($r->accuracy <= $this->minaccuracy) {
          $accuracyLevel = '<span style="color:#ff0000">'.$accuraciesLevels[$r->accuracy].'</span>';
        } else {
          $accuracyLevel =  $accuraciesLevels[$r->accuracy];
        }
        $accuracyHtml  = '<br><table class="list2"><tr><th style="text-align:center">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapgeocodageauto', 'text').'</th><th>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapaccuracy', 'text').'</th></tr><tr><td>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'yes', 'text').'</td><td>'.$accuracyLevel.'</td></tr></table>';
      } else {
        $accuracyHtml = '';
      }
      if ($r->lat && $r->lng) {
        if ($this->displayMap) {
          $r->html = $this->map($r);
        } else {
          $localize = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmaplocalize', 'text');
          if (\Seolan\Core\Shell::admini_mode()){
            $jsFunction = 'TZR.localize';
            if ($this->mapType === 'osm') {
              $jsFunction = 'TZR.localizeOSM';
            }
	    $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
	    $r->html = $r->lat.','.$r->lng.' <a href="#" onclick="'.$jsFunction.'({id:\''.$r->varid.'\', zoom:\''.$this->defaultZoom.'\', defaultLocation:\''.$r->lat.','.$r->lng.'\', mapGeometry: \''.$this->mapGeometry.'\', title:\''.$title.'\', tilesURL: \''.$this->osmTiles.'\', geocodingUrl: \''.$this->osmGeocodingUrl.'\', scrollWheelZoom : \''.$this->osmScrollWheelZoom.'\'});return false;" id="'.$r->varid.'-loc">'.$localize.'</a>'.$accuracyHtml;
          }else{
            $r->html = $this->map($r);
          }
        }
      }
    }
    return $r;
  }
  
  // edition du champ
  function my_edit(&$value, &$options, &$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options, true);
    $uniqid = $r->varid;
    $br = '';
    $intable = 0;
    if (isset($options['intable'])){
      $intable = 1;
      $fname = $this->field."[{$options['intable']}]";
      $hiddenname = $this->field."_HID[{$options['intable']}]";
      $br = '<br>';
    } elseif (!empty($options['fieldname'])) {
      $fname = $options['fieldname'];
      $hiddenname = $options['fieldname'].'_HID';
    } else {
      $fname = $this->field;
      $hiddenname = $this->field.'_HID';
    }
    $r->raw = $value;
    list($r->lat, $r->lng, $r->type, $r->accuracy, $r->upd) = preg_split('/;/', $value);
    list($deflat, $deflng) = preg_split('/[,;\| ]/', $this->defaultLocation);
    $localize = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmaplocalize', 'text');
    $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
    $jsFunction = 'TZR.localize';
    if ($this->mapType === 'osm') {
      $jsFunction = 'TZR.localizeOSM';
    }
    $r->html = <<<EOT
      <div class="form-group">
      <label for="{$r->varid}-lat">Lat</label>&nbsp;<input type="text" name="{$hiddenname}[lat]" id="{$r->varid}-lat" value="{$r->lat}" size="15">{$br}
      </div>
      <div class="form-group">
      <label for="{$r->varid}-lng">Lng</label>&nbsp;<input type="text" name="{$hiddenname}[lng]" id="{$r->varid}-lng" value="{$r->lng}" size="15">
      </div>
      <a href="#" onclick="{$jsFunction}({intable:{$intable}, id:'{$r->varid}', zoom:{$this->defaultZoom}, defaultLocation:'{$this->defaultLocation}', mapGeometry: '{$this->mapGeometry}', edit:1, title:'{$title}', tilesURL: '{$this->osmTiles}', geocodingUrl: '{$this->osmGeocodingUrl}', scrollWheelZoom : '{$this->osmScrollWheelZoom}'});return false;" id="{$r->varid}-loc">{$localize}</a>
      <input type="hidden" name="{$fname}" value="1">
      <input type="hidden" id="{$r->varid}-manual" name="{$hiddenname}[manual]" value="1">
      <input type="hidden" name="{$hiddenname}[oldvalue]" value="{$r->raw}">
EOT;
    if (isset($options['intable'])) { // edition intable, on essaye de ré
      $address = '';
      foreach (explode(',', $this->addrFields) as $addrField) {
        if (preg_match("/'(.*)'/", $addrField, $matches))
          $address .= ' ' . $matches[1];
        elseif ($fields_complement[$addrField] && !\Seolan\Core\Kernel::isAMultipleKoid($fields_complement[$addrField]))
          $address .= ' ' . str_replace("\n", ' ', $fields_complement[$addrField]);
      }
      $r->html .= <<<EOT
      <input type="hidden" id="{$r->varid}-address" value="{$address}">
EOT;
    } else {
      $r->html .= <<<EOT
      <input type="hidden" id="{$r->varid}-addrFields" value="{$this->addrFields}">
EOT;
    }
    return $r;
  }
    
  // gerer le unchanged / upd 
  // en particuler pour les champs geocodés automatiquement
  function post_edit($value, $options=NULL, &$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options, true);
    if ($value != 1) { // pas par formulaire
      $r->raw = $value;
      return $r;
    }
    if (empty($options['old'])) // table sans tracking
      $options['old'] = $this->display($v = $options[$this->field.'_HID']['oldvalue']);
    if ($options[$this->field.'_HID']['lat'] == $options['old']->lat && $options[$this->field.'_HID']['lng'] == $options['old']->lng)
      $r->raw = $options['old']->raw;
    else {
      $type = $options[$this->field.'_HID']['manual'] == 1 ? 'M' : 'A';
      $r->raw = $options[$this->field.'_HID']['lat'].';'.$options[$this->field.'_HID']['lng'].';'.$type.';;'.date('Y-m-d H:i:s');
    }
    return $r;
  }

  function post_query($o, $options) {
    $value=$o->value;
    if ($value == 'empty'){
      $fn = $o->field;
      $o->rq = "isnull($fn) or  $fn NOT LIKE '%;%;%;%;%' or $fn='' or $fn like ';;_;%;%'";
      return;
    } else if($value == 'manual'){
      $o->op = 'like';
      $o->value = '%;%;M;%;%';
    } else if($value == 'auto'){
      $o->op = 'like';
      $o->value = '%;%;A;%;%';
    } else if (!empty($value)){ // accuracy
      $o->op = 'like';
      $o->value = '%;%;_;'.$o->value.';%';
    } 
    return parent::post_query($o, $options);
  }
  
  function my_query($value,$options=NULL) {
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options,true);
    if (is_array($value)) 
      $value = implode(';', $value);
    if (isset($value)) 
      $t1 = htmlspecialchars($value);
    else 
      $t1=NULL;
    $fname = isset($options['fieldname'])? $options['fieldname']: $this->field;
    $accuraciesLevels = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapaccuracieslevels');
    $t='<input type="hidden" name="'.$fname.'_op" value=""/><select '.(@$options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' id="'.$fname.'" name="'.$fname.'">';
    if (@$options['query_format'] !== \Seolan\Core\Field\Field::QUICKQUERY_FORMAT || !$this->isFilterCompulsory($options)) {
      $t .= '<option '.($t1==null ? 'SELECTED' : '').' value=""></option>';
    }
    $t.='<optgroup label="'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'general').'">';
    $t.='<option '.($t1=='empty'?'SELECTED':'').' value="empty">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapempty').'</option>';
    $t.='<option '.($t1=='manual'?'SELECTED':'').' value="manual">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapmanual').'</option>';
    $t.='<option '.($t1=='auto'?'SELECTED':'').' value="auto">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapgeocodageauto').'</option>';
    $t.='</optgroup>';
    $t.='<optgroup label="'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmapaccuracy').'">';
    foreach($accuraciesLevels as $ac=>$al){
      $t.='<option '.($t1===$ac?'SELECTED':'').' value="'.$ac.'">'.$al.'</option>';
    }
    $t.='<optgroup></SELECT>';
    $r->html=$t;
    return $r;
  }
  
  function quickquery($value, $options=NULL){
    if (empty($value) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_value'])) {
      $value = @$options['fields_complement']['query_comp_field_value'];
    }
    if(empty($options['query_format'])) $options['query_format']=\Seolan\Core\Field\Field::QUICKQUERY_FORMAT;
    $r=$this->my_query($value, $options);
    $r->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    return $r;
  }
  
  // Ecriture dans un csv
  function writeCSV($o,$textsep) {
    return $textsep.$o->raw.$textsep;
  }
  
  function chk(&$message) {
    if (!$this->autogc)
      return;
    $rows = getDB()->fetchAll('select * from '.$this->table.' where lang="'.TZR_DEFAULT_LANG.'"');
    $dataSource = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->table);
    $addrFields = explode(',', $this->addrFields);
    try {
      foreach ($rows as $row) {
        $display = $dataSource->rDisplay('', $row);
        $gmapPoint = $display['o' . $this->field];

        if (empty($gmapPoint->lat) || empty($gmapPoint->lng) || ($display['oUPD']->raw > $gmapPoint->upd && $gmapPoint->type == 'A')) {
          $address = array();
          foreach ($addrFields as $fieldName)
            $address[$fieldName] = $display['o' . $fieldName]->text;
          $location = $this->_geocode( $address );
          sleep(1);
          if (!$location[0])
            \Seolan\Core\Logs::notice(get_class($this), '::chk unable to encode '.$oid);
          else
            getDB()->execute('UPDATE '.$this->table.' SET UPD=UPD, '.$this->field.'="'.$location[1].'" WHERE KOID="'.$row['KOID'].'"');
        }
      }
    } catch (\Exception $e) {
      \Seolan\Core\Logs::critical(get_class($this), '::chk, Geocoder exception ' . $e->getMessage());
    }
    unset($rows);
  }
  
  private function _geocode($address) {
    static $url;
    if (empty($url)) {
      $url = 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false';
      $gmap_key = $this->gmap_key;
      if ($gmap_key)
        $url .= '&key='.$gmap_key;
      $url .= '&address=';
    }
    do {
      $_url = $url . urlencode(implode(' ', $address));
      $resp = json_decode(file_get_contents($_url), true);

      if (isset($this->googleGeoStatusV3[$resp['status']])) {
        switch ($resp['status']) {
          case 'OK':
            if (array_key_exists($resp['results'][0]['types'][0], $this->googleComponentTypes)) {
              list($code, $lbl) = $this->googleComponentTypes[$resp['results'][0]['types'][0]];
            } else {
              $code = 0;
            }
            return array(true,$resp['results'][0]['geometry']['location']['lat'].';'.$resp['results'][0]['geometry']['location']['lng'].';A;'.$code.';'.date('Y-m-d H:i:s'));
          case 'OVER_QUERY_LIMIT':
            throw new \Exception('Over Query Limit');
          case 'ZERO_RESULTS':
          case 'REQUEST_DENIED':
          case 'INVALID_REQUEST':
          case 'UNKNOWN_ERROR':
            return array(false, $this->googleGeoStatusV3[$status].' '.$resp['status']);;
        }
        array_shift($address);
      }
    } while (count($address) > 0);
    //status de la réponse inconnu
    return array(false, 'unknown google status '.$resp['status']);
  }
    
  function sqltype() {
    return 'varchar(124)';
  }
  
  // simple map display
  // autoload gmaps script if needed
  // require generic8.js 
  function map($r, $geometry=null) {
    if ($geometry)
      list($width, $height, $zoom) = explode('x', $geometry);
    else
      list($width, $height, $zoom) = explode('x', $this->mapGeometry);
    if (!$zoom)
      $zoom = $this->defaultZoom;
    if(!strstr($width,'%')){
      $width = $width.'px'; 
    }
    $jsFunction = 'TZR.gmapdisplay';
    if ($this->mapType === 'osm') {
      $jsFunction = 'TZR.osmDisplay';
    }
    $map =<<<EOT
    <div id="map{$r->varid}" class="tzr-gmap" style="width:{$width};height:{$height}px"></div>
      <script type="text/javascript">{$jsFunction}({id:'map{$r->varid}', zoom:{$zoom}, defaultLocation:'{$r->lat},{$r->lng}', tilesURL: '{$this->osmTiles}', geocodingUrl: '{$this->osmGeocodingUrl}', scrollWheelZoom : '{$this->osmScrollWheelZoom}'});</script>
EOT;
    return  $map;
  }
  
  // simple nyromodal map display
  // autoload gmaps script if needed
  // require generic8.js and nyroModal 2.0 + to be include 
  function nyromap($r, $geometry=null, $linkcontent=null) {
    if ($geometry)
      list($width, $height, $zoom) = explode('x', $geometry);
    else
      list($width, $height, $zoom) = explode('x', $this->mapGeometry);
    if (!$zoom)
      $zoom = $this->defaultZoom;
    if (!$linkcontent)
      $linkcontent = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'xgmaplocalize', 'text');
    $map =<<<EOT
    <a href="#nm{$r->varid}" class="nyroMap" id="nyro{$r->varid}" onclick="jQuery(this).nyroModal({minWidth:{$width},height:{$height},callbacks: {
        afterShowCont: function(nm) {
          TZR.gmapdisplay({id:'nmmap{$r->varid}', zoom:{$zoom}, defaultLocation:'{$r->lat},{$r->lng}'});
        },
        afterClose: function(nm) {
          jQuery('#nmmap{$r->varid}').html('');
        }
      }}).nmCall();">{$linkcontent}</a>
    <div id="nm{$r->varid}" class="tzr-gmap" style="display:none"><div id="nmmap{$r->varid}" style="width:{$width}px;height:{$height}px;"></div></div>
EOT;
    return  $map;
  }
}
?>
