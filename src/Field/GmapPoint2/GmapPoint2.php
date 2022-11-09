<?php
namespace Seolan\Field\GmapPoint2;
/*
  Coordonnees google (v3)
  compatible \Seolan\Field\GeodesicCoordinates\GeodesicCoordinates
  pas de modmap nécessaire
  Structures des données brutes
  latitude (float) longitude (float)
*/
class GmapPoint2 extends \Seolan\Core\Field\Field {
  private static $SRID = '4326'; //wgs84
  private $googleGeoStatusV3 = array(
    'OK' => 'No errors occurred; the address was successfully parsed and at least one geocode was returned.',
    'ZERO_RESULTS' => 'The geocode was successful but returned no results. This may occur if the geocode was passed a non-existent address or a latlng in a remote location.',
    'OVER_QUERY_LIMIT' => 'Over your quota.',
    'REQUEST_DENIED' => 'Request was denied, generally because of lack of a sensor parameter.',
    'INVALID_REQUEST' => 'The query (address or latlng) is missing.',
    'UNKNOWN_ERROR' => 'Request could not be processed due to a server error. The request may succeed if you try again.'
  );
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
    $group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','specific');
    $this->_options->setOpt('Position par défaut', 'defaultLocation', 'text', null, '44.090454,6.22605', $group);
    $this->_options->setOpt('Zoom', 'defaultZoom', 'text', null, '8', $group);
    $this->_options->setOpt('Géométrie carte', 'mapGeometry', 'text', null, '400x300', $group);
    $this->_options->setOpt('Activer l\'indexation spatiale', 'spatialIndex', 'boolean', true, null, $group);
    $this->_options->setOpt('Google Maps key', 'gmaps_key', 'text', null,'', $group);
    $this->_options->setOpt('Afficher la carte dans la fiche', 'displayMap', 'boolean', null,false, $group);
    $this->_options->setOpt('Type de carte', 'mapType', 'list', array('values'=>['gmap', 'osm'],'labels'=>['Google Map', 'Open Street Map']),false, $group);
    $this->_options->setOpt('URL tuiles OSM', 'osmTiles', 'text', null,'https://osmtiles.xsalto.com/osm_tiles/{z}/{x}/{y}.png', $group);
    $this->_options->setOpt('URL service geocoding', 'osmGeocodingUrl', 'text', null,'https://nominatim.openstreetmap.org/', $group);
    $this->_options->setOpt('OSM activer le zoom scroll', 'osmScrollWheelZoom', 'boolean', null, false, $group);
  }

  
  function my_browse(&$value,&$options,$genid=false) {
    return parent::my_browse($value,$options,true);
  }

  private function addLatLng(&$r) {
    if ($r->raw) {
      $value = explode (' ', $r->raw);
      $r->lat = $value[0];
      $r->lng = $value[1];
    }
    return $r;
  }
  
  function my_browse_deferred(&$r){
    $this->addLatLng($r);
    $value=&$r->raw;
    $uniqid = $r->varid;
    $r->text = $value;
    if (!empty($value)) {
      $style = '';
      if ($r->lat && $r->lng) {
        $txt = sprintf('%.2f, %.2f', $r->lat, $r->lng);
        $localize=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'view');
	$closeLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'close'));
	$title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
        $jsFunction = 'TZR.localize';
        if ($this->mapType === 'osm') {
          $jsFunction = 'TZR.localizeOSM';
        }
        $r->html = <<<EOT
	  <span {$style}>{$txt}</span><button type="button" class="btn btn-default btn-md btn-inverse" onclick="{$jsFunction}({labels:{close:'{$closeLabel}',save:null}, id:'{$r->varid}', title:'{$title}', zoom:{$this->defaultZoom}, defaultLocation:'{$r->lat},{$r->lng}', mapGeometry: '{$this->mapGeometry}', tilesURL: '{$this->osmTiles}', geocodingUrl: '{$this->osmGeocodingUrl}', scrollWheelZoom : '{$this->osmScrollWheelZoom}'});return false;" id="{$r->varid}-loc">$localize</button>
