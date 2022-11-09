TZR_gmapRoute = function(){};
TZR_gmapRoute.prototype = {

  lat: null,
  lng: null,
  labels: null,
  wrapperId: null,
  markerImageUrl: null,

  map: null,
  gposition: null,
  geocoder: null,
  directionsdisplay: null,
  directionsservice: null,
  gtarget: null,
  wrapper: null,

  // Initialisation de l'objet
  initialize: function(data) {
    var self = this;
    jQuery.each(data,function(i,e){self[i] = e;});

    this.wrapper = jQuery("#"+this.wrapperId).parents(".cv3-gmap:first");
    this.gposition = new google.maps.LatLng(this.lat,this.lng);
    this.localise(this.gposition);

    jQuery("input.from",this.wrapper).click(function(){
      var a = jQuery.trim(this.value);
      if (a == self.labels.gmap_city_start)
        this.value = '';
      else
        this.select();
    }).bind('blur', function(){
      var a = jQuery.trim(this.value);
      if (a == '') {
        this.value = self.labels.gmap_city_start;
      }
    });

    // Affichage des etapes de l'itineraire
    jQuery("a.draw",this.wrapper).click(function(e){
      e.preventDefault();
      self.drawRoute();
    });
    jQuery("form",this.wrapper).submit(function(e){
      e.preventDefault();
      self.drawRoute();
    });

    // Affichage des etapes de l'itineraire sur maps.google.com
    jQuery("a.goGoogle",this.wrapper).click(function(e){
      var address = jQuery.trim(jQuery("input.from",self.wrapper).val());
      if (address != self.labels.gmap_your_address) {
        jQuery(this).attr('href','http://maps.google.com/maps?saddr='+self.gposition.lat()+'%20'+self.gposition.lng()+'&daddr='+address);
        return true;
      }
      return false;
    });

    // Centrage de la carte
    jQuery("a.center",this.wrapper).click(function(e){
      e.preventDefault();
      self.map.setCenter(self.gposition);
    });

    // Changement de type de carte
    jQuery("a.satellite",this.wrapper).click(function(){self.map.setMapTypeId(google.maps.MapTypeId.SATELLITE); return false;});
    jQuery("a.roadmap",this.wrapper).click(function(){self.map.setMapTypeId(google.maps.MapTypeId.ROADMAP); return false;});
    jQuery("a.hybrid",this.wrapper).click(function(){self.map.setMapTypeId(google.maps.MapTypeId.HYBRID); return false;});
    jQuery("a.terrain",this.wrapper).click(function(){self.map.setMapTypeId(google.maps.MapTypeId.TERRAIN); return false;});
    jQuery("ul.buttons a",this.wrapper).click(function(){
      jQuery("ul.buttons li",self.wrapper).removeClass('active');
      jQuery(this).parents("li:first").addClass('active');
      return false;
    });
    jQuery("a.roadmap",this.wrapper).click();
  },

  // Fonction qui affiche le point selon les coordonnees
  localise: function(point) {
    this.map = new google.maps.Map(document.getElementById(this.wrapperId), {
      zoom: 14,
      center: point,
      mapTypeControl:false,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    });
    new google.maps.Marker({
      position: point,
      map:      this.map,
      icon:     this.markerImageUrl ? new google.maps.MarkerImage(this.markerImageUrl) : null
    });
  },

  alert: function(m) {
    jQuery(".message",this.wrapper).html(m).slideDown(100);
    var self = this;
    setTimeout(function() {
      jQuery(".message",self.wrapper).slideUp(500);
    }, 2000);
  },

  drawRoute: function() {
    if (this.geocoder == null)
      this.geocoder = new google.maps.Geocoder();

    // coder l'adresse
    var address = jQuery.trim(jQuery("input.from",this.wrapper).val());
    if (address != '') {
      var self = this;
      this.geocoder.geocode({'address': address}, function(results, status) {
        if (status == google.maps.GeocoderStatus.OK) {
          self.gtarget = results[0].geometry.location;
          if (self.directionsdisplay == null) {
            self.directionsdisplay = new google.maps.DirectionsRenderer({hideTripList:false});
            self.directionsdisplay.setMap(self.map);
            self.directionsservice = new google.maps.DirectionsService();
            self.directionsdisplay.setPanel(jQuery(".route",self.wrapper).get(0));
          }
          var request = {
            origin:self.gtarget,
            destination:self.gposition,
            travelMode: google.maps.DirectionsTravelMode.DRIVING
          };
          self.directionsservice.route(request, function(result, status) {
            if (status == google.maps.DirectionsStatus.OK) {
              jQuery(".map",self.wrapper).addClass('separated');
              jQuery(".route",self.wrapper).show();
              self.directionsdisplay.setDirections(result);
            } else {
              self.alert(self.labels.gmap_alert_unknown_address.replace(/\%s/,'<i><b>'+address+'</b></i>'));
              //self.clearRoute();
            }
          });

        } else {
          self.alert(self.labels.gmap_alert_unknown_address.replace(/\%s/,'<i><b>'+address+'</b></i>'));
          //self.clearRoute();
        }
      });
    } else {
      this.alert(this.labels.gmap_alert_incomplete_address);
      //this.clearRoute();
    }
    return false;
  },

  clearRoute: function() {
    jQuery(".map",self.wrapper).removeClass('separated');
    jQuery(".route",self.wrapper).hide();
    if (this.directionsdisplay != null)
      this.directionsdisplay.setTripIndex(-1);
  }
};