<?php
namespace Seolan\Module\TarteAuCitron\Services;

class Youtube extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'youtube';
    $this->title = 'Youtube';
    $this->image = 'Youtube.png';
  }

  public function getInstallInfo(){
    return '&lt;div class="youtube_player" videoID="<b>video_id</b>" width="<b>width</b>" height="<b>height</b>" theme="<b>theme (dark | light)</b>" rel="<b>rel (1 | 0)</b>" controls="<b>controls (1 | 0)</b>" showinfo="<b>showinfo (1 | 0)</b>" autoplay="<b>autoplay (0 | 1)</b>"&gt;&lt;/div&gt;';
  }
}