EOT;
      }
    }
    return $r;
  }
  
  function my_display_deferred(&$r){
    $uniqid = $r->varid;
    $r->text = $r->raw;
    if (!empty($r->raw)) {
      $this->addLatLng($r);
      if ($r->lat && $r->lng) {
        if ($this->displayMap) {
          $r->html = $this->map($r);
        } else {
          $localize = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'view');
	  $closeLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'close'));
          if (\Seolan\Core\Shell::admini_mode()){
            $jsFunction = 'TZR.localize';
            if ($this->mapType === 'osm') {
              $jsFunction = 'TZR.localizeOSM';
            }
	    $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
	    $r->html = $r->lat.','.$r->lng.' <button class="btn btn-default btn-md btn-inverse" onclick="'.$jsFunction.'({labels:{close:\''.$closeLabel.'\',save:null}, id:\''.$r->varid.'\', zoom:\''.$this->defaultZoom.'\', defaultLocation:\''.$r->lat.','.$r->lng.'\', mapGeometry: \''.$this->mapGeometry.'\', title:\''.$title.'\', tilesURL: \''.$this->osmTiles.'\', geocodingUrl: \''.$this->osmGeocodingUrl.'\', scrollWheelZoom : \''.$this->osmScrollWheelZoom.'\'});return false;" id="'.$r->varid.'-loc">'.$localize.'</button>';
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
    $this->addLatLng($r);
    list($deflat, $deflng) = preg_split('/[,;\| ]/', $this->defaultLocation);
    $localize = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'geolocalize');
    $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
    $jsFunction = 'TZR.localize';
    if ($this->mapType === 'osm') {
      $jsFunction = 'TZR.localizeOSM';
    }
    $closeLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'close'));
    $saveLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'ok'));
    $r->html = <<<EOT
      <div class="form-group">
      <label for="{$r->varid}-lat">Lat</label>&nbsp;<input type="text" name="{$hiddenname}[lat]" id="{$r->varid}-lat" value="{$r->lat}" size="15">{$br}
      <label for="{$r->varid}-lng">Lng</label>&nbsp;<input type="text" name="{$hiddenname}[lng]" id="{$r->varid}-lng" value="{$r->lng}" size="15">
      <button type="button" class="btn btn-default btn-md btn-inverse" onclick="{$jsFunction}({labels:{close:'{$closeLabel}',save:'{$saveLabel}'}, intable:{$intable}, id:'{$r->varid}', zoom:{$this->defaultZoom}, defaultLocation:'{$this->defaultLocation}', mapGeometry: '{$this->mapGeometry}', edit:1, title:'{$title}', tilesURL: '{$this->osmTiles}', geocodingUrl: '{$this->osmGeocodingUrl}', scrollWheelZoom : '{$this->osmScrollWheelZoom}'});return false;" id="{$r->varid}-loc">{$localize}</button>
       </div>
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
    $r->func = 'ST_GeomFromText(?, ?)';

    $lat = 0;
    $lng = 0;
    // pas par formulaire
    if ($value != 1) {
      if(!$value || $value == ' ') $value = '0 0';
      $split = preg_split('/[^0-9.-]/', $value, null, PREG_SPLIT_NO_EMPTY);
      $lat = ($split[0] && is_numeric($split[0])) ? $split[0] : 0;
      $lng = ($split[1] && is_numeric($split[1])) ? $split[1] : 0;
    }
    else {
      $lat = ($options[$this->field.'_HID']['lat'] && is_numeric($options[$this->field.'_HID']['lat'])) ? $options[$this->field.'_HID']['lat'] : 0;
      $lng = ($options[$this->field.'_HID']['lng'] && is_numeric($options[$this->field.'_HID']['lng'])) ? $options[$this->field.'_HID']['lng'] : 0;
    }

    $r->raw = ['POINT('.$lat.' '.$lng.')', \Seolan\Field\GmapPoint2\GmapPoint2::$SRID];
    return $r;
  }

  function post_query($o, $options) {
    $value=$o->value;
    if($value) {
      list($minLat, $minLng, $maxLat, $maxLng) = explode(';', $value);
      if(is_numeric($minLat) and is_numeric($minLng) and is_numeric($maxLat) and is_numeric($maxLng)) {
        $o->rq = 'WithIn('.$this->field.', ST_GeomFromText("Polygon(('.$maxLat.' '.$minLng.', '.$maxLat.' '.$maxLng.', '.$minLat.' '.$maxLng.', '.$minLat.' '.$minLng.', '.$maxLat.' '.$minLng.'))", '.\Seolan\Field\GmapPoint2\GmapPoint2::$SRID.'))';
        return;
      }
    }/* else {
      \Seolan\Field\GmapPoint2\GmapPoint2::sqlDistanceSphereFunction();
      $o->rq = 'ST_Distance_Sphere(POINT('.$this->defaultLocation.'), '.$this->field.') < 10000';
      return;
      }*/
    return parent::post_query($o, $options);
  }
  
  function my_query($value,$options=NULL) {
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options,true);

    list($deflat, $deflng) = preg_split('/[,;\| ]/', $this->defaultLocation);
    $localize = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'edit');
    $jsFunction = 'TZR.localize';
    if ($this->mapType === 'osm') {
      $jsFunction = 'TZR.localizeOSM';
    }
    $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
    $r->html = '<button type="button" class="btn btn-default btn-md btn-inverse" onclick="'.$jsFunction.'({id:\''.$r->varid.'\', zoom:\''.$this->defaultZoom.'\', defaultLocation:\''.$this->defaultLocation.'\', mapGeometry: \''.$this->mapGeometry.'\', title:\''.$title.'\', tilesURL: \''.$this->osmTiles.'\', geocodingUrl: \''.$this->osmGeocodingUrl.'\', scrollWheelZoom : \''.$this->osmScrollWheelZoom.'\', bounds: \'1\'}); return false;" id="'.$r->varid.'-loc">'.$localize.'</button>'.
      '<input type="hidden" value="'.$value[0].'" id="'.$r->varid.'-bounds" name="'.$this->field.'">';

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
    // ajout d'un index spatial sur les champs non multivalues
    if(!$this->get_multivalued()) {
      $hasIndex = getDB()->count('SHOW INDEX FROM '.$this->table.' where Column_name="'.$this->field.'"');
      if (!$hasIndex and $this->spatialIndex) {
        $engine = strtoupper( getDB()->fetchOne('SELECT engine FROM information_schema.tables WHERE table_name = "'.$this->table.'" AND table_schema = "'.$GLOBALS['DATABASE_NAME'].'"') );
        $version = getDB()->fetchRow('SHOW VARIABLES LIKE "innodb_version"')['Value'];

        // Conversion en MyISAM pour le support de l'index spatial ?
        /*if ($engine === 'InnoDB' and version_compare($version, '10.2.2') === -1 ) {
          getDB()->execute('ALTER TABLE '.$this->table.' ENGINE=MYISAM');
        }*/

        if (($engine === 'INNODB' and version_compare($version, '10.2.2') === 1 ) or $engine === 'MYISAM') {
          try {
            getDB()->execute('ALTER TABLE '.$this->table.' CHANGE COLUMN `'.$this->field.'` `'.$this->field.'` '.$this->sqltype().' NOT NULL, ADD SPATIAL INDEX(`'.$this->field.'`)');        
          } catch(\Exception $e) {
            \Seolan\Core\Logs::critical('Unable to create spatial index on '.$this->table.'.'.$this->field.' : '.$e->getMessage());
          }
        }
      } elseif ($hasIndex && !$this->spatialIndex) {
        getDB()->execute('DROP INDEX '.$this->field.' ON '.$this->table);
      }
    }
    return parent::chk($msg);
  }

  // deprecated
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
    return 'point not null';
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

  function getQueryText($o){
    list($minLat, $minLng, $maxLat, $maxLng) = explode(';', $o->value);
    return '['.$minLat.';'.$minLng.'] ['.$maxLat.';'.$maxLng.']';
  }

  static function sqlDistanceSphereFunction() {
    getDB()->execute('CREATE FUNCTION IF NOT EXISTS `ST_Distance_Sphere` (point1 POINT, point2 POINT)
    RETURNS FLOAT
    no sql deterministic
    BEGIN
    declare R INTEGER DEFAULT 6371000;
    declare `φ1` float;
    declare `φ2` float;
    declare `Δφ` float;
    declare `Δλ` float;
    declare a float;
    declare c float;
    set `φ1` = radians(y(point1));
    set `φ2` = radians(y(point2));
    set `Δφ` = radians(y(point2) - y(point1));
    set `Δλ` = radians(x(point2) - x(point1));
    set a = sin(`Δφ` / 2) * sin(`Δφ` / 2) + cos(`φ1`) * cos(`φ2`) * sin(`Δλ` / 2) * sin(`Δλ` / 2);
    set c = 2 * atan2(sqrt(a), sqrt(1-a));
    return R * c;
    END;');
  }

  public function get_sqlSelectExpr($table=NULL) {
    return  'concat(st_x('.($table?:$this->table).'.'.$this->field.'), " ",st_y('.($table?:$this->table).'.'.$this->field.')) as '.$this->field;
  }

}
?>
