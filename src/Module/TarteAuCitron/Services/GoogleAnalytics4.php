<?php
namespace Seolan\Module\TarteAuCitron\Services;

class GoogleAnalytics4 extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'gtag';
    $this->title = 'Google Analytics 4';
    $this->image = 'GoogleAnalytics.png';
    $this->fields = [
      'mainparam'=>['name'=>'gtagUa','value'=>''],
      'extraparams'=>['name'=>'gtagMore','value'=>'']
    ];
  }

  public function getScript(){
    $script = 'tarteaucitron.user.gtagUa = "'.$this->getFieldValue('mainparam').'";' . "\n";
    // extraparam ?
    $gtagMore = $this->getFieldValue('extraparams');
    if(!empty($gtagMore)) $script .= 'tarteaucitron.user.gtagMore = function () { '.$gtagMore.' };' . "\n";
    $script .= parent::getScript();
    return $script ;
  }
}