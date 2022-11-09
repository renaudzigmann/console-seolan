/**
 * Plugin jQuery seolanMap permettant de :
 *  - charger une liste d'items dans une carte GMap
 *  - filtrer dynamiquement ces items (appel AJAX)
 *  - regrouper les items (markerclusterer)
 *
 * Code JS :
 *   jQuery('.seolanMap').seolanMap({ options... });
 *
 * Surcharge JS des options par défaut :
 *   jQuery.extend(jQuery.fn.seolanMap.defaults, { options... });
 *
 * Code HTML :
 *   <div class="seolanMap">
 *     <div class="seolanMap-map"></div>
 *     <div class="seolanMap-items">
 *       <div class="seolanMap-item" data-lat="44.25" data-lng="10.95" data-icon="/url/de/l/icon.png">
 *         <h3>Mon item 1</h3>
 *         <div class="text">Ma description de l'item</div>
 *       </div>
 *       <div class="seolanMap-item" data-lat="45.25" data-lng="11.95">
 *         <h3>Mon item 2</h3>
 *         <div class="text">Ma description de l'item</div>
 *       </div>
 *       <div class="seolanMap-item" data-lat="46.25" data-lng="12.95">
 *         <h3>Mon item 3</h3>
 *         <div class="text">Ma description de l'item</div>
 *       </div>
 *     </div>
 *   </div>
 */
(function($) {

  // Arguments par défaut de la carte
  var defaults = {
    // Largeur de la carte
    width: '100%',
    // Hauteur de la carte
    height: '350px',
    // Type d'affichage de la carte : ROADMAP|SATELLITE|HYBRID|TERRAIN
    mapTypeId: 'ROADMAP',
    // Options des regroupement de markers
    markerClustererOptions: {
      gridSize: 25,
      maxZoom: 15,
      zoomOnClick: false,
      styles: [{
        url: TZR._sharetemplates+'xmodmap/marker-clusterer-default.png',
        height: 34,
        width: 43,
        textColor: '#FFFFFF',
        textSize: 11
      }]
    },
    // Nombre maximum de markers à afficher dans une liste regroupée par un cluster
    maxMarkersInACluster: 6,
    // Largeur maximale des info-bulles s'affichant au click sur un marker
    infoWindowMaxWidth: 300,
    // Limite le zoom du premier affichage de la carte lors du chargement des points
    maxInitialZoom: 15,
    // Selecteur jQuery pour le DIV contenant la carte
    mapSelector: '.seolanMap-map',
    // Selecteur jQuery pour un item à placer sur la carte
    itemSelector: '.seolanMap-item',
    // Selecteur jQuery pour le titre des items à placer sur la carte
    itemTitleSelector: '.text > div:first',
    // Selecteur jQuery pour la description des items à placer sur la carte
    itemDescriptionSelector: '.text',
    // OPTIONNEL : Liste d'objets à ajouter à la carte remplaçant les objets itemSelector si renseigné
    //   Objet de la forme {
    //     lat: '',   // Lattitude du point
    //     lng: '',   // Longitude du point
    //     title: '', // Titre à afficher dans les Clusterers
    //     icon: '',  // URL de l'image à utiliser en tant que marker sur la carte
    //     url: ''    // URL à charger en AJAX pour afficher la description de l'item dans la popup GMap (InfoWindow)
    //   }
    items: {},
    // Callback appelée dès que la carte est chargée
    callbackMapLoaded: function(map){},
    // Callback appelée dès qu'un point est chargé sur la carte
    callbackMarkerLoaded: function(map, marker){},
    // Callback appelée dès que tous les points sont chargés sur la carte
    callbackAllMarkersLoaded: function(map){},
    // Callback appelée après chaque affichage de popup GMap (InfoWindow)
    callbackShowInfoWindow: function(infoWindow){}
  };

  // Exécution du plugin en vérifiant que l'API Google est chargée
  $.fn.seolanMap = function(options) {

    // Récupération des options par défaut
    options = $.extend(true, {}, $.fn.seolanMap.defaults, options);
    options.$container = $(this);

    // Initialisation de la classe TZR.seolanMap
    var map = new TZR.seolanMap();
    $(this).data('seolanMap', map);
    $(this).data('seolanMapState', 'loading');
    $(this).data('seolanMapOptions', options);

    // Gestion des callbacks pour lancer le calcul des points après le chargement de l'API Google
    if (typeof TZR.gmap_callbacks == 'undefined')
      TZR.seolanMap_callbacks = $.Callbacks('once');
    var self = this;
    TZR.seolanMap_callbacks.add(function() {
      $(self).data('seolanMapState', 'initializing');
      map.initialize(options);
      $(self).data('seolanMapState', 'rendered');
    });

    // Callback appelée directement si API Google chargée sinon passée en paramètre d'URL du JS à charger
    var gmapCallbackName = 'gmapCallback'+Math.random().toString(36).substr(2, 9);
    window[gmapCallbackName] = function(){
      TZR.seolanMap_callbacks.fire();
    };
    if (typeof TZR.seolanMap_gmapLoading == 'undefined' && (window.google == undefined || 'undefined' == typeof google.maps)) {
      var script = document.createElement("script");
      script.src = "https://maps.googleapis.com/maps/api/js?callback="+gmapCallbackName + 
        (typeof options.gmapApiKey != 'undefined' ? "&key="+options.gmapApiKey : '');
      document.body.appendChild(script);
    } else if (window.google != undefined && 'undefined' != typeof google.maps) {
      TZR.seolanMap_callbacks.fire();
    }
    TZR.seolanMap_gmapLoading = true;
    return this;
  };

  $.fn.seolanMap.defaults = defaults;

})(jQuery);

