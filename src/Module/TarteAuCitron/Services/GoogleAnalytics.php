<?php
namespace Seolan\Module\TarteAuCitron\Services;

class GoogleAnalytics extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'analytics';
    $this->title = 'Google Analytics';
    $this->image = 'GoogleAnalytics.png';
    $this->fields = [
      'mainparam'=>['name'=>'analyticsUa','value'=>''],
      'extraparams'=>['name'=>'analyticsMore','value'=>'']
    ];
  }

  public function getScript(){
    $script = 'tarteaucitron.user.analyticsUa = "'.$this->getFieldValue('mainparam').'";' . "\n";
    // extraparam ?
    $gajsMore = $this->getFieldValue('extraparams');
    if(!empty($gajsMore)) $script .= 'tarteaucitron.user.analyticsMore = function () { '.$gajsMore.' };' . "\n";
    $script .= parent::getScript();
    return $script ;
  }
}
