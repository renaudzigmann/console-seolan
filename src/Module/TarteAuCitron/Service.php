<?php
namespace Seolan\Module\TarteAuCitron;
use \Seolan\Module\TarteAuCitron\TarteAuCitron;

/**
 * Classe parente utilisée par tous les services.
 */
class Service extends \Seolan\Module\Table\Table {
  protected $name = 'service';
  protected $title = 'Common service';
  protected $image = 'blank.gif';

  protected $fields = [];

  /**
   * display()
   * + Afficher le code d'intégration pour indiquer comment intégrer le service actuel
   */
  public function display($ar=NULL){
    $ret = parent::display($ar);

    $xmodtarteaucitron = new TarteAuCitron();
    $xmodtarteaucitron->displayCodeIntegration($ret);
    return $ret;
  }

  /**
   * edit()
   * + Afficher le code d'intégration pour indiquer comment intégrer le service actuel
   * + Forcer la saisie de certains champs
   */
  public function edit($ar=NULL){
    $ret = parent::edit($ar);

    $xmodtarteaucitron = new TarteAuCitron();
    $xmodtarteaucitron->displayCodeIntegration($ret);

    $xmodtarteaucitron->setDynamicCompulsoryFieldsAndShowMessage($this, $ret['oservice']->raw);
    return $ret;
  }


  /**
   * Renvoi la valeur d'une propriété de cette classe
   */
  public function get($prop){
    return $this->$prop;
  }

  /**
   * Assigne une valeur à une propriété de classe
   * Utilisé dans TarteAuCitron::_buildServices() et TarteAuCitron::displayCodeIntegration()
   */
  public function setFieldValue($field, $value){
    $this->fields[$field]['value'] = $value;
  }

  /**
   * Renvoie la valeur d'un des paramètres renseigné manuellement (Identifiant ou paramètre)
   */
  public function getFieldValue($field){
    return $this->fields[$field]['value'];
  }

  public function getFields(){
    return $this->fields;
  }

  /**
   * Renvoie un script particulier à indiquer dans le footer (utilisé notamment pour la méthode "initMap" de GoogleMaps)
   */
  public function getSpecificFooterScript(){
    return '';
  }

  // Utilisation d'un setInterval afin de pouvoir réécrire la valeur needConsent
  public function getScript(){
    $script = 'var interval'.$this->name.' = setInterval(function(){';
    $script .= 'if(tarteaucitron.services.'.$this->name.'){';
      $script .= 'clearInterval(interval'.$this->name.');';
      if(!$this->getFieldValue('needConsent'))
        $script .= 'tarteaucitron.services.'.$this->name.'.needConsent=false;'. "\n";
      $script .= '(tarteaucitron.job = tarteaucitron.job || []).push("'.$this->name.'");';
    $script .= '}';
    $script .= '},500);';
    return $script;
  }

  public function getInstallInfo(){
    return '';
  }
}