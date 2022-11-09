var map = null;
GMarker.prototype.setTZROptions = function(options){
 this.tzroptions = options;
};
var mapmngt = {
  mapid:'gmap',
  active:false,
  divwaitid:'gwait',
  gicons:[],
  markers:[],
  clusterer:null,
  options:null,
  constantOverlays:[],
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
    if (options.styles){
      var style=null;
      for(var i = 0; i < options.styles.length; i++){
        style = options.styles[i];
        var iconsize = style.icon.size.split('X');
        var gicon = new GIcon();
        gicon.image = style.icon.url;
        gicon.iconSize = new GSize(iconsize[0], iconsize[1]);
        gicon.iconAnchor = new GPoint(iconsize[0]/2, iconsize[1]);
        gicon.infoWindowAnchor = new GPoint(iconsize[0], iconsize[1]);
        if (style.shadow){
	  // ...
        } else {
	  // ...
        }
        this.gicons['#'+style.id] = gicon;
      }
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
    this.active = false;
  },
  // debloque la carte
  unlockMap:function(){
    var w = document.getElementById(this.divwaitid);
    w.style.display='none';
    this.active = true;
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
  setConstantOverlays:function(){
    for(var i=0; i<this.constantOverlays.length; i++){
      map.addOverlay(this.constantOverlays[i]);
    }
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
    this.setConstantOverlays();
    this.initLayer();
  },
  // initialise la carte avec la couche par defaut
  initLayer:function(){
    if (this.options.layeroids && this.options.layeroids.length > 0){
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
    GDownloadUrl(this.options.layerurl+ulayeroid, function(data){
      var foo = mapmngt.requestedLayers.pop(); // on depile immediatement
      var xml = GXml.parse(data);
      // lecture des styles styles/style
      var styles = xml.documentElement.getElementsByTagName("style");
      for (var j=0; j<styles.length; j++){
	var styleid = styles[j].getAttribute('id');
	var icons = styles[j].getElementsByTagName("icon");
	if (icons.length > 0){
	  var icon = icons[0];
	  var iconurl = icon.getElementsByTagName("url")[0].firstChild.data;
	  var iconsize = icon.getAttribute("size").split('X');
	  var gicon = new GIcon();
	  gicon.image = iconurl; 
	  gicon.iconSize = new GSize(iconsize[0], iconsize[1]);
	  gicon.iconAnchor = new GPoint(iconsize[0]/2, iconsize[1]);
	  gicon.infoWindowAnchor = new GPoint(iconsize[0], iconsize[1]);
	  var shadow = styles[j].getElementsByTagName("shadow");
	  if (shadow.length > 0){
	    // ...
	  } else {
	    // ...
	  }
	  // écrase eventuellement un style prechargé
	  mapmngt.gicons['#'+styleid] = gicon;
	}
      }
      // creation du gestionnaire de groupage
      if (mapmngt.clusterer == null)
	mapmngt.createClusterer();
      // legcture des points 
      var places = xml.documentElement.getElementsByTagName("marker");
      for (var i = 0; i < places.length; i++) {
	var styleid = places[i].getAttribute("styleid");
	var placeoid = places[i].getAttribute('id');
	var type = styleid;
	var name = places[i].getElementsByTagName("name")[0].firstChild.data;
	var coordinates = places[i].getElementsByTagName("coordinates")[0].firstChild.data;
	var latlng =coordinates.split(',');
	var point = new GLatLng(parseFloat(latlng[1]), parseFloat(latlng[0]));
	var moptions = [];
	
	var poptions = places[i].getElementsByTagName("options");
	if (poptions.length == 1){
	  var poption = poptions[0].firstChild;
	  while(poption){
	    if (poption.nodeType == 1 && poption.firstChild /* un element */){
	      moptions[poption.tagName] = poption.firstChild.data;
	    }
	    poption = poption.nextSibling;
	  }
	}
	var amarker = mapmngt.createMarker(point, type, {layeroid:places[i].parentNode.getAttribute('layerid'), name:name, index:mapmngt.markers.length, oid:placeoid, options:moptions});
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
  expendCluster:function(){
    var cmarkers = [];
    for ( var i = 0; i < this.clusterer.poppedUpCluster.markers.length; ++i ){
      if ( this.clusterer.poppedUpCluster.markers[i] != null ){
	cmarkers.push(this.clusterer.poppedUpCluster.markers[i]);
      }
    }
    mapmngt.viewport(map, cmarkers);
  },
  viewport:function(map, markers){
    var newbounds = this.getLatLngBounds(markers);
    var newzoom = map.getBoundsZoomLevel(newbounds);
    map.setZoom(newzoom);
    var xsw = map.fromLatLngToDivPixel(newbounds.getSouthWest());
    var xne = map.fromLatLngToDivPixel(newbounds.getNorthEast());
    var nxsw = new GPoint(xsw.x-50, xsw.y+5);
    var nxne = new GPoint(xne.x+5, xne.y-50);
    var nb = new GLatLngBounds(map.fromDivPixelToLatLng(nxsw), map.fromDivPixelToLatLng(nxne));
    map.panTo(nb.getCenter());
    //    map.panTo(newbounds.getCenter());
  },
  createMarker:function(point, type, tzroptions) {
    if (type == null){
      icon = G_DEFAULT_ICON;
    }else{
      icon = this.gicons['#'+type];
    }
    //    var marker = new GMarker(point, icon, false);
    var marker = new GMarker(point, {icon:icon, title:tzroptions.name});
    marker.setTZROptions(tzroptions);
    GEvent.addListener(marker, 'click', function() {
      map.savePosition();
      GDownloadUrl(mapmngt.options.displayurl+'&layeroid='+this.tzroptions.layeroid+'&oid='+this.tzroptions.oid, function(data){
	var xml = GXml.parse(data);
	var content = xml.documentElement.firstChild.data;
	map.openInfoWindowHtml(point, content, {maxWidth:mapmngt.options.infoWindowWidth, maxHeight:mapmngt.options.infoWindowHeight, suppressMapPan:true, onCloseFn:function(){map.returnToSavedPosition();}});
	var CDivPixel = map.fromLatLngToDivPixel(map.getCenter());
	var pointDivPixel = map.fromLatLngToDivPixel(point);
	var fromCenter = mapmngt.distPoints(pointDivPixel, CDivPixel);
	var ms = map.getSize();
	var dx = ms.width-mapmngt.options.infoWindowWidth;
	var dy = ms.height-mapmngt.options.infoWindowHeight;
	map.panBy(new GSize(-fromCenter.x-dx/3,-fromCenter.y+dy));
      });
    });
    return marker;
  },
  distPoints:function(a,b){
    return new GPoint(a.x-b.x, a.y-b.y);
  },
  unsetFilter:function(){
    if (!this.active){
      return;
    }
    this.lockMap();
    map.clearOverlays();
    this.setConstantOverlays();
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
    this.clusterer.SetMinMarkersPerCluster(this.options.minMarkersPerCluster);
    this.clusterer.SetMaxVisibleMarkers(this.options.maxVisibleMarkers);
    this.clusterer.defaultMaxLinesPerInfoBox = this.options.defaultMaxLinesPerInfoBox;
    this.clusterer.maxLinesPerInfoBox = this.options.maxLinesPerInfoBox;
    if (this.options.clusterAction == 'zoom'){
      Clusterer.PopUp = function ( cluster ){
	var cmarkers = [];
	for ( var i = 0; i < cluster.markers.length; ++i ){
	  var marker = cluster.markers[i];
	  if ( marker != null ){
	    cmarkers.unshift(marker);
	  }
	}
	mapmngt.viewport(map, cmarkers);
	return;
      };
    }
  },
  applyFilter:function(options, filterFunction){
    if (!this.active){
      return;
    }
    if (options.length == 0){
      return;
    }
    if (map == null || !map.isLoaded()){
      return;
    }    
    this.lockMap();
    this.clusterer.unregister();
    map.clearOverlays();
    this.setConstantOverlays();
    this.createClusterer();
    var nmarkers = [];
    var amarker = null;
    for(var i=0; i<this.markers.length; i++){
      var matach = false;
      match = filterFunction(this.markers[i].tzroptions, options);
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
  applyFilterOptions:function(options){
    this.applyFilter(options, function(moptions, foptions){
      for(var io = 0; io<foptions.length; io++){
	var crit = foptions[io];
	if (!moptions.options[crit.name] || moptions.options[crit.name] != crit.value){
	  return false;
	}
      }
      return true;
    });
  },
  applyFilterOption:function(optionname, optionvalue){
    this.applyFilterOptions([{name:optionname, value:optionvalue}])
  },
  checkMOptions:function(moptions, foptions){
    for(var io = 0; io<foptions.length; io++){
      var crit = foptions[io];
      if (!moptions.options[crit.name] || moptions.options[crit.name] != crit.value){
	return false;
      }
    }
    return true;
  },
  applyFilterMarker:function(oids){
    this.applyFilter(oids, function(moptions, options){
      for(var j=0; j<options.length; j++){
	if (moptions.oid == options[j]){
	  return true;
	}
      }
      return false;
    });
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
function initMap(){
  var myOnLoad = function(){
    if (GBrowserIsCompatible()) {
      mapmngt.lockMap();
      map = new GMap2(document.getElementById(mapmngt.mapid));
      GEvent.addListener(map, "load", function(){
        mapmngt.initMapControls();
        mapmngt.configure(); // modifier des options, etc apr
        setTimeout("mapmngt.initLayer()", 100);
      });
      map.setCenter(new GLatLng(mapmngt.options.initialLat, mapmngt.options.initialLng), mapmngt.options.initialZoom) ;
    }
    // ...
    if (window.attachEvent && typeof(correctPNG) != 'undefined'){
      setTimeout("correctPNG()", 10);;
    }
  }
  // ajout du onload
  var oldOnLoad = window.onload;
  window.onunload="<%$map_onunload%>";
  try{
    if (window.attachEvent && typeof(correctPNG) == 'function'){
      window.detachEvent('onload', correctPNG);
    }
  }catch(e){}
  if (oldOnLoad){
    window.onload = function(){ oldOnLoad(); myOnLoad();}
  } else {
    window.onload = function(){myOnLoad()};
  }
}
