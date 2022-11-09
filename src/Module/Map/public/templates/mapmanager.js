var map = null;
function pageLoad(){
  if (GBrowserIsCompatible()) {
    mapmngt.lockMap();
    map = new GMap2(document.getElementById(mapmngt.mapid));
    GEvent.addListener(map, "load", function(){
      mapmngt.initMapControls();
      mapmngt.configure(); // modifier des options, etc après chargement
      setTimeout("mapmngt.initLayer()", 100);
    });
    map.setCenter(new GLatLng(mapmngt.options.initialLat, mapmngt.options.initialLng), mapmngt.options.initialZoom) 
  }
};
GMarker.prototype.setTZROptions = function(options){
 this.tzroptions = options;
};
var mapmngt = {
  mapid:'gmap',
  divwaitid:'gwait',
  gicons:[],
  markers:[],
  clusterer:null,
  options:null,
  requestedLayers:null,
  // initialise les controles de la carte 
  initMapControls:function(){
      map.addMapType(G_PHYSICAL_MAP);
      map.setMapType(G_PHYSICAL_MAP);
      map.addControl(new GLargeMapControl());
      map.addControl(new GMapTypeControl());
      map.enableScrollWheelZoom();
      map.disableDoubleClickZoom();
      map.enableContinuousZoom();
  },
  // configuration
  configure:function(){
    // pour modifier ce qui doit après chargement de la carte
  },
  // options de configurations
  setOptions:function(options){
    this.options = options;
    if (!this.options.infoWindowWidth){
      this.options.infoWindowWidth = 400;
    }
    if (!this.options.infoWindowHeight){
      this.options.infoWindowHeight = 300;
    }
  },
  // bloque la carte pendant le chargement d'une couche
  lockMap:function(){
    var wait = document.getElementById(this.divwaitid);
    var tolock = null;
    tolock = document.getElementById('gmap');
    wait.style.left = this.getX(tolock)+"px";
    wait.style.top = this.getY(tolock)+"px";
    wait.style.width = tolock.offsetWidth+"px";
    wait.style.height = tolock.offsetHeight+"px";
    wait.style.display = "block";
  },
  // debloque la carte
  unlockMap:function(){
    var w = document.getElementById(this.divwaitid);
    w.style.display='none';
  },
  // outil de positionnement
  getX:function(e){
    if (e.offsetParent)
    return e.offsetLeft + this.getX(e.offsetParent);
    return e.offsetLeft;
  },
  // outil de positionnement
  getY:function(e){
    if (e.offsetParent)
    return e.offsetTop + this.getY(e.offsetParent);
    return e.offsetTop;
  },
  // recharge une ou plusieurs couches
  newLayers:function(newlayeroids){
    this.lockMap();
    this.options.layeroids = [];
    this.options.layeroids = newlayeroids;
    this.gicons = [];
    this.markers = [];
    this.clusterer.unregister();
    this.clusterer = null;
    map.clearOverlays();
    this.initLayer();
  },
  // initialise la carte avec la couche par defaut
  initLayer:function(){
    if (this.options.layeroids && this.options.layeroids.length > 1){
      this.loadLayers(this.options.layeroids);
    } else {
      this.requestedLayers = [];
      this.loadLayer(this.options.defaultlayeroid);
    }
  },
  loadLayers:function(layeroids){
    this.requestedLayers = [];
    for(var i=0; i<layeroids.length; i++){
      this.loadLayer(layeroids[i]);
    }
  },
  // charge les markers d'une couche
  loadLayer:function(layeroid){
    var ulayeroid = '&layeroid='+layeroid;
    this.requestedLayers.push('1'); /* on empile la demande */
    GDownloadUrl(this.options.kmlurl+ulayeroid, function(data){
      var foo = mapmngt.requestedLayers.pop(); // on depile immediatement
      var xml = GXml.parse(data);
      // lecture des styles
      var styles = xml.documentElement.getElementsByTagName("Style");
      for (var j=0; j<styles.length; j++){
	var styleid = styles[j].getAttribute('id');
	var iconStyles = styles[j].getElementsByTagName("IconStyle");
	if (iconStyles.length > 0){
	  var icon = iconStyles[0].getElementsByTagName("Icon")[0];
	  var iconurl = icon.getElementsByTagName("href")[0].firstChild.data;
	  var iconSize = iconStyles[0].getElementsByTagName("iconSize")[0];
	  if (iconSize == null)
	    iconSize = iconStyles[0].getElementsByTagName("tzr:iconSize")[0];
	  iconSize = iconSize.firstChild.data;
	  var iconSize = iconSize.split('X');
	  var gicon = new GIcon();
	  gicon.image = iconurl;
	  gicon.iconSize = new GSize(iconSize[0], iconSize[1]);
	  gicon.iconAnchor = new GPoint(iconSize[0]/2, iconSize[1]);
	  gicon.infoWindowAnchor = new GPoint(iconSize[0], iconSize[1]);
	  var shadow = iconStyles[0].getElementsByTagName("shadowIconUrl");
	  if (shadow.length > 0){
	    gicon.shadowSize = new GSize(64, 32);
	    gicon.shadow = shadow[0].firstChild.data;
	  } else {
	    // ...
	  }
	  mapmngt.gicons['#'+styleid] = gicon;
	}
      }
      // creation du gestionnaire de groupage
      if (mapmngt.clusterer == null)
	mapmngt.createClusterer();
      // lecture des points 
      var places = xml.documentElement.getElementsByTagName("Placemark");
      for (var i = 0; i < places.length; i++) {
	var styles = places[i].getElementsByTagName("styleUrl");
	var type = null;
	if (styles.length > 0){
	  type = styles[0].firstChild.data;
	} else {
	  type = null;
	}
	var placeoid = places[i].getAttribute('id');
	var name = places[i].getElementsByTagName("name")[0].firstChild.data;
	var description = places[i].getElementsByTagName("description")[0].firstChild.data;
	var ppoint = places[i].getElementsByTagName("Point")[0];
	var coordinates = ppoint.getElementsByTagName("coordinates")[0].firstChild.data;
	var latlng =coordinates.split(',');
	var point = new GLatLng(parseFloat(latlng[1]), parseFloat(latlng[0]));
	var moptions = [];
	var poptions = places[i].getElementsByTagName("tzr:options"); /* ie */
	if (poptions.length == 0){
	  var poptions = places[i].getElementsByTagName("options"); /* ie */
	}
	if (poptions.length == 1){
	  var poption = poptions[0].firstChild;
	  while(poption){
	    if (poption.nodeType == 1 && poption.firstChild /* un element */){
	      moptions[poption.tagName] = poption.firstChild.data;
	    }
	    poption = poption.nextSibling;
	  }
	}
	var amarker = mapmngt.createMarker(point, type, {name:name, description:description, index:mapmngt.markers.length, oid:placeoid, options:moptions});
	mapmngt.addMarker(amarker);
	mapmngt.markers.push(amarker);
      }
      if (mapmngt.requestedLayers.length == 0 && mapmngt.markers.length > 0){
	mapmngt.viewport(map, mapmngt.markers);
	mapmngt.unlockMap();
      }
    });
  },
  addMarker:function(amarker){
    this.clusterer.AddMarker(amarker, amarker.tzroptions.name);
  },
  getLatLngBounds:function(markers){
    var s = null;
    var n = null;
    var w = null;
    var e = null;
    var p = null;
    for(var i=0; i < markers.length; i++){
      p = markers[i].getLatLng();
      if (s == null || p.lat() < s.lat())
	s = p;
      if (n == null || p.lat() > n.lat())
	n = p;
      if (w == null || p.lng() < w.lng())
	w = p;
      if (e == null || p.lng() > e.lng())
	e = p;
    }
    var newbounds = new GLatLngBounds(new GLatLng(s.lat(), w.lng()), new GLatLng(n.lat(), e.lng()));
    return newbounds;
  },
  viewport:function(map, markers){
    var newbounds = this.getLatLngBounds(markers);
    var newzoom = map.getBoundsZoomLevel(newbounds);
    map.setZoom(newzoom);
    map.panTo(newbounds.getCenter());
  },
  createMarker:function(point, type, tzroptions) {
    if (type == null){
      icon = G_DEFAULT_ICON;
    }else{
      icon = this.gicons[type];
    }
    var marker = new GMarker(point, icon, false);
    marker.setTZROptions(tzroptions);
    
    GEvent.addListener(marker, 'click', function() {
      map.savePosition();
      map.openInfoWindowHtml(point, this.tzroptions.name + '<br>' + this.tzroptions.description, {maxWidth:mapmngt.options.infoWindowWidth, maxHeight:mapmngt.options.infoWindowHeight, suppressMapPan:true, onCloseFn:function(){map.returnToSavedPosition();}});
      var CDivPixel = map.fromLatLngToDivPixel(map.getCenter());
      var pointDivPixel = map.fromLatLngToDivPixel(point);
      var fromCenter = mapmngt.distPoints(pointDivPixel, CDivPixel);
      var ms = map.getSize();
      var dx = ms.width-mapmngt.options.infoWindowWidth;
      var dy = ms.height-mapmngt.options.infoWindowHeight;
      map.panBy(new GSize(-fromCenter.x-dx/3,-fromCenter.y+dy));
    });
    return marker;
  },
  distPoints:function(a,b){
    return new GPoint(a.x-b.x, a.y-b.y);
  },
  unsetFilter:function(){
    this.lockMap();
    map.clearOverlays();
    this.clusterer.unregister();
    this.createClusterer();
    for(var i=0; i<this.markers.length; i++){
      this.addMarker(this.markers[i]);
    }
    this.viewport(map, this.markers);
    this.unlockMap();
  },
  createClusterer:function(){
    Clusterer.defaultGridSize = this.options.gridSize;
    Clusterer.defaultIcon = new GIcon();
    Clusterer.defaultIcon.image = this.options.clusterIco.url;
    Clusterer.defaultIcon.shadow = this.options.clusterIcoShadow.url;
    Clusterer.defaultIcon.iconSize = new GSize( this.options.clusterIco.width, this.options.clusterIco.height );
    Clusterer.defaultIcon.shadowSize = new GSize( this.options.clusterIcoShadow.width, this.options.clusterIcoShadow.height);
    Clusterer.defaultIcon.iconAnchor = new GPoint( parseInt(this.options.clusterIco.width/2), this.options.clusterIco.height);
    Clusterer.defaultIcon.infoWindowAnchor = new GPoint( parseInt(this.options.clusterIco.width/2), this.options.clusterIco.height);
    Clusterer.defaultIcon.infoShadowAnchor = new GPoint( parseInt(this.options.clusterIco.width/2), this.options.clusterIco.height);    
    this.clusterer = new Clusterer(map);
    this.clusterer.SetMinMarkersPerCluster(2); // ! 2 1
    this.clusterer.SetMaxVisibleMarkers(1); 
  },
  applyFilterOptions:function(options){
    if (options.length == 0){
      return;
    }
    if (map == null || !map.isLoaded()){
      return;
    }    
    this.lockMap();
    this.clusterer.unregister();
    map.clearOverlays();
    this.createClusterer();
    var nmarkers = [];
    var amarker = null;
    for(var i=0; i<this.markers.length; i++){
      var match = true;
      for(var io = 0; io<options.length; io++){
	var crit = options[io];
	if (!this.markers[i].tzroptions.options[crit.name] || this.markers[i].tzroptions.options[crit.name] != crit.value){
	  match=false;
	}
      }
      if (match){
	amarker = this.markers[i];
	nmarkers.push(amarker);
	this.addMarker(amarker);
      }
    }
    if (nmarkers.length > 0){
      this.viewport(map, nmarkers);
    }
    this.unlockMap();

  },
  applyFilterOption:function(optionname, optionvalue){
    if (map == null || !map.isLoaded()){
      return;
    }    
    this.lockMap();
    this.clusterer.unregister();
    map.clearOverlays();
    this.createClusterer();
    var nmarkers = [];
    var amarker = null;
    for(var i=0; i<this.markers.length; i++){
      if (this.markers[i].tzroptions.options[optionname] && this.markers[i].tzroptions.options[optionname] == optionvalue){
	amarker = this.markers[i];
	nmarkers.push(amarker);
	this.addMarker(amarker);
      } else {
      }
    }
    if (nmarkers.length > 0){
      this.viewport(map, nmarkers);
    }
    this.unlockMap();
  },
  applyFilterMarker:function(oids){
    this.lockMap();
    if (oids.length == 0 || map == null || !map.isLoaded()){
      return;
    }
    this.clusterer.unregister();
    map.clearOverlays();
    this.createClusterer();
    var nmarkers = [];
    var amarker = null;
    for(var i=0; i<this.markers.length; i++){
      for(var j=0; j<oids.length; j++){
	if (this.markers[i].tzroptions.oid == oids[j]){
	  amarker = this.markers[i];
	  nmarkers.push(amarker);
	  this.addMarker(amarker);
	} else {
	}
      }    
    }
    this.viewport(map, nmarkers);
    this.unlockMap();
  },
  showMapBlowup:function(index){
    this.markers[index].showMapBlowup();
  },
  showInfoWindow:function(index){
    GEvent.trigger(this.markers[index], 'click');
  },
  gotoPoint:function(oid){
    var target = null;
    for(var i=0; i<this.markers.length; i++){
      if (this.markers[i].tzroptions.oid == oid){
	target = i;
	break;
      }
    }
    //  setTimeout(function(mmngt, target){mmngt.showInfoWindow(target);}, 200, this, target);
    setTimeout("mapmngt.showInfoWindow("+target+")", 200);
    map.panTo(this.markers[target].getLatLng());
  }
};
