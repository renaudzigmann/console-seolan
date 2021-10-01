if( typeof(TZR.Gpath) == "undefined" ) {
  TZR.Gpath=new Object();

  TZR.Gpath.localizeOSM = function(options) {
    if (window.L == undefined) {
      console.error('Leaflet manquant !');
      return false;
    }
    
    var params = jQuery.extend({ edit: 0, }, options);

    var defaultLocation = params.defaultLocation.split(/,|;/);
    height = params.mapGeometry.split('x')[1];
    if (typeof(params.labels) == "undefined"){
      var closebutton=null;
      var reversebutton='Inverser';
      var addbutton='Ajouter un point';
      var deleteinfos='Double-clic sur un point pour le supprimer';
    } else {
      var closebutton=params.labels.close;
      var reversebutton=params.labels.reverse;
      var addbutton=params.labels.add;
      var deleteinfos=params.labels.deleteInfos;
    }

    let group = jQuery("#"+params.varid+' .form-group.latlng');
    
    if (params.edit) {
      var optionsSelect = TZR.Gpath.getOptionsSelect(group.length);
      var menuInverse1 = '<select name="reverse_pt1" id="reverse_pt1">'+optionsSelect+'</select>';
      var menuInverse2 = '<select name="reverse_pt2" id="reverse_pt2">'+optionsSelect+'</select>';

      var htmlcontents = '<div class="google-map" id="gmap'+params.id+'">';
      htmlcontents    += '<div class="title">'+params.title+'</div>';

      htmlcontents    += '<div style="width:100%;height:'+height+'px" id="map'+params.id+'"></div></div>';
      
      htmlcontents    += '<div class="margin-top">';

      htmlcontents    += '<div class="form-group">';
      htmlcontents    += '<button id="'+params.varid+'-addpoint" class="btn btn-primary addpoint">'+addbutton+'</button>';
      htmlcontents    += '<span class="text-info">'+deleteinfos+'</span>';

      htmlcontents    += '<div class="reverse pull-right">';
      htmlcontents    += menuInverse1+menuInverse2;
      htmlcontents    += '<button id="'+params.varid+'-reverse" class="btn btn-default">'+reversebutton+'</button>';
      htmlcontents    += '</div>';
      htmlcontents    += '</div>';

      htmlcontents    += '<div class="message alert alert-danger hidden"></div>';

      htmlcontents    += '</div>';

      htmlcontents    += '<div class="tzr-action"><div class="form-group">';
      if (closebutton != null)
        htmlcontents    += '<button data-dismiss="modal" class="btn btn-default">'+closebutton+'</button>';
      htmlcontents    += '</div></div>';
    } else {
      var htmlcontents =  '<div class="google-map" id="gmap'+params.id+'"><div class="title">'+params.title+'</div>';
      htmlcontents += '<div style="width:100%;height:'+height+'px" id="map'+params.id+'"></div></div>';
      if (closebutton != null)
        htmlcontents    += '<div class="tzr-action"><button data-dismiss="modal" class="btn btn-default">'+closebutton+'</button></div>';
    }
    
    // ouverture de la modal sauf si option modal='none' : cas du display en front par exemple
    if (typeof params.modal == 'undefined' || params.modal !== 'none'){
      TZR.Dialog.show(htmlcontents, {allowMove:true,backdrop:true});
    }
    TZR.gmap_div = jQuery('#gmap'+params.id);
    
    var center = defaultLocation;
    
    TZR.osm = L.map('map'+params.id, {gestureHandling: true}).setView(center, parseInt(params.zoom));
    TZR.osmFeatureGroup = L.featureGroup().addTo(TZR.osm);
    
    L.tileLayer(params.tilesURL, { attribution : 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="https://opendatacommons.org/licenses/odbl/">ODbL</a>, Imagery &copy; <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>' }).addTo(TZR.osm);
    if (params.scrollWheelZoom) {
      TZR.osm.scrollWheelZoom.enable();
    } else {
      TZR.osm.scrollWheelZoom.disable();
    }
    
    if ( typeof params.points == 'object'){
      TZR.Gpath.createMarkers(null, params.varid, params.points, params.edit);
    }else if ( group.length > 0 ){
      TZR.Gpath.createMarkers(group, params.varid, '', params.edit);
    }else{
      TZR.marker = L.marker(center);
      TZR.marker.addTo(TZR.osmFeatureGroup);
    } 
    
    bounds = TZR.osmFeatureGroup.getBounds();
    TZR.osm.fitBounds(bounds, {padding:[50,50], duration:1.0, maxZoom:parseInt(params.zoom)});

    TZR.gmap_div.click(function(e){
      e.stopPropagation();
    });
    
    return false;
  };
  
  TZR.Gpath.osmRemove = function(){
    if (TZR.gmap_div != undefined) {
      TZR.gmap_div.remove();
      TZR.gmap_div = null;
    }
    TZR.Dialog.closeDialog();
  };

  // mise a jour de la position des points et de leur id
  TZR.Gpath.setOrder = function(varid){
    group = jQuery('#'+varid+' .form-group.latlng');
    let currentGroup = null;
    let number;
    for (let i = 0; i < group.length; i++) {
      currentGroup = jQuery(group[i]);
      currentGroup.attr('data-order',i);
      number = i+1;
      currentGroup.children('span.title').text(TZR.Gpath.elementName+" "+number+" : ");
      let input = currentGroup.children('input');
      input.first().attr("id", varid+"-"+i+"-lat");
      input.last().attr("id",varid+"-"+i+"-lng");
      let label = currentGroup.children('label');
      label.first().attr("for", varid+"-"+i+"-lat");
      label.last().attr("for",varid+"-"+i+"-lng");
    }
  };

  // Mise a jours de la position dans le formulaire
  TZR.Gpath.osmSetPosition = function(close = false) {
    let layers = TZR.osmFeatureGroup._layers;
    for(var i in layers){
        id = layers[i].options.id;
        let pos = layers[i]._latlng;
        jQuery("#"+id+"-lat").val(pos.lat);
        jQuery("#"+id+"-lng").val(pos.lng);
    };
    if (close) TZR.Gpath.osmRemove();
  };

  TZR.Gpath.addEventMarker = function(marker, varid, edit){
    marker.on('moveend', function(e){  // modifie l'input correspondant après le deplacement
      let pos = e.target.getLatLng();
      let id = e.target.options.id;
      jQuery("#"+id+"-lat").val(pos.lat);
      jQuery("#"+id+"-lng").val(pos.lng);
    });

    marker.on('dblclick', function(e){  // suppression du marker au double clic
      let order = e.target.options.order;
      let varid = e.target.options.id.split('-')[0];
      jQuery("#"+varid+' .form-group.latlng[data-order='+order+']').remove();
      TZR.Gpath.setOrder(varid);
      TZR.Gpath.setMap(varid, {edit:edit});
      TZR.Gpath.updateOptionsSelect(TZR.osmFeatureGroup.getLayers().length);
    });
  } 

  // Prepare les markers les ajoute au group TZR.osmFeatureGroup
  TZR.Gpath.createMarkers = function(group, varid, points = "", edit = false) {
    let marker;
    if( typeof points != "object" ){
      let currentGroup = null;
      for (let i = 0; i < group.length; i++) {
        currentGroup = jQuery(group[i]);
        let order = currentGroup.attr('data-order');
        let id  = varid+"-"+order;
        let lat = currentGroup.children('input').first().val();
        let lng = currentGroup.children('input').last().val();
        marker = TZR.Gpath.createMarker(lat, lng, id, order, edit);
        TZR.Gpath.addEventMarker(marker, varid, edit);
        marker.addTo(TZR.osmFeatureGroup);
      }
    }else{ // For display
      let lat = points.lat;
      let lng = points.lng;
      if (lat.length != lng.length ){
        console.warn('lng.length != lat.length'); 
        return false;
      }
      for (let i = 0; i < lat.length; i++) {
        let order = id = i;
        marker = TZR.Gpath.createMarker(lat[i],lng[i],id,order);
        marker.addTo(TZR.osmFeatureGroup);
      }
    }
  };

  // Prepare et retourne un marker
  TZR.Gpath.createMarker = function(lat, lng, id, order, edit) {
    let options = {
      id: id,
      order: order,
    }
    if (edit) options.draggable = true;
    icon = "/csx/src/Pack/Leaflet/public/picture/marker-icon.png";
    let number = parseInt(options.order,10)+1;
    options.icon = L.divIcon({
      html: '<div class="custom-icon"><img src="'+icon+'" style="position:absolute;top:-41px;left:-12.5px;"><span class="ordre">'+number+'</span></div>',
      iconSize: [0, 0],
      popupAnchor: this.popupAnchor
    }); 
    return L.marker([lat, lng],options);
  };

  // supprime tous les markers de TZR.OSMFeatureroup
  TZR.Gpath.removeMarkers = function() {
    let layers = TZR.osmFeatureGroup._layers;
    for(var i in layers){
      layers[i].removeFrom(TZR.osmFeatureGroup);
    }
  }

  // Ajout d'un point dans le formulaire
  TZR.Gpath.addInputGroup = function(varid, options) {
    let groupLength = jQuery("#"+varid+" .form-group.latlng").length;

    if ( groupLength > 0 ){
      let lastGroup = jQuery("#"+varid+" .form-group.latlng").last();
      jQuery("#"+varid+" .fields").append(lastGroup.clone());
    }else{
      jQuery("#"+varid+" .fields").append(TZR.Gpath.htmlDefaultPoint);
    }
    TZR.Gpath.setOrder(varid);
  }

  // Suppression d'un point dans le formulaire
  TZR.Gpath.removeInputGroup = function(target,varid) {
    jQuery(target).closest('.form-group').remove();
    TZR.Gpath.setOrder(varid);
  }

  // Inversion de 2 points
  TZR.Gpath.reverse = function(pt1, pt2, varid) {
    pt1 = jQuery('#'+varid+' .form-group.latlng[data-order='+pt1+']');
    pt2 = jQuery('#'+varid+' .form-group.latlng[data-order='+pt2+']');
    clonept1 = pt1.clone();
    clonept2 = pt2.clone();
    pt1.addClass('oldgroup');
    pt2.addClass('oldgroup');
    clonept1.insertAfter(pt2);
    clonept2.insertAfter(pt1);
    jQuery('#'+varid+' .form-group.latlng.oldgroup').remove();
    TZR.Gpath.setOrder(varid);
  }

  // Création des options de la liste déroulante pour l'inversion des points
  TZR.Gpath.getOptionsSelect = function(size) {
    var optionsSelect = '<option value="">---</option>';
    let number;
    for (let i = 0; i < size; i++) {          
      number = i+1;
      optionsSelect  += '<option value="'+i+'">'+TZR.Gpath.elementName+' '+number+'</option>';
    }
    return optionsSelect;
  }

  // Mise a jour des liste déroulantes pour l'inversion des points
  TZR.Gpath.updateOptionsSelect = function(size) {
    let opt = TZR.Gpath.getOptionsSelect(size);
    document.getElementById('reverse_pt1').innerHTML = opt;  
    document.getElementById('reverse_pt2').innerHTML = opt;  
  }

  // decalage a droite du dernier point pour éviter la superposition
  TZR.Gpath.shiftLastPoint = function(varid, options) {
    let mapBounds = TZR.osm.getBounds();
    let ecart = (mapBounds._northEast.lng - mapBounds._southWest.lng) / 40;
    let newPoint = jQuery("#"+varid+" .form-group.latlng").last().children('input[id$=lng]');
    newPoint.val(parseFloat(newPoint.val())+ecart);
    TZR.Gpath.setMap(varid, options);
  }

  // Mise a jour de la carte
  TZR.Gpath.setMap = function(varid, options) {
    TZR.Gpath.removeMarkers();
    let group = jQuery("#"+varid+' .form-group.latlng');
    TZR.Gpath.createMarkers(group, varid, '', options.edit);
  }

  // Initialisation de la carte
  // 2 mode : 
  //   'one' => un seul point : utilisation de localizeOSM() de generic8 utilisé pour le champ Gmap2
  //   'all' => affichage de tous les point avec numérotation via TZR.Gpath.localizeOSM()
  TZR.Gpath.openOSMMap = function(options, mode) {
    let varid = options.varid;
    let paramOSM = {
      varid           : options.varid,
      labels          : {
                        close: options.closeLabel, 
                        save: options.saveLabel, 
                        reverse: options.reverseLabel, 
                        add: options.addLabel, 
                        deleteInfos: options.deleteInfos,
                      }, 
      intable         : options.intable, 
      zoom            : options.defaultZoom, 
      defaultLocation : options.defaultLocation, 
      mapGeometry     : options.mapGeometry,
      edit            : options.edit, 
      title           : options.title, 
      tilesURL        : options.tilesURL, 
      geocodingUrl    : options.geocodingUrl, 
      scrollWheelZoom : options.scrollWheelZoom
    }

    if ( typeof(options.mode) && options.mode == 'display' && typeof options.points == "string"){      
      paramOSM.points = JSON.parse(options.points);
    }

    if ( mode == 'one'){
      let number = parseInt(options.order,10)+1;
      paramOSM.title = options.title+" : "+TZR.Gpath.elementName+" n°"+number;
      paramOSM.id = options.varid+"-"+options.order;
      TZR.localizeOSM(paramOSM);
      return false;
    }else if (mode == 'all'){
      paramOSM.id = options.varid;
      let groupLength = jQuery("#"+varid+" .form-group.latlng").length;
      // ouverture de la carte sans point : on l'ajoute avant
      if ( options.mode != 'display' && groupLength == 0 ){
        TZR.Gpath.addInputGroup(varid, options);
      }
      TZR.Gpath.localizeOSM(paramOSM);
      return false;
    }
  }

  // Initialisation du champ
  TZR.Gpath.init = function(options) {
    let varid = options.varid;
    TZR.Gpath.elementName = typeof options.elementName == 'undefined' ? 'Point' : options.elementName;
    var body = jQuery("body");
    var gPath = jQuery("#"+varid);

    if (options.modal === 'none'){
      TZR.Gpath.openOSMMap(options, 'all');
      return;
    }

    gPath.on('click', 'button.viewmapall', function(e){
      e.preventDefault();
      TZR.Gpath.openOSMMap(options, 'all');
    });
    
    if ( options.edit == 1){
      // Initialisation pour la liste triable
      jQuery("#"+varid+" ul.fields").sortable({ 
        axis: "y",
        handle: ".sortablehandler",  
        cursor: "move",
        stop: function( e, ui ) {
          TZR.Gpath.setOrder(varid);
        },
      });  

      // MODAL EVENT
      body.on("click", ".modal-content #"+varid+"-addpoint", function(){
        TZR.Gpath.osmSetPosition();
        TZR.Gpath.addInputGroup(varid), options;
        TZR.Gpath.shiftLastPoint(varid, options);
        TZR.Gpath.updateOptionsSelect(TZR.osmFeatureGroup.getLayers().length);
      });
      body.on("click", ".modal-content #"+varid+"-reverse", function(){
        TZR.Gpath.osmSetPosition();
        let pt1 = jQuery('#reverse_pt1').val();
        let pt2 = jQuery('#reverse_pt2').val();
        let group = jQuery("#"+varid+' .form-group.latlng');

        // si les deux point existent
        if( pt1 != "" && pt2 != "" && pt1 >= 0 && pt1 < group.length && pt2 >= 0 && pt2 < group.length){
          if( pt1 == pt2 ) return;
          TZR.Gpath.reverse(pt1, pt2, varid);
          TZR.Gpath.setMap(varid, options);
        }else{
          console.warn("Un des points séléctionné dans la liste déroulante n'existe. Vérifier leur construcion dans Gpath.js" )
        }
      });

      // FORM EVENT
      gPath.on("click", "button.addlatlng", function(){
        TZR.Gpath.addInputGroup(varid, options);     
        jQuery("#"+varid+" .form-group.latlng").last().children('button.viewmapone').trigger('click');
      });
      gPath.on("click", ".delete-group", function(e){
        TZR.Gpath.removeInputGroup(e.currentTarget, varid);
      });
      gPath.on('click', 'button.viewmapone', function(e){
        options.order = e.currentTarget.parentElement.dataset.order;
        TZR.Gpath.openOSMMap(options, 'one');
      });
    }
  };
}