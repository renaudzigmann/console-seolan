<?php
namespace Seolan\Module\TarteAuCitron\Services;

class Dailymotion extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'dailymotion';
    $this->title = 'Dailymotion';
    $this->image = 'Dailymotion.png';
  }

  public function getInstallInfo(){
    return '&lt;div class="dailymotion_player" videoID="<b>video_id</b>" width="<b>width</b>" height="<b>height</b>" showinfo="<b>showinfo (1 | 0)</b>" autoplay="<b>autoplay (0 | 1)</b>"&gt;&lt;/div&gt;';
  }
}