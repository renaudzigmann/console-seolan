var seolanAppli = {
    listCallback : {
        init : {},
        state_change : {},
    },
    prefixIdx : 'callback',
    keyJsInterface : 'MobileApp',
    prefixKeyExpiration : '#expiration#_',
    context : "pending"
};

/**
 * Ajoute une callback à la pile
 *
 * @param type {string} : type de la callback. valeur possible : "init" ou "state_change"
 * @param fn {function} : callback. Doit être du type function.
 * @param args liste des paramètres à passer à la callback.
 */
seolanAppli.appendCallback = function(type, fn, ...args) {
    type = type.toLowerCase();
    if (Object.keys(this.listCallback).indexOf(type) === -1) {
        throw new Error('seolanAppli.appendCallback : Le paramètre type doit faire parti d\'une des valeurs suivantes : '+JSON.stringify(Object.keys(this.listCallback)));
    }

    var keyIfExists = null;
    for(var k in this.listCallback[type]) {
        if (this.listCallback[type][k] === fn) {
            keyIfExists = k;
            break;
        }
    }

    if (keyIfExists === null) {
        keyIfExists = this.prefixIdx+(this.getLastCallbackIdx(type)+1);
    }

    this.listCallback[type][keyIfExists] = {
        fn : fn,
        args : args,
        function_name : fn.name.length > 0 ? fn.name : 'anonymous'
    };

    if (type.toLowerCase() === 'state_change') {
        if (args && args.length > 0 && args[0] !== null) {
            console.warn('seolanAppli.appendCallback : Les callback de type "state_change" ne peuvent pas avoir de paramètres ! "'+JSON.stringify(args)+'" ignoré.');
        }
    }
};

/**
 * Fonction appelée par l'application à la fin de l'initialisation
 */
seolanAppli.init = function() {
    this.context = window.hasOwnProperty(seolanAppli.keyJsInterface) ? 'mobile_app' : 'web';
    for(var idx in this.listCallback.init) {
        this.listCallback.init[idx].fn.apply(null, this.listCallback.init[idx].args);
    }
};

/**
 * Fonction appelée par l'application à chaque changement de statut.
 *
 * @param state {string} : vaut soit "background" soit "foreground"
 */
seolanAppli.stateChange = function (state) {
    for(var idx in this.listCallback.state_change) {
        this.listCallback.state_change[idx].fn.apply(null, [state]);
    }
};

/**
 *
 * @param type {string} : type de la callback. valeur possible : "init" ou "state_change"
 * @returns {number}
 */
seolanAppli.getLastCallbackIdx = function(type) {
    var lastIdx = 0;
    type = type.toLowerCase();
    if (this.listCallback.hasOwnProperty(type) && typeof this.listCallback[type] === "object") {
        var keys = Object.keys(this.listCallback[type]);
        for (var i in keys) {
            var curIdx = parseInt(keys[i].replace(this.prefixIdx, ''));
            if (curIdx > lastIdx) {
                lastIdx = curIdx;
            }
        }
    }

    return lastIdx;
}

seolanAppli.goBack = function() {
    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].goBack();
    } else {
        history.back();
    }
};

seolanAppli.alert = function (title, message) {
    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].alert(title, message);
    } else {
        alert(message);
    }
};

seolanAppli.confirm = function (title, message, OkCallback, KoCallback, OkLabel, KoLabel) {
    var idOkCallback = OkCallback;
    if (typeof OkCallback === 'function') {
        idOkCallback = 'seolanAppliOkCallback'+Math.floor(Math.random() * 1000000);
        window[idOkCallback] = OkCallback;
    }

    var idKoCallback = KoCallback;
    if (typeof KoCallback === 'function') {
        idKoCallback = 'seolanAppliKoCallback'+Math.floor(Math.random() * 1000000);
        window[idKoCallback] = KoCallback;
    }

    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].confirm(title, message, idOkCallback, idKoCallback, OkLabel, KoLabel);
    } else {
        if (confirm(message)) {
            eval(idOkCallback+'();');
        } else {
            eval(idKoCallback+'();');
        }
    }
};

