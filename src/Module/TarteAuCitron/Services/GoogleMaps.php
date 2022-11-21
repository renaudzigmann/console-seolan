<?php
namespace Seolan\Module\TarteAuCitron\Services;

class GoogleMaps extends \Seolan\Module\TarteAuCitron\Service {

  private static $defaulfFunction = 'initMap';

  public function __construct(){
    $this->name = 'googlemaps';
    $this->title = 'Google Maps';
    $this->image = 'GoogleMaps.png';
    $this->fields = [
      'mainparam'=>['name'=>'mapsApiKey','value'=>''],
      'extraparams'=>['name'=>'mapsMore','value'=>'']
    ];
  }

  // Création d'une méthode tampon "checkinitMap" qui va vérifier l'existence de la méthode de callback (par défaut "initMap")
  public function getSpecificFooterScript(){
    $callbackFunction = $this->getFieldValue('extraparams');
    if(empty($extraparams)) $extraparams = self::$defaulfFunction;
    return 'function check'.$extraparams.'(){if("function" === typeof('.$extraparams.')){'.$extraparams.'()}}' . "\n";
  }

  public function getScript(){
    $mapApiKey = $this->getFieldValue('mainparam');
    $callbackFunction = $this->getFieldValue('extraparams');
    $script = "tarteaucitron.user.googlemapsKey = '".$mapApiKey."';";    
    $script .= "tarteaucitron.user.mapscallback = 'check".(empty($callbackFunction) ? self::$defaulfFunction : $callbackFunction)."';";
    $script .= parent::getScript();
    return $script ;
  }


  public function getInstallInfo(){
    return '<br>&lt;div class="googlemaps-canvas" zoom="<b>zoom</b>" latitude="<b>latitude</b>" longitude="<b>longitude</b>" style="width: <b>widthpx</b>; height: <b>heightpx</b>;"&gt;&lt;/div&gt;
    <br>
    <br>Mettre la clé google map dans le champ "identifiant"
    <br>Mettre le nom de la fonction de callback dans le champ "Paramètres supplémentaires"';
  }
}