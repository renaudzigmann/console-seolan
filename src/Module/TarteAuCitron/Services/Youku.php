<?php
namespace Seolan\Module\TarteAuCitron\Services;

class Youku extends \Seolan\Module\TarteAuCitron\Service {

  public function __construct(){
    $this->name = 'youku';
    $this->title = 'Youku';
    $this->image = 'Youku.png';
  }

  public function getScript(){
    $script = "
      // youku
      tarteaucitron.services.youku = {
        'key': 'youku',
        'type': 'video',
        'name': 'Youku',
        'uri': 'http://mapp.youku.com/service/agreement-eng',
        'needConsent': true,
        'cookies': ['__arpvid','__ayft','__aypstp','__ayscnt','__aysid','__ayspstp','__aysvstp','__ayvstp','__ysuid','cna','isg'],
        'js': function () {
          'use strict';
          tarteaucitron.fallback(['youku_player'], function (x) {
            var id = x.getAttribute('videoID');
            var width = x.getAttribute('width');
            var height = x.getAttribute('height');
            var size = '';
            if(width) size += ' width=' + width;
            if(height) size += ' height=' + height;
            if (id === undefined) {
              return '';
            }
            return '<iframe ' + size + ' src=\"https://player.youku.com/embed/' + id + '\" frameborder=0 \"allowfullscreen\"></iframe>';
          });
        },
        'fallback': function () {
          'use strict';
          var id = 'youku';
          tarteaucitron.fallback(['youku_player'], function (elem) {
            elem.style.width = elem.getAttribute('width') + 'px';
            elem.style.height = elem.getAttribute('height') + 'px';
            return tarteaucitron.engage(id);
          });
        }
      };
    ";

    $script .= parent::getScript();
    return $script ;
  }

  public function getInstallInfo(){
    return '&lt;div class="youku_player" videoID="<b>video_id</b>" width="<b>width</b>" height="<b>height</b>" &gt;&lt;/div&gt;';
  }
}