seolanAppli.setData = function(key, value, callback, expiration) {
    if (typeof expiration === 'undefined') {
        expiration = null;
    }

    var idCallback = callback;
    if (typeof callback === 'function') {
        idCallback = 'seolanAppliCallback' + Math.floor(Math.random() * 1000000);
        window[idCallback] = callback;
    }

    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].setData(key, value, idCallback, expiration);
    } else {
        var expirationTs = null;

        if (expiration instanceof Date) {
            expirationTs = Math.round(expiration.getTime() / 1000);
        } else if (typeof expiration === 'number' && Number.isInteger(expiration)) {
            expirationTs = expiration;
        } else if (expiration !== null) {
            console.error('Le paramètre `expiration` doit être soit du type `integer` ou une instance `Date`.');
        }

        if (expirationTs !== null) {
            localStorage.setItem(seolanAppli.prefixKeyExpiration + key, expirationTs.toString());
        }

        if (typeof value === 'object') {
            value = JSON.stringify(value);
        }

        localStorage.setItem(key, value);

        if (typeof idCallback === 'string' && typeof window[idCallback] === 'function') {
            eval(idCallback + '();');
        }
    }
};

seolanAppli.isDataExpire = function(key) {
    var expiration = localStorage.getItem(seolanAppli.prefixKeyExpiration + key);

    return expiration !== null && parseInt(expiration) < Math.round(Date.now() / 1000);
};

seolanAppli.getData = function(key, callback) {
    var idCallback = callback;
    if (typeof callback === 'function') {
        idCallback = 'seolanAppliCallback'+Math.floor(Math.random() * 1000000);
        window[idCallback] = callback;
    }

    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].getData(key, idCallback);
    } else {
        var value = null;

        if (!seolanAppli.isDataExpire(key)) {
            value = localStorage.getItem(key);
        } else {
            seolanAppli.delData(key);
        }

        try {
            if (value !== null) {
                value = JSON.parse(value);
            }
        } catch (e) {
            //Do nothing
        }

        if (typeof value === 'string') {
            value = '"'+value.replace('"', '\\"')+'"';
        } else {
            value = JSON.stringify(value);
        }

        eval(idCallback+'('+value+')');
    }
};

seolanAppli.getAllData = function(callback) {
    var idCallback = callback;
    if (typeof callback === 'function') {
        idCallback = 'seolanAppliCallback'+Math.floor(Math.random() * 1000000);
        window[idCallback] = callback;
    }

    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].getAllData(idCallback);
    } else {
        var keys = Object.keys(localStorage);
        var allData = {};

        for (var i in keys) {
            if (keys[i].indexOf(seolanAppli.prefixKeyExpiration) === -1) {
                if (!seolanAppli.isDataExpire(key)) {
                    allData[keys[i]] = localStorage[keys[i]];
                } else {
                    localStorage.removeItem(seolanAppli.prefixKeyExpiration + key);
                    localStorage.removeItem(key);
                }
            }
        }

        eval(idCallback+'('+JSON.stringify(allData)+');');
    }
};

seolanAppli.delData = function(key, callback) {
    var idCallback = callback;
    if (typeof callback === 'function') {
        idCallback = 'seolanAppliCallback' + Math.floor(Math.random() * 1000000);
        window[idCallback] = callback;
    }

    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].delData(key, idCallback);
    } else {
        localStorage.removeItem(seolanAppli.prefixKeyExpiration + key);
        localStorage.removeItem(key);

        if (typeof idCallback === 'string' && typeof window[idCallback] === 'function') {
            eval(idCallback + '();');
        }
    }
};

