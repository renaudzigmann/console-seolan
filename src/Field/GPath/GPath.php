<?php
namespace Seolan\Field\GPath;
/*
  pas de modmap nécessaire
  Structures des données brutes
  latitude (float) longitude (float)
*/
class GPath extends \Seolan\Core\Field\Field {
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
    $this->_options->setOpt('Zoom', 'defaultZoom', 'text', null, '12', $group);
    $this->_options->setOpt('Géométrie carte', 'mapGeometry', 'text', null, '400x300', $group);
    $this->_options->setOpt('Activer l\'indexation spatiale', 'spatialIndex', 'boolean', true, null, $group);
    $this->_options->setOpt('Google Maps key', 'gmaps_key', 'text', null,'', $group);
    $this->_options->setOpt('Afficher la carte dans la fiche', 'displayMap', 'boolean', null,false, $group);
    $this->_options->setOpt('Type de carte', 'mapType', 'list', array('values'=>['osm'],'labels'=>['Open Street Map']),false, $group);
    $this->_options->setOpt('URL tuiles OSM', 'osmTiles', 'text', null,'https://osmtiles.xsalto.com/osm_tiles/{z}/{x}/{y}.png', $group);
    $this->_options->setOpt('URL service geocoding', 'osmGeocodingUrl', 'text', null,'https://nominatim.openstreetmap.org/', $group);
    $this->_options->setOpt('OSM activer le zoom scroll', 'osmScrollWheelZoom', 'boolean', null, false, $group);
  }

  
  function my_browse(&$value,&$options,$genid=false) {
    return parent::my_browse($value,$options,true);
  }
  
  private function addLatLng(&$r) {
    if ($r->raw) {
      $data = str_replace('MULTIPOINT(','',$r->raw);
      $data = str_replace(')','',$data);

      $values = explode(',',$data);
      $r->lat = [];
      $r->lng = [];
      
      foreach($values as $k => $value){
        $value = explode (' ', $value);
        $r->lat[] = $value[0];
        $r->lng[] = $value[1];
      }
    }
    return $r;
  }

  private function getLatLngString(&$r){
    $txt = "";
    for ( $i=0; $i < count($r->lat) && $i < 10; $i++ ){
      $txt .= $r->method == "my_display" ?  $r->lat[$i].', '.$r->lng[$i].'<br>' : sprintf('%.2f, %.2f<br>', $r->lat[$i], $r->lng[$i]);
    }
    if(count($r->lat) > 10){
      $txt .= '...';
    }
    return $txt;
  }

  private function getGPathFieldJS(){
    if ( !defined(TZR_ISSET_GPATH_SCRIPT) ){
      define(TZR_ISSET_GPATH_SCRIPT, true);
      return '<script src="/csx/src/Field/GPath/public/js/GPath.js"></script>';
    }
    return '';
  }
  
  function my_browse_deferred(&$r){
    $this->addLatLng($r);
    $value=&$r->raw;
    $uniqid = $r->varid;
    $r->text = $value;
    if (!empty($value)) {
      $style = '';
      if ($r->lat && $r->lng) {
        $txt = $this->getLatLngString($r);
        $localize=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'view');
	      $closeLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'close'));
	      $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
        
        $points = json_encode(array('lat'=>$r->lat, 'lng'=>$r->lng));
        $r->html = $this->getGPathFieldJS();
        $r->html .= '<div id="'.$uniqid.'">'.$txt;
        if ($txt != ""){
          $r->html .= ' <button class="btn btn-default btn-md btn-inverse viewmapall" id="'.$uniqid.'-loc">'.$localize.'</button>';
          $js  = <<<EOT
          <script>
            TZR.GPath.init({
                varid: '{$uniqid}',
                closeLabel: '{$closeLabel}',
                intable: '{$intable}',
                defaultZoom: '{$this->defaultZoom}',
                defaultLocation: '{$this->defaultLocation}',
                mapGeometry: '{$this->mapGeometry}',
                edit: 0,
                title: '{$title}',
                tilesURL: '{$this->osmTiles}',
                geocodingUrl: '{$this->osmGeocodingUrl}',
                scrollWheelZoom: '{$this->osmScrollWheelZoom}',
                points: '{$points}',
                mode: 'display'
              }
            );
          </script>
EOT;
          $r->html .= $js;
        }
        $r->html .= '</div>';
      }
    }
    return $r;
  }
  
  function my_display_deferred(&$r){
    $uniqid = $r->varid;
    $fieldid = 'cont-'.$r->fielddef->field;
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
	          $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
            $txt = $this->getLatLngString($r);
            $points = json_encode(array('lat'=>$r->lat, 'lng'=>$r->lng));
            $r->html = $this->getGPathFieldJS();
            $r->html .= $txt;
            if ($txt != ""){
              $r->html .= ' <button class="btn btn-default btn-md btn-inverse viewmapall" id="'.$uniqid.'-loc">'.$localize.'</button>';
              $js  = <<<EOT
              <script>
                TZR.GPath.init({
                    varid: '{$fieldid}',
                    closeLabel: '{$closeLabel}',
                    intable: '{$intable}',
                    defaultZoom: '{$this->defaultZoom}',
                    defaultLocation: '{$this->defaultLocation}',
                    mapGeometry: '{$this->mapGeometry}',
                    edit: 0,
                    title: '{$title}',
                    tilesURL: '{$this->osmTiles}',
                    geocodingUrl: '{$this->osmGeocodingUrl}',
                    scrollWheelZoom: '{$this->osmScrollWheelZoom}',
                    points: '{$points}',
                    mode: 'display'
                  }
                );
              </script>
EOT;
              $r->html .= $js;
            }
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
    $fieldid = 'cont-'.$r->fielddef->field;
    $br = '';
    $intable = 0;
    if (isset($options['intable'])){
      $intable = 1;
      $intableValue = $options['intable'];
      $fname = $this->field."[{$intableValue}]";
      $hiddenname = $this->field."_HID[{$intableValue}]";
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
    
    $localizeAll = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'manage_from_map');
    $localize = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'geolocalize');
    $title = htmlspecialchars(addslashes($this->label), ENT_QUOTES);
    $addLatLngLabel = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'add');
    $closeLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'close'));
    $saveLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'ok'));
    $reverseLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'reverse_point_button'));
    $addLabel = htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'add_point_button'));
    $deleteInfos =  htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'delete_infos_method'));
    $elementName =  htmlspecialchars(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'element_name'));

    // Actions par point
    $btn_move = '<span class="btn btn-default btn-md btn-inverse sortablehandler ui-sortable-handle"><span class="glyphicon csico-move" aria-hidden="true"></span></span>';
    $btn_delete = '<button type="button" class="btn btn-default btn-md btn-inverse delete-group"><span class="glyphicon csico-delete"></span></button>';
    $btn_localize_one = '<button type="button" class="btn btn-default btn-md btn-inverse viewmapone" id="'.$uniqid.'-loc">'.$localize.'</button>';
    $actions = $btn_delete.$btn_localize_one.$btn_move;

    $r->htmlmore = <<<EOT
      <li class="form-group latlng"  data-order=x>\
      <span class="title point">{$elementName} x : </span>\
      <label for="uniqidx-lat">Lat</label>&nbsp;<input type="text" name="{$hiddenname}[lat][]" id="uniqidx-lat" value="{$deflat}" size="15">\
      <label for="uniqidx-lng">Lng</label>&nbsp;<input type="text" name="{$hiddenname}[lng][]" id="uniqidx-lng" value="{$deflng}" size="15">\
      {$actions}\
      </li>
