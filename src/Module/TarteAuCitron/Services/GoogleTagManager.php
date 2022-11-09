<?php
namespace Seolan\Module\TarteAuCitron\Services;

class GoogleTagManager extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'googletagmanager';
    $this->title = 'Google Tag Manager';
    $this->image = 'GoogleTagManager.png';
    $this->fields = [
      'mainparam'=>['name'=>'googletagmanagerId','value'=>''],
    ];
  }

  public function getScript(){
    $script = 'tarteaucitron.user.googletagmanagerId = "'.$this->getFieldValue('mainparam').'";' . "\n";
    $script .= parent::getScript();
    return $script ;
  }
}