seolanAppli.clearAllData = function(callback) {
    var idCallback = callback;
    if (typeof callback === 'function') {
        idCallback = 'seolanAppliCallback'+Math.floor(Math.random() * 1000000);
        window[idCallback] = callback;
    }

    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].clearAllData(idCallback);
    } else {
        localStorage.clear();

        if (typeof idCallback === 'string' && typeof window[idCallback] === 'function') {
            eval(idCallback + '();');
        }
    }
};

seolanAppli.clearOldData = function() {
    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].clearOldData();
    } else {
        var now = Math.round(Date.now() / 1000);
        var keys = Object.keys(localStorage);

        for (var i in keys) {
            if (keys[i].indexOf(seolanAppli.prefixKeyExpiration) === 0 && parseInt(localStorage[keys[i]]) < now) {
                localStorage.removeItem(seolanAppli.prefixKeyExpiration + keys[i]);
                localStorage.removeItem(keys[i]);
            }
        }
    }
}

seolanAppli.setOverlay = function(boolValue) {
    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].setOverlay(boolValue);
    }
};

seolanAppli.log = function (...args) {
    if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
        window[seolanAppli.keyJsInterface].log(args);
    } else {
        console.log(args);
    }
};

// ******************* NOTIFICATION *******************
seolanAppli.notificationShow = function(title, body, sound, subtitle, channelID, badgeCount, data){
  if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
    window[seolanAppli.keyJsInterface].notificationShow( title, body, sound, subtitle, channelID, badgeCount, data );
  } else {
    console.warn('notificationShow : indisponible en web !');
  }
};

// ******************* LOCALISATION *******************
seolanAppli.geolocalisation_getLocation = function(callbackOK, callbackKO){
  seolanAppli.log('seolanAppli geolocalisation_getLocation');
  if (typeof callbackOK !== 'string' || typeof callbackKO !== 'string') {
    console.error("seolanAppli.geolocalisation_getLocation : callbackOK and callbackKO must be of type string");
    seolanAppli.log("seolanAppli.geolocalisation_getLocation : callbackOK and callbackKO must be of type string");
    return;
  }
  
  if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
    window[seolanAppli.keyJsInterface].geolocalisation_getLocation( callbackOK, callbackKO );
  } else {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function(position) { window[callbackOK](position); },  
        function(error) { window[callbackKO](error); }, 
        {enableHighAccuracy:true}
      );
    } else {
      console.warn('geolocalisation_getLocation : indisponible !');
    }
  }
};