EOT;
    $input = "";
    for ( $i=0; $i < count($r->lat); $i++ ){
      $number = $i+1;
      $input .= <<<EOT
      <li class="form-group latlng" data-order={$i}>
      <span class="title point">{$elementName} {$number} : </span>
      <label for="{$uniqid}-{$i}-lat">Lat</label>&nbsp;<input type="text" name="{$hiddenname}[lat][]" id="{$uniqid}-{$i}-lat" value="{$r->lat[$i]}" size="15">
      <label for="{$uniqid}-{$i}-lng">Lng</label>&nbsp;<input type="text" name="{$hiddenname}[lng][]" id="{$uniqid}-{$i}-lng" value="{$r->lng[$i]}" size="15">
      {$actions}
      </li>
EOT;
    }

    $r->html = $this->getGPathFieldJS();
    $r->html .= <<<EOT
      <div id="{$uniqid}">
      <div class="form-group">
      <ul class="fields"> 
        {$input}
      </ul>
      <button type="button" class="btn btn-default addlatlng">{$addLatLngLabel}</button>
      <button type="button" class="btn btn-default viewmapall" id="{$uniqid}-loc">{$localizeAll}</button>
       </div>
      <input type="hidden" name="{$fname}" value="1">
      <input type="hidden" id="{$uniqid}-manual" name="{$hiddenname}[manual]" value="1">
      <input type="hidden" name="{$hiddenname}[oldvalue]" value="{$r->raw}">
      </div>