// On le garde pour la rétro-compatibilité mais cette fonction n'est plus utile
jqSeolanMapGmapInit = function(options) {
  console.error('DEPRECATED: Use jQuery("#divId").seolanMap(...) instead of jqSeolanMapGmapInit(...)');
  jQuery(options.divId).seolanMap(options);
}

// Classe de construction d'une Map Google à markers multiples
TZR = TZR || {};
TZR.seolanMap = function(){};
TZR.seolanMap.prototype = {

  // Charge la carte GMap et y ajoute les markers
  initialize: function(options) {

    // Récupération des options par défaut ou personnalisées
    var self = jQuery.extend(this, options);

    // Initialisation des éléments de carte
    var $mapElement = jQuery(this.mapSelector, this.$container).width(this.width).height(this.height);
    if (!$mapElement.length) {
      console.error('mapSelector "'+this.mapSelector+'" not found in $container', this.$container);
      return;
    }
    this.ginfowindow = new google.maps.InfoWindow({});
    this.glatlng = new google.maps.LatLng(44.39, 6.65);
    this.gmap = new google.maps.Map($mapElement[0], {
      zoom: this.maxInitialZoom,
      center: this.glatlng,
      mapTypeControl: true,
      streetViewControl: false,
      navigationControl: true,
      mapTypeId: google.maps.MapTypeId[this.mapTypeId]
    });
    this.callbackMapLoaded(this.gmap);

    // Ajout de tous les points
    this.loadItems();
    this.callbackAllMarkersLoaded(this.gmap);
  },

  // Affiche les markers sur la carte et centre la vue
  loadItems: function(items) {
    var self = this;
    if (this.gmarkerclusterer == null) {
      this.gmarkerclusterer = new MarkerClusterer(this.gmap, null, this.markerClustererOptions);
    } else {
      this.gmarkerclusterer.clearMarkers();
    }
    google.maps.event.trigger(this.gmap, 'resize');
    var bounds = new google.maps.LatLngBounds();
    if (items && items.length) this.items = items;
    // Cas du chargement en AJAX du contenu des InfoWindows
    if (this.items && this.items.length) {
      jQuery(this.items).each(function(i, data){
        if (isNaN(data.lat) || isNaN(data.lng))
          return true; // continue;
        var position = new google.maps.LatLng(data.lat, data.lng);
        bounds.extend(position);
        var icon = typeof data.icon != 'string' || !data.icon.length
          ? TZR._sharetemplates+'xmodmap/marker-default.png'
            : data.icon;
        var marker = new google.maps.Marker({
          title: data.title,
          position: position,
          icon: icon,
          properties: data
        });
        marker.url = data.url;
        marker.markerShowInfoWindow = function(center) {
          var marker = this;
          if (marker.properties.url && marker.properties.url.length) {
            self.showInfoWindow('<div class="loading">Loading...</div>', center, this);
            jQuery.ajax(marker.properties.url).done(function(data) {
              self.showInfoWindow(data, center, marker);
            });
          } else if (marker.properties.description && marker.properties.description.length) {
            self.showInfoWindow('<div class="description">'+marker.properties.description+'</div>', center, marker);
          } else if (marker.properties.title && marker.properties.title.length) {
            self.showInfoWindow('<div class="title">'+marker.properties.title+'</div>', center, marker);
          }
        };
        google.maps.event.addListener(marker, 'click', function(){
          this.markerShowInfoWindow();
        });
        self.gmarkerclusterer.addMarker(marker);
        self.callbackMarkerLoaded(self.gmap, marker);
      });
    // Cas du chargement via les éléments du DOM
    } else {
      this.$container.find(this.itemSelector).each(function(i,e){
        var $item = jQuery(this);
        var data = jQuery(this).data();
        if (isNaN(data.lat) || isNaN(data.lng))
          return true; // continue;
        var position = new google.maps.LatLng(data.lat, data.lng);
        bounds.extend(position);
        var icon = typeof data.icon == 'undefined'
        ? TZR._sharetemplates+'xmodmap/marker-default.png'
          : data.icon;
        var marker = new google.maps.Marker({
          title: $item.find(self.itemTitleSelector).html(),
          position: position,
          //map: self.gmap,
          icon: icon
        });
        marker.description = $item.find(self.itemDescriptionSelector).html();
        marker.markerShowInfoWindow = function(center) {
          if (marker.description && marker.description.length) {
            self.showInfoWindow('<div class="description">'+marker.description+'</div>', center, marker);
          } else if (marker.title && marker.title.length) {
            self.showInfoWindow('<div class="title">'+marker.title+'</div>', center, marker);
          }
        };
        google.maps.event.addListener(marker, 'click', function(){
          this.markerShowInfoWindow();
        });
        self.gmarkerclusterer.addMarker(marker);
        self.callbackMarkerLoaded(self.gmap, marker);
      });
    }
    google.maps.event.addListener(this.gmarkerclusterer, 'clusterclick', function(cluster) {
      self.onMarkerClustererClick(cluster, self);
    });
    this.gmap.fitBounds(bounds);
    var listener = google.maps.event.addListener(this.gmap, "idle", function() {
      if (self.maxInitialZoom <= self.gmap.getZoom())
        self.gmap.setZoom(self.maxInitialZoom);
      google.maps.event.removeListener(listener);
    });
  },

  // Exécuté au click sur un marker
  showInfoWindow: function(content, position, marker) {
    this.ginfowindow.close();
    var $description = jQuery(content).wrapAll('<div class="infoWindowContent"></div>').parent();
    this.ginfowindow = new google.maps.InfoWindow({
      content: $description[0],
      position: position,
      maxWidth: this.infoWindowMaxWidth
    });
    this.ginfowindow.open(this.gmap, marker);
    this.callbackShowInfoWindow(this.ginfowindow);
    return false;
  },

  // Evènement déclenché lors du click sur un regrouppement de marker
  onMarkerClustererClick: function(cluster, self) {
    var markerClusterer = cluster.markerClusterer_;
    var maxZoom = markerClusterer.map_.mapTypes[markerClusterer.map_.getMapTypeId()].maxZoom-4;
    var zoom = markerClusterer.map_.getZoom();
    // S'il y a moins de 7 markers dans le cluster, on affiche la liste des markers, sinon on zoom
    if (cluster.markers_.length <= self.maxMarkersInACluster || zoom >= maxZoom+2) {
      var $content = jQuery('<ul class="markerClusterer"></ul>');
      for (var i = 0, m; m = cluster.markers_[i]; i++) {
        $a = jQuery('<li><a href="#"><img src="'+m.icon+'" alt="" /> '+m.getTitle()+'</a></li>').data('marker',m).click(function(e){
          e.preventDefault();
          var marker = jQuery(this).data('marker');
          marker.markerShowInfoWindow(cluster.getCenter());
          return false;
        });
        $content.append($a);
      }
      self.showInfoWindow($content, cluster.getCenter());
    } else {
      // Center the map on this cluster.
      cluster.map_.panTo(cluster.getCenter());
      // Zoom into the cluster.
      cluster.map_.fitBounds(cluster.getBounds());
    }
  },

  // Rafraichit l'affichage de la carte (utile pour l'affichage en ui.tabs)
  refresh: function() {
    google.maps.event.trigger(this.gmap, 'resize');
  }

};
