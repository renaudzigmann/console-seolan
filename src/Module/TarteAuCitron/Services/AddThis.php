<?php
namespace Seolan\Module\TarteAuCitron\Services;

class AddThis extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'addthis';
    $this->title = 'AddThis';
    $this->image = 'AddThis.png';
    $this->fields = [
      'mainparam'=>['name'=>'addthisPubId','value'=>'']
    ];
  }

  public function getScript(){
    $script = 'tarteaucitron.user.addthisPubId = "'.$this->getFieldValue('mainparam').'";' . "\n";
    $script .= parent::getScript();
    return $script ;
  }

  public function getInstallInfo(){
    return '&lt;div class="addthis_sharing_toolbox"></div&gt;';
  }
}