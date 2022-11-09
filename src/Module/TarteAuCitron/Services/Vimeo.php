<?php
namespace Seolan\Module\TarteAuCitron\Services;

class Vimeo extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'vimeo';
    $this->title = 'Vimeo';
    $this->image = 'Vimeo.png';
  }

  public function getInstallInfo(){
    return '&lt;div class="vimeo_player" videoID="<b>video_id</b>" width="<b>width</b>" height="<b>height</b>"&gt;&lt;/div&gt;';
  }
}