EOT;
    $js  = <<<EOT
    <script>
      TZR.GPath.htmlDefaultPoint = '{$r->htmlmore}';
      TZR.GPath.init({
          varid: '{$uniqid}',
          fieldName: '{$r->fielddef->field}',
          closeLabel: '{$closeLabel}',
          saveLabel:  '{$saveLabel}',
          reverseLabel:  '{$reverseLabel}',
          addLabel:  '{$addLabel}',
          deleteInfos: '{$deleteInfos}',
          elementName: '{$elementName}',
          intable: '{$intable}',
          intableValue: '{$intableValue}',
          defaultZoom: '{$this->defaultZoom}',
          defaultLocation: '{$this->defaultLocation}',
          mapGeometry: '{$this->mapGeometry}',
          edit: 1,
          title: '{$title}',
          tilesURL: '{$this->osmTiles}',
          geocodingUrl: '{$this->osmGeocodingUrl}',
          scrollWheelZoom: '{$this->osmScrollWheelZoom}'
        }
      );
    </script>
EOT;
    $r->html .= $js;

    if (isset($options['intable'])) { // edition intable, on essaye de ré
      $address = '';
      foreach (explode(',', $this->addrFields) as $addrField) {
        if (preg_match("/'(.*)'/", $addrField, $matches))
          $address .= ' ' . $matches[1];
        elseif ($fields_complement[$addrField] && !\Seolan\Core\Kernel::isAMultipleKoid($fields_complement[$addrField]))
          $address .= ' ' . str_replace("\n", ' ', $fields_complement[$addrField]);
      }
      $r->html .= <<<EOT
      <input type="hidden" id="{$uniqid}-address" value="{$address}">
EOT;
    } else {
      $r->html .= <<<EOT
      <input type="hidden" id="{$uniqid}-addrFields" value="{$this->addrFields}">
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
      $r->raw = ['MULTIPOINT('.$lat.' '.$lng.')', \Seolan\Field\GPath\GPath::$SRID];
    }
    else {
      $points = [];
      for ( $i=0; $i < count($options[$this->field.'_HID']['lat']); $i++ ){
        $lat_HID = $options[$this->field.'_HID']['lat'][$i];
        $lng_HID = $options[$this->field.'_HID']['lng'][$i];
        $lat = ($lat_HID && is_numeric($lat_HID)) ? $lat_HID : 0;
        $lng = ($lng_HID && is_numeric($lng_HID)) ? $lng_HID : 0;
        $points[] = $lat.' '.$lng;
      }

       $r->raw = ['MULTIPOINT('.implode(',',$points).')', \Seolan\Field\GPath\GPath::$SRID];
    }

    return $r;
  }

  // simple map display
  // autoload gmaps script if needed
  function map($r, $geometry=null) {
    if ($geometry)
      list($width, $height, $zoom) = explode('x', $geometry);
    else
      list($width, $height, $zoom) = explode('x', $this->mapGeometry);

    $points = json_encode(array('lat'=>$r->lat, 'lng'=>$r->lng));
    $mapid = $r->fielddef->field;
    $map = $this->getGPathFieldJS();
    $map .= ' <div id="map'.$mapid.'" class="google-map" style="width:'.$width.';height:'.$height.'px"></div>';
    $js  = <<<EOT
    <script>
    TZR.GPath.init({
        varid: '{$mapid}',
        modal: 'none',
        intable: '{$intable}',
        defaultZoom: '{$this->defaultZoom}',
        defaultLocation: '{$this->defaultLocation}',
        mapGeometry: '{$this->mapGeometry}',
        edit: 0,
        tilesURL: '{$this->osmTiles}',
        geocodingUrl: '{$this->osmGeocodingUrl}',
        scrollWheelZoom: '{$this->osmScrollWheelZoom}',
        points: '{$points}',
        mode: 'display'
      }
    );
    </script>
EOT;
      $map .= $js; 
      return $map;
  }

  function sqltype() {
    return 'multipoint';
  }
  public function get_sqlSelectExpr($table=NULL) {
    return  'asText('.$this->table.'.'.$this->field.') as '.$this->field;
  }

}
?>