seolanAppli.geolocalisation_startTrackingForeground = function(callbackOK, callbackKO, options){
  if (typeof callbackOK !== 'string' || typeof callbackKO !== 'string') {
    seolanAppli.log("seolanAppli.geolocalisation_startTrackingForeground : callbackOK and callbackKO must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
    seolanAppli.log('tracking foreground : seolan app');
    window[seolanAppli.keyJsInterface].geolocalisation_startTrackingForeground(callbackOK, callbackKO, options);
  } else {
    console.warn('geolocalisation_startTrackingForeground : indisponible en web !');
  }
};

seolanAppli.geolocalisation_stopTrackingForeground = function(callback){
  seolanAppli.log('seolanAppli.geolocalisation_stopTrackingForeground');
  if ( typeof callback !== "undefined" && typeof callback !== 'string') {
    seolanAppli.log("seolanAppli.geolocalisation_stopTrackingForeground : callback must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].geolocalisation_stopTrackingForeground(callback);
  } else {
    console.warn('geolocalisation_stopTrackingForeground : indisponible en web !');
  }
};

seolanAppli.geolocalisation_startTrackingBackground = function(callbackOK, callbackKO, options){
  seolanAppli.log('seolanAppli geolocalisation_startTrackingBackground');
  if (typeof callbackOK !== 'string' || typeof callbackKO !== 'string') {
    seolanAppli.log("seolanAppli.geolocalisation_startTrackingBackground : callbackOK and callbackKO must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface)) {
    window[seolanAppli.keyJsInterface].geolocalisation_startTrackingBackground( callbackOK, callbackKO, options);
  } else {
    console.warn('geolocalisation_startTrackingBackground : indisponible en web !');
  }
};

seolanAppli.geolocalisation_stopTrackingBackground = function(callback){
  seolanAppli.log('seolanAppli.stopTrackPositionBackground');
  if ( typeof callback !== "undefined" && typeof callback !== 'string') {
    seolanAppli.log("seolanAppli.geolocalisation_stopTrackingBackground : callback must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].geolocalisation_stopTrackingBackground(callback);
  } else {
    console.warn('geolocalisation_stopTrackingBackground : indisponible en web !');
  }
};

seolanAppli.geolocalisation_askPermissionsAgain = function(callback){
  seolanAppli.log('seolanAppli.geolocalisation_askPermissionsAgain');
  if ( typeof callback !== "undefined" && typeof callback !== 'string') {
    seolanAppli.log("seolanAppli.geolocalisation_askPermissionsAgain : callback must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].geolocalisation_askPermissionsAgain(callback);
  } else {
    console.warn('geolocalisation_askPermissionsAgain : indisponible en web !');
  }
};

// ******************* SENSORS *******************
seolanAppli.magnetometer_subscribe = function(callbackOK, callbackKO){
  seolanAppli.log('seolanAppli.magnetometer_subscribe');
  if ( typeof callbackOK !== "undefined" && typeof callbackOK !== 'string') {
    seolanAppli.log("seolanAppli.magnetometer_subscribe : callbackOK must be of type string");
    return;
  }
  if ( typeof callbackKO !== "undefined" && typeof callbackKO !== 'string') {
    seolanAppli.log("seolanAppli.magnetometer_subscribe : callbackKO must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].sensors_magnetometerSubscribe(callbackOK, callbackKO);
  } else {
    console.warn('magnetometer_subscribe : indisponible en web !');
  }
};

seolanAppli.magnetometer_unsubscribe = function(callback){
  seolanAppli.log('seolanAppli.magnetometer_unsubscribe');
  if ( typeof callback !== "undefined" && typeof callback !== 'string') {
    seolanAppli.log("seolanAppli.magnetometer_unsubscribe : callback must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].sensors_magnetometerUnsubscribe(callback);
  } else {
    console.warn('magnetometer_unsubscribe : indisponible en web !');
  }
};

seolanAppli.magnetometer_setUpdateinterval = function(value, callback){
  seolanAppli.log('seolanAppli.magnetometer_setUpdateinterval');
  if ( typeof callback !== "undefined" && typeof callback !== 'string') {
    seolanAppli.log("seolanAppli.magnetometer_setUpdateinterval : callback must be of type string");
    return;
  }
  if(typeof value !== "number"){
    seolanAppli.log("seolanAppli.magnetometer_setUpdateinterval : value must be of type number");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].sensors_magnetometerSetUpdateinterval(value, callback);
  } else {
    console.warn('magnetometer_setUpdateinterval : indisponible en web !');
  }
};

seolanAppli.magnetometer_getAngle = function(callback){
  seolanAppli.log('seolanAppli.magnetometer_getAngle');
  if ( typeof callback !== "undefined" && typeof callback !== 'string') {
    seolanAppli.log("seolanAppli.magnetometer_getAngle : callback must be of type string");
    return;
  }
  if (window.hasOwnProperty(seolanAppli.keyJsInterface) ) {
    window[seolanAppli.keyJsInterface].sensors_getMagnetometerData(callback);
  } else {
    console.warn('magnetometer_getAngle : indisponible en web !');
  }
};

(function(){
  seolanAppli.clearOldData();
  window.onload=function() {
    if (!window.hasOwnProperty(seolanAppli.keyJsInterface) ) { 
      seolanAppli.init(); 
    }
  }
})();