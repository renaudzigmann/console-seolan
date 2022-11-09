<?php
namespace Seolan\Module\Map;
/**
module geocodage et 'cartographie' 
l'EF contient les paramètres de gestion des EF utilisant des points geographiques
fonction d'aide a la saisie via la carte
fonctions de geocodage
fonctions de restitution google map
*/
define('EARTHRAY',6378.137);
class Map extends \Seolan\Module\Table\Table{
  static $defaultSRS = 'MAPSRS:EPSG4326'; 
  var $googleGeoCodeUrl = 'http://maps.google.fr/maps/geo?';
  var $googleGeoCodeEncoding = 'UTF-8 ? a voir ';
  /* la pertinence d'une adresse */
  var $googleGeoAccuracy = array('0'=>array(0, 'Unknown location.'),
				 '1'=>array(1, 'Country level accuracy'),
				 '2'=>array(2, 'Region (state, province, prefecture, etc.) level accuracy'),
				 '3'=>array(3, 'Sub-region (county, municipality, etc.) level accuracy.e'),
				 '4'=>array(4, 'Town (city, village) level accuracy'),
				 '5'=>array(5, 'Post code (zip code) level accuracy'),
				 '6'=>array(6, 'Street level accuracy'),
				 '7'=>array(7, 'Intersection level accuracy'),
				 '8'=>array(8, 'Address level accuracy'),
				 '9'=>array(9, 'Permise level accuracy')
				 );
				 
  /* les status http geocodage google */
  var $googleGeoStatus = array('200'=>array(200, 'G_GEO_SUCCESS (200)', 'No errors occurred; the address was successfully parsed and its geocode has been returned. (Since 2.55)'),
			       '400'=>array(400, 'G_GEO_BAD_REQUEST (400)', 'A directions request could not be successfully parsed. (Since 2.81)'),
			       '500'=>array(500, 'G_GEO_SERVER_ERROR (500)', 'A geocoding or directions request could not be successfully processed, yet the exact reason for the failure is not known. (Since 2.55)'),
			       '601'=>array(601, 'G_GEO_MISSING_QUERY (601)', 'The HTTP q parameter was either missing or had no value. For geocoding requests, this means that an empty address was specified as input. For directions requests, this means that no query was specified in the input. (Since 2.81)'),
			       '602'=>array(602, 'GEO_UNKNOWN_ADDRESS (602)', 'No corresponding geographic location could be found for the specified address. This may be due to the fact that the address is relatively new, or it may be incorrect. (Since 2.55)'),
			       '603'=>array(603, 'G_GEO_UNAVAILABLE_ADDRESS (603)', 'The geocode for the given address or the route for the given directions query cannot be returned due to legal or contractual reasons. (Since 2.55)'),
			       '604'=>array(604, 'G_GEO_UNKNOWN_DIRECTIONS (604)', 'The GDirections object could not compute directions between the points mentioned in the query. This is usually because there is no route available between the two points, or because we do not have data for routing in that region. (Since 2.81)'),
			       '610'=>array(610, 'G_GEO_BAD_KEY (610)', 'The given key is either invalid or does not match the domain for which it was given. (Since 2.55)'),
			       '620'=>array(620, 'G_GEO_TOO_MANY_QUERIES (620)', '')
			       );


  function __contruct($ar=NULL){
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Map_Map');
  }
  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['preConvertCoords']=array('ro', 'rw', 'rwv', 'admin');
    $g['procConvertCoords']=array('ro', 'rw', 'rwv', 'admin');
    $g['convertCoords']=array('none', 'ro', 'rw', 'rwv', 'admin');
    $g['geoSearch']=array('ro', 'rw','rwv','admin');
    $g['simpleLayerKML']=array('none', 'list', 'ro', 'rw', 'rwv', 'admin');
    $g['rawLayer']=array('none', 'list', 'ro', 'rw', 'rwv', 'admin');
    $g['geoCodeAuto']=array('admin');
    $g['procStat']=array('ro', 'rw', 'rwv', 'admin');
    $g['simpleMap'] = array('none', 'list', 'ro', 'rw', 'rwv', 'admin');
    $g['simpleMap2'] = array('none', 'list', 'ro', 'rw', 'rwv', 'admin');
    $g['xPoiList'] = array('ro', 'rw', 'rwv', 'admin');
    $g['xDisplayMarker'] = array('none', 'ro', 'rw', 'rwv', 'admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  // paramètres pour une carte simple
  //
  function simpleMap($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $tpl = $p->get('tplentry');
    $nocache = $p->get('nocache');
    if (isset($nocache)){
      $nocache = '&nocache='.$nocache;
    }
    $layeroid = $p->get('layeroid'); // peut être un tableau ?
    
    if (!is_array($layeroid)){
      $layeroid = array($layeroid);
    }
    $jslayeroid = '[';
    $sep = '';
    foreach($layeroid as $il=>$oidl){
      $jslayeroid .= $sep.'\''.$oidl.'\'';
      if ($sep == '')
	$sep = ',';
    }
    $jslayeroid .= ']';
    $modmap = $this->xset->rdisplay($layeroid[0]);
    $jsstyles = '';
    if ($loadstyles){
      $styles = array();
      if (isset($modmap['oricon1']->raw)){
	$styles[$modmap['oid']] = array('icon'=>array('size'=>$modmap['oricon1']->getImageSize('fullwidth').'X'.$modmap['oricon1']->getImageSize('fullheight'),
						      'url'=>$fullself.$modmap['oricon1']->url.'?foo=.png')
					);
	if ($modmap['orshad1']->html){
	  $styles[$modmap['oid']]['shadow'] = array('size'=>$modmap['orshad1']->getImageSize('fullwidth').'X'.$modmap['orshad1']->getImageSize('fullheight'),
						    'url'=>$fullself.$modmap['orshad1']->url.'?foo=.png');
	}
      }
      for($is=1; $is < count($layeroid); $is++){
	$modmapi = $this->xset->rdisplay($layeroid[$is]);
	if (isset($modmapi['oricon1']->raw)){
	  $styles[$modmapi['oid']] = array('icon'=>array('size'=>$modmapi['oricon1']->getImageSize('fullwidth').'X'.$modmapi['oricon1']->getImageSize('fullheight'),
							 'url'=>$fullself.$modmapi['oricon1']->url.'?foo=.png')
					   );
	  if ($modmapi['orshad1']->html){
	    $styles[$modmapi['oid']]['shadow'] = array('size'=>$modmapi['orshad1']->getImageSize('fullwidth').'X'.$modmapi['orshad1']->getImageSize('fullheight'),
						       'url'=>$fullself.$modmapi['orshad']->url.'?foo=.png');
	  }
	}
      }
      $jsstyles .="styles:["; $sep = '';
      foreach($styles as $sid=>$style){
	$jsstyles .= "\n{$sep}{id:'{$sid}', icon:{url:'{$style['icon']['url']}', size:'{$style['icon']['size']}'}";
	$sep = ',';
	if (isset($style['shadow'])){
	  $jsstyles .= ", shadow:{url:'{$style['shadow']['url']}', size:'{$style['shadow']['size']}'}";
	}
	$jsstyles .= "}";
      }
      $jsstyles .="],";
    }
    list($initialLat, $initialLng) = explode(';', $modmap['oelatlng']->raw);
    $initialZoom = $modmap['oezoom']->raw;
    if (empty($modmap['orcicon']->url)){
      $clusterIco =  TZR_SHARE_URL.'xmodmap/cluster.png';
      $clusterIcoShadow =  TZR_SHARE_URL.'xmodmap/cluster_shadow.png';
      $clusterIcoSize = 'width : 30, height : 51';
    } else {
      $clusterIco = $modmap['orcicon']->url.'?foo=.png';
      $clusterIcoSize = "width : {$modmap['orcicon']->getImageSize('fullwidth')}, height : {$modmap['orcicon']->getImageSize('fullheight')}";
      if (!empty($modmap['orshad']->url)){
	$clusterIcoShadow =  $modmap['orshad']->url;
      }else{
	$clusterIcoShadow =  '';
      }
    }
    $glang = $this->getGLang();
    $fullself = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true);
    $kmlurl = "{$fullself}&moid={$this->_moid}&function=simpleLayerKML{$nocache}&kml=0&_silent=1";
    $js = '<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;hl='.$glang.'&amp;oe=utf-8&amp;key='.$this->key.'" language="javascript" type="text/javascript"></script>';
    $js .= '<script src="/tzr/templates/xmodmap/Clusterer2.js" type="text/javascript"></script>';
    $js .= '<script src="/tzr/templates/xmodmap/mapmanager.js" type="text/javascript"></script>';
    $js .= "<script type=\"text/javascript\">
         mapmngt.setOptions({defaultlayeroid:'{$layeroid[0]}',layeroids:{$jslayeroid},
               initialLat:{$initialLat},
               initialLng:{$initialLng},
               initialZoom:{$initialZoom},
               gridSize:20,
	       kmlurl:'{$kmlurl}',
	       clusterIco:{url:'{$clusterIco}', {$clusterIcoSize}},
               clusterIcoShadow:{url:'{$clusterIcoShadow}',width:56, height:51}
               });
         </script>";

    $res = array('js'=>$js,
		 'key'=>$this->key,
		 'kmlurl'=>$kmlurl,
		 'onload'=>'pageLoad()',
		 'onunload'=>'GUnload()');
    return \Seolan\Core\Shell::toScreen1($tpl, $res);
  }
  // paramètres pour une carte simple
  //
  function simpleMap2($ar){
    $p = new \Seolan\Core\Param($ar, array('loadstyles'=>0));
    $tpl = $p->get('tplentry');
    $nocache = $p->get('nocache');
    $loadstyles = $p->get('loadstyles');
    if (isset($nocache)){
      $nocache = '&nocache='.$nocache;
    }
    $layeroid = $p->get('layeroid'); // peut être un tableau ?
    
    if (!is_array($layeroid)){
      $layeroid = array($layeroid);
    }
    $jslayeroid = '[';
    $sep = '';
    foreach($layeroid as $il=>$oidl){
      $jslayeroid .= $sep.'\''.$oidl.'\'';
      if ($sep == '')
	$sep = ',';
    }
    $jslayeroid .= ']';
    $modmap = $this->xset->rdisplay($layeroid[0]);
    // on lit les informations de styles
    if ($loadstyles){
      $styles = array();
      if (isset($modmap['oricon1']->raw)){
        $styles[$modmap['oid']] = array('icon'=>array('size'=>$modmap['oricon1']->getImageSize('fullwidth').'X'.$modmap['oricon1']->getImageSize('fullheight'),
                                                      'url'=>$fullself.$modmap['oricon1']->url.'?foo=.png')
                                        );
        if ($modmap['orshad1']->html){
          $styles[$modmap['oid']]['shadow'] = array('size'=>$modmap['orshad1']->getImageSize('fullwidth').'X'.$modmap['orshad1']->getImageSize('fullheight'),
                                                    'url'=>$fullself.$modmap['orshad1']->url.'?foo=.png');
        }
      }
      for($is=1; $is < count($layeroid); $is++){
        $modmapi = $this->xset->rdisplay($layeroid[$is]);
        if (isset($modmapi['oricon1']->raw)){
          $styles[$modmapi['oid']] = array('icon'=>array('size'=>$modmapi['oricon1']->getImageSize('fullwidth').'X'.$modmapi['oricon1']->getImageSize('fullheight'),
                                                         'url'=>$fullself.$modmapi['oricon1']->url.'?foo=.png')
                                           );
          if ($modmapi['orshad1']->html){
            $styles[$modmapi['oid']]['shadow'] = array('size'=>$modmapi['orshad1']->getImageSize('fullwidth').'X'.$modmapi['orshad1']->getImageSize('fullheight'),
                                                       'url'=>$fullself.$modmapi['orshad']->url.'?foo=.png');
          }
        }
      }
      $jsstyles .="styles:["; $sep = '';
      foreach($styles as $sid=>$style){
        $jsstyles .= "\n{$sep}{id:'{$sid}', icon:{url:'{$style['icon']['url']}', size:'{$style['icon']['size']}'}";
        $sep = ',';
        if (isset($style['shadow'])){
          $jsstyles .= ", shadow:{url:'{$style['shadow']['url']}', size:'{$style['shadow']['size']}'}";
        }
        $jsstyles .= "}";
      }
      $jsstyles .="],"; 
    }
    list($initialLat, $initialLng) = explode(';', $modmap['oelatlng']->raw);
    $initialZoom = $modmap['oezoom']->raw;
    if (empty($modmap['orcicon']->url)){
      $clusterIco =  TZR_SHARE_URL.'xmodmap/cluster.png';
      $clusterIcoShadow =  TZR_SHARE_URL.'xmodmap/cluster_shadow.png';
      $clusterIcoSize = 'width : 30, height : 51';
    } else {
      $clusterIco = $modmap['orcicon']->url.'?foo=.png';
      $clusterIcoSize = "width : {$modmap['orcicon']->getImageSize('fullwidth')}, height : {$modmap['orcicon']->getImageSize('fullheight')}";
      if (!empty($modmap['orshad']->url)){
	$clusterIcoShadow =  $modmap['orshad']->url;
      }else{
	$clusterIcoShadow =  '';//TZR_SHARE_URL.'xmodmap/cluster_shadow.png';
      }
    }
    $glang = $this->getGLang();
    $fullself = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true);
    $kmlurl = "{$fullself}&moid={$this->_moid}&function=simpleLayerKML{$nocache}&kml=1";
    $layerurl = "{$fullself}&moid={$this->_moid}&function=rawLayer&_silent=1&placestemplate=xmodmap/markerlist.xml{$nocache}";
    $displayurl = "{$fullself}&moid={$this->_moid}&function=xDisplayMarker{$nocache}";
    $js = '<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;hl='.$glang.'&amp;oe=utf-8&amp;key='.$this->key.'" language="javascript" type="text/javascript"></script>';
    $js .= '<script src="/tzr/templates/xmodmap/Clusterer2.js" type="text/javascript"></script>';
    $js .= '<script src="/tzr/templates/xmodmap/mapmanager2.js" type="text/javascript"></script>';
    $js .= "<script type=\"text/javascript\">
         mapmngt.setOptions(
              {defaultlayeroid:'{$layeroid[0]}',layeroids:{$jslayeroid},
              {$jsstyles}
               initialLat:{$initialLat},
               initialLng:{$initialLng},
               initialZoom:{$initialZoom},
               gridSize:20,
               infoWindowWidth:200,
               infoWindowHeight:200,
               minMarkersPerCluster:5,
               maxVisibleMarkers:1,
               defaultMaxLinesPerInfoBox:5,
               maxLinesPerInfoBox:5,
               displayurl:'{$displayurl}',
	       kmlurl:'{$kmlurl}',
               layerurl:'{$layerurl}',
               clusterAction:'zoom', // zoom | pop
	       clusterIco:{url:'{$clusterIco}', {$clusterIcoSize}},
               clusterIcoShadow:{url:'{$clusterIcoShadow}',width:56, height:51}
               });
         </script>";
    $res = array('js'=>$js,
		 'key'=>$this->key,
		 'kmlurl'=>$kmlurl,
		 'onload'=>'pageLoad()',
		 'onunload'=>'GUnload()');
    return \Seolan\Core\Shell::toScreen1($tpl, $res);
  }
  // lecture d'un point d'une carte
  //
  function xDisplayMarker($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'br', 'markertemplate'=>'xmodmap/displaymarker.xml'));;
    $layeroid = $p->get('layeroid');
    $oid = $p->get('oid');
    $template = $p->get('markertemplate');
    $tpl = $p->get('tplentry');
    $modmap = $this->xset->rdisplay($layeroid);
    if (!is_array($modmap)){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this)."::simpleLayerKML layer not found {$layeroid}");
      die('Could not find layeroid '.$layeroid);
    }
    $mod = \Seolan\Core\Module\Module::objectFactory($modmap['ormoid']->raw);
    if (!empty($modmap['orfilter']->raw)){
      $select = $mod->xset->select_query().' and '.$modmap['orfilter']->raw;
    } else {
      $select = $mod->xset->select_query();
    }
    if (!is_array($oid)){
      $select .= ' and '.$mod->table.'.KOID = \''.$oid.'\'';
    }  else {
      foreach($oid as $foo=>&$aoid){
	$aoid = '\''.$aoid.'\'';
      }
      $select .= ' and '.$mod->table.'.KOID in (' . implode(",", $oid) . ')';
    }

    $br = $mod->browse(array('_options'=>array(),
			     'tplentry'=>TZR_RETURN_DATA,
			     'selectedfields'=>'all',
			     'first'=>0,
			     'select'=>$select,
			     'pagesize'=>9999));
    $places = array();
    $fname = $modmap['ofname']->raw;
    foreach($br['lines_oid'] as $i=>$lineoid){
      $coords = $br['lines_o'.$fname][$i]->raw;
      list($lat, $lng, $type, $acc, $upd) = explode(';', $coords);
      $aplace = $this->getPlace($i, $mod, $br, $modmap);
      if (!empty($lat) && !empty($lng)){
	$aplace['valid'] = true;
      } else {
	$aplace['valid'] = false;
      }
      $places[] = array('oid'=>$lineoid,
			'latlng'=>$lng.','.$lat,
			'name'=>$aplace['name'],
			'valid'=>$aplace['valid'],
			'description'=>$aplace['descr']);
      unset($aplace);
    }
    $ret = array('places'=>$places,
		 'browse'=>$br,
		 'modmap'=>$modmap);
    $xt = new \Seolan\Core\Template('file://'.TZR_SHARE_DIR.$template);
    if (file_exists(TZR_SHARE_DIR.$template)){
      $xt = new \Seolan\Core\Template('file://'.TZR_SHARE_DIR.$template);
    } else if (file_exists($GLOBALS['TEMPLATES_DIR'].$template)){
      $xt = new \Seolan\Core\Template('file://'.$GLOBALS['TEMPLATES_DIR'].$template);
    } else {
      echo('-');
      exit(0);
    }
    $labels=$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
    $xt->set_glob(array('labels'=>&$labels));
    $r3=array();
    // avoir vide ...    die($tpl);
    $tpldata['br']=$ret;
    $content=$xt->parse($tpldata,$r3,NULL);
    header('Content-type: text/xml');
    header('Content-disposition: inline');
    echo($content);
    exit(0);          
  }
  // initialisation des propriétés
  // -> ! table est _MODMAP
  //
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','key'), 'key', 'text',             NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','geocodeurl'), 'geocodeurl', 'text',     NULL,NULL,$alabel);
  }
  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $myoid=@$_REQUEST['oid'];
    $user = \Seolan\Core\User::get_user();
    if (\Seolan\Core\Shell::_function() == 'edit' || \Seolan\Core\Shell::_function() == 'display'){
      $o1=new \Seolan\Core\Module\Action($this,'procStat', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','statistics'),
			    'class='.$myclass.'&amp;moid='.$moid.'&_function=procStat&template=Module/Map.statistics.html&tplentry=br&oid='.$myoid);
      $o1->homepageable=$o1->quicklinkable=$o1->menuable=true;
      $o1->group='edit';
      $my['procstats']=$o1;
    }
    if (\Seolan\Core\Shell::_function() == 'procStat'){
      $br=\Seolan\Core\Shell::from_screen('br');
      $o1=new \Seolan\Core\Module\Action($this,'display',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','display','text'),
			    'class='.$myclass.'&amp;moid='.$moid.
			    '&_function=display&template=Module/Table.view.html&tplentry=br&oid='.$myoid);
      $o1->homepageable=$o1->quicklinkable=$o1->menuable=true;
      $o1->setToolbar('Seolan_Core_General','display');
      $my['display'] = $o1;
    }
    if (defined('TZR_GDAL_PATH') && file_exists(TZR_GDAL_PATH.'gdaltransform')){
      $o1=new \Seolan\Core\Module\Action($this,'convertCoords', 'Convertions',
			    'class='.$myclass.'&amp;moid='.$moid.'&_function=preConvertCoords&template=Module/Map.convert.html&tplentry=br');
      $o1->homepageable=$o1->quicklinkable=$o1->menuable=true;
      $o1->group='more';
      $my['convert']=$o1;
    }
  }
  // liste de points selon certains critères
  //
  function xPoiList($ar){
    $p = new \Seolan\Core\Param($ar, array());
    \Seolan\Core\Logs::notice(get_class($this), get_class($this)."::xPoiList");
    $layeroid = $p->get('layeroid');
    $query = $p->get('query');
    $value = $p->get('value');
    $modmap = $this->xset->rdisplay($layeroid);
    $mod = \Seolan\Core\Module\Module::objectFactory($modmap['ormoid']->raw);
    switch($query){
    case 'manual':
      $select = $mod->xset->select_query(array('cond'=>array($modmap['ofname']->raw=>array('like', '%;%;M;%;%'))));
      $select2 = str_replace($mod->xset->table.'.*', $mod->xset->table.'.KOID ', $select);
      break;
    case 'auto':
      $select = $mod->xset->select_query(array('cond'=>array($modmap['ofname']->raw=>array('like', '%;%;A;%;%'))));
      $select2 = str_replace($mod->xset->table.'.*', $mod->xset->table.'.KOID ', $select);
      break;
    case 'accuracy':
      $select = $mod->xset->select_query(array('cond'=>array($modmap['ofname']->raw=>array('like', '%;%;A;'.$value.';%'))));
      $select2 = str_replace($mod->xset->table.'.*', $mod->xset->table.'.KOID ', $select);
      break;
      break;
    }
    if (!empty($modmap['orfilter']->raw)){
      $select2 .= ' and '.$modmap['orfilter']->raw;
    }
    \Seolan\Core\Logs::notice(get_class($this), get_class($this)."::xPoiList $select2");
    $rs = getDB()->select($select2);
    $ret = array();
    while($ors = $rs->fetch()){
      $ret[] = $ors['KOID'];
    }
    die(json_encode($ret));
  }
  // statistiques sur le geocodage
  //
  function procStat($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $tpl = $p->get('tplentry');
    $layeroid = $p->get('oid');
    $stats = array('total'=>0, 'empty'=>0, 'types'=>array('A'=>0, 'M'=>0), 'accuracy'=>array());
    $modmap = $this->xset->rdisplay($layeroid);
    // total
    if (empty($modmap['ormoid']->raw)){
      $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$modmap['oftable']->raw);
      $select = $xset->select_query();
    } else {
      $mod = \Seolan\Core\Module\Module::objectFactory($modmap['ormoid']->raw);
      $xset = $mod->xset;
      if (!empty($modmap['orfilter']->raw)){
	$select = $mod->xset->select_query().' and '.$modmap['orfilter']->raw;
      } else {
	$select = $mod->xset->select_query();
      }
    }
    $select2 = str_replace($xset->getTable().'.*', 'ifnull(count(*), 0) as count', $select);
    $rs = getDB()->select($select2);
    $ors = $rs->fetch();
    $stats['total'] = $ors['count'];
    $accuraciesLevels = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracieslevels');
    foreach($accuraciesLevels as $ac=>$al){
      $stats['accuracy'][$ac] = array('label'=>$al, 'count'=>0);
    }
    // par type, accuracy, un peu brutal ...
    $fname = $modmap['ofname']->raw;
    $select2 = str_replace($xset->getTable().'.*', $xset->getTable().'.'.$fname.' as rawcoords', $select);
    $rs = getDB()->select($select2);
    while($rs && $ors=$rs->fetch()){
      if (empty($ors['rawcoords'])){
	$stats['empty']+=1;
      } else {
	list($lat, $lng, $type, $accuracy, $upd) = \Seolan\Field\GeodesicCoordinates\GeodesicCoordinates::explode($ors['rawcoords']);
	if (empty($lat) || empty($lng)){
	  $stats['empty']+=1;
	} 
	if ($type == 'M'){
	  $stats['types']['M']+=1;
	} else {
	  if (empty($accuracy)){
	    $accuracy = 'N/A';
	  }
	  $stats['types']['A']+=1;
	  if (!isset($stats['accuracy'][$accuracy])){
	      $stats['accuracy'][$accuracy] = array('label'=>$this->accuracyLabel($accuracy), 'count'=>0);
	  }
	  $stats['accuracy'][$accuracy]['count']+=1;
	}
      }
    }
    // fin pour le moment
    $res = array();
    $res['modmap'] = $modmap;
    $res['statistics'] = $stats; 
    \Seolan\Core\Shell::toScreen1($tpl, $res);
  }
  // generation d'une couche en KML
  // -> icone des marker de la couche
  // -> champ titre de la couche
  // -> champs de description 
  //
  function simpleLayerKML($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'br', 'kml'=>0));
    $tpl = $p->get('tplentry');
    $layeroid = $p->get('layeroid');
    $template = $p->get('template');
    $kml = $p->get('kml');
    // lecture de la description de la carte
    $modmap = $this->xset->rdisplay($layeroid);
    if (!is_array($modmap)){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this)."::simpleLayerKML layer not found {$layeroid}");
      return array('Could not find layeroid '.$layeroid);
    }
    // browse sur le module associé avec la clause where specifiée
    //
    $mod = \Seolan\Core\Module\Module::objectFactory($modmap['ormoid']->raw);
    if (!empty($modmap['orfilter']->raw)){
      $select = $mod->xset->select_query().' and '.$modmap['orfilter']->raw;
    } else {
      $select = '';
    }
    $br = $mod->browse(array('_options'=>array(),
			     'tplentry'=>TZR_RETURN_DATA,
			     'selectedfields'=>'all',
			     'first'=>0,
			     'select'=>$select,
			     'pagesize'=>9999));
    $places = array();
    $fname = $modmap['ofname']->raw;
    foreach($br['lines_oid'] as $i=>$lineoid){
      $coords = $br['lines_o'.$fname][$i]->raw;
      list($lat, $lng, $type, $acc, $upd) = explode(';', $coords);
      $aplace = $this->getPlace($i, $mod, $br, $modmap);
      if (!empty($lat) && !empty($lng)){
	$aplace['valid'] = true;
      } else {
	$aplace['valid'] = false;
      }
      $places[] = array('oid'=>$lineoid,
			'latlng'=>$lng.','.$lat,
			'name'=>$aplace['name'],
			'valid'=>$aplace['valid'],
			'description'=>$aplace['descr']);
      unset($aplace);
    }
    $ret = array('places'=>$places,
		 'browse'=>$br,
		 'modmap'=>$modmap);
    if (!empty($kml)){
      $xt = new \Seolan\Core\Template('file://'.TZR_SHARE_DIR.'xmodmap/simplekml.xml');
      $r3=array();
      $tpldata['br']=$ret;
      $content=$xt->parse($tpldata,$r3,NULL);
      $filename = cleanFileName($modmap['name'].'.kml');
      header('Content-Name: '.$filename);
      header('Content-type: application/vnd.google-earth.kml+xml');
      header('Content-disposition: attachment; filename='.$filename);
      echo($content);
      exit(0);      
    } else if ($tpl == TZR_RETURN_DATA){
      return $ret;
    } else if (empty($template)){
      // on genere le kml avec xmodmap/simplelayerkml.xml
      $xt = new \Seolan\Core\Template('file://'.TZR_SHARE_DIR.'xmodmap/simplelayerkml.xml');
      $r3=array();
      $tpldata['br']=$ret;
      $content=$xt->parse($tpldata,$r3,NULL);
      header('Content-type: text/xml');
      header('Content-disposition: inline');
      echo($content);
      exit(0);      
    } else {
      \Seolan\Core\Shell::toScreen1('br', $ret);
    }
  }
  // generation d'une couche en tzr kml
  // -> icone des marker de la couche
  // -> champ titre de la couche
  // -> options
  //
  function rawLayer($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'br'));
    $tpl = $p->get('tplentry');
    $layeroid = $p->get('layeroid');
    $placestemplate = $p->get('placestemplate');
    // lecture de la description de la carte
    $modmap = $this->xset->rdisplay($layeroid);
    if (!is_array($modmap)){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this)."::simpleLayerKML layer not found {$layeroid}");
      return  array(
		    'places'=>NULL,
		    'modmap'=>NULL
		    );
    }
    // select sur le module associé avec la clause where specifiée
    //
    if (empty($modmap['ormoid']->raw)){
      \Seolan\Core\Logs::critical(get_class($this), '::rawLayer empty display module, layeroid : '.$layeroid);
      return  array(
		    'places'=>NULL,
		    'modmap'=>NULL
		    );
    }
    $mod = \Seolan\Core\Module\Module::objectFactory($modmap['ormoid']->raw);
    if (!empty($modmap['orfilter']->raw)){
      $select = $mod->xset->select_query().' and '.$modmap['orfilter']->raw;
    } else {
      $select = $mod->xset->select_query();
    }
    $places = array();
    $fname = $modmap['ofname']->raw;
    $ftitle = $modmap['ortitlef']->raw;
    $lrs = getDB()->select($select);
    $i = 0;
    while($lrs && $lors = $lrs->fetch()){
      $coords = $lors[$fname];
      list($lat, $lng, $type, $acc, $upd) = explode(';', $coords);
      if (!empty($lat) && !empty($lng)){
	$valid = true;
      } else {
	$valid = false;
      }
      $places[] = array('oid'=>$lors['KOID'],
			'latlng'=>$lng.','.$lat,
			'name'=>$lors[$ftitle],
			'valid'=>$valid,
			'ors'=>$lors
			);
      unset($lors);
    }
    $ret = array(
		 'places'=>$places,
		 'modmap'=>$modmap
		 );
    if (!empty($placestemplate)){
      // on genere le kml avec xmodmap/simplelayerkml.xml
      $xt = new \Seolan\Core\Template('file://'.TZR_SHARE_DIR.$placestemplate);
      $r3=array();
      $tpldata['br']=$ret;
      $content=$xt->parse($tpldata,$r3,NULL);
      header('Content-type: text/xml');
      header('Content-disposition: inline');
      echo($content);
      exit(0);      
    } else if ($tpl == TZR_RETURN_DATA){
      return $ret;
    } else {
      // on genere le kml avec xmodmap/simplelayerkml.xml
      $xt = new \Seolan\Core\Template('file://'.TZR_SHARE_DIR.'xmodmap/simplelayerkml.xml');
      $r3=array();
      $tpldata['br']=$ret;
      $content=$xt->parse($tpldata,$r3,NULL);
      header('Content-type: text/xml');
      header('Content-disposition: inline');
      echo($content);
      exit(0);      
    }
  }
  // retourne le nom et la description d'un point
  // -> par defaut
  //
  protected function &getPlace($i, &$mod, &$br, &$modmap){
    // titre 
    $title = $br['lines_o'.$modmap['ortitlef']->raw][$i]->html;
    $desc = '';
    $df = explode(' ', $modmap['ordescrf']->raw);
    $iurl = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName();
    foreach($df as $foo=>$fn){
      
      $o = $br['lines_o'.$fn][$i];
      $v = trim($o->html);
      if (!empty($v)){
	if ($o->fielddef->ftype == '\Seolan\Field\Image\Image'){
	  $desc .= "<br><img src='{$iurl}{$o->resizer}&geometry=X100%3E'/>";
	}else{
	  $desc .= $v.'<br>';
	}
      }
    }
    // description
    return array('name'=>$title,
		 'descr'=>$desc
		 );
  }
  //enregistrement d'une valeur
  //-> verifier les champs
  //-> mettre a jour le champ 
  //
  function procEdit($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $ftable = $p->get('ftable');
    $fname = $p->get('fname');
    $fgcaddr = $p->get('fgcaddr');
    $fgccntr = $p->get('fgccntr');
    $fgctown = $p->get('fgctown');
    $fgcauto_HID = $p->get('fgcauto_HID');
    // si module, trouver la table 

    // verifier que fname, fgcaddr (liste), fgccntr et fgctown existent bien pour la table
    // mettre a jour les informations dans le champs ftable fname (si il est du bon type)
    $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ftable);
    $fd = $xset->getField($fname);
    if ($fd->ftype != '\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates'){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this)."procEdit error $fname not XGeodesic type");
      return; // message etc ...
    }
    // vérifier les champs passes

    if (!$xset->fieldExists($fname)){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this)."procEdit error $fname not exists");
      return; // message etc ...
    }
    if (isset($fgcauto_HID['val'])){
      if((!empty($fgccntr) && !$xset->fieldExists($fgccntr))||
	 (!empty($fgctown) && !$xset->fieldExists($fgctown))){
	\Seolan\Core\Logs::critical(get_class($this), get_class($this)."procEdit error some fields do not exists in table");
	return; // message etc
      }
      // les champs addresse
      $fsaddr = explode(' ', $fgcaddr);
      foreach($fsaddr as $foo=>$faddr){
	if (!empty($faddr)){
	  if (!$xset->fieldExists($faddr)){
	    \Seolan\Core\Logs::critical(get_class($this), get_class($this)."procEdit error ");
	    return; // message etc ...
	  }
	}
      }
    }
    // procEdit
    parent::procEdit($ar);
    // mettre a jour les options du champ avec l'oid de ce module map
    $xseta=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ftable);
    $xseta->procEditField(array('field'=>$fname, 'tplentry'=>TZR_RETURN_DATA, '_todo'=>'save', 'options'=>array('gmoid'=>$this->_moid)));
  }
  // retourne une url pour ouvrir une page de visualisation 
  //
  function getGeoViewUrl($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $table = $p->get('table');
    $fieldname = $p->get('fieldname');
    $moid = $this->_moid;
    $templates = 'googlesearch.html';
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true)."skip=1&moid={$moid}&class=\Seolan\Module\Map\Map&table={$table}&field={$fieldname}&provider=google&function=geoSearch&template=Module/Map.geosearch.html&readonly=readonly&tplentry=br";
  }
  // retourne une url pour ouvrir une page de recherche pour ce champ
  //
  function getGeoSearchUrl($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $table = $p->get('table');
    $fieldname = $p->get('fieldname');
    $moid = $this->_moid;
    $templates = 'googlesearch.html';
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true)."skip=1&&moid={$moid}&class=\Seolan\Module\Map\Map&table={$table}&field={$fieldname}&provider=google&function=geoSearch&template=Module/Map.geosearch.html&tplentry=br";
  }
  // recherche une entree pour cette table et ce champ
  // -> si plusieurs couches, on suppose que la fonc en edition est commune
  //
  function &getFieldSetup($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $ftable = $p->get('ftable');
    $fieldname = $p->get('fname');
    $rs = getDB()->select("select * from {$this->table} where fname='{$fieldname}' and ftable='{$ftable}'");
    if ($ors = $rs->fetch()){
      list ($lat, $lng) = explode(';', $ors['elatlng']);
      $ors['elat']=$lat;
      $ors['elng']=$lng;
      $ors['autogc']=($ors['fgcauto']==2)?false:true;
      $ors['layerscount'] = $rs->rowCount();
      $ors['minaccuracy'] = 4;
      return $ors;
    } else {
      // \Seolan\Core\Shell print2log ....
      return array();
    }
  }
  function geoSearch($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'br'));
    $tpl = $p->get('tplentry');
    $fs = $this->getFieldSetup($ar);
    $ar['fs'] = $fs;
    // affichage des couches (?)
    $provider = $p->get('provider');
    if ($provider == 'google')
      $res = $this->googleSearch($ar);
    else 
      $res = NULL;
    $res['fs'] = $fs;
    return \Seolan\Core\Shell::toScreen1($tpl, $res);
  }
  // affiche uen carte de recherche pour la table et le champ donne
  // -> google ici
  //
  function googleSearch($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'br'));
    $fs = $p->get('fs');
    $tpl = $p->get('tplentry');
    $ftable = $p->get('ftable');
    $fname = $p->get('fname');
    $fid = $p->get('fid');
    $flatlng = $p->get('flatlng');
    $flatlng = trim($flatlng);
    $foptions = $p->get('foptions');
    $readonly = $p->get('readonly');
    $oid = $p->get('oid');
    // recherche des infos table/champ dans l'EF
    //    $fs = $this->getFieldSetupEdit($ar);
    // position initiale reçue sinon defaut
    if (!empty($flatlng) && $flatlng!=';'){
      list($plat, $plng) = explode(';', $flatlng);
      $mlat = $fs['elat'];
      $mlng = $fs['elng'];
      $newpoint = false;
    } elseif (!empty($oid)) {
      /* to do editer l'occurence ? */
    } else {
      $plat = $mlat = $fs['elat'];
      $plng = $mlng = $fs['elng'];
      $newpoint = true;
    }
    // lire le champ ? ... la cle etc
    $res = array('google'=>array('key'=>$this->key),
		 'map'=>array('zoom'=>$fs['ezoom'], 
			      'readonly'=>$readonly,
			      'lng'=>$mlat, 
			      'lat'=>$mlng),
		 'point'=>array('newpoint'=>$newpoint,
				'lat'=>$plat,
				'lng'=>$plng,
				'ftable'=>$ftable,
				'fid'=>$fid,
				'fname'=>$fname,
				'foptions'=>$foptions
				),
		 'maplabels'=>array('normalview'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','normalview', 'text'),
				    'physicalview'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','physicalview', 'text'),
				    'satelliteview'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','sattelitview', 'text'),
				    'hybridview'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','hybridview', 'text'),
				    'search'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'query', 'text'),
				    'validate'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'save', 'text'),
				    'quit'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'close', 'text'),
				    'notfound'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'unknownaddress', 'text'),
				    'centerview'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'centerview', 'text')
				    )
		 );
    return $res;
  }
  // deamon
  // -> faire le geocodage auto
  protected function _daemon($period='any') {
    return true;
  }  
  // traitement des geocodages automatiques
  // -> pour chaque carte, si geocodeauto, faire les recherches
  //
  function geoCodeAuto($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $done = array();
    $cr ="";
    \Seolan\Core\Logs::notice(get_class($this),get_class($this)."::geoCodeAuto start");
    $rs = getDB()->select("select ftable, fname, KOID from {$this->table} where fgcauto=1");
    $cr .= "{$rs->rowCount()} map(s) to scan";
    \Seolan\Core\Logs::update('geocoding', NULL, 'start '.$cr);
    while($rs && $ors = $rs->fetch()){
      if (!isset($done[$ors['ftable'].$ors['fname']])){
	$cr = "scanning : {$ors['ftable']} {$ors['fname']}\n";
	$done[$ors['ftable'].$ors['fname']] = array();
	$modmap = $this->xset->rdisplay($ors['KOID']);
	$r = $this->geoCodeXSet($modmap);
	$cr .= "\t rows : {$r['nt']} querie(s) : {$r['nq']} omitted : {$r['nqn']}\n";
	\Seolan\Core\Logs::update('geocoding', NULL, $cr);
      }
    }
    \Seolan\Core\Logs::notice(get_class($this),get_class($this)."::geoCodeAuto end");
    // compte rendu ?
    \Seolan\Core\Logs::update('geocoding', NULL, 'end');
  }
  // traitement de geocodage d'une table donnee
  // -> si fiche modifiee, refaire le geocodage
  //
  function geoCodeXSet($modmap){
    // Cas ou l'on passe directement un oid
    if(!is_array($modmap)) $modmap=$this->xset->rdisplay($modmap);
    $nq = 0; $nqn = 0;
    $fname = $modmap['ofname']->raw;
    $ftable = $modmap['oftable']->raw;
    \Seolan\Core\Logs::notice(get_class($this),get_class($this)."::geoCodeAutoXSet start");
    $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ftable);
    $rs = getDB()->select($xset->select_query(array()));
    $tests = 0;
    $delay = 0;
    while($rs && ($ors = $rs->fetch())){
      
      $pending = true;
      
      while($pending){
	$foo = array();
	$ofc = $xset->getField($fname);
	$fv = $ofc->edit($ors[$fname], $foo);
	if($fv->type == 'A' && ($fv->accuracy == '' || $fv->accuracy == 'N/A' || empty($fv->upd) || $ors['UPD'] > $fv->upd || 
				$modmap['oUPD']->raw > $fv->upd)){
	  $gar = array();
	  $addressfields = explode(' ', $modmap['ofgcaddr']->raw);
	  foreach($addressfields as $foo=>$afn){
	    $afv = trim($ors[$afn]);
	    if (!empty($afv))
	      $gar['address'][] = array('value'=>$afv, 'retry'=>true, 'default'=>NULL);
	  }
	  $gar['city'] = array('value'=>$ors[$modmap['ofgctown']->raw], 'retry'=>true, 'default'=>NULL);
	  $gar['zipcode'] = array('value'=>$ors[$modmap['ofgczipc']->raw], 'retry'=>false, 'default'=>NULL);
	  $gar['country'] = array('value'=>$ors[$modmap['ofgccntr']->raw], 'retry'=>false, 'default'=>NULL);
	  $nq += 1;
	  $rg = $this->googleGeoCode($gar, 0, $ors['KOID']);
	  list ($ok, $mess, $accuracy, $coords, $maddress, $retry, $query) = $rg;
	  \Seolan\Core\Logs::notice(get_class($this).get_class($this)."::googleGeoCode $ok $mess $retry $query");
	  $tests += 1;
	  if ($ok){
	    $pending = false;
	    $fres = array('latlng'=>$coords[1].';'.$coords[0],
			  'autogc'=>1,
			  'accuracy'=>$accuracy);
	    $xset->procEdit(array('_options'=>array('local'=>true),
				  'tplentry'=>TZR_RETURN_DATA,
				  $fname=>$fres,
				  'oid'=>$ors['KOID']));
	  } else if ($mess == 'too many query'){
	    $delay += 100000; // pending reste a true
	    \Seolan\Core\Logs::notice(get_class($this),get_class($this)."::geoCodeAutoXSet delaying $delay $nq");
	    \Seolan\Core\Logs::update('geocoding', NULL, "incr delaying $delay $nq");
	  }else{
	    $pending =false;
	    $fres = array('latlng'=>'',
			  'autogc'=>1,
			  'accuracy'=>0);
	    $xset->procEdit(array('_options'=>array('local'=>true),
				  'tplentry'=>TZR_RETURN_DATA,
				  $fname=>$fres,
				  'oid'=>$ors['KOID']));
	    
	  }
	} else { 
	  \Seolan\Core\Logs::notice(get_class($this),get_class($this)."::geCodeXSet up to date ".$ors['KOID']);
	  $nqn += 1;
	  $pending = false;
	  $delay = 0;
	}
	usleep($delay);
	\Seolan\Core\Logs::notice('\Seolan\Module\Map\Map', "geoCodeXSet query $nq ommitted $nqn of {$rs->rowCount()}");
      }
    }
    \Seolan\Core\Logs::notice(get_class($this),get_class($this)."::geoCodeAutoXSet end");
    return array('nq'=>$nq, 'nqn'=>$nqn, 'nt'=>$rs->rowCount());
  }
  // requete geocodage google
  // 
  function googleGeoCode($addressFields, $retry=0, $oid=NULL /*pour trace*/){
    $q = '';
    foreach($addressFields['address'] as $foo=>$fd){
      $this->googleAppendtoQuery($fd, $q, ' ');
    }
    $otherfields = array('zipcode', 'city', 'country');
    foreach($otherfields as $foo=>$fn){
      if (!empty($addressFields[$fn])){
      $this->googleAppendtoQuery($addressFields[$fn], $q, ',');
      }
    }
    \Seolan\Core\Logs::notice(get_class($this), get_class($this)."::googleGeoCode $retry $oid start, query : $q");

    $url = $this->geocodeurl.'key='.$this->key.'&output=kml&q='.urlencode($q);

    $gres = file_get_contents($url, NULL);

    if ($this->googleGeoCodeEncoding != 'UTF-8')
      $gres = utf8_encode($gres);
    
    $r = $this->decodeGoogleGeocodeXml($gres);

    list($ok, $mess, $accuracy, $coordinates, $matchedaddress) = $r;
    if (!$ok){
      $na = count($addressFields['address']);
      if ($na >= 1 && $mess != 'too many query'){
	array_shift($addressFields['address']);
	return $this->googleGeocode($addressFields, $retry+1, $oid);
      }
    } else {
      array_push($r, $retry, $q);
    }
    \Seolan\Core\Logs::notice(get_class($this).get_class($this)."::googleGeoCode $retry $oid end");
    return $r;
  }

  /// formatage d'une requete google geocodage
  function googleAppendToQuery($fd, &$q, $sep=''){
    $f = (object)$fd;
    $fv = '';
    if (empty($f->value) && !empty($f->default))
      $fv = $f->default;
    else
      $fv = $f->value;
    if (!empty($fv)){
      $fv = preg_replace('/,/', '', $fv);
      if (empty($q))
	$q = $fv;
      else
	$q .= $sep.$fv;
    }
  }

  /// decode une reponse geocode xml/kml
  function decodeGoogleGeocodeXml(&$xml){
    $d = new \DOMDocument();
    $d->loadXML($xml); // ?
    $xq = new \DOMXPath($d);
    $kmlns = 'http://earth.google.com/kml/2.0';
    $oasins = 'urn:oasis:names:tc:ciq:xsdschema:xAL:2.0';
    $xq->registerNameSpace('kml', $kmlns);
    $xq->registerNameSpace('oasi', $oasins);
    $r1 = $xq->query('/kml:kml/kml:Response/kml:Status/kml:code/text()');
    if ($r1->length != 1){
      return array(false, 'unable to decode google response');
    }
    $status = $r1->item(0)->wholeText;
    if (isset($this->googleGeoStatus[$status])){
      switch($status){
      case 200:
	// check multiples responses and accuracy
	$r2 = $xq->query('/kml:kml/kml:Response/kml:Placemark');
	if ($r2->length == 0){
	  return array(false, 'unable to decode google response : address');
	}
	if ($r2->length == 1){
	  $place = $r2->item(0);
	  $r3 = $xq->query('oasi:AddressDetails', $place);
	  $accuracy = $r3->item(0)->getAttributeNode('Accuracy')->value;
	  // check accuracy ...
	  if (!isset($this->googleGeoAccuracy[$accuracy])){
	    return array(false, 'unknown accuracy '.$accuracy);
	  }
	  if ($accuracy == 0){
	    return array(false, 'null accuracy ');
	  }
	  unset($r3);
	  $r4 = $xq->query('kml:Point/kml:coordinates/text()', $place);
	  if ($r4->length == 0){
	    return array(false, 'unable to decode google response Point coordinates');
	  }
	  $raw = $r4->item(0)->wholeText;
	  list($lat, $lng) = explode(',', $raw);
	  unset($r4);
	  // read the exact address found
	  $r5 = $xq->query('kml:address/text()', $place);
	  $maddress = $r5->item(0)->wholeText;
	  $ret = array(true, 'exact match', $accuracy, array($lat, $lng), $maddress);
	} else {
	  // multiple responses
	  $ret = array(false, 'multiple matches');
	}
	unset($r2);
	return $ret;
	break;
      case 400:
      case 601:
      return array(false, 'invalid query');
      break;
      case 602:
      case 603:
	return array(false, 'unknow adress/unavailable address');
	break;
      case 610:
	return array(false, 'invalid key');
	break;
      case 620:
	return array(false, 'too many query');
	break;
      default:
	return array(false, 'unknow google status'.$status);
	break;
      }
    }else{
      return array(false, 'unknown google status '.$status);
    }
  }
  private function accuracyLabel($accuracy){
    if (!isset($this->googleGeoAccuracy[$accuracy]))
      return '***';
    return $this->googleGeoAccuracy[$accuracy][1];
  }

  /// Retourne les bornes d'une zone autour d'un point à une distance donnee sous forme de tableau ou de requete
  /// form : circle => cercle de rayon $d / in => carre interieur du cercle de rayon $d / out => carre exterieur du cercle de rayon $d
  static function getZoneCoord($lt,$lg,$d,$form='circle',$field=NULL){
    if($form=='circle'){
      if(empty($field)) return NULL;
      return " where $d >= (acos(cos(radians($lt))*cos(radians($lg))*cos(radians(SUBSTRING_INDEX($field,';',1)))*cos(radians(SUBSTRING_INDEX(SUBSTRING_INDEX($field,';',2),';',-1))) + cos(radians($lt))*sin(radians($lg))*cos(radians(SUBSTRING_INDEX($field,';',1)))*sin(radians(SUBSTRING_INDEX(SUBSTRING_INDEX($field,';',2),';',-1))) + sin(radians($lt))*sin(radians(SUBSTRING_INDEX($field,';',1)))) * ".EARTHRAY.")";
    }
    if($form=='in') $d=sqrt(2*pow($d,2));
    $top=$lt+($d*360)/(2*pi()*EARTHRAY);
    $bot=$lt-($d*360)/(2*pi()*EARTHRAY);
    $left=$lg-($d*360)/(cos(deg2rad($lt))*2*pi()*EARTHRAY);
    $right=$lg+($d*360)/(cos(deg2rad($lt))*2*pi()*EARTHRAY);
    if(!empty($field)){
      return " where SUBSTRING_INDEX($field,';',1)>$bot and SUBSTRING_INDEX($field,';',1)<$top ".
	"and SUBSTRING_INDEX(SUBSTRING_INDEX($field,';',2),';',-1)>$left and SUBSTRING_INDEX(SUBSTRING_INDEX($field,';',2),';',-1)<$right";
    }else{
      return array('top'=>$top,'bot'=>$bot,'left'=>$left,'right'=>$right);
    }
  }
  private function getGLang(){
    $ulang = \Seolan\Core\Shell::getLangUser();
    if($ulang == 'FR')
      $glang = 'fr';
    else if ($ulang == 'IT')
      $glang = 'it';
    else
      $glang = 'en';
  }
  /**
   * fonction de transformations 
   * \param String $fromSyst : code du système d'origine
   * \param String $fromSyst : code du système destination
   * \param array $data :
   * - in : fichier à traiter 
   * - out : fichier en sortie
   * \note 
   * voir http://spatialreference.org/
   *'EPSG:32600' : 'UTM WGS84 North, 32601 -> zone 1, ...',
   *'EPSG:32700' : 'UTM WGS84 South, 32701 -> zone 1, ...',
   *'EPSG:4326'  : 'WGS84',
   *'EPSG:6171'  : 'RGF93', // à vérifier
   *'EPSG:2154'  : 'Lambert 93',
   *'EPSG:27561' : 'Lambert carto Nord',
   *'EPSG:27562' : 'Lambert carto Centre',
   *'EPSG:2154'  : 'Lambert carto Sud',
   *'EPSG:27564' : 'Lambert carto Corse',
   *'EPSG:27571' : 'Lambert zone I',
   *'EPSG:27572' : 'Lambert zone II',idem zone 2 etendu
   *'EPSG:27573' : 'Lambert zone III',
   *'EPSG:27574' : 'Lambert zone IV'
   */
  public static function convertFile($fromSyst, $toSyst, $in, $out){
    \Seolan\Core\Logs::notice(get_class(), '::convertFile : gdaltransform -s_srs '.$fromSyst.' -t_srs '.$toSyst.' < '.$in.'  > '.$out);
    system(TZR_GDAL_PATH.'gdaltransform -s_srs '.$fromSyst.' -t_srs '.$toSyst.' < '.$in.'  > '.$out);
  }
  /**
   * \note 
   * idem convertFile mais à partir d'un tableau de lignes x y (z)
   */
  public static function convertArray($fromSyst, $toSyst, $in){
    $infilename = TZR_TMP_DIR.uniqid('xmodmapconversion_in');
    $outfilename = TZR_TMP_DIR.uniqid('xmodmapconversion_out');

    $fp = fopen($infilename, 'w');
    foreach($in as $coordline){
      fwrite($fp, $coordline."\n");
    }
    fclose($fp);

    self::convertFile($fromSyst, $toSyst, $infilename, $outfilename);

    $res = explode("\n", trim(file_get_contents($outfilename)));

    unlink($infilename);
    unlink($outfilename);

    return $res;
  }
  /**
   * conversion d'une liste de coordonnées
   */
  function convertCoords($ar){
    $p = new \Seolan\Core\Param($ar, array('insrs'=>self::$defaultSRS, 'mode'=>'ajax'));
    $coords = $p->get('coords');
    $insrs = $p->get('insrs');
    $tosrs = $p->get('tosrs');
    $tpl = $p->get('tplentry');
    $mode = $p->get('mode');
    if (!is_array($coords)){
      $coords = array($coords);
    }
    // lecture des systèmes
    $fromOrs = getDB()->select('select * from MAPSRS where lang="FR" and KOID = "'.$insrs.'"')->fetch();
    $toOrs = getDB()->select('select * from MAPSRS where lang="FR" and KOID = "'.$tosrs.'"')->fetch();

    // conversion
    $res = self::convertArray($fromOrs['epsgcode'], $toOrs['epsgcode'], $coords);
    $res2 = array();

    // mise en forme
    foreach($res as $ares){
      list($x, $y) = explode(' ', $ares);
      if (!empty($toOrs['dformat'])){
	$res2[]  = str_replace(array('%_x', '%_y'), array(sprintf('%d', $x), sprintf('%d',$y)), $toOrs['dformat']);
      } else {
	$res2[] = $x.' '.$y;
      }
    }

    if ($mode == 'ajax'){
      die(json_encode($res2));
    }

    return \Seolan\Core\Shell::toScreen1($tpl, $res2);
  }
  /**
   * convertions depuis l'admin
   */
  function preConvertCoords($ar){
    // liste des systèmes configurés
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=MAPSRS');
    $ds->browse(array('tplentry'=>'br1', 'pagesize'=>9999, 'order'=>'title asc'));
  }
  function procConvertCoords($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $fromSrs = $p->get('insrs');
    $toSrs = $p->get('tosrs');
    $incoords = trim($p->get('incoords'));
    if (empty($incoords) && (!isset($_FILES['infile']['tmp_name']) || !file_exists($_FILES['infile']['tmp_name']))){
      \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=preConvertCoords&template=Module/Map.convert.html&tplentry=br');
      \Seolan\Core\Shell::setNextData('message', '...');
      return;
    }
    if (!empty($incoords)){
      // convertion des coordonnées saisies
      $ar['coords'] = explode('\n', $incoords);
      $ar['mode'] = 'ret';
      $ar['tplentry'] = TZR_RETURN_DATA;
      $res = $this->convertCoords($ar);
      setSessionVar('message', '<pre>'.implode("\n", $res)."\n".implode("\n", $ar['coords']).'</pre>');
    } else {
      // lecture des systèmes
      $fromOrs = getDB()->select('select * from MAPSRS where lang="FR" and KOID = "'.$fromSrs.'"')->fetch();
      $toOrs = getDB()->select('select * from MAPSRS where lang="FR" and KOID = "'.$toSrs.'"')->fetch();
      // convertion du fichier       
      $outfilename = TZR_TMP_DIR.uniqid('xmodmapconversion_out');
      self::convertFile($fromOrs['epsgcode'], $toOrs['epsgcode'], $_FILES['infile']['tmp_name'], $outfilename);
      \Seolan\Core\Shell::setNextFile($outfilename, 'converted_'.$_FILES['infile']['name'], 'text/csv');      
    }
    \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=preConvertCoords&template=Module/Map.convert.html&tplentry=br');
  }
}
?>
