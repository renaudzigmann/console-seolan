<?php

namespace Seolan\Field\Video;

use Seolan\Core\Field\Value;
use Seolan\Core\Labels;
use Seolan\Field\File\File;
use Seolan\Library\MimeTypes;

class Video extends File {
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj = NULL) {
    parent::__construct($obj);
    $this->multivalued = true;
  }

  function my_display(&$value, &$options, $genid = false) {
    $r = $this->_newXFieldVal($options, true);
    $r->decoded_raw = json_decode($value, true);

    if($value == '' || $value == TZR_UNCHANGED || !is_array($r->decoded_raw) || empty($r->decoded_raw) || empty($r->decoded_raw['files'])) {
      return $r;
    }

    $dirobject = $this->dirname($r->decoded_raw['dir']);
    $dirname = $dirobject.'/';
    $r->dirname = $GLOBALS['DATA_DIR'].$dirname;
    $r->tracks = array();
    $r->catalog = array();

    // Récupération des infos sur les fichiers
    $files = $r->decoded_raw['files'];
    foreach($files as $i => $file) {
      $mime = $file['mime'];
      $originalname = $file['name'];
      $title = $r->title = $file['title'];
      if(empty($originalname)) {
        $originalname = 'download';
        $mimeClasse = MimeTypes::getInstance();
        $originalname .= '.'.$mimeClasse->get_extension($mime);
      }
      $shortfilename = $dirname.$file['file'];
      $filename = $GLOBALS['DATA_DIR'].$shortfilename;
      $originalname_corrected = preg_replace('@([^\._a-zA-Z0-9-]+)@', '_', $originalname);
      $downloader = $this->getDownloader($shortfilename, $mime, $originalname_corrected, $title, @$options['fmoid']);
      if(strpos($mime, "text") !== false) {
        $track = array();
        $track['mime'] = $mime;
        $track['title'] = $title;
        $track['originalname'] = $originalname;
        $track['filename'] = $filename;
        $track['url'] = $downloader[0];
        $track['lang_iso'] = preg_replace("/.*\.([^.]+)\.[^.]+$/", "$1", $originalname) ?: TZR_DEFAULT_LANG;
        $track['lang_label'] = \Locale::getDisplayLanguage($track['lang_iso'], $track['lang_iso']);
        $r->tracks[] = $track;
      }
      elseif(File::isVideo($mime)) {
        $r->isVideo = true;
        $r->filename = $filename;
        $r->shortfilename = $shortfilename;
        $r->mime = $mime;
        $r->title = $title;
        $r->originalname = $originalname;
        $r->raw = $value;
        $r->url = $downloader[0];
        $r->text = $r->title ?: $r->originalname;
        $r->mimepicto = Labels::getSysLabel('Seolan_Core_Mime', $mime, 'both', 'Seolan_Core_Mime', 'default');
        $r->html_default = '<a bt-xpath="'.TZR_AJAX8.'?class=_Seolan_Field_File_File&function=xfiledef_getfilesize&file='.$files.'&_='.\Seolan\Core\Shell::uniqid().'" class="tzr-hottip-c tzr-file" href="'.$r->url.'" target="_self">'.$r->mimepicto.htmlspecialchars((empty($title) ? $originalname : $title)).'</a>';
        $r->resizer = TZR_VIDEOCONVERT.'?filename='.$r->shortfilename;
        $r->preview = TZR_VIDEOCONVERT.'?preview=true&filename='.$r->shortfilename;
        $this->getImageSize($r);
        $r->originalGeometry = $r->fullwidth.'x'.$r->fullheight;
      }
      $r->catalog[$i] = new Value();
      $r->catalog[$i]->table = $r->table;
      $r->catalog[$i]->field = $r->field;
      $r->catalog[$i]->fielddef = $this;
      $r->catalog[$i]->filename = $GLOBALS['DATA_DIR'].$shortfilename;
      $r->catalog[$i]->shortfilename = $shortfilename;
      $r->catalog[$i]->mime = $mime;
      $r->catalog[$i]->title = $title;
      $r->catalog[$i]->originalname = $originalname;
      $r->catalog[$i]->url = $downloader[0];
      $r->catalog[$i]->html = $downloader[1];
    }

    // Création du html
    $r->html_preview = $this->html_previewVideo($r, $options);
    $r->html = $this->htmlVideo($r, $options);

    return $r;
  }

  function htmlVideo_getTracks($r, $options) {
    $html = parent::htmlVideo_getTracks($r, $options);
    foreach($r->tracks as $track) {
      $html .= '<track kind="subtitles" srclang="'.$track['lang_iso'].'" label="'.$track['lang_label'].'" src="'.$track['url'].'" '.($track['lang_iso'] == TZR_DEFAULT_LANG ? 'default' : '').' />';
    }
    return $html;
  }

  function htmlVideo_getJs($r, $options, $isSecure, $width, $height) {
    $html = '';
    if($isSecure) {
      $html .= '
        <script>
          TZR.XFileDef.secureVideo({
            urlAjax : "'.TZR_AJAX8.'",
            table : "'.$this->table.'",
            field : "'.$this->field.'",
            element : "#'.$r->varid.'"
          });
        </script>';
    }
    return $html;
  }

  protected function chkMultiple(&$messages) {
  }
}
