<?php
namespace Seolan\Field\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

/// Gestion d'un champ fichier, y compris images et videos
class File extends \Seolan\Core\Field\Field {
  public $maxlength='';
  public $usealt=true;
  public $accept='.*';
  public $secured=false;
  public $crypt=false;
  public $sourcemodule=NULL;
  public $nocopydup=false;
  public $image_crop_ratio='';
  public $image_geometry='640x480%3E';
  public $image_max_geometry=null;
  public $video_geometry='';
  public $video_bitrate='512';
  public $audio_geometry='310x30';
  public $audio_bitrate='128';
  public $autoplay=false;
  public $muted=false;
  public $usemimehtml=false;
  public $viewlink=true;
  public $gzipped=false;
  public $indexable=true;
  public $auto_write_meta=false;
  public $confopts=array('name'=>'conf','encoding'=>'UTF-8');
  public $separator=null;
  public $multiseparator=null;
  public $multiseparatortext=null;
  public $fileorder="alphasort";
  static $vectorTypes = ['image/svg+xml'];
  static $html5_video_format = array(
    'webm' => array('ffmpeg_opts' => FFMPEG_WEBM_OPTS),
    'ogg'  => array('ffmpeg_opts' => FFMPEG_OGG_OPTS),
    'mp4'  => array('ffmpeg_opts' => FFMPEG_MP4_OPTS),
  );
  public $electronic_signature;

  function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->thumb_geometry = TZR_THUMB_SIZE.'x'.TZR_THUMB_SIZE;
    if(empty($this->image_max_geometry) && defined('XFILEDEF_IMAGE_MAX_GEOMETRY')) $this->image_max_geometry=XFILEDEF_IMAGE_MAX_GEOMETRY;
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setDefaultGroup(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','specific').' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','file'));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xfileviewlink'), 'viewlink','boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usemimehtml'), 'usemimehtml','boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','maxlength'), 'maxlength', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usealt'), 'usealt', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','accept'), 'accept', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','browsemods'), 'browsemods', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','sourcemodule'), 'sourcemodule', 'module');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','secured'), 'secured', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','crypt'), 'crypt', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','image_crop_ratio'), 'image_crop_ratio', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','image_geometry'), 'image_geometry', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','image_max_geometry'), 'image_max_geometry', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','video_geometry'), 'video_geometry', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','video_bitrate'), 'video_bitrate', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','audio_bitrate'), 'audio_bitrate', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','autoplay'), 'autoplay', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','muted'), 'muted', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','gzipped'), 'gzipped', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','allow_externalfile'), 'allow_externalfile', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xfiledef_autowritemeta'),'auto_write_meta','boolean');
    if($this->multivalued) {
      $methods=['values'=>['alphasort','naturalsort','antinaturalsort'], 'labels'=>[\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','alphasort'), \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','naturalsort'), \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','antinaturalsort')]];
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','order'), 'fileorder', 'list', $methods, 'alphasort');
    }
    $this->_options->delOpt('separator');
    $this->_options->delOpt('multiseparator');
    $this->_options->delOpt('multiseparatortext');

    $querygroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','query');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','indexable'), 'indexable', 'boolean', NULL, true, $querygroup);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','electronic_signature'),'electronic_signature','boolean');

    // L'option de signature électronique des documents est-elle déjà activées?
    if( $this->DPARAM['electronic_signature'] ){
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','electronic_signature_destination'),'electronic_signature_destination','field');
    }
  }

  /// Retourne vrai si le type mime représente une image
  static public function isImage($mime){
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    return $mimeClasse->isImage($mime);
  }
  /// Retourne vrai si le type mime représente une vidéo
  static public function isVideo($mime){
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    return $mimeClasse->isVideo($mime);
  }
  /// Retourne vrai si le type mime représente un fichier audio
  static public function isAudio($mime){
    $mimeClasse = \Seolan\Library\MimeTypes::getInstance();
    return $mimeClasse->isAudio($mime);
  }

  static function player_from_video_url($link, $dataType="embed") {
    $type="none";
    $code="";

    //DAILYMOTION
    if(preg_match('#(?:dailymotion\.com(?:(?:/[a-z]*)?/video|/hub)|dai\.ly)/([0-9a-z]+)(?:[\-_0-9a-zA-Z]+\#video=([a-z0-9]+))?#', $link, $matches)){
      if(strlen($matches[2])){
        $type = "dailymotion";
        $code = $matches[2];
      }
      elseif(strlen($matches[1])){
        $type = "dailymotion";
        $code = $matches[1];
      }
    }
    //YOUKU
    if($code=="" && preg_match("#youku\.com/(?:player.php/sid/|v_show/(?:show)?id_|embed/)([a-zA-Z0-9=]+)#", $link, $matches)){
      if(strlen($matches[1])) {
        $type = "youku";
        $code = $matches[1];
      }
    }

    //YOUTUBE
    if($code=="" && preg_match('#(?<=(?:v|i)=)[a-zA-Z0-9-]+(?=&)|(?<=(?:v|i)\/)[^&\n]+|(?<=embed\/)[^"&\n]+|(?<=(?:v|i)=)[^&\n]+|(?<=youtu.be\/)[^&\n]+#', $link, $matches)){
      if(strlen($matches[0])) {
        $type = "youtube";
        $code = $matches[0];
      }
    }

    //VIMEO
    if($code=="" && preg_match('#(https?://)?(www.)?(player.)?vimeo.com/([a-z]*/)*([0-9]{6,11})[?]?.*#', $link, $matches)){
      if(strlen($matches[5])) {
        $type = "vimeo";
        $code = $matches[5];
      }
    }

    return self::player_from_video_code($type, $code, $dataType);
  }

  static function player_from_video_code($type, $code, $dataType="embed") {
    $embed = "";
    $image = "";
    if($type == "dailymotion") {
      $embed = 'https://www.dailymotion.com/embed/video/'.$code;
      $image = 'https://www.dailymotion.com/thumbnail/160x120/video/'.$code;
    }
    elseif($type == "youku") {
      $embed = 'https://player.youku.com/embed/'.$code;
      $image = 'https://events.youku.com/global/api/video-thumb.php?vid='.$code;
    }
    elseif($type == "youtube") {
      $embed = 'https://www.youtube.com/embed/'.$code;
      $image = 'https://img.youtube.com/vi/'.$code.'/0.jpg';
    }
    elseif($type == "vimeo") {
      $embed = 'https://player.vimeo.com/video/'.$code;
      $image = 'https://i.vimeocdn.com/video/'.$code.'_640.jpg';
    }

    switch($dataType)
    {
      case "type" :
        return $type;
      case "code" :
        return $code;
      case "embed" :
        return $embed;
      case "image" :
        return $image;
      case "all" :
        return array("type"=>$type,"code"=>$code,"embed"=>$embed,"image"=>$image);
      default :
        return $embed;
    }
  }

  static function html_from_video_url($link, $options=array()) {
    $player = self::player_from_video_url($link, 'all');

    return self::html_from_video_code($player["type"], $player["code"], $options);
  }

  // Fonction qui renvoie le html d'une vidéo externe en tenant compte du module tarteaucitron si il existe
  static function html_from_video_code($type, $code, $options=array()) {
    $options = array_merge(array(
      'width' => '100%',
      'height' => '100%',
      'theme' => 'dark',
      'rel' => '1',
      'controls' => '1',
      'showinfo' => '1',
      'autoplay' => '0'
    ), $options);

    if (!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Module\Module::moduleExists('', XMODTARTEAUCITRON_TOID)){
      $serviceActivated = getDB()->fetchExists('select 1 from TARTEAUCITRON_SERVICES where service=?', array($type));
      if($serviceActivated) {
        $params = array(
          'youtube' => array('width', 'height', 'theme', 'rel', 'controls', 'showinfo', 'autoplay'),
          'dailymotion' => array('width', 'height', 'showinfo', 'autoplay'),
          'youku' => array('width', 'height'),
          'vimeo' => array('width', 'height'),
        );
        $paramsHtml = '';
        foreach($params[$type] as $param) {
          $val = $options[$param];
          if($val !== '') {
            $paramsHtml .= " $param='$val'";
          }
        }

        return "<div class='${type}_player' videoID='$code' $paramsHtml></div>";
      }
    }

    $embed = self::player_from_video_code($type, $code);
    $width = ($options['width'] !== '') ? " width='".$options['width']."'" : "";
    $height = ($options['height'] !== '') ? " height='".$options['height']."'" : "";

    return "<iframe src='$embed' $width $height frameborder='0' allowfullscreen></iframe>";
  }

  /**
   * Function isEmpty
   * @return true si le champ n'est pas remplit
   */
  public function isEmpty($r) {
    if(empty($r)) return true;
    if (property_exists($r, 'filename') ){
      return empty($r->filename);
    }
    if (property_exists($r, 'externalUrl') ){
      return empty($r->externalUrl);
    }
    if (property_exists($r, 'catalog'))
      return empty($r->catalog);
    return true;
  }

  /**
   * Function isQueryEmpty
   * @return true si il n'y a pas de recherche en cours sur le champ
   */
  public function isQueryEmpty($query=array(), $isValueEmpty=false){
    $p = new \Seolan\Core\Param($query);
    $field = ($query && count($query['_FIELDS'])) ? array_search($this->field, $query['_FIELDS']) : $this->field;
    $fieldVal = $p->get($field);
    $isValueEmpty = (is_array($fieldVal) && empty($fieldVal['name']) && empty($fieldVal['mime']));

    return parent::isQueryEmpty($query, $isValueEmpty);
  }

  static function mkfilename($str, $mime) {
    $s=preg_replace('@([^\/0-9a-zA-Z_-]+)@','',$str);
    $mimeClasse = \Seolan\Library\MimeTypes::getInstance();
    $ext=$mimeClasse->get_extension($mime);
    return $s.'.'.$ext;
  }

  public function hasExternals() {
    return true;
  }
  /// Retourne le nom du fichier
  public function externals($value) {
    if($this->multivalued){
      // A faire
      return NULL;
    }else{
      $info=json_decode($value);
      $files=$this->filename($info->file,false,true);
      if(!empty($files)) return array($files);
      else return NULL;
    }
  }
  /// Copie le fichier d'une fiche vers une autre fiche (~=restoreExternals)
  public function copyExternalsTo($value,$oidsrc,$oiddst,$upd=NULL) {
    if($this->multivalued) return $this->copyExternalsToMultiple($value,$oidsrc,$oiddst,$upd);
    else return $this->copyExternalsToSimple($value,$oidsrc,$oiddst,$upd);
  }
  protected function copyExternalsToSimple($value,$oidsrc,$oiddst,$upd=null) {
    if(empty($value) || empty($oiddst)) return NULL;
    if($value==TZR_UNCHANGED) return $value;
    $info=json_decode($value);
    if(strpos($info->file,'.'))
      list($lang)=explode('.',$info->file);
    $files=$this->filename($info->file,false,true);
    // Vidéo externe
    if(empty($files) && strpos($info->file, ':') !== false) {
      return $value;
    } elseif (!empty($files) && file_exists($GLOBALS['DATA_DIR'].$files)) {
      list($table,$idd) = explode(':',$oiddst);
      if(!empty($lang)) $idd=$lang.'.'.$idd;
      // 1er appel : création de l'arbo / 2eme appel : récupération du nom du fichier
      $nfiles=$this->filename($idd,true,false,$upd);
      $nfiles=$this->filename($idd,false,false,$upd);
      copy($GLOBALS['DATA_DIR'].$files,$GLOBALS['DATA_DIR'].$nfiles);
      return json_encode(array('file'=>$idd,'mime'=>$info->mime,'name'=>$info->name,'title'=>$info->title));
    }else{
      return TZR_UNCHANGED;
    }
  }
  protected function copyExternalsToMultiple($value,$oidsrc,$oiddst,$upd=null) {
    global $DATA_DIR;
    if(empty($value) ||empty($oidsrc) || empty($oiddst)) return NULL;
    $json = json_decode($value, true);
    list($t,$fsrc)=explode(':',$oidsrc);
    list($t,$fdst)=explode(':',$oiddst);
    if(strpos($json['dir'],'.')){
      list($lang)=explode('.',$json['dir']);
      $fsrc=$lang.'.'.$fsrc;
      $fdst=$lang.'.'.$fdst;
    }
    $dirsrc=$this->dirname($fsrc);
    $dirdst=$this->dirname($fdst,$upd);
    \Seolan\Core\Logs::debug('\Seolan\Field\File\File::copyExternalsToMultiple(): Trying to copy '.$DATA_DIR.$dirsrc.' '.$DATA_DIR.$dirdst);
    \Seolan\Library\Dir::copy($DATA_DIR.$dirsrc,$DATA_DIR.$dirdst,true);
    $json['dir'] = $fdst;
    return json_encode($json);
  }
  /// déplace ou copie les fichiers d'une archive vers une fiche (~=copyExternals)
  public function restoreExternals($value,$oidsrc,$oiddst,$upd) {
      if($this->multivalued) return $this->restoreExternalsMultiple($value,$oidsrc,$oiddst,$upd);
      else return $this->restoreExternalsSimple($value,$oidsrc,$oiddst,$upd);
  }
  protected function restoreExternalsSimple($value,$oidsrc,$oiddst,$upd) {
    if(empty($value) || empty($oiddst)) return NULL;
    if($value==TZR_UNCHANGED) return $value;
    $info=json_decode($value);
    if(strpos($info->file,'.')) list($lang)=explode('.',$info->file);
    $files=$this->filename($info->file,false,true,$upd);
    if(!empty($files) && file_exists($GLOBALS['DATA_DIR'].$files)) {
      list($table,$idd) = explode(':',$oiddst);
      if(!empty($lang)) $idd=$lang.'.'.$idd;
      // 1er appel : création de l'arbo / 2eme appel : récupération du nom du fichier
      $nfiles=$this->filename($idd,true,false);
      $nfiles=$this->filename($idd,false,false);

      copy($GLOBALS['DATA_DIR'].$files,$GLOBALS['DATA_DIR'].$nfiles);

      return json_encode(array('file'=>$idd,'mime'=>$info->mime,'name'=>$info->name,'title'=>$info->title));

    }else{
      return TZR_UNCHANGED;
    }
  }
  protected function restoreExternalsMultiple($value,$oidsrc,$oiddst,$upd) {
    global $DATA_DIR;
    if(empty($value) ||empty($oidsrc) || empty($oiddst)) return NULL;
    $json = json_decode($value, true);
    list($t,$fsrc)=explode(':',$oidsrc);
    list($t,$fdst)=explode(':',$oiddst);
    if(strpos($value,'.')){
      list($lang)=explode('.',$value);
      $fsrc=$lang.'.'.$fsrc;
      $fdst=$lang.'.'.$fdst;
    }
    $dirsrc=$this->dirname($fsrc,$upd);
    $dirdst=$this->dirname($fdst);
    \Seolan\Core\Logs::debug(__METHOD__.' Trying to copy '.$DATA_DIR.$dirsrc.' '.$DATA_DIR.$dirdst);
    \Seolan\Library\Dir::copy($DATA_DIR.$dirsrc,$DATA_DIR.$dirdst,true);
    $json->dir = $fdst;
    return json_encode($json);
  }
  // Verification que les repertoires qui contiennent les fichiers n'existent pas,  sinon on les cree
  function _checkDir() {
    global $DATA_DIR;
    umask(0000);
    $root=$DATA_DIR.$this->table.'/'.$this->field;
    if(!is_dir($root)) {
      if(!is_dir($DATA_DIR.$this->table)) {
	if (false === @mkdir($DATA_DIR.$this->table,0777))
          \Seolan\Core\Logs::critical('\Seolan\Field\File\File::_checkDir unable to create ' . $DATA_DIR . $this->table);
      }
      if(!is_dir($DATA_DIR.$this->table.'/'.$this->field)) {
	if (false === @mkdir($DATA_DIR.$this->table.'/'.$this->field,0777))
          \Seolan\Core\Logs::critical('\Seolan\Field\File\File::_checkDir unable to create ' . $DATA_DIR . $this->table);
      }
    }
  }

  /// Suppression du champ, i.e. suppression du repertoire et des donnees incluses dans ce repertoire
  function delfield() {
    global $DATA_DIR;
    \Seolan\Library\Dir::unlink($DATA_DIR.$this->table.'/'.$this->field);
  }
  /// Contenu d'une cellule pour feuille de calcul
  function getSpreadSheetCellValue($value, $options=null){
    if (isset($options['name']) || is_string($value)){
      return $value;
    } elseif (is_a($value, \Seolan\Core\Field\Value::class)){
      $v = '';
      if ($this->multivalued){ // liste de noms (on peut pas mettre n liens)
	$v = array_reduce($value->catalog, function($carry, $item)use($options){
	    if (isset($options['url'])) {
        if (substr($GLOBALS['HOME_ROOT_URL'], -1) === '/' && substr($item->url, 0, 1) === '/') {
          $v = substr($GLOBALS['HOME_ROOT_URL'], 0, -1).$item->url;
        } else {
          $v = $GLOBALS['HOME_ROOT_URL'].$item->url;
        }
        return $carry.(empty($carry)?'':',').$v;
      }
	    return $carry.(empty($carry)?'':',').$item->originalname;
	  }, '');
      } else {
	if (isset($options['link']))
	  $v = empty($value->text)?'Link':$value->text;
	elseif(isset($options['url'])) {
	  if (substr($GLOBALS['HOME_ROOT_URL'], -1) === '/' && substr($value->url, 0, 1) === '/') {
      $v = substr($GLOBALS['HOME_ROOT_URL'], 0, -1).$value->url;
    } else {
      $v = $GLOBALS['HOME_ROOT_URL'].$value->url;
    }
  }
	else
	  $v = $value->text;
      }
    }
    return $v;
  }
  // Valeur pour une cellule de CSV
  function getCSVValue($value, $textsep, $format=null, $options=null){
    if ($options == null)
      $options = ['url'=>1];
    return parent::getCSVValue($value, $textsep, $format, $options);
  }
  /// 3 modes : url, link, name
  function writeXLSPHPOffice(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,$rownum,$colnum,$value,$format=0,$options=['link'=>1]) {
    $v = $this->getSpreadSheetCellValue($value, $options);
    convert_charset($v,TZR_INTERNAL_CHARSET,'UTF-8');
    if (!empty($options['includeimages']) && $value->isImage) {
      $gdImage = imagecreatefromjpeg($GLOBALS['HOME_ROOT_URL'] . $value->resizer
				     . '&density=72&quality=75&meta=0&mime=image/jpeg&geometry=x' . $options['imagesheight'] . '%3E');
      if ($gdImage) {
	$objDrawing = new MemoryDrawing();
	$objDrawing->setName($v);
	$objDrawing->setDescription($v);
	$objDrawing->setImageResource($gdImage);
	$objDrawing->setRenderingFunction(MemoryDrawing::RENDERING_JPEG);
	$objDrawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
	$objDrawing->setWidthAndHeight(imagesx($gdImage), imagesy($gdImage));
	$objDrawing->setWorksheet($worksheet);
	$columnLetter = \PHPExcel_Cell::stringFromColumnIndex($colnum-1);
	$objDrawing->setCoordinates("$columnLetter$rownum")->setResizeProportional(true);
	$worksheet->getColumnDimensionByColumn($colnum)->setAutoSize(true);
	$worksheet->getRowDimension($rownum)->setRowHeight(max(imagesy($gdImage)*0.75 +15, $worksheet->getRowDimension($rownum)->getRowHeight()));
      }
    }
    if (!empty($value->url) && isset($options['url']) && (int)$options['url'] === 1) {
      $worksheet->setCellValueByColumnAndRow($colnum,$rownum,$GLOBALS['HOME_ROOT_URL'].$value->url);
    } else {
      $worksheet->setCellValueByColumnAndRow($colnum,$rownum,is_object($v) ? $v->text : $v);
      if ($options['includeimages'] && $value->isImage && $value->url) $worksheet->getCellByColumnAndRow($colnum, $rownum)->getHyperlink()->setUrl($GLOBALS['HOME_ROOT_URL'] . $value->url);
    }
    if(!empty($value->url) && isset($options['link']))
      $worksheet->getCellByColumnAndRow($colnum,$rownum)->getHyperlink()->setUrl($GLOBALS['HOME_ROOT_URL'].$value->url);
    if(is_array($format))
      $worksheet->getStyleByColumnAndRow($colnum,$rownum)->applyFromArray($format);
  }
  /// Ecriture dans un fichier excel (deprecated);
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=NULL) {
    if(!empty($value->url)){
      $lab=$value->text;
      if(empty($lab)) $lab='Link';
      convert_charset($lab,TZR_INTERNAL_CHARSET,'UTF-8');
      $xl->setCellValueByColumnAndRow($j,$i,$lab);
      $xl->getCellByColumnAndRow($j,$i)->getHyperlink()->setUrl($GLOBALS['HOME_ROOT_URL'].$value->url);
      if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
    }
  }
  /// Ecriture dans un csv (deprecated)
  function writeCSV($o,$textsep){
    $v = $this->getSpreadSheetCellValue($o);
    return $textsep.$v.$textsep;
  }
  /// Affiche dans le mode parcourir
  function my_browse(&$value,&$options,$genid=false) {
    $r=$this->my_display($value,$options,$genid);
    if($this->usemimehtml && !$this->multivalued && !empty($options['admin'])) {
      $r->html=$r->html_preview;
    }
    return $r;
  }

  /// Retourne la taille du fichier
  function getFileSize($r){
    if(empty($r->filesize)) $r->filesize=filesize($r->filename);
    return $r->filesize;
  }

  function my_getJSon($o, $options) {
    if (isset($options['property']))
      return $o->{$options['property']};
    $osimplified=(object)null;
    $notnull=false;

    if(!$this->multivalued) {
      if($o->isExternal){

	$fields=['title','type','externalUrl'];
	$tofields=['title','externalType','externalUrl'];

      }else{
        $fields=['title','mime','url','originalname'];
        $tofields=['title','mimetype','url','originalname'];
      }
      foreach($fields as $i=>$field) {
	if(!empty($o->$field)) {
          $key = $tofields[$i];
	  $osimplified->$key=$o->$field;
	  $notnull=true;
	}
      }
    }
    // Multivalued
    else{
      // $osimplified devient un array qui va contenir des objets
      $osimplified=[];
      foreach($o->catalog as $file){
        $fields=$tofields=[];
        if($o->isExternal){
          $fields[]=['title','type','externalUrl'];
          $tofields[]=['title','externalType','externalUrl'];

        }else{
          $fields[]=['title','mime','url','originalname'];
          $tofields[]=['title','mimetype','url','originalname'];
        }
        foreach($fields as $j=>$subfields) {
          $osimplifiedTmp = (object)null;
          $objecthasproperties=false;
          foreach($subfields as $i => $subfield){
            if(!empty($file->$subfield)) {
              $key = $tofields[$j][$i];
              $osimplifiedTmp->$key=$file->$subfield;
              $notnull=$objecthasproperties=true;
            }
          }
          if($objecthasproperties)
            $osimplified[]=$osimplifiedTmp;
        }
      }
    }
    if(!$notnull) $osimplified=null;
    return $osimplified;
  }

  /// Affichage du champ
  function my_display(&$value,&$options,$genid=false) {
    if($this->multivalued) return $this->my_display_multiple($value,$options,$genid);
    else return $this->my_display_simple($value,$options,$genid);
  }
  protected function my_display_simple(&$value,&$options,$genid=false) {
    global $DATA_DIR;
    $r=$this->_newXFieldVal($options, true);
    $r->type = NULL;
    $r->resizer = NULL;
    $r->mime = NULL;
    $r->isImage = false;
    $r->isVideo = false;

    if($value=='' || $value==TZR_UNCHANGED)
      return $r;

    $r->decoded_raw=json_decode($value);
    if(!$r->decoded_raw) return $r;
    $file=$r->decoded_raw->file;
    $mime=$r->decoded_raw->mime;
    $originalname=$r->decoded_raw->name;
    $title = $r->title = $r->decoded_raw->title;

    $r->isExternal=false;
    if ($this->allow_externalfile && strpos($file, ':') !== false)
      return $this->displayExternal($r, $file, $value, $options);
    if(empty($originalname)) {
      $originalname = 'download';
      $mimeClasse = \Seolan\Library\MimeTypes::getInstance();
      $originalname .= '.'.$mimeClasse->get_extension($mime);
    }
    if (isset($options['_archive']))
      $secondaryroot = str_replace([' ','-',':'], '', $options['_archive']);
    else
      $secondaryroot = null;
    $files=$this->filename($file,false,false,$secondaryroot);
    if(!$files) return $r;
    $filename=$DATA_DIR.$files;
    $originalname_corrected=preg_replace('@([^\._a-zA-Z0-9-]+)@','_',$originalname);
    $downloader=$this->getDownloader($files, $mime, $originalname_corrected, $title, @$options['fmoid']);
    $r->filename=$filename;
    $r->shortfilename=$files;
    $r->mime=$mime;
    $r->title=$title;
    $r->originalname=$originalname;
    $r->raw=$value;
    $r->url=$downloader[0];
    $r->text=$r->title?$r->title:$r->originalname;
    $r->mimepicto=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Mime',$mime, 'both', 'Seolan_Core_Mime', 'default');
    $r->html_default='<a class="tzr-file" href="'.$r->url.'" target="_self">'.$r->mimepicto.htmlspecialchars((empty($title)?$originalname:$title)).'</a>';
    $r->html_preview=$r->html_default;
    $r->html=$r->html_default;
    if(\Seolan\Field\File\File::isVideo($mime)) $this->displayVideo($r,$value,$options);
    elseif(\Seolan\Field\File\File::isImage($mime)) $this->displayImage($r,$value,$options);
    elseif($mime=='application/pdf') $this->displayPDF($r,$value,$options);
    elseif(\Seolan\Field\File\File::isAudio($mime)) $this->displayAudio($r,$value,$options);
    else {
      $r->html = $this->getHtmlViewer($r, $options, false).$r->html;
    }

    return $r;
  }
  function my_display_multiple(&$value,&$options,$genid=false) {
    if(isset($options['fmoid'])) $moid=$options['fmoid'];
    else $moid='';
    $r = $this->_newXFieldVal($options);
    $r->raw=$value;
    $r->text='';
    $r->catalog=[];
    // calcul du nom du fichier associe
    // @renaud : cette ligne est inutile d'après moi
    //@list($retval,$f)=@explode(';',$value);

    if(empty($value) || !is_string($value))
      return $r;

    $json = json_decode($value, true);

    if(empty($json) || empty($json['files'])) return $r;

    $secondaryroot = null;
    if(!empty($options['_archive'])) $secondaryroot = str_replace([' ','-',':'], '', $options['_archive']);

    $dirobject=$this->dirname($json['dir'], $secondaryroot);
    $dirname=$dirobject.'/';
    $r->dirname=$GLOBALS['DATA_DIR'].$dirname;

    $txt='';
    $cols=0;
    $xfile=new \Seolan\Field\File\File();
    if(is_array($json) and isset($json['files'])) {
      $files=$json['files'];
      switch($this->fileorder) {
        case "natural":
          break;
        case "antinatural":
	  $files=array_reverse($files,true);
          break;
        default:
          usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
      }
      foreach($files as $i=>$file) {
        if(is_array($file)) {
          $cols++;
          $r->catalog[$i]=new \Seolan\Core\Field\Value();
          $r->catalog[$i]->table = $r->table;
          $r->catalog[$i]->field = $r->field;
          $r->catalog[$i]->fielddef=$xfile;
          $r->catalog[$i]->filename=$GLOBALS['DATA_DIR'].$dirname.$file['file'];
          $r->catalog[$i]->shortfilename=$dirname.$file['file'];
          $r->catalog[$i]->mime=$file['mime'];
          $r->catalog[$i]->title=$file['title'];
          $r->catalog[$i]->originalname=$file['name'];
          list($url,$fullurl)=$this->getDownloader($dirname.$file['file'],$file['mime'],$file['name'],$file['title'],$moid);
          $r->catalog[$i]->url=$url;
          $r->catalog[$i]->html="$fullurl";
          if(\Seolan\Field\File\File::isImage($file['mime'])){
            $xfile->displayImage($r->catalog[$i],$value,$options);
          } else {
            $viewerText = $this->getHtmlViewer($r->catalog[$i], $options, true);
	    $r->catalog[$i]->html = "{$viewerText}&nbsp;{$r->catalog[$i]->html}";
          }
          $txt.="<div>{$r->catalog[$i]->html}</div>";
          $r->text.=$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$url."\r\n";
        }
      }
    }
    $nb=count($json['files']);
    $oid = $options['oid'];
    $r->html="<div data-field=\"{$this->field}\" data-oid=\"{$oid}\""
	    ." class='tzr-xdirdef-dir browse text-overflow'>{$txt}</div>";
    return $r;
  }
  /// Affichage d'un fichier externe
  function displayExternal(&$r, $file, $value = '', $options = []) {
    $r->file = $file;
    $r->raw = $value;
    list($r->type, $r->id) = explode(':', $file, 2);
    $r->isExternal = true;
    $displayFunction = 'display'.$r->type;
    if (method_exists($this, $displayFunction))
      return $this->$displayFunction($r, $options);
    $r->externalUrl = $r->id;
    return $r;
  }

  /* Image */
  /// Complete l'objet d'affichage dans le cas d'une image
  function displayImage(&$r, &$value, $options = []) {
    $resizer=$this->getResizer($r->shortfilename,$r->mime,$r->originalname, $options);
    if(!empty($resizer) && !empty($resizer['resizer'])) {
      $r->resizer=$resizer['resizer'];
      $r->weresizer=$resizer['weresizer'];
    }
    $r->isImage=true;
    // Si les dimensions de l'image n'existent pas en base, on les lit et on les enregistre
    if((!isset($options['_archive']))
    && ($this->table!='%' && !isset($r->decoded_raw->w) && !empty($options['oid']))){
      $this->getImageSize($r);
      if ($r->fullwidth) {
        $r->decoded_raw->w=$r->fullwidth;
        $r->decoded_raw->h=$r->fullheight;
        getDB()->execute('update '.$this->table.' set UPD=UPD,'.$this->field.'=? where KOID=? and LANG=?',
                       array(json_encode($r->decoded_raw),$options['oid'],\Seolan\Core\Shell::getLangData()));
      }
    }
    $tooltip='';
    if (\Seolan\Core\Shell::admini_mode()){
      $txtTooltip = $this->fileInfos($r, $value, $options, $r, null, null);
      $tooltip=" data-html=\"true\" data-toggle=\"tooltip\" title=\"{$txtTooltip}\" ";
    }
    if($this->usemimehtml){
      $r->html = '<img '.$tooltip.' class="tzr-image" src="'.$r->resizer.'&geometry='.$this->image_geometry.'" alt="'.$r->title.'" title="'.$r->title.'">';
      if ($this->viewlink)
	$r->html.='<br>'.$r->html_default;
    }
    $r->html_preview = '<img class="tzr-image" src="'.$r->resizer.'&geometry='.TZR_THUMB_SIZE.'x'.TZR_THUMB_SIZE.'%3E" alt="'.$r->title.'" title="'.$r->title.'">';
    if ($this->viewlink)
      $r->html_preview.='<br>'.$r->html_default;
  }

  /// Retourne le code html du preview d'une image
  function html_previewImage($r, $options=array()) {
    $options = array_merge(array('title' => $r->title, 'geometry' => $this->thumb_geometry), $options);
    return $this->htmlImage($r, $options);
  }

  /// Retourne le code html d'une image
  function htmlImage($r, $options=array()) {
    $options = array_merge(array('title' => $r->title, 'geometry' => $this->image_geometry), $options);
    $geometry = $options['geometry'];
    $this->getImageSize($r);
    preg_match('/(\d*)x?(\d*)/', $geometry, $matches);
    $w = (integer)$matches[1];
    $h = (integer)$matches[2];
    $ratio = $r->fullheight / $r->fullwidth;
    if (isset($options['gravity'])) { // on peux croper
      if ($ratio < 1) {
        $gw = ($w / $r->fullheight) * $r->fullwidth;
        $gh = $h;
      } else {
        $gw = $w;
        $gh = ($h / $r->fullwidth) * $r->fullheight;
      }
      $geometry = (integer)$gw.'x'.(integer)$gh;
      $crop = '&amp;crop='.$w.'x'.$h.'&amp;gravity='.$options['gravity'];
    } else {
      list($w, $h) = $this->getImageResize($r,$geometry);
      $geometry = $w.'x'.$h;
    }
    return '<img class="tzr-image" src="'.$r->resizer.'&amp;geometry='.$geometry.@$crop.'" '.(!empty($options['title']) ? ' alt="'.@$options['title'].'" title="'.@$options['title'].'"':'').(isset($w)?' width="'.$w.'" height="'.$h.'"':'').'>';
  }

  /**
  * buildPictureTag
  * @param resizer resizer for the image
  * @param alt html attribute
  * @param title html attribute
  * @param srcsetId key of the global sourcesets array. Value is an
  *        array of [media-query] => [resize parameters]
  * @return an html picture Tag for the image's resizer with different source tags
  */
  public static function buildPictureTag(&$params) {
    if (empty($params['alt'])) $alt = 'image';
    else $alt = htmlspecialchars(strip_tags($params['alt']), ENT_QUOTES);
    if (!empty($params['title'])) $title = htmlspecialchars(strip_tags($params['title']), ENT_QUOTES);
    $class = empty($params['class']) ? TZR_FO_IMG_CLASS : $params['class'];

    try {
      if (empty($params['resizer'])) {
        throw new \Exception("Parameter 'resizer' not defined");
      }
      if (empty($GLOBALS['TZR_SRCSETS'])) {
        throw new \Exception('Global Variable TZR_SRCSETS Not defined');
      }
      if (empty($params['srcsetId'])) {
        throw new \Exception("Parameter 'srcsetId' not defined");
      }

      if (isset($GLOBALS['TZR_SRCSETS'][$params["zone"]]) && isset($GLOBALS['TZR_SRCSETS'][$params["zone"]][$params["srcsetId"]])) {
	$srcset =  $GLOBALS['TZR_SRCSETS'][$params["zone"]][$params['srcsetId']];
      } else {
	$srcset =  $GLOBALS['TZR_SRCSETS'][$params['srcsetId']];
      }

      if (empty($srcset)) {
        throw new \Exception("TZR_SRCSETS does not contain any ".$params['srcsetId']." element");
      }

      if (!$GLOBALS['TZR_PACKS']->packDefined('\Seolan\Pack\PictureFill\PictureFill')) {
        $geo = \Seolan\Field\File\File::pictureBuildSrcset($params['resizer'], $srcset['NOSCRIPT'], true);
        $picture = '<img class="'.$class.'" src="'.$geo.'" alt="'.$alt.(!empty($title)?('" title="'.$title):"").'">';
        return $picture;
      }


      if (\Seolan\Core\Shell::admini_mode()) {
        $geo = \Seolan\Field\File\File::pictureBuildSrcset($params['resizer'], $srcset['NOSCRIPT'], true);
        $picture = '<img class="'.TZR_BO_IMG_CLASS.'" src="'.$geo.'" alt="'.$alt.(!empty($title)?('" title="'.$title):"").'">';
        return $picture;
      }

      $eol = '';
      if (!empty($params['beautify'])) {
        $eol = PHP_EOL;
      }

      $picture = '<picture>'.$eol;
      $picture .= '<!--[if IE 9]><video style="display: none;"><![endif]-->'.$eol;
      foreach($srcset as $i => $src) {
        if ($i === 'NOSCRIPT') continue;
        $geo = \Seolan\Field\File\File::pictureBuildSrcset($params['resizer'], $src);
        $picture .= '<source ' . (is_numeric($i)?'':'media="'.$i.'"') . ' srcset="'.$geo.'">'.$eol;
      }
      $picture .= '<!--[if IE 9]></video><![endif]-->'.$eol;
      $geonoscript = \Seolan\Field\File\File::pictureBuildSrcset($params['resizer'], $srcset['NOSCRIPT'], true);


      $urlOptions = ' alt="'.$alt.'"';
      if (!empty($params["tooltipTitle"]) && trim($params["tooltipTitle"]) !== "") {
	$urlOptions .= ' data-placement="'.$params["tooltipPlacement"].'" data-toggle="'.$params["tooltipToggle"].'" data-original-title="'.$params["tooltipTitle"].'"';
      } else {
	$urlOptions .= ' title="'.$title.'"';
      }

      $imgUrl = '<img class="'.$class.'" src="'.$geonoscript.'" '.$urlOptions.'>'.$eol;

      //IE8 support: mettre un source par défaut dans le tag image
      $picture .= $imgUrl;
      $picture .= '</picture>'.$eol;

    } catch (\Exception $e) {
      \Seolan\Core\Logs::critical('buildPictureTag', $e->getMessage());
      $picture = '<img class="'.$class.'" src="'.$params['resizer'].'" alt="'.$alt.(!empty($title)?('" title="'.$title):"").'">';
    }

    return $picture;
  }

  /**
  * buildPictureTag
  * @param array options:
  *    w => width
  *    h => height
  *    onlybigger  => 0/1 : resize only bigger, default 0
  *    onlysmaller => 0/1 : resize only smaller, default 0
  *    fill        => 0/1 : fill given dimensions, default 0, except if crop option is set
  *    crop        => 0/1 : default 0
  *    2x          => 0/1 : whether image source with density '2x' is generated, default 0
  * @param boolean oneSrcOnly: return only 1 density ignoring options['2x']
  * @return string: 'srcset' attribute value for <source> html tag = image source '1x'[, image source '2x']
  */
  public static function pictureBuildSrcset($resizer, &$options, $oneSrcOnly=false) {
    if (empty($options) || empty($resizer)) return "";
    if (!is_array($options)) return $options;

    $options['geometry'] = (!empty($options['w']) ? $options['w']:"") . "x" . (!empty($options['h']) ? $options['h']:"");
    $params = myUrl2cdn($resizer.\Seolan\Field\File\File::_pictureBuildResize($options));

    if (!empty($options['2x']) && !($oneSrcOnly)) {
      $options['geometry'] = (!empty($options['w']) ? intval($options['w'])*2 :"") . "x" . (!empty($options['h']) ? intval($options['h'])*2 :"");
      $params2x = myUrl2cdn($resizer.\Seolan\Field\File\File::_pictureBuildResize($options));
      $params = $params." 1x, ".$params2x." 2x";
    }

    return $params;
  }
  /**
  * _pictureBuildResize
  * @param array options:
  *    'geometry'    => [width]"x"[height]
  *    'onlybigger'  => 0/1 : resize only bigger, default 0
  *    'onlysmaller' => 0/1 : resize only smaller, default 0
  *    'fill'        => 0/1 : fill given dimensions, default 0, except if crop option is set
  *    'crop'        => 0/1 : default 0
  * @return string: ImageMagick encoded url parameters
  */
  private static function _pictureBuildResize($options) {

    $params = "&amp;geometry=".$options['geometry'];
    if ($options['onlybigger']) $params.=urlencode('>');
    elseif ($options['fill']) $params.=urlencode('^');
    elseif ($options['onlysmaller']) $params.=urlencode('<');
    $NOrzOpts = empty($options['onlybigger']) && empty($options['fill']) && empty($options['onlysmaller']);
    if (!empty($options['crop'])) {
        $params.=($NOrzOpts?urlencode('^'):"")."&amp;gravity=Center&amp;crop=".$options['geometry'].urlencode("+0+0");
    }
    return $params;
  }

  /// Retourne le code html
  function completeHTML($r, $options=[], $alt=NULL){
    if (empty($r) || $r->raw == '')
      return '';
    if (is_string($options)) { // from template
      parse_str($options, $options);
    }
    if (count($options)==1 && current($options) == '') // compatibilité
      $options = array('geometry' => key($options));
    if ($r->isExternal) {
      $htmlFunction = 'html'.$r->type;
      if (method_exists($this, $htmlFunction))
        $html = $this->$htmlFunction($r, $options);
    }
    elseif ($r->isImage || $r->isPDF)
      $html = $this->htmlImage($r, $options);
   elseif ($r->isVideo)
      $html = $this->htmlVideo($r, $options);
    elseif ($r->isAudio)
      $html = $this->htmlAudio($r, $options, $alt);
    else
      $html = $r->html_default;

    return $html;
  }
  /// Champ sécurisé
  public function isSecure(){
    return isSecureField($this->table, $this->field);
  }
  /**
   * Retourne l'url des resizer
   * ajoute le sessionid si l'option keep_session le précise
   */
  protected function getResizer($filename,$mime='null/null',$originalname=NULL, $options=[]){
    $mimedst = $mime;
    if ($mime == 'image/x-png') $mime = $mimedst = 'image/png';
    if (!in_array($mime, array('image/gif','image/png','image/x-icon','image/svg+xml'))) $mimedst = 'image/jpeg';
    $mimedst = rawurlencode($mimedst);
    $fmime = '';
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    if($mime=='application/pdf'){
      $fmime=$mime;
    }elseif($mimeClasse->isImage($mime)) {
      $fmime=$mimedst;
    }
    if(!empty($fmime)){
      $params = ['filename'=>$filename,'mime'=>$fmime];
      if($originalname)
	$params['originalname']=$originalname;
      if (isset($options['keep_session']))
	$params['sessionid']=session_id();
      $queryString = http_build_query($params);
      $resizer=TZR_RESIZER.'?'.$queryString;
      $weresizer=TZR_WERESIZER.'?'.$queryString;
    }else{
      $resizer=$weresizer='';
    }
    return array('resizer'=>$resizer,'weresizer'=>$weresizer);
  }
  /**
   * taille d'une image svg
   * width et height non renseignés = 100% = ?
   * versions du svg ?
   *
   */
  protected function getVectorImageSize($filename){
    $doc = new \DomDocument();
    $doc->load($filename);
    $x = new \DomXpath($doc);
    $x->registerNamespace('svg', 'http://www.w3.org/2000/svg');
    $svg = $x->query('//svg:svg');
    if ($svg->length==1){
      $svg = $svg->item(0);
      $width = $svg->getAttribute('width');
      $height = $svg->getAttribute('height');
      if (!empty($height)){
	return [intval($width), intval($height)];
      } else {
	$viewbox = $svg->getAttribute('viewBox');
	if ($viewbox){
	  list($x, $y, $w, $h) = explode(' ', $viewbox);
	  return [intval($w), intval($h)];
	} else {
	  return [TZR_MEDIA_THUMB_SIZE, TZR_MEDIA_THUMB_SIZE];
	}
      }
    }
    unset($xpath);
    unset($doc);
    return [TZR_MEDIA_THUMB_SIZE, TZR_MEDIA_THUMB_SIZE];
  }
  /// Retourne la taille en px d'une image
  function getImageSize(&$r,$dim=NULL){
    if(!isset($r->fullwidth)){
      if(isset($r->decoded_raw->w)) $t=array($r->decoded_raw->w,$r->decoded_raw->h);
      elseif($r->isImage){ $t=getimagesize($r->filename);}
      elseif($r->isVideo) $t=getimagesize($r->filename.'-fullsizeimage');
      elseif($r->isPDF) {
        if (!file_exists($r->filename.'-fullsizeimage'))
          exec(TZR_MOGRIFY_RESIZER . " " . escapeshellarg($r->filename. "[0]")." ".escapeshellarg("jpeg:" . $r->filename . "-fullsizeimage"));
        $t=getimagesize($r->filename."-fullsizeimage");
      }
      if (!is_array($t)){
	$t=[0,0];
      }
      $r->fullwidth=$t[0];
      $r->fullheight=$t[1];
    }
    if($dim) return $r->$dim;
  }

  /// Recupere la largeur ou la hauteur finale d'une image en fonction d'une géométrie passée au resizer
  function getImageResize($r,$geometry,$dim=null){
    if (empty($r->_cache['size'][$geometry])){
      $this->getImageSize($r);
      $t=array($r->fullwidth,$r->fullheight);
      $oratio=$t[0]/$t[1];
      $size=explode('x',$geometry);
      $size[0]=(int)$size[0];
      $size[1]=(int)$size[1];
      if(empty($size[0])) $size[0]=0;
      if(empty($size[1])) $size[1]=0;
      if($t[0]<=$size[0] && $t[1]<=$size[1]){
        $w=$r->fullwidth;
        $h=$r->fullheight;
      }else{
        $ratio=$size[0]/$size[1];
        if($oratio>$ratio || empty($size[1])){
          $w=$size[0];
          $h=round($t[1]*$size[0]/$t[0]);
        }else{
          $w=round($t[0]*$size[1]/$t[1]);
          $h=$size[1];
        }
      }
      $r->_cache['size'][$geometry]=array('width'=>$w,'height'=>$h);
    }
    if ($dim != null){
      return $r->_cache['size'][$geometry][$dim];
    } else {
      return [$r->_cache['size'][$geometry]['width'], $r->_cache['size'][$geometry]['height']];
    }
  }
  /// Retourne une propriété iptc ou l'objet iptc de l'image
  static function getIPTC($r,$prop=NULL){
    if($r->mime=='image/jpeg'){
      if(empty($r->meta_data)) $r->meta_data=\Seolan\Field\File\File::loadMeta($r->filename);
      if(!$prop) return $r->meta_data;
      else return $r->meta_data->getIPTCProperty($prop);
    }
    return NULL;
  }
  /// Retourne une propriété exif ou l'objet exif de l'image
  function getEXIF($r,$prop=NULL){
    if($r->mime=='image/jpeg'){
      if(!isset($r->exif_data)){
        $tmp=exif_read_data($r->filename);
        if($tmp){
          foreach($tmp as $n=>$v){
            $r->exif_data[$n]=(object)array('raw'=>$v);
          }
        }else{
          $r->exif_data=array();
        }
      }
      if(!$prop) return $r->exif_data;
      else return $r->exif_data[$prop];
    }
    return NULL;
  }
  /// Retourne une propriété XMP ou l'objet XMP de l'image
  static function getXMP($r,$prop=NULL){
    if($r->mime=='image/jpeg'){
      if(empty($r->meta_data)) $r->meta_data=\Seolan\Field\File\File::loadMeta($r->filename);
      if(!$prop) return $r->meta_data;
      else return $r->meta_data->getXMPProperty($prop,array('Alt'=>array('lang'=>array(\Seolan\Core\Shell::getLangData(),'x-default'))));
    }
    return NULL;
  }
  /// Charge l'objet meta du fichier
  static function loadMeta($file){
    return new \Seolan\Library\MetaAnalyser($file);
  }
  /// Types texte / text vectoriel
  static function isVectorImage($type){
    \Seolan\Core\Logs::debug(__METHOD__.$type);
    return in_array($type, self::$vectorTypes);
  }
  /// Redimensione une image
  function resizeImage($file,$geo=null,$autorotate=false){
    if(!$geo) return false;
    // Verifie si l'image est plus grande qua la taille max ou pas
    $s=getimagesize($file);
    list($w,$h)=explode('x',$geo);
    if(!is_numeric($w)) $w=99999;
    if(empty($h) || !is_numeric($h)) $h=99999;
    if($s[0]<$w && $s[1]<$h) return false;
    // Redimensionne l'image
    $geo=rawurldecode($geo);
    exec(TZR_MOGRIFY_RESIZER.($autorotate?' -auto-orient ':'').' -resize '.escapeshellarg($geo).' '.escapeshellarg($file).' '.escapeshellarg($file));
    return true;
  }

  /// Oriente l'image en fonction des données exif
  function rotateAndFlip($filename){
    exec(TZR_MOGRIFY_RESIZER.' -auto-orient '.escapeshellarg($filename).' '.escapeshellarg($filename), $r, $ret);
    return true;
  }

  /* Video */
  /// Complete l'objet d'affichage dans le cas d'une video
  function displayVideo(&$r, &$value, &$options = []){
    $r->isVideo=true;
    $r->resizer=TZR_VIDEOCONVERT.'?filename='.$r->shortfilename;
    $r->preview=TZR_VIDEOCONVERT.'?preview=true&filename='.$r->shortfilename;
    $this->getImageSize($r);
    $r->originalGeometry = $r->fullwidth.'x'.$r->fullheight;

    if ($this->usemimehtml) {
      $r->html_preview = $this->html_previewVideo($r, $options);
      $r->html = $this->htmlVideo($r, $options);
      if ($this->viewlink) {
        $r->html .= '<br>'.$r->html_default;
        $r->html_preview .= '<br>'.$r->html_default;
      }
    } else
      $r->html = $r->html_preview;
  }
  /// Code html du preview d'une vidéo
  function html_previewVideo($r, $options=array()) {
    $options = array_merge(array('title' => $r->title, 'geometry' => $this->thumb_geometry), $options);
    $geometry = \Seolan\Field\File\File::videoGetGeometry($r->filename, $options['geometry']);
    list($width, $height) = explode('x', $geometry);
    return '<img class="tzr-image" src="'.$r->preview.'&geometry='.$geometry.'" alt="'.$options['title'].'" title="'.$options['title'].'" width="'.$width.'" height="'.$height.'">';
  }
  /// Code html de l'affichage d'une vidéo
  function htmlVideo(&$r, $options=array(1=>1)) {
    $oriGeom =@$options['geometry'];
    $options = array_merge(array('title' => $r->title, 'geometry' => $this->video_geometry, 'video_bitrate' => $this->video_bitrate, 'autoplay' => $this->autoplay, 'muted' => $this->muted, 'fullscreen' => 1), $options);
    $geometry = \Seolan\Field\File\File::videoGetGeometry($r->filename, $options['geometry']);
    list($width, $height) = explode('x', $geometry);
    $r->video_bitrate = $options['video_bitrate'];
    if ($this->videoReady($r, $geometry, true)) {
      $html = "<!-- $oriGeom {$this->video_geometry} $geometry {$r->preview} -->";
      $html .= '
        <div class="tzr-video-div">
          <video controlsList="nodownload" id="'.$r->varid.'" class="tzr-video" '.($options['autoplay'] ? ' autoplay="autoplay"' : '').($options['muted'] ? ' muted="muted"' : '').' width="100%" height="100%" controls preload="True">';
        $isSecure = (!empty($GLOBALS['TZR_SECURE']['_all']) || !empty($GLOBALS['TZR_SECURE'][$this->table][$this->field])
                      || (!empty($GLOBALS['TZR_SECURE'][$this->table]) && $GLOBALS['TZR_SECURE'][$this->table] === '_all'));

      if ($isSecure) {
        if (!issetSessionVar('protect_video', 'SECU_VIDEO') || (int) getSessionVar('protect_video', 'SECU_VIDEO')===0) {
          setSessionVar('protect_video', 1, 'SECU_VIDEO');
        } else {
          setSessionVar('protect_video', (int) getSessionVar('protect_video')+1, 'SECU_VIDEO');
        }
        setSessionVar('x'.$r->filename.getSessionVar('protect_video', 'SECU_VIDEO'), 0, 'SECU_VIDEO');
        setSessionVar('file'.getSessionVar('protect_video', 'SECU_VIDEO'), md5('XsaltoProtectVideo').base64_encode(base64_encode($r->filename)), 'SECU_VIDEO');
        setSessionVar('enable_play', false, 'SECU_VIDEO');
      }
      foreach (\Seolan\Field\File\File::$html5_video_format as $format => $opts) {
        $html .= '
          <source src="'.$r->resizer.'&geometry='.$geometry.'&bitrate='.$options['video_bitrate'].'&format='.$format.'&type=.'.$format.($isSecure ? '&_pv='.getSessionVar('protect_video', 'SECU_VIDEO') : '').'"  type="video/'.$format.'"/>';
      }
      $html .= $this->htmlVideo_getTracks($r, $options);
      $html .= '</video>';
      $html .= $this->htmlVideo_getJs($r, $options, $isSecure, $width, $height);
      $html .= '</div>';
    } else {
      $html = '<img src="'.$r->preview.'&geometry='.$geometry.'" alt="'.$title.'" title="'.$title.'" width="100%" height="100%" style="max-width:'.$width.'px">';
    }
    return $html;
  }

  // Utilisé dans le type de champ "Video"
  function htmlVideo_getTracks($r, $options) {
    return "";
  }

  function htmlVideo_getJs($r, $options, $isSecure, $width, $height) {
    $html = '
        <script>
          jQuery("#'.$r->varid.'").mediaelementplayer({pluginPath: "'.\Seolan\Pack\MediaElement\MediaElement::ressourcepath().'", showPosterWhenEnded:true, defaultVideoWidth:"'.$width.'", defaultVideoHeight:"'.$height.'"});
        </script>';

    if ($isSecure) {
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

  /// Determine si le fichier est directement disponible dans le format.
  /// Si 'force' a true et fichier non disponible, lance la tache de conversion.
  function videoReady($r,$geometry,$forceConvert=true) {
    if(!\Seolan\Field\File\File::isVideo($r->mime))
      return false;
    $ready = true;
    $ffmpeg = '';
    // affichage en 100% un seul encodage
    $geometry = \Seolan\Field\File\File::videoGetGeometry($r->filename, $geometry);
    foreach (\Seolan\Field\File\File::$html5_video_format as $format => $opts) {
      $hash = md5($geometry.$r->video_bitrate.'video/'.$format);
      $filename_cache = $r->filename.'-'.$hash.'-cache';
      if (!file_exists($filename_cache) || (filemtime($r->filename)>filemtime($filename_cache))) {
        $ready = false;
        if ($forceConvert) {
          $ffmpeg .= FFMPEG." -y -nostats -i {$r->filename} {$opts['ffmpeg_opts']} -vb {$r->video_bitrate}k -s $geometry $filename_cache ;\n";
        }
      }
    }
    if ($ffmpeg) {
      $s=new \Seolan\Module\Scheduler\Scheduler(array('tplentry'=>TZR_RETURN_DATA));
      $s->createIdleShellJob(md5($r->filename),'Video encoding',$ffmpeg,'');
    }
    return $ready;
  }
  /// Affichage d'une video youtube
  function displayYoutube(&$r, $options = []) {
    $r->videoid = $r->id;
    $r->videourl = 'https://www.youtube.com/watch?v='.$r->videoid;
    $r->isVideo = true;
    $r->videoReady = true;
    $r->resizer = '';
    $r->externalUrl = 'https://youtu.be/'.$r->videoid;
    $r->embedUrl = 'https://www.youtube.com/embed/' . $r->videoid;
    $r->preview = array(
      'width' => 480,
      'height' => 360,
      'url' => 'https://img.youtube.com/vi/'.$r->videoid.'/0.jpg');
    $r->fullwidth = 1600;
    $r->fullheight = 900;
    $r->html_preview = $this->html_previewYoutube($r, $options);
    if ($this->usemimehtml)
      $r->html = $this->htmlYoutube($r, $options);
    else
      $r->html = $r->html_preview;
    return $r;
  }

  /// html du preview d'une video youtube
  function html_previewYoutube($r, $options=array()) {
    if (isset($options['geometry']))
      list($width, $height) = explode('x', str_replace('>', '', $options['geometry']));
    else
      $width = $height = TZR_THUMB_SIZE;
    $margin = (int) -($width * $r->preview['height']/$r->preview['width'] - $height) /2;
    return '<div style="width:'.$width.'px;height:'.$height.'px;overflow:hidden;display:inline"><img src="'.$r->preview['url'].'" alt="'.$r->title.'" width="'.$width.'" class="tzr-externalfile" rel="'.$r->file.'"'.($margin<0?'style="margin-top:'.$margin.'px"':'').'></div>';
  }
  /// html d'une video youtube
  function htmlYoutube($r, $options=array()) {
    if(is_array($options))
      $options = array_merge(array('autoplay' => $this->autoplay, 'muted' => $this->muted, 'geometry' => $this->video_geometry), $options);
    else
      $options = array('autoplay' => $this->autoplay, 'geometry' => $this->video_geometry);

    if (!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Module\Module::moduleExists('', XMODTARTEAUCITRON_TOID)) {
      $options['rel'] = 0;
      $options['width'] = $width;
      $options['height'] = $height;

      return self::html_from_video_url($r->embedUrl, $options);
    }

    return '<div class="flex-video widescreen"><iframe frameborder="0" width="100%" height="100%" id="'.$r->varid.'" src="'.$r->embedUrl.'?enablejsapi=1&origin='.$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'&wmode=transparent&rel=0'.($options['autoplay']?'&autoplay=1':'').($options['muted']?'&mute=1':'').'" allowfullscreen></iframe></div>';
  }

  /**
   * Affichage d'une video Daily Motion
   * NOTE Les paramètres n'étaient pas dans le même ordre que les autres fonction displayXXX (soit $r, $value, $options)
   */
  function displayDailyMotion(&$r, $options = []) {
    $videoid = substr($r->raw, 12);
    $r->videoid = $r->id;
    $r->videourl = 'https://www.dailymotion.com/video/'.$r->videoid;
    $r->isVideo = true;
    $r->videoReady = true;
    $r->resizer = '';
    if (!$videoEntry = file_get_contents('https://api.dailymotion.com/video/'.$r->videoid.'?fields=allow_embed,url,embed_url,thumbnail_medium_url,title')) {
      \Seolan\Core\Logs::notice('\Seolan\Field\File\File::displayDailyMotion', "Error : cannot retreive video information ");
      $r->error = true;
      return $r;
    }
    $videoEntry = json_decode($videoEntry);
    if ($videoEntry->error) {
      \Seolan\Core\Logs::notice('\Seolan\Field\File\File::displayDailyMotion', 'Error : ' . $videoEntry->error->message);
      $r->error = true;
      return $r;
    }
   if (!$videoEntry->allow_embed) {
      \Seolan\Core\Logs::notice('\Seolan\Field\File\File::displayDailyMotion', 'Error : '.$r->videoid.' embed not allowed');
      return $r;
    }
    $r->preview = array(
      'url' => $videoEntry->thumbnail_medium_url,
      'width' => 160,
      'height' => 120
    );
    $r->title = $videoEntry->title;
    $r->externalUrl = $videoEntry->url;
    $r->embedUrl = $videoEntry->embed_url;
    $r->html_preview = $this->html_previewDailyMotion($r, $options);
    if ($this->usemimehtml)
      $r->html = $this->htmlDailyMotion($r, $options);
    else
      $r->html = $r->html_preview;
    return $r;
  }
  /// html du preview d'une video Daily Motion
  function html_previewDailyMotion($r, $options=array()) {
    return $this->html_previewYoutube($r, $options=array());
  }
  /// html d'une video Daily Motion
  function htmlDailyMotion($r, $options=array()) {
    return $this->htmlYoutube($r, $options=array());
  }

  /// Affichage d'une video vimeo
  function displayVimeo(&$r, $options = []) {
    $r->videoid = $r->id;
    $r->videourl = 'https://vimeo.com/'.$r->videoid;
    $r->isVideo = true;
    $r->videoReady = true;
    $r->resizer = '';
    $r->externalUrl = 'https://vimeo.com/'.$r->videoid;
    $r->embedUrl = 'https://player.vimeo.com/video/'.$r->videoid;
    $r->flashplayer = '';
    $video = json_decode(file_get_contents('https://vimeo.com/api/v2/video/'.$r->videoid.'.json'));
    $r->preview = array('url' => $video[0]->thumbnail_medium);
    $r->title = $video[0]->title;
    $r->error = false;
    $r->fullwidth = $video[0]->width;
    $r->fullheight = $video[0]->height;
    $r->more = $video[0];
    $r->html_preview = $this->html_previewVimeo($r, $options);
    if ($this->usemimehtml)
      $r->html = $this->htmlVimeo($r, $options);
    else
      $r->html = $r->html_preview;
    return $r;
  }
  /// html du preview d'une video Vimeo
  function html_previewVimeo($r, $options=array()) {
    return $this->html_previewYoutube($r, $options);
  }
  /// html d'une video Vimeo
  function htmlVimeo($r, $options=array()) {
    return $this->htmlYoutube($r, $options);
  }


  /// Gestion de la geometrie d'une video
  static function videoGetGeometry($filename,$geometry){
    if(strpos($filename,$GLOBALS["DATA_DIR"])===false)  $filename=$GLOBALS['DATA_DIR'].$filename;
    $geometry=rawurldecode($geometry);
    if(substr($geometry,-1)=='>')
      $geometry=substr($geometry,0,-1);
    $filefullsizeimage=$filename."-fullsizeimage";
    if(!($t=@getimagesize($filefullsizeimage))){
      exec(FFMPEG." -y -i ".escapeshellarg($filename)." -f image2 -vcodec mjpeg -vframes 1 -an ".escapeshellarg($filefullsizeimage));
      $t=@getimagesize($filefullsizeimage);
    }
    $oratio=$t[0]/$t[1];
    $size=explode('x',$geometry);
    if (empty($size) || empty($size[0]) || empty($size[1])){
      $size = [$t[0], $t[1]];
    }
    $ratio=$size[0]/$size[1];
    if (!$size[0] || !$size[1]) {
      $size[0] = $t[0];
      $size[1] = $t[1];
      $ratio = $size[0]/$size[1];
    }
    if($oratio>$ratio){
      $w=$size[0];
      $h=round($t[1]*$size[0]/$t[0]);
    }else{
      $w=round($t[0]*$size[1]/$t[1]);
      $h=$size[1];
    }
    if($w%2) $w--;
    if($h%2) $h--;
    $geometry=$w.'x'.$h;
    return $geometry;
  }

  /* Audio */
  /// Complete l'objet d'affichage dans le cas d'un fichier audio
  function displayAudio(&$r, &$value, &$options = []) {
    $r->isAudio=true;
    $r->audio_bitrate = $this->audio_bitrate;
    $r->prehear = TZR_AUDIOCONVERT.'?prehear=true&filename='.$r->shortfilename.'&type=.mp3';
    $r->fullaudio = TZR_AUDIOCONVERT.'?filename='.$r->shortfilename.'&type=.mp3';
    if (defined('HTML5MEDIA')) {
      $r->prehear_ogg = TZR_AUDIOCONVERT.'?mime=audio/ogg&prehear=true&filename='.$r->shortfilename.'&type=.ogg';
      $r->fullaudio_ogg = TZR_AUDIOCONVERT.'?mime=audio/ogg&filename='.$r->shortfilename.'&type=.ogg';
    }
    if ($this->usemimehtml) {
      $r->html_preview = $this->html_previewAudio($r, $options);
      $r->html = $this->htmlAudio($r, $options);
      if ($this->viewlink) {
        $r->html .= '<br>'.$r->html_default;
        $r->html_preview .= '<br>'.$r->html_default;
      }
    } else
      $r->html = $r->html_preview;
    return $r;
  }

  /// html preview audio, $alt est un media alternatif (image)
  function html_previewAudio($r, $options=array(), $alt='') {
    // ? à voir ce que preview image fat dans preview audio ?
    if ($alt && isset($alt->raw)){
      $options = array_merge([
	'title' => $r->title,
	'geometry' => $this->thumb_geometry
      ], $options);
      return $this->html_previewImage($alt, $options);
    }
    return \Seolan\Core\Labels::getSysLabel('Seolan_Core_Mime',$r->mime, 'csico');
  }
  /// html audio, $alt est un media alternatif (image)
  function htmlAudio(&$r, $options=array(), $alt='') {
    $options = array_merge(array('audio_bitrate' => $this->audio_bitrate, 'audio_geometry' => $this->audio_geometry, 'geometry' => $this->geometry, 'autoplay' => $this->autoplay), $options);
    list($width, $height) = explode('x', str_replace('>', '', $options['audio_geometry']));
    list($altwidth, $altheight) = explode('x', str_replace('>', '', $options['geometry']));
    if ($options['audio_bitrate'] != $this->audio_bitrate) {
      $r->audio_bitrate = $options['audio_bitrate'];
      $this->displayAudio($r);
    }
    if ($alt && is_object($alt) && $alt->raw) {
      $options['geometry'] = $altwidth.'x'.($altheight-$height);
      $img = '<div class="tzr-audio-alt">' . $this->htmlImage($alt, $options) . '</div>';
    }
    if (defined('HTML5MEDIA')) {
      if ($this->audioReady($r, true))
        $audio = '
          <audio id="'.$r->varid.'" class="tzr-audio" '.($options['autoplay'] ? ' autoplay="autoplay"' : '').' controls="controls" controlsList="nodownload" height="'.$height.'" width="'.$width.'" style="height:'.$height.'px;width:'.$width.'px" preload="none">
            <source src="'.$r->fullaudio_ogg.'" type="audio/ogg" />
            <source src="'.$r->fullaudio.'" type="audio/mp3" />
          </audio>';
      else
        $audio = '
          <audio id="'.$r->varid.'" class="tzr-audio" '.($options['autoplay'] ? ' autoplay="autoplay"' : '').' controls="controls" controlsList="nodownload" height="'.$height.'" width="'.$width.'" style="height:'.$height.'px;width:'.$width.'px">
            <source src="'.$r->prehear_ogg.'" type="audio/ogg" />
            <source src="'.$r->prehear.'" type="audio/mp3" />
          </audio>';
      $html = '
        <div class="tzr-audio-div">
          '.@$img.$audio.'
          <script type="text/javascript">
            /*jQuery("#'.$r->varid.'").mediaelementplayer({pluginPath: "'.TZR_SHARE_URL.'js/jmediaelement/build/"});*/
            jQuery("#'.$r->varid.'").mediaelementplayer({pluginPath:"/csx/src/Pack/MediaElement/public"});
          </script>
        </div>';
    }
    else
      $html = @$img.'<div style="height:27px;width:'.$width.'px;"><div id="'.$r->varid.'"></div></div><script type="text/javascript">swfobject.embedSWF("'.TZR_SHARE_URL.'flash/mpw_player.swf","'.$r->varid.'","'.$width.'","27","8","",{mp3:"'.urlencode(($this->audioReady($r,true)?$r->fullaudio:$r->prehear.'&bitrate='.$r->audio_bitrate)).'",autoplay:'.($options['autoplay']?'1':'0').'},{bgcolor:"000000"});</script>';
    return $html;
  }
  /// Determine si le fichier est directement disponible dans la format. Si 'force' a true et fichier non disponible, lance la tache
  function audioReady($r, $forceConvert=true){
    if(!\Seolan\Field\File\File::isAudio($r->mime))
      return false;
    $ready = true;
    $filename_cache = $r->filename.'-'.md5($r->audio_bitrate.'audio/mpeg').'-cache';
    if(!file_exists($filename_cache) || (filemtime($r->filename)>filemtime($filename_cache))){
      $ready = false;
      if($forceConvert){
        $ffmpeg = FFMPEG.' -y -nostats -i '.$r->filename.' -b '.$r->audio_bitrate.'k -ar 44100 -f mp3 '.$filename_cache.';';
      }
    }
    if (defined('HTML5MEDIA')) {
      $filename_cache=$r->filename.'-'.md5($r->audio_bitrate.'audio/ogg').'-cache';
      if(!file_exists($filename_cache) || (filemtime($r->filename)>filemtime($filename_cache))){
        $ready = false;
        if($forceConvert){
          $ffmpeg .= FFMPEG.' -y -nostats -i '.$r->filename.' -b '.$r->audio_bitrate.'k  -acodec libvorbis -f ogg '.$filename_cache;
        }
      }
    }
    if ($ffmpeg) {
      $s=new \Seolan\Module\Scheduler\Scheduler(array('tplentry'=>TZR_RETURN_DATA));
      $s->createIdleShellJob(md5($r->filename),'Audio encoding',$ffmpeg,'');
    }
    return $ready;
  }
  /// Renvoie la duree d'une video ou d'un audio en secondes
  function getDuration($file,$mime){
    if(!$file || !file_exists($file)) return 0;
    elseif(\Seolan\Field\File\File::isVideo($mime) || \Seolan\Field\File\File::isAudio($mime)){
      $ffmpeg=exec(FFMPEG.' -i '.escapeshellarg($file).' 2>&1|grep "Duration"');
      $duree = preg_replace('/.*Duration:\s+(\d{2}:\d{2}:\d{2}.\d{2}).*/', '$1', $ffmpeg);
      if(!empty($duree)) list($h,$m,$s)=explode(':',$duree);
      else return 0;
      if($h && $m && $s) return intval($h)*3600+intval($m)*60+intval($s);
    }
    return 0;
  }

    /* PDF */
    /// Complete l'objet d'affichage dans le cas d'une image
    function displayPDF(&$r, &$value, &$options = []) {
      $resizer=$this->getResizer($r->shortfilename,$r->mime,$r->originalname, $options);
      if(!empty($resizer) && !empty($resizer['resizer'])) {
        $r->resizer=$resizer['resizer'];
            $r->weresizer=$resizer['weresizer'];
      }
      $r->isPDF = true;
      $htmlViewer =  $this->getHtmlViewer($r, $options, false);

      if($this->usemimehtml && !empty($r->resizer)){
        $preview = '<img class="tzr-image" src="'.$r->resizer.'&geometry='.TZR_THUMB_SIZE.'x'.TZR_THUMB_SIZE.'%3E" alt="'.$r->title.'" title="'.$r->title.'">';
        $r->html_preview = $r->html = '<a href="'.$r->url.'" class="tzrfile">'.$preview.'</a>';
        if ($this->viewlink){
          $r->html.="<br>{$htmlViewer}{$r->html_default}";
          $r->html_preview.='<br>'.$r->html_default;
        }
      } else {
	$r->html = $htmlViewer.$r->html_default;
      }
      return $r;
    }

  /// Affichage d'un pdf Calaméo
  function displayCalameo(&$r, $options = []) {
    $book_id = substr($r->file, 8);
    $params = array(
      'action' => 'API.getBookInfos',
      'apikey' => CALAMEO_PUBLIC_KEY, // a définir dans le local.php
      'book_id' => $book_id,
      'output' => 'PHP'
    );
    $url = 'https://api.calameo.com/1.0?';
    $signature = CALAMEO_SECRET_KEY; // a définir dans le local.php
    foreach ($params as $key => $value) {
      $url .= "$key=$value&";
      $signature .= "$key$value";
    }
    $url .= 'signature=' . md5($signature);
    $response = unserialize(file_get_contents($url));
    $response = $response['response'];
    if ($response['status'] == 'error') {
      \Seolan\Core\Logs::notice(__CLASS__.'::'.__FUNCTION__, "error code {$response['error']['code']}, {$response['error']['message']}");
      $r->error = true;
      return $r;
    }
    $r->error = false;
    $r->isCalameo = true;
    $r->mime = 'application/pdf';
    $r->resizer = '';
    $r->preview = $response['content']['ThumbUrl'];
    $r->title = $response['content']['Name'];
    $r->externalUrl = $response['content']['ViewUrl'];
    $r->downloadUrl = str_replace('read', 'download', $response['content']['ViewUrl']);
    $r->fullwidth = $response['content']['Width'];
    $r->fullheight = $response['content']['Height'];
    $r->dltarget = 'calameo'; // target pour le lien download, si pdf non dispo
    $r->html_preview = $this->html_previewCalameo($r, $options);
    if ($this->usemimehtml)
      $r->html = $this->htmlCalameo($r, $options);
    else
      $r->html = $r->html_preview;
    return $r;
  }

  /// html preview calaméo
  function html_previewCalameo($r, $options=array()) {
    if ($r->error)
      return '';
    if (isset($options['geometry'])) {
      list($width, $height) = explode('x', str_replace('>', '', $options['geometry']));
      $width = (integer)$width;
      $height = (integer)$height;
      $ratio = min($width / $r->fullwidth, $height / $r->fullheight);
      $width = (integer)($ratio * $r->fullwidth);
      $height = (integer)($ratio * $r->fullwidth);
    } else {
      $width = TZR_THUMB_SIZE;
      $height = TZR_THUMB_SIZE;
    }
    return '<img src="'.$r->preview.'" width="'.$width.'" height="'.$height.'" alt="'.$r->title.'" class="tzr-externalfile" rel="'.$r->file.'">';
  }

  /// html calaméo
  function htmlCalameo($r, $options=array()) {
    if ($r->error)
      return '';
    $geometry = @$options['geometry'] ? $options['geometry'] : $this->image_geometry;
    list($width, $height) = explode('x', $geometry);
    $width = (integer)$width;
    $height = (integer)$height;
    return '<iframe id="'.$r->varid.'" src="'.$r->externalUrl.'" wmode="transparent" style="width:'.$width.'px;height:'.$height.'px"></iframe>';
  }

  /// Tag les valeurs IPTC/XMP d'un fichier via un tableau de donnée
  static function setFileMetaWithArray($data,$file,$tmpfile=NULL,$clean=false){
    if(empty($file)) return false;
    if($tmpfile=='auto'){
      $infos=pathinfo($file);
      $tmpfile=TZR_TMP_DIR.uniqid('metafile').'.'.$infos['extension'];
      copy($file,$tmpfile);
    }elseif($tmpfile){
      copy($file,$tmpfile);
    }else{
      $tmpfile=$file;
    }

    if($clean) \Seolan\Field\File\File::cleanFileMeta($tmpfile);

    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $mime=$mimeClasse->getValidMime(NULL,$tmpfile,NULL);
    if($mime=='image/jpeg'){
      $meta=\Seolan\Field\File\File::loadMeta($tmpfile);
      if(!empty($data['IPTC'])){
	foreach($data['IPTC'] as $tag=>$value){
	  $meta->setIPTCProperty($tag,$value);
	}
      }
      if(!empty($data['XMP'])){
	foreach($data['XMP'] as $tag=>$value){
	  $meta->setXMPProperty($tag,$value);
	}
      }
      $meta->save();
    }
    return $tmpfile;
  }

  /// Tag les valeurs IPTC/XMP d'un fichier via un template
  static function setFileMetaWithTemplate($tpl,$file,$tmpfile=NULL,$standards=array('IPTC','XMP'),$clean=false){
    if(empty($file)) return false;
    if($tmpfile=='auto'){
      $infos=pathinfo($file);
      $tmpfile=TZR_TMP_DIR.uniqid('metafile').'.'.$infos['extension'];
      copy($file,$tmpfile);
    }elseif($tmpfile){
      copy($file,$tmpfile);
    }else{
      $tmpfile=$file;
    }

    if($clean) \Seolan\Field\File\File::cleanFileMeta($tmpfile);

    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $mime=$mimeClasse->getValidMime(NULL,$tmpfile,NULL);
    if($mime=='image/jpeg' && !empty($standards)){
      $tplmeta=\Seolan\Field\File\File::loadMeta($tpl);
      $meta=\Seolan\Field\File\File::loadMeta($tmpfile);
      // Traitement des tags IPTC
      if(in_array('IPTC',$standards)){
	$tags=$tplmeta->getIPTCAll();
	foreach($tags as $tag=>$value){
	  $meta->setIPTCProperty($value['IPTC_Type'],$value['RecData']);
	}
      }

      // Traitement des tags XMP
      if(in_array('XMP',$standards)){
	$tags=$tplmeta->xmp_xpath->query($tplmeta->xmp_descr_path.'/'.$tplmeta->xmp_descr_node.'/*');
	foreach($tags as $tag){
	  list($prefix,$foo)=explode(':',$tag->nodeName);
	  if($prefix!='dc') continue;
	  $n=$meta->xmp_xpath->query($meta->xmp_descr_path.'/'.$meta->xmp_descr_node.'/'.$tag->nodeName);
	  $p=NULL;
	  if($n->length){
	    $p=$n->parentNode;
	    if($p) $p->removeChild($n);
	  }else{
	    $d=$meta->getXMPDescriptionFromPrefix($prefix);
	    if(empty($d)) $d=$meta->addXMPDescription($prefix,$tag->parentNode->lookupNamespaceURI($prefix));
	    $p=$d;
	  }
	  if($p) $p->appendChild($meta->xmp_dom->importNode($tag,true));
	}
      }
      $meta->save();
    }
    return $tmpfile;
  }

  /// Tag les valeurs IPTC/XMP d'un fichier via un display
  static function setFileMetaWithDisplay($d,$file,$tmpfile=NULL,$standards=array('IPTC','XMP'),$clean=false){
    if(empty($file)) return false;
    if($tmpfile=='auto'){
      $infos=pathinfo($file);
      $tmpfile=TZR_TMP_DIR.uniqid('metafile').'.'.$infos['extension'];
      copy($file,$tmpfile);
    }elseif($tmpfile){
      copy($file,$tmpfile);
    }else{
      $tmpfile=$file;
    }

    if($clean) \Seolan\Field\File\File::cleanFileMeta($tmpfile);

    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $mime=$mimeClasse->getValidMime(NULL,$file,NULL);
    if($mime=='image/jpeg' && !empty($standards)){
      $meta=\Seolan\Field\File\File::loadMeta($tmpfile);
      if(!isset($d['fields_object'])){
	foreach($d as $i=>&$dd){
	  foreach($dd['fields_object'] as $i=>&$f){
	    $fdef=&$f->fielddef;
	    $fdef->setMetaFromValue($meta,$dd['o'.$fdef->field],$standards);
	  }
	}
      }else{
	foreach($d['fields_object'] as $i=>&$f){
	  $fdef=&$f->fielddef;
	  $fdef->setMetaFromValue($meta,$d['o'.$fdef->field],$standards);
	}
      }
      $meta->save();
    }
    return $tmpfile;
  }

  /// Nettoie tous les metas d'un fichier
  static function cleanFileMeta($file){
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $mime=$mimeClasse->getValidMime(NULL,$file,NULL);
    if($mime=='image/jpeg'){
      system(TZR_MOGRIFY_RESIZER.' +profile "!exif,*" '.escapeshellarg($file).' '.escapeshellarg($file).';'. TZR_MOGRIFY_RESIZER.' +profile "*" '.escapeshellarg($file."_ld")." ".escapeshellarg($file.'_ld'));
    }
  }
  /**
   * Géneration du nom du fichier
   * + check et création éventuelle du répertoire associé
   * + prise en compte de l'archive (secondaryroot)
   */
  function filename($value,$test=false,$testfinal=false,$secondaryroot=NULL) {
    global $DATA_DIR;
    $ar=NULL;
    if($value=='') return NULL;
    if($value==TZR_UNCHANGED) return NULL;
    // Si $value commence par un {, alors $value contient une valeur raw, sinon $value contient uniquement le nom du fichier
    $file=strpos($value,'{')===0?json_decode($value)->file:$value;
    if(!$file) return NULL;
    if($test) $this->_checkDir();
    umask(0000);
    $root=$this->table;
    if(!empty($secondaryroot)) {
      $root='A_'.$this->table.'/'.$secondaryroot;
    }
    $root.='/'.$this->field;
    $subdir=md5($file);
    while(strlen($subdir)>=16) {
      $tmp=substr($subdir,0,2);
      $root.='/'.$tmp;
      $subdir=substr($subdir,16);
    }
    if($test) \Seolan\Library\Dir::mkdir($DATA_DIR.$root, false);
    $filename=$root.'/'.$file;
    if($test || $testfinal) {
      if(file_exists($DATA_DIR.$filename)) {
        $ar=$filename;
      } elseif($test) {
        $ar=NULL;
      }
    } else {
      $ar=$filename;
    }
    return $ar;
  }

  /// Generation du nom du dossier
  function dirname($value, $secondaryroot=NULL, $createdirs=true) {
    global $DATA_DIR;
    if($value=='') return NULL;
    if($value==TZR_UNCHANGED) return NULL;
    $langdata = \Seolan\Core\Shell::getLangData();
    @list($file,$other) = explode(';',$value);

    umask(0000);
    $root=$this->table.'/'.$this->field;
    if(!empty($secondaryroot)){
      $root = 'A_'.$this->table.'/'.$secondaryroot.'/'.$this->field;
    }
    $dirname=$root;
    $subdir=md5($file);
    while(strlen($subdir)>=16) {
      $tmp=substr($subdir,0,2);
      $dirname.='/'.$tmp;
      $subdir=substr($subdir,16);
    }
    $filename=$dirname.'/'.$file;
    $fulldirname=$DATA_DIR.$filename;
    if($createdirs) \Seolan\Library\Dir::mkdir($fulldirname, false);
    return $filename;
  }

  /// Ajout d'un fichier au catalogue
  protected function addToFolder($id, $retval, $dir, $filename, $del=true) {
    global $DATA_DIR;
    $fulldir=$DATA_DIR.$dir;
    $fulldirname=$DATA_DIR.$dir;
    if($this->crypt) cryptAndSignFile($filename);
    @copy($filename, $fulldir.$id);
    if($del) @unlink($filename);
    return $fulldir.$id;
  }

  /// Supprime un fichier du catalogue
  protected function delFromFolder($dir, $filename) {
    global $DATA_DIR;
    $fulldir=$DATA_DIR.$dir;
    $fulldirname=$DATA_DIR.$dir;
    @unlink($fulldirname.$filename);
    return $fulldirname.$filename;
  }

  /// Generation du champ pour modification du fichier
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    if($this->multivalued) return $this->my_edit_multiple($value,$options,$fields_complement);
    else return $this->my_edit_simple($value,$options,$fields_complement);
  }
  protected function my_edit_simple(&$value,&$options,&$fields_complement=NULL) {
    $lang = \Seolan\Core\Shell::getLangUser();
    $r=$this->_newXFieldVal($options,true);
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field.'['.$o.']';
      $hiddenname=$this->field.'_HID['.$o.']';
      $titlename=$this->field.'_title['.$o.']';
      $delname=$this->field.'_del['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
      $titlename=$options['fieldname'].'_title';
      $delname=$options['fieldname'].'_del';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
      $titlename=$this->field.'_title';
      $delname=$this->field.'_del';
    }
    $r->raw=$value;
    $this->_checkDir();
    $varid=$r->varid;
    $disp=$this->my_display($value,$options);
    $r->resizer = $disp->resizer;
    $r->url = $disp->url;
    $r->disphtml=$disp->html;
    $r->originalname=$disp->originalname;
    $r->filename=$disp->filename;
    $r->mime=$disp->mime;
    $r->decoded_raw=$disp->decoded_raw;

    $txtDel = $txtBase = $txtCurrent = $txtPreview = $txtInput = '';
    // Bouton  de supression
    if(!$this->isEmpty($disp) && !$this->compulsory){
      $txtDel = "<input type='hidden' value='' name='{$delname}'><button onclick=\"markdel{$varid}();return false;\" type='button' class='btn btn-default btn-md btn-inverse'>".\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete')."</button>";
      $txtDel .= "<script type=\"text/javascript\">function markdel{$varid}(){var btn=jQuery(\"#field-file-current-{$varid} tr:first\"); btn.toggleClass(\"napp-dataline\");jQuery(\"input[name='{$delname}']\", btn).val(btn.hasClass(\"napp-dataline\")?1:0);jQuery(\"#field-file-preview-{$varid}\").toggle();}</script>";
    }
    $txtInput ="<table class=\"table table-auto table-condensed tzr-xfiledef\"><tr>";
    if($this->usealt){
      $txtInput.='<td><label>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','title').'</label></td><td id="'.$varid.'-tdtitle"><input type="text" name="'.$titlename.'" value="'.$disp->title.'"/></td></tr>';
      $txtInput.='<tr><td><label>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','file').'</label></td>';
    }
    // Input fichier + bouton parcourir console
    $txtInput.='<td id="'.$varid.'-tdfile"'.($disp->isExternal?' style="display:none"':'').'>';
    if(\Seolan\Core\Shell::admini_mode() || $GLOBALS['TZR_PACKS']->packDefined('\Seolan\Pack\Plupload\Plupload') && empty($options['noPlupload']))
      $txtInput.=$this->my_edit_get_uploader($fname,$hiddenname,$varid);
    else {
      $accept = '';
      if (count($this->getAllowedMimes())) {
        $accept = 'accept="'.implode(',', $this->getAllowedMimes()).'"';
      }
      $txtInput.='<input type="file" name="'.$fname.'" '.$accept.' id="'.$varid.'"'.($this->compulsory && $this->isEmpty($disp) ? ' required':'').' data-maxsize="'.$this->maxlength.'"><br/>';
    }

    if(\Seolan\Core\Shell::admini_mode() && $this->browsemods) {
      // selection de module par type ou module source des données
      $paramspicker = [];
      if($this->sourcemodule)
	$paramspicker['ajaxmoid']=$this->sourcemodule;
      else
	$paramspicker['toid']=[XMODTABLE_TOID, XMODMEDIA_TOID];
      $url = $this->getModulesFilePickerHtml($r, $paramspicker);
      $txtInput .= '<br><input  class="browseModuleButton" type="button" value="'
		  .\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','browsemods')
		  .'" onclick="TZR.Dialog.openURL(\''.$url.'\',null,{location:\''.$url.'\'});">';
    }
    $txtInput.='</td>';
    if (\Seolan\Core\Shell::admini_mode() && $this->browsemods) {
      $txtInput .= '</tr><tr><td></td> <td id="'.$r->varid.'-tdbrowsemods"> </td>';
    }
    $txtInput.='</tr>';

    // Fichier externe
    if ($this->allow_externalfile) {
      $txtInput.='<tr><td></td> <td><input id="'.$r->varid.'-external-button" '.($disp->isExternal?' style="display:none"':'').' type="button" value="'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','externalfile').'" onclick="jQuery(this).hide(); jQuery(\'#'.$r->varid.'-tdfile, #'.$r->varid.'-trexternal, #'.$r->varid.'-trcancel\').toggle();"></td></tr>';
      $txtInput.='<tr id="'.$r->varid.'-trexternal"  '.(!$disp->isExternal?' style="display:none"':'').'><td></td> <td>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','externalfile').' : <input type="text" id="'.$r->varid.'-external" value="'.$disp->externalUrl.'" name="'.$hiddenname.'[external]" size="50" maxlength="200"></td></tr>';
      if ($disp->isExternal) // pour pouvoir tracer la suppression de l'url dans l'input
	$txtInput.="<input type='hidden' name='{$hiddenname}[externalOn]' value='1'>";
    }
    if (\Seolan\Core\Shell::admini_mode() && $this->browsemods || $this->allow_externalfile) {
      $txtInput.='<tr '.($disp->isExternal?'':' style="display:none"').' id="'.$r->varid.'-trcancel"><td></td> <td><input type="button" value="'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','cancel').'" onclick="jQuery(\'#'.$r->varid.'-trexternal,#'.$r->varid.'-trcancel\').hide(); jQuery(\'#'.$r->varid.'-tdbrowsemods\').html(\'\').hide(); jQuery(\'#'.$r->varid.'-tdfile, #'.$r->varid.'-external-button\').show();jQuery(\'#'.$r->varid.'-from, #'.$r->varid.'-external\').val(\'\')"></td></tr>';
    }
    $txtInput.='</table>';

    $txtBase.='<input type="hidden" id="'.$r->varid.'-old" name="'.$hiddenname.'[old]" value="'.htmlspecialchars($value).'"/>';
    $txtBase.='<input id="'.$varid.'-from" type="hidden" name="'.$hiddenname.'[from]" value=""/>';
    if(isset($o))
      $txtBase.='<input type="hidden" name="'.$hiddenname.'[intable]" value="'.$o.'"/>';
      $txtBase .= '<script type="text/javascript">
TZR.uploadFileMaxSizeError="'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','file_max_size').'";
TZR.uploadFileAllowedType="'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','file_allowed_types').$this->allow_externalfile.'";
TZR.addValidator(["'.$r->varid.'","","'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'","\Seolan\Field\File\File","","'.$this->browsemods.'","'.$this->allow_externalfile.'"]);
jQuery("#'.$varid.'").on("change", function(){
  val = TZR.validator.find(elt => elt[0]==this.id);
  return TZR.isFileValid(val[0],val[1],val[2],val[3],val[6],val[7]);
});</script>';

    // à terme modifier configureViewer ?
    $htmlView = $this->getHtmlViewer($rview, $options, false);
    $actions = $htmlView;

    $actions .= $this->editAction($r,$value,$options,$disp,$fname,$hiddenname);

    $actions .= $txtDel;

    $txtTooltip = $this->fileInfos($r,$value,$options,$disp,$fname,$hiddenname);

    $txtCurrent = "<table id=\"field-file-current-{$varid}\">";
    $txtCurrent .= "<tr><td>{$actions}</td><td>{$disp->mimepicto}&nbsp;</td><td><a data-html=\"true\" data-toggle=\"tooltip\" title=\"{$txtTooltip}\" href=\"{$disp->url}\" target=\"_self\">".(htmlspecialchars(empty($disp->title)?$disp->originalname:$disp->title)).'</a></td></tr>';
    $txtCurrent .= '</table>';

    $txtPreview = $this->filePreview($r,$value,$options,$disp,$fname,$hiddenname);


    $r->html=$txtBase;
    $r->html.=$txtCurrent;
    $r->html.=$txtPreview;
    $r->html.=$txtInput;

    if ($this->electronic_signature) {
      $buttonlabel = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'electronic_signature');
      $r->html .= '<input type="button" value="'.$buttonlabel.'" class="" onclick="TZR.openContactListForElectronicSignature(\''.$options['fmoid'].'\',\''.$options['oid'].'\',\''.$fname.'\',\''.$this->electronic_signature_destination.'\')">';
    }

    return $r;
  }

  /// html d'appel de la popup de sélection/ajout

  protected function my_edit_multiple(&$value,&$options,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options,true);
    if(isset($options['intable'])) {
      return $r;
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }
    $r1=$this->display($value,$options);
    $txt='<div class="tzr-xfolderdef">';
    if(!empty($r1->catalog)){
      $txt.='<table class="list2">';
      $delico=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
      $deltxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete');
      $js = '';
      foreach($r1->catalog as $i=>$f) {
	$tmp=explode('/',$f->filename);
	$fileid=array_pop($tmp);
	$mimelabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Mime',$f->mime, 'both', 'Seolan_Core_Mime', 'default');
	if(!empty($mimelabel)) $mimelabel.='&nbsp;';
	$rowclass = $i%2?'even-dataline':'odd-dataline';

	$txt.="<tr class=\"{$rowclass}\" id=\"ffdocrow{$fname}{$i}\"><td><input id=\"{$fname}_del_{$fileid}_id\" type=\"hidden\" name=\"{$fname}_del[{$fileid}]\" value=\"\"/><button type=\"button\" class=\"btn btn-default btn-md btn-inverse\" onclick=\"markdel{$r->varid}{$i}()\" title=\"{$deltxt}\">{$delico}</button></td><td>{$mimelabel}</td><td><a href=\"{$f->url}\">".((empty($f->title))?$f->originalname:$f->title)."</a></td></tr>";

	$js .= "function markdel{$r->varid}{$i}(){var chkb = document.getElementById('{$fname}_del_{$fileid}_id'); if (chkb.value == ''){chkb.value=1; document.getElementById('ffdocrow{$fname}{$i}').className+=' napp-dataline';}else{chkb.value=''; document.getElementById('ffdocrow{$fname}{$i}').className=document.getElementById('ffdocrow{$fname}{$i}').className.replace('napp-dataline', '');}}";

      }
      $txt.="</table><br><script type=\"text/javascript\">{$js}</script>";
    }
    $txt.=$this->my_edit_get_uploader($fname,$hiddenname,$r->varid);
    $txt.='<input type="hidden" name="'.$fname.'_old" value="'.htmlspecialchars($value).'" id="'.$r->varid.'-old" />';
    $r->raw=$value;
    $r->html=$txt;
    return $r;
  }
  /**
   * Compute the allowed extensions from mimes
   * @param stopChar if true, insert the stop char before each extension
   * @return Array of allowed extensions
   */
  protected function getAllowedExtensions($stopChar=false) {
    // Liste des extentions permises
    $accept=trim($this->accept);
    $allowed_extensions=array();
    if($accept && $accept!='.*' && $accept!='*.*' && $accept!='*'){
      $types=explode(',',$accept);
      $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
      foreach($types as $i=>$mime){
        if(strpos($mime,'/')) {
          $extensions = $mimeClasse->get_extension($mime,true);
          if ($stopChar) {
            $extensions = '.' . str_replace( ',', ',.', $extensions);
          }
          $allowed_extensions[] = trim($extensions);
        } else $allowed_extensions[] = str_replace(array('.', '*'), '', trim($mime));
      }
    }

    return $allowed_extensions;
  }

  /**
   * Compute the allowed mimes from extensions
   * @return Array of allowed mimes
   */
  protected function getAllowedMimes() {
    $accept=trim($this->accept);
    $allowed_mimes=array();
    if($accept && $accept!='.*' && $accept!='*.*' && $accept!='*'){
      $types=explode(',',$accept);
      $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
      foreach($types as $i=>$mime){
        if(!strpos($mime,'/')) {
          $allowed_mimes[] = $mimeClasse->get_type(trim($mime));
        } else $allowed_mimes[] = str_replace(array('.', '*'), '', trim($mime));;
      }
    }
    return $allowed_mimes;
  }
  function my_edit_get_uploader($fname,$hiddenname,$varid){
    // Formate le fname (utilisé dans le cas d'édition en colonne)
    $fname=preg_replace('/\[.+$/','',$fname);
    $maxsize=min(min((int)$this->maxlength, getBytes(ini_get('upload_max_filesize'))),getBytes(ini_get('post_max_size')));
    // Liste des extentions permises
    $allowed_extensions_str = implode(',',$this->getAllowedExtensions());
    $allowed_extensions=!empty($allowed_extensions_str)?'{title:"",extensions:"'.$allowed_extensions_str.'"}':'';
    $autosavemessage=addslashes(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','auto_save_form'));
    $color=\Seolan\Core\Ini::get('error_color');
    if($this->multivalued){
      $buttonlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xfiledef_uploadmultiple');
      $droplabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xfiledef_ordropfiles');
    }else{
      $buttonlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xfiledef_uploadone');
      $droplabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xfiledef_ordropfile');
    }

    $txt='<div id="'.$varid.'_cont" class="uploadCont">';
    $txt.='<div class="uploadQueue"></div>'.
      '<div class="uploadActions"><input type="button" value="'.$buttonlabel.'" class="uploadAddButton">'.$droplabel.'</div></div>';
    $txt.='<input type="hidden" id="'.$varid.'" data-compulsory="'.$this->compulsory.'"/>';
    $txt.='<input type="hidden" name="'.$hiddenname.'[id]" value="'.$varid.'"/>';
    $txt.='<script type="text/javascript">
      TZR.uploadAutoSaveMessage="'.$autosavemessage.'";
      TZR.addXFileUploader("'.$this->table.'","'.$fname.'","'.$varid.'",{
        filters:{max_file_size:"'.$maxsize.'",mime_types:['.$allowed_extensions.']},multi_selection:'.($this->multivalued?'true':'false').'
      });
      TZR.addValidator(["'.$varid.'","","","'.$color.'","XFileUploader"]);
      </script>';
    $txt.='</div>';
    return $txt;
  }

  /**
   * Complete l'objet d'edition dans le cas d'une image
   * @note : comme editHTML, gardée pour compatibilité
   */
  function editImage(&$r,&$value,&$options,&$disp,$fname,$hiddenname){
    $r->isImage=true;
    if(!empty($this->image_crop_ratio)){
        // RZ: a implémenter avec wie
    }
    if($disp->html){
      $px=' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','pixels');
      $r->html='<table><tr><td colspan="2">'.$r->html.'</td></tr><tr><td><img src="'.$disp->resizer.'&geometry='.TZR_THUMB_SIZE.'x'.TZR_THUMB_SIZE.'%3E&uniq'.$this->getFileSize($r).'" id="'.$r->varid.'-img"></td><td>'.
	$disp->html_default.'<br>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','file_size').' : '.round($this->getFileSize($r)/1024).' Ko<br>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','width').' : '.$this->getImageSize($r,'fullwidth').$px.'<br>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','height').' : '.$this->getImageSize($r,'fullheight').$px.
	'</td></tr></table>';
    }
  }
  /// Info bulle d'un fichier
  protected function fileInfos($r,$value,$options,$disp,$fname,$hiddenname){
    if ($this->isEmpty($disp))
      return ''; // ou empty image ?
    $html = '';
    $filesize = $this->getFileSize($r);
    $html = htmlspecialchars(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','file_size')).' : '.round($filesize/1024).' Ko<br>';
    if(\Seolan\Field\File\File::isImage($disp->mime) || $this instanceof \Seolan\Field\Image\Image){
      $px=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','pixels');
      $html.= htmlspecialchars(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','width')).' : '.$this->getImageSize($r,'fullwidth')." $px<br>".
	      htmlspecialchars(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','height')).' : '.$this->getImageSize($r,'fullheight')." $px";
    }
    return $html;
  }

  /// Preview en ligne d'un fichier
  protected function filePreview($r,$value,$options,$disp,$fname,$hiddenname){
    if ($this->isEmpty($disp))
      return ''; // ou empty image ?
    $html = '';
    if(\Seolan\Field\File\File::isImage($disp->mime) || $this instanceof \Seolan\Field\Image\Image){
      $px=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','pixels');
      $filesize = $this->getFileSize($r);
      $tooltip = $this->fileInfos($r,$value,$options,$disp,$fname,$hiddenname);
      $html="<span id=\"field-file-preview-{$r->varid}\" data-placement=\"right\" data-toggle=\"tooltip\" data-html=\"true\" title=\"{$tooltip}\"><img src=\"{$disp->resizer}&geometry=".TZR_THUMB_SIZE.'x'.TZR_THUMB_SIZE."%3E&uniq{$filesize}\" id=\"{$r->varid}-img\"></span>";
    }
    return $html;
  }
  /// Bouton edition d'un fichier,
  protected function editAction($r,$value,$options,$disp,$fname,$hiddenname){
    if($disp->mime=='text/html' || $disp->mime=='text/plain'){
      if (empty($options['fmoid']))
	return '';
      $tmpname=md5(session_id().$disp->table.$disp->field.$options['oid']);
      copy($disp->filename, TZR_TMP_DIR.$tmpname);
      $dla=$this->getDownloader($tmpname, $disp->mime, $disp->originalname, $disp->originalname, $options['fmoid']);
      $dl=$dla[0].'&tmp=1&disp=inline';
      $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true).'&function=edit&selectedfields[]='.$this->field.'&moid='.$options['fmoid'].'&template=Field/File.live-edit.html&field='.$this->field.'&tplentry=br&oid='.$options['oid'];
      $upl=TZR_FILE_EDITOR_UPLOADER.'?moid='.$options['fmoid'].'&table='.$disp->table.'&field='.$disp->field.'&oid='.$options['oid'].'&moid='.$options['fmoid'];
      $edition='<input type="hidden" name="'.$hiddenname.'[editflag]" value="no"/>';
      $edition.='<button type="button" class="btn btn-default btn-md btn-inverse" onclick="TZR.openhtmlfileeditor({uniqid:\''.\Seolan\Core\Shell::uniqid().'\',url:\''.$url.'\',uploader:\''.$upl.'\', editflag:\''.$hiddenname.'[editflag]\'}); return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit').'</button>';
      return $edition;
    }
  }
  /**
   * Complete l'objet d'édition dans le cas d'un fichier HTML
   * @note : gardée pour compatibilité
   */
  function editHTML($r,$value,$options,$disp,$fname,$hiddenname){
    $html = '';
    if($options['fmoid']){
      $edition='';
      $tmpname=md5(session_id().$disp->table.$disp->field.$options['oid']);
      copy($disp->filename, TZR_TMP_DIR.$tmpname);
      $dla=$this->getDownloader($tmpname, $disp->mime, $disp->originalname, $disp->originalname, $options['fmoid']);
      $dl=$dla[0].'&tmp=1&disp=inline';
      $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true).'&function=edit&selectedfields[]='.$this->field.'&moid='.$options['fmoid'].'&template=Field/File.live-edit.html&field='.$this->field.'&tplentry=br&oid='.$options['oid'];
      $upl=TZR_FILE_EDITOR_UPLOADER.'?moid='.$options['fmoid'].'&table='.$disp->table.'&field='.$disp->field.'&oid='.$options['oid'].'&moid='.$options['fmoid'];
      $edition.='<input type="hidden" name="'.$hiddenname.'[editflag]" value="no"/>';
      $edition.=' <a href="#" onclick="TZR.openhtmlfileeditor({uniqid:\''.\Seolan\Core\Shell::uniqid().'\',url:\''.$url.'\',uploader:\''.$upl.'\', editflag:\''.$hiddenname.'[editflag]\'}); return false;">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit').'</a>';
      $html.=$disp->html.$edition;
    }else{
      $html.=$disp->html;
    }
  }

  function sqltype() {
    if($this->multivalued) {
      return 'text';
    }
    return 'varchar(255)';
  }
  // verifie que le nom encodé correspond bien au nom attendu dans la langue
  // - champs non traduisibles ont les mêmes valeurs
  // - anciens 'bugs' dans la création de langue et des traductions
  private function langFileNameIsOk($filename, $oid, $langdata){
    if ($langdata != TZR_DEFAULT_LANG){
      list($t,$f)=explode(':',$oid);
      $lfilename = $langdata.'.'.$f;
      if ($lfilename != $filename){
	\Seolan\Core\Logs::debug(__METHOD__."lang file name mismatch '$filename' '$lfilename'");
	return false;
      }
    }
    return true;
  }
  /// Suppression de la donnée, dans la table ou une ligne d'archive
  function deleteVal($value,$oid) {
    if($this->multivalued)
      return $this->deleteMultiple($value,$oid);
    else
      return $this->deleteSimple($value,$oid);
  }
  protected function deleteSimple($value,$oid) {
    global $DATA_DIR;
    if (!$this->langFileNameIsOk($value->decoded_raw->file, $oid, \Seolan\Core\shell::getLangData())){
      \Seolan\Core\Logs::debug(__METHOD__." $langdata filename mismatch no delete");
      return 1;
    }
    $filename= $value->filename;
    if($filename) {
      $files = array_merge(array($filename, $filename.'_ld'), glob($filename.'-*'));
      foreach($files as $file) {
	if(file_exists($file)) {
	  \Seolan\Core\Logs::debug(__METHOD__." unlink {$file}");
	  @unlink($file);
	}
      }
    }
    $this->deleteViewerData($value);
    return 1;
  }
  protected function deleteMultiple($value,$oid) {
    // calcul du nom du fichier associe
    if (!$this->langFileNameIsOk(basename($value->dirname), $oid, \Seolan\Core\shell::getLangData())){
      \Seolan\Core\Logs::debug(__METHOD__."$langdata filename mismatch no delete");
      return 1;
    }
    \Seolan\Library\Dir::unlink($value->dirname);
    foreach($value->catalog as $afile){
      $this->deleteViewerData($afile);
    }
    return 1;
  }

  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $hidden=$options[$this->field.'_HID'];
    if($hidden && isset($hidden['intable'])){
      $p=new \Seolan\Core\Param(($ar=[]));
      $tmp=$p->get($this->field.'_title');
      $options[$this->field.'_title']=$tmp[$hidden['intable']];
      $tmp=$p->get($this->field.'_del');
      $options[$this->field.'_del']=$tmp[$hidden['intable']];
    }
    if($this->multivalued) return $this->post_edit_multiple($value,$options,$fields_complement);
    else return $this->post_edit_simple($value,$options,$fields_complement);
  }
  protected function post_edit_simple($value,$options=NULL,&$fields_complement=NULL) {
    global $DATA_DIR;
    $p=new \Seolan\Core\Param($options,array('del'=>true));
    $r=$this->_newXFieldVal($options);
    $langdata=\Seolan\Core\Shell::getLangData();
    $hidden=$options[$this->field.'_HID'];
    $del=$p->get('del');
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $retval=array();
    $upload_title=$upload_size=$upload_type=$upload_name=$upload_filename=$upload_filename_del=null;

    // Verification que les repertoires existent
    $oid=$p->get('oid')??$p->get('oidit');
    // Calcul du nom du fichier associe
    list($t,$f)=explode(':',$oid);
    if($this->translatable) $foid=$langdata.'.'.$f;
    else $foid=$f;
    $oldfile=$this->filename($foid,true);
    $file=$this->filename($foid,false);
    if($file) $filename=$DATA_DIR.$file;
    $oldfilename='';
    if($oldfile) $oldfilename=$DATA_DIR.$oldfile;
    $link=(@$hidden['link']?true:false);

    // saisie ou maj d'un url externe
    if(!empty($hidden['external']))
      return $this->post_editExternal($value,$options);

    extract($this->post_edit_analyse($value,$options,$hidden));

    if (!empty($hidden['externalOn']) && (empty($upload_filename) || $upload_filename == 'None')){
      return $this->post_editExternal($value, $options);
    }

    // Récupère les parametres ne dépendant pas d'un fichier dans le cas ou aucun fichier n'a été posté
    if($upload_filename_del===NULL) $upload_filename_del=$p->get($this->field.'_del');
    if($upload_title===NULL) $upload_title=$p->get($this->field.'_title');
    // Recupere un type mime fiable
    $upload_type=$mimeClasse->getValidMime(@$upload_type,$upload_filename,@$upload_name);
    // Renseigne la taille si non renseigné
    if(empty($upload_size)) $upload_size=@filesize($upload_filename);
    // On verifie qu'on est dans les types acceptés
    $ok = ($upload_filename!='none' && !empty($upload_filename)) && ($mimeClasse->isAccepted($upload_type,$this->accept));
    if (!$ok){
      \Seolan\Core\Logs::debug('\Seolan\Field\File\File::post_edit() upload _type :"'.$upload_type.'" rejected');
    }
    // Verification de la taille du fichier
    $maxlength=trim($this->maxlength);
    if(!empty($maxlength) && ($upload_size > $maxlength)) $ok=false;
    if($upload_filename_del && $upload_filename_del != 'off') {
      if(isset($GLOBALS['XREPLI'])) $GLOBALS['XREPLI']->journalize('del',$oldfilename);
      \Seolan\Core\Logs::debug('\Seolan\Field\File\File::post_edit(): unlink('.$oldfilename.')'.$upload_filename_del);
      $this->trace($options['old'],$r, '[-]file deleted');
      @unlink($oldfilename);
      $retval='';
    }else{
      if(\Seolan\Field\File\File::isImage($upload_type)){
	if (!self::isVectorImage($upload_type)){
	  if(!$link){
	    // Redimmnssion l'image en l'orientant. Si jamais il n'y a pas de resize à faire, on aplique seulement l'orientation
	    if(!$this->resizeImage($upload_filename,$this->image_max_geometry,true)){
	      $this->rotateAndFlip($upload_filename);
	    }
	  }else{
	    // Oriente l'image
	    $this->rotateAndFlip($upload_filename);
	  }
	}

	$dim=getimagesize($upload_filename);
	if ($dim === false && self::isVectorImage($upload_type)){
	  $dim = $this->getVectorImageSize($upload_filename);
	}
	$retval['w']=$dim[0];
	$retval['h']=$dim[1];
      }
      elseif(self::isVideo($upload_type)) {
        $filefullsizeimage=$upload_filename.'-fullsizeimage';
        exec(FFMPEG." -y -i ".escapeshellarg($upload_filename)." -f image2 -vcodec mjpeg -vframes 1 -an ".escapeshellarg($filefullsizeimage));
        $dim=getimagesize($filefullsizeimage);
        $retval['w']=$dim[0];
        $retval['h']=$dim[1];
      }
      if($ok && ($upload_filename!='none') && !empty($upload_filename)){
	if(!empty($oldfilename) && file_exists($oldfilename)) {
	  \Seolan\Core\Logs::debug('\Seolan\Field\File\File::post_edit(): unlink('.$oldfilename.')');
	  unlink($oldfilename);
	}
	if(file_exists($filename)) {
	  \Seolan\Core\Logs::debug('\Seolan\Field\File\File::post_edit(): unlink('.$filename.')');
	  unlink($filename);
	}
	if(!empty($hidden['crop'])){
	  $upload_filename_crop=$upload_filename.'_crop';
	  exec(TZR_MOGRIFY_RESIZER.' +profile "*" -crop '.escapeshellarg($hidden['crop']['w'].'x'.$hidden['crop']['h'].'+'.$hidden['crop']['x'].'+'.$hidden['crop']['y']).' '.
	       escapeshellarg($upload_filename).' '.escapeshellarg($upload_filename_crop).' 2>&1  > /dev/null');
	  $upload_filename=$upload_filename_crop;
	}
	// Cryptage/Signature des fichiers
	if($this->crypt) cryptAndSignFile($upload_filename);
        // gzip
        if ($this->gzipped == 1) {
          $content = file_get_contents($upload_filename);
          $fh = gzopen($filename, 'w');
          gzwrite($fh, $content);
          gzclose($fh);
        } elseif($link) {
	  link($upload_filename,$filename);
	} else {
          if (false === copy($upload_filename, $filename))
            \Seolan\Core\Logs::critical("\Seolan\Field\File\File::post_edit_simple unable to copy file from $upload_filename to $filename");
        }
        // Calcul de l'image réduite de reference pour le resizer
	if(\Seolan\Field\File\File::isImage($upload_type)  && !self::isVectorImage($upload_type) && empty($options['no_ld'])){
          exec(TZR_MOGRIFY_RESIZER.' -resize "'.TZR_LD_IMAGE_SIZE.'>" -density 72x72 '.escapeshellarg($filename).' '.escapeshellarg($filename.'_ld'). " 2>&1  > /dev/null");
        }

	$this->trace(@$options['old'],$r, (!empty($upload_name)?$upload_name:'file changed'));
	$r->filename=$filename;
	$r->mime=$upload_type;
	if(isset($GLOBALS['XREPLI'])) $GLOBALS['XREPLI']->journalize('upd',$filename);
	$retval['file']=$foid;
	$retval['mime']=$upload_type;
	$retval['name']=$upload_name;
	$retval['title']=$upload_title;
	$retval=json_encode((object)$retval);
	if(!$link && $del && empty($options['editbatch'])){
	  \Seolan\Core\Logs::debug('\Seolan\Field\File\File::post_edit(): unlink('.$upload_filename.')');
	  @unlink($upload_filename);
	}
	if(!empty($upload_filename_crop)) @unlink($upload_filename_crop);
      }else{
	// Modification du libellé seulement
	$oldvalue=json_decode($options['old']->raw ?? $hidden['old']);
	if($oldvalue->title!=$upload_title) {
	  $oldvalue->title=$upload_title;
	  $retval=json_encode($oldvalue);
	  $this->trace($options['old'],$r, 'file label changed => '.$upload_title);
	} else {
	  $retval = TZR_UNCHANGED;
	}
      }
    }
    $r->raw=$retval;
    return $r;
  }
  function post_edit_multiple($value,$options=NULL,&$fields_complement=NULL) {
    global $DATA_DIR;
    global $DATA_URL;

    $p = new \Seolan\Core\Param($options,array('del'=>true));
    $r = $this->_newXFieldVal($options);
    $langdata = \Seolan\Core\Shell::getLangData();
    $idmax = 0;

    $del=$p->get('del');
    if(!empty($options['editbatch'])) $del=false;
    $oid=$p->get('oid');
    // calcul du nom du fichier associe
    list($t,$f)=explode(':',$oid);
    if($this->translatable) {
      $filedir = $langdata.'.'.$f;
    } else {
      $filedir = $f;
    }
    // Création du dossier s'il n'existe pas
    $dirname = $this->dirname($filedir);
    $hidden = $p->get($this->field.'_HID');

    if (is_string($options['old'])){
      $json = json_decode($options['old'], true)??['dir'=>$filedir, 'files'=>[]];
    } else {
      $json = json_decode($options['old']->raw, true)??['dir'=>$filedir, 'files'=>[]];
    }
    if(!$json) {
      $json = ['dir'=>$filedir, 'files'=>[]];
    }
    $files = (array) $json['files'];

    // Liste des fichiers à supprimer
    $todelete=$p->get($this->field.'_del');

    // Parours pour suppression, calcul idmax, accès par nom
    $filesByNames = [];
    foreach($files as $k=>$file) {
      if(is_array($todelete) && $todelete[$file['file']]) {
        $this->delFromFolder($dirname.'/',$file['file']);
        $this->trace($options['old'],$r, '[-]file deleted');
        unset($files[$k]);
      } else {
        $idmax = $file['file'] > $idmax ? $file['file'] : $idmax;
	$filesByNames[$file['name']]=[$file['file'], $k];
      }
    }

    // Liste des nouveaux fichiers
    $newFiles=$this->post_edit_analyse($value,$options,$hidden);

    // dédoublonnage par nom des nouveaux fichiers
    $carry = array_reduce($newFiles,
			  function($carry, $file){
			    if (!in_array($file['upload_filename'], $carry['names'])){
			      $carry['names'][] = $file['upload_filename'];
			      $carry['files'][] = $file;
			    }
			    return $carry;
			  },
			  ['names'=>[],'files'=>[]]);
    $newFiles = $carry['files'];

    // Ajout/remplacement des nouveaux fichiers
    if($newFiles){
      foreach($newFiles as $i=>$file){
        if(empty($file['upload_filename']) || $file['upload_filename']==='none') continue;
	// remplacement : on efface les actuels de même nom
	if (isset($filesByNames[$file['upload_name']])){
	  list($filenum, $fileindex) = $filesByNames[$file['upload_name']];
	  $this->delFromFolder($dirname.'/',$filenum);
          unset($files[$fileindex]);
	}
        $idmax++;
        $this->addToFolder($idmax, $filedir, $dirname.'/', $file['upload_filename'], $del);
        $files[$idmax]['file']=$idmax;
        $files[$idmax]['mime']=$file['upload_type'];
        $files[$idmax]['name']=$file['upload_name'];
        $files[$idmax]['title']='';

        $this->trace(@$options['old'], $r, '[+]'.(!empty($file['upload_name'])?$file['upload_name']:''));
        if(isset($GLOBALS['XREPLI'])) $GLOBALS['XREPLI']->journalize('upd',$file['upload_filename']);
      }
      if($del){
        //RZ BUG BUGS ligne fausse
        \Seolan\Library\Dir::unlink(TZR_TMP_DIR.'upload'.$hidden['id']);
      }

    }
    $json['files'] = array_values($files);
    $retval=json_encode($json);
    $r->raw=$retval;
    return $r;
  }
  /// Cherche le mode d'édition et recupere les infos du fichier
  protected function post_edit_analyse(&$value,&$options,&$hidden){
    $modes=array('post_edit_from','post_edit_catalog','post_edit_url',
                 'post_edit_path','post_edit_array','post_edit_with_edition',
                 'post_edit_base64','post_edit_html');
    foreach($modes as $mode){
      $ret=$this->$mode($value,$options,$hidden);
      if($ret){
        \Seolan\Core\Logs::debug("post_edit_analyse $mode return");
	// Dans le cas d'un champ multivalué, la fonction doit retourner un tableau de tableau de fichier
	if($this->multivalued && array_key_exists('upload_filename',$ret)){
	  $ret=array($ret);
	}
	return $ret;
      }
    }

    if($this->translatable == "1" && $hidden['old'] && $_REQUEST['procEditAllLang']) {
      $decodedRaw = json_decode($hidden['old']);
      $oldfilename = $GLOBALS['DATA_DIR'].$this->filename($decodedRaw->file);
      $newfilename = TZR_TMP_DIR.$decodedRaw->name;
      copy($oldfilename, $newfilename);
      $ret = $this->post_edit_path($newfilename, $options, $hidden);
      if($ret) {
        // Dans le cas d'un champ multivalué, la fonction doit retourner un tableau de tableau de fichier
        if($this->multivalued && array_key_exists('upload_filename', $ret)) {
          $ret = array($ret);
        }
        return $ret;
      }
    }

    return array();
  }
  /// Traitement du post_edit avec un fichier de la base
  protected function post_edit_from(&$value,&$options,&$hidden){
    if(empty($hidden['from'])) return false;

    $from=$hidden['from'];
    $link=(@$hidden['link']?true:false);
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $p=new \Seolan\Core\Param($options,[]);

    @list($url,$mime,$name)=@explode(';',$from);
    $infos=pathinfo($url);
    if(empty($mime)) $mime=@$infos['mime'];
    if(substr($url,0,1)!='/') $url=TZR_WWW_DIR.$url;
    if($link) $tmp_name=$from;
    else {
      $tmp_name=TZR_TMP_DIR.uniqid();
      copy($url,$tmp_name);
    }
    $upload_type=$mime;
    $upload_filename=$tmp_name;
    if(!empty($name)){
      $upload_name=$name;
      $upload_type=$mimeClasse->getValidMime(NULL,$upload_filename,$name);
    }elseif(!empty($infos['extension'])){
      $upload_name=$infos['basename'];
      $upload_type='';
    }else{
      $upload_type=$mimeClasse->getValidMime(NULL,$upload_filename,NULL);
      $upload_name=$infos['basename'].'.'.$mimeClasse->get_extension($upload_type);
    }
    $upload_filename_del='off';
    $upload_title=$p->get($this->field.'_title');
    $upload_size=@filesize($tmp_name);
    return array(
      'upload_title'=>$upload_title,
      'upload_size'=>$upload_size,
      'upload_type'=>$upload_type,
      'upload_name'=>$upload_name,
      'upload_filename'=>$upload_filename,
      'upload_filename_del'=>$upload_filename_del
    );
  }
  /// Traitement du post_edit avec une url
  protected function post_edit_url(&$value,&$options,&$hidden){
    if(!is_string($value) || (substr($value,0,7)!="http://" && substr($value,0,8)!="https://")) return false;

    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $tmp_name=TZR_TMP_DIR.uniqid();
    $content=file_get_contents($value);
    file_put_contents($tmp_name,$content);
    $upload_filename=$tmp_name;

    if(!$this->scanFile($upload_filename)) return false;

    $infos=parse_url($value);
    $infos=pathinfo($infos['path']);
    $headers = get_headers($value,1);
    if($headers && $headers['Content-disposition'] && preg_match('/filename="(.*)"$/',$headers['Content-disposition'],$eregs) && $eregs[1]){
      $upload_name = $eregs[1];
      $upload_type = $mimeClasse->getValidMime($headers['Content-Type'],$upload_filename,$upload_name);
    }elseif($infos['extension']){
      $upload_name = $infos['basename'];
      $upload_type = '';
    }else{
      $upload_type = $mimeClasse->getValidMime(NULL,$upload_filename,NULL);
      $upload_name = $infos['basename'].'.'.$mimeClasse->get_extension($upload_type);
    }
    $upload_filename_del='off';
    $upload_title=NULL;
    $upload_size=@filesize($tmp_name);
    return array(
      'upload_title'=>$upload_title,
      'upload_size'=>$upload_size,
      'upload_type'=>$upload_type,
      'upload_name'=>$upload_name,
      'upload_filename'=>$upload_filename,
      'upload_filename_del'=>$upload_filename_del
    );
  }
  /// Traitement du post_edit avec un fichier du serveur
  protected function post_edit_path(&$value,&$options,&$hidden){

    if(!empty($value['tmp_name'])) return false;

    if ($this->multivalued && is_array($value) && is_numeric(array_keys($value)[0])){
      // champ multivalué, cas ou tmp_name contient un tableau de chemins
      $infos = [];
      $infos = array_reduce($value, function($carry, $item)use($options,$hidden){
	  $info = false;
	  if (is_string($item))
	    $info = $this->post_edit_path($item, $options, $hidden);
	  if ($info)
	    $carry[] = $info;
	  return $carry;
	},$infos);

      if (empty($infos)){
	return false;
      } else {
	return $infos;
      }
    }

    if(!is_string($value) || !file_exists($value)) return false;
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $infos=pathinfo($value);
    $upload_filename=$value;
    $upload_type=$mimeClasse->getValidMime(NULL,$upload_filename,$infos['basename']);

    if(isset($infos['extension'])){
      $upload_name=$infos['basename'];
    }else{
      $upload_name=$infos['basename'].'.'.$mimeClasse->get_extension($upload_type);
    }
    // vérification antivirus
    if(!$this->scanFile($upload_filename, $upload_name)) return false;

    $upload_filename_del='off';
    $upload_title=NULL;
    $upload_size=@filesize($value);
    return array(
      'upload_title'=>$upload_title,
      'upload_size'=>$upload_size,
      'upload_type'=>$upload_type,
      'upload_name'=>$upload_name,
      'upload_filename'=>$upload_filename,
      'upload_filename_del'=>$upload_filename_del
    );
  }
  /// Traitement du post_edit pour un tableau
  protected function post_edit_array(&$value,&$options,&$hidden){
    if(!is_array($value)) return false;

    // tmp_name peut contenir tous les type de fichier sous forme de chaine possible (url, path, base64..)
    $foo=array();
    $info=$this->post_edit_analyse($value['tmp_name'],$foo,$foo);
    // Dans le cas d'un champ multivalué, la fonction doit retourner un tableau de tableau de fichier
    if($this->multivalued){
      foreach($info as &$file){
	if(!isset($file['upload_type']) && isset($value['type'])) $file['upload_type']=$value['type'];
      }
    }else{
      if(isset($value['type'])) $info['upload_type']=$value['type'];
      if(isset($value['name'])) $info['upload_name']=$value['name'];
      if(isset($value['title'])) $info['upload_title']=$value['title'];
      if(isset($value['size'])) $info['upload_size']=$value['size'];
    }
    return $info;
  }
  /// Traitement du post_edit avec édition du fichier en ligne
  protected function post_edit_with_edition(&$value,&$options,&$hidden){
    if(!(isset($hidden['editflag']) && $hidden['editflag']=='yes')) return false;

    $tmpfile=TZR_TMP_DIR.md5(session_id().$this->table.$this->field.$options['oid']);
    $upload_filename=$tmpfile;
    $info=json_decode($hidden['old']);
    $upload_type=$info->mime;
    $upload_name=$info->name;
    $upload_filename_del='off';
    $upload_title=NULL;
    $upload_size=@filesize($tmpfile);
    return array(
      'upload_title'=>$upload_title,
      'upload_size'=>$upload_size,
      'upload_type'=>$upload_type,
      'upload_name'=>$upload_name,
      'upload_filename'=>$upload_filename,
      'upload_filename_del'=>$upload_filename_del
    );
  }
  /// Traitement du post_edit pour un tableau de valeur
  protected function post_edit_base64(&$value,&$options,&$hidden){
    if(!is_string($value)) return false;
    $file_content=base64_decode($value,true);
    $decode_rencode = base64_encode(base64_decode($value,true));

    if(!($value && $file_content !== false && $decode_rencode == $value)) {
      return false;
    }

    $upload_filename=TZR_TMP_DIR.uniqid();
    file_put_contents($upload_filename,$file_content);

    // vérification antivirus
    if(!$this->scanFile($upload_filename)) return false;

    $upload_title='';
    if(!empty($options[$this->field.'_title'])) {
      $upload_title=$options[$this->field.'_title'];
    }
    $upload_filename_del='off';
    return array(
      'upload_title'=>$upload_title,
      'upload_filename'=>$upload_filename,
      'upload_filename_del'=>$upload_filename_del
    );
  }
  /// Traitement du post_edit pour un fichier provenant d'un formulaire HTML
  protected function post_edit_html(&$value,&$options,&$hidden){
    if(!isset($_FILES[$this->field])) return false;
    $p=new \Seolan\Core\Param($options,array('del'=>true));
    $upload_filename=@$_FILES[$this->field]['tmp_name'];
    $upload_type=@$_FILES[$this->field]['type'];
    $upload_name=@$_FILES[$this->field]['name'];
    $upload_size=@$_FILES[$this->field]['size'];
    $upload_title=$p->get($this->field.'_title');

    // vérification antivirus
    if(!$this->scanFile($upload_filename, $upload_name)) return false;

    return array(
      'upload_title'=>$upload_title,
      'upload_size'=>$upload_size,
      'upload_type'=>$upload_type,
      'upload_name'=>$upload_name,
      'upload_filename'=>$upload_filename,
      'upload_filename_del'=>$p->get($this->field.'_del') === 'on' ? 'on' : 'off', //On va chercher la valeur dans la request
    );
  }
  /// Traitement du post_edit pour un catalog
  protected function post_edit_catalog(&$value,&$options,&$hidden){
    if(!is_array($hidden) || empty($hidden['id']) || !is_string($hidden['id'])) return false;
    $tmpdir=TZR_TMP_DIR.'upload'.$hidden['id'].'/'.$this->table.'/'.$this->field;
    if(!file_exists($tmpdir.'/'.$hidden['id'].'_catalog.txt')) return false;

    if($this->multivalued){
      $title='';
    }else{
      $p=new \Seolan\Core\Param($options);
      $title=$p->get($this->field.'_title');
    }
    $flist=unserialize(file_get_contents($tmpdir.'/'.$hidden['id'].'_catalog.txt'));
    $files=array();
    // calcul des noms des fichiers uploades
    $upload_filename=$flist['tmp_name'];
    $upload_type=$flist['type'];
    $upload_name=$flist['name'];
    $upload_size=$flist['size'];


    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $f=0;
    foreach($flist['name'] as $i=>$name){
      if(empty($flist['tmp_name'][$i]) || $flist['tmp_name'][$i]==='none') continue;
      $files[$f]=array(
        'upload_size'=>$flist['size'][$i],
        'upload_type'=>$mimeClasse->getValidMime($flist['type'][$i],$flist['tmp_name'][$i],$flist['name'][$i]),
        'upload_name'=>$flist['name'][$i],
        'upload_filename'=>$flist['tmp_name'][$i],
        'upload_filename_del'=>'off',
        'upload_title'=>$title
      );
      // vérification antivirus
      if(!$this->scanFile($files[$f]["upload_filename"], $files[$f]["upload_name"])) return false;
      $f++;
    }
    if($this->multivalued) return $files;
    else return array_pop($files);
  }

  /// traitement apres saisie en duplication
  function post_editExternal($value,$options) {
    $r = $this->_newXFieldVal($options);
    $hidden = $options[$this->field.'_HID'];
    $url = preg_replace('@^http(s)?://@', '', $hidden['external']);
    $external = ['title' => $options[$this->field.'_title']];
    if (empty($hidden['external'])){
      $r->raw = json_encode([]);
      return $r;
    }
    if (preg_match('@^(www.youtube.com/watch\?v=|www.youtube.com/embed/|youtu.be/|www.youtube.com/v/|youtube:)([A-Za-z0-9_-]{11})@', $url, $matches)) {
      $videoid = $matches[2];
      $external['mime'] = 'video/x-flv';
      $external['file'] = 'youtube:'.$videoid;
    }
    if (preg_match('@(calameo.com/[^\/]*/|calameo:)([0-9a-z]*)@', $url, $matches)) {
      $book_id = $matches[2];
      $external['mime'] = 'application/pdf';
      $external['file'] = 'calameo:'.$book_id;
    }
    if (preg_match('@(www.dailymotion.com/video/|dai.ly/|dailymotion:)([0-9a-z]*)@', $url, $matches)) {
      $videoid = $matches[2];
      $external['mime'] = 'video/x-flv';
      $external['file'] = 'dailymotion:'.$videoid;
    }
    if (preg_match('@(vimeo.com/|vimeo:)([0-9]*)@', $url, $matches)) {
      $videoid = $matches[2];
      $external['mime'] = 'video/x-flv';
      $external['file'] = 'vimeo:'.$videoid;
    }
    $r->raw = json_encode((object) $external);
    return $r;
  }
  /**
   * traitement de traduction d'une valeur en base
   * et dans le cas fichier : changement du nom, des répertoires
   * et copie du contenu
   */
  function data_duplicate($value, $langSrc, $langDest, $copy=false){
    \Seolan\Core\Logs::notice(__METHOD__.'richard'.$this->field, "$landSrc $langDest $value $copy 1");
    if ($value == TZR_UNCHANGED || !$this->translatable || empty($value))
      return $value;
    $root = $GLOBALS['DATA_DIR'];
    if ($this->multivalued){
      $decoded_raw = json_decode($value, false);  // en objet
      if (empty($decoded_raw) || !is_object($decoded_raw)){
	\Seolan\Core\Logs::critical(__METHOD__,"Error decoded raw ".json_last_error_msg());
        return $value;
      }
      $decoded_dest = clone($decoded_raw);
      $dirname = $decoded_dest->dir;
      if(empty($dirname))
        return $value;
      // donnée initialement traduisible / non traduisible
      if (strpos($dirname, '.') !== false){
        list($foolang,$oidpart)=explode('.',$dirname);
      } else {
        $oidpart = $dirname;
      }
      $srcPath = $root.$this->filename($dirname,false,false);
      $destFile = $langDest.'.'.$oidpart;
      $destPath = $root.$this->filename($destFile,false,false);

      if ($copy && file_exists($srcPath)){
        if (!file_exists($destPath)){
          \Seolan\Library\Dir::mkdir($destPath, false);
        }
        \Seolan\Library\Dir::copy($srcPath, $destPath, true);
      }
      $decoded_dest->dir = $destFile;
      return json_encode($decoded_dest);
    } else {
      $decoded_raw = json_decode($value);
      if (empty($decoded_raw) || !is_object($decoded_raw))
        return $value;
      $decoded_dest = clone($decoded_raw);
      // donnée initialement traduisible / non traduisible
      if (strpos($decoded_raw->file, '.') !== false){
        list($foolang,$oidpart)=explode('.',$decoded_raw->file);
      } else {
        $oidpart = $decoded_raw->file;
      }
      $srcPath = $root.$this->filename($decoded_raw->file,false,false);
      $destFile = $langDest.'.'.$oidpart;
      $destPath = $root.$this->filename($destFile,false,false);
      $decoded_dest->file = $destFile;
      if ($copy && file_exists($srcPath) && !file_exists($destPath)){
        $dir = dirname($destPath);
        if (!file_exists($dir)){
          \Seolan\Library\Dir::mkdir($dir, false);
        }
        $r = copy($srcPath,$destPath);
        if (!$r || !file_exists($destPath)){
          \Seolan\Core\Logs::critical(__METHOD__,"error copy file : $srcPath,$destPath");
        }
      }
      return json_encode($decoded_dest);
    }
  }
  /// traitement apres saisie en duplication
  function post_edit_dup($value,$options) {
    if($this->multivalued) return $this->post_edit_dup_multiple($value,$options);
    else return $this->post_edit_dup_simple($value,$options);
  }
  function post_edit_dup_simple($value,$options) {
    $p = new \Seolan\Core\Param($options,array());
    $oidsrc=$p->get('oidsrc');
    $oiddst=$p->get('oiddst');
    // Si on a renseigné le champ pour un nouveau fichier
    // 8.1 : filepub_del  ou isset $_FILES
    $options['oid' ]=$oiddst;
    $r=$this->post_edit($value,$options);
    if ($r->raw != TZR_UNCHANGED){
      return $r;
    }
    // sinon copie du fichier source
    if(!$this->nocopydup){
      $oldvalue=$p->get($this->field.'_HID');
      $r->raw=$this->copyExternalsTo($oldvalue['old'],$oidsrc,$oiddst);
      return $r;
    }
    return NULL;
  }
  /**
   * on duplique les anciens fichiers vers la destination
   * on traite les fichiers ajoutés / supprimés (post_edit std)
   */
  protected function post_edit_dup_multiple($value,$options) {
    $p=new \Seolan\Core\Param($options,[]);
    $oidsrc=$p->get('oidsrc');
    $oiddst=$p->get('oiddst');
    if(!$this->nocopydup){
      $oldvalue=$p->get($this->field.'_old');
      $newval=$this->copyExternalsTo($oldvalue,$oidsrc,$oiddst);
    }
    // Traite les demandes d'ajout/supression de fichiers
    $options['oid']=$oiddst;
    // options['old'] ne peut pas exister sur une duplication, on "simule" un display
    $options['old'] = $newval; // les fichiers que l'on vient de dupliquer
    return $this->post_edit($value,$options);
  }

  /// nettoyage des repertoires de données
  public function chk(&$messages) {
    if($this->multivalued) return $this->chkMultiple($messages);
    else return $this->chkSimple($messages);
  }
  protected function chkSimple(&$messages) {
    $this->chkChangedSimple();
    $this->checkDbImages($messages);
    $this->checkDiskImages($messages);
  }

  // verification que les images renseignees en base existent sur le disque
  protected function checkDbImages(&$messages) {
    $rs=getDB()->select("SELECT KOID,{$this->field},LANG FROM {$this->table} WHERE {$this->field}!=?",[TZR_UNCHANGED]);
    while($rs && ($ors=$rs->fetch())) {
      $opts=array();
      $val=$this->my_display($ors[$this->field],$opts);
      if(!$val->isExternal && !file_exists($val->filename)) {
        $previousValue = $ors[$this->field];
        $newValue = TZR_UNCHANGED;
        \Seolan\Core\Logs::update('update', $ors['KOID'], array($this->field => "[$previousValue]->[$newValue]"));
	getDB()->execute('UPDATE '.$this->table.' SET UPD=UPD,'.$this->field.'="'.TZR_UNCHANGED.'" WHERE KOID=? AND LANG=?', [$ors['KOID'],$ors['LANG']]);
      }
      unset($val);
    }
  }

  // verification pour x000 images au hasard que les images sur le disque sont rattachees en base
  protected function checkDiskImages(&$messages) {
    $root=$GLOBALS['DATA_DIR'].$this->table.'/'.$this->field;
    $files1 = \Seolan\Library\Dir::scan($root);
    if(!empty($files1)) {
      $files = array_rand($files1, min(10000,count($files1)));
      // dans le cas ou il n'y a qu'un element dans $files1, array_rand ne rend pas un tableau
      if(!is_array($files)) { $f1=$files;unset($files);$files=array($f1);}
      $list=$listtodelete=array();
      foreach($files as $i => $file1) {
        $file=$files1[$file1];
        $names=explode('/',$file);
        $names=array_reverse($names);
        $idx=$names[0];
	if ($idx == '.htaccess'){
	  continue;
	}
        // Ancien format
        if(preg_match("@cache([\.a-z0-9]+)-([a-z0-9]+)@i",$names[0],$eregs)) {
          $idx=$eregs[1];
        }
        // Nouveau format
        if(preg_match("@^([\.a-z0-9_-]+)((-[a-z0-9]+-(WE-)?cache|-fullsizeimage)|_ld)$@Ui",$names[0],$eregs)) {
          $idx=$eregs[1];
        }
        $list[]=array($idx,$file);
      }
      foreach($list as $i => $o) {
        $idx=$o[0];
        $filename=$o[1];
	$parts  = explode('.', $idx);
        $oid = $this->table . ':' . end($parts); // trad FR.xxx
        $cnt=getDB()->count('SELECT COUNT(KOID) FROM '.$this->table.' WHERE KOID=?', [$oid]);
        if($cnt<=0) {
          // si pas trouvé recherche ancien format
          $cnt=getDB()->count('SELECT COUNT(KOID) FROM '.$this->table.' WHERE '.$this->field.' like ?', ['%"file":"'.$idx.'"%']);
          if($cnt<=0) {
            $listtodelete[]=$filename;
          }
        }
      }
      foreach($listtodelete as $filename) {
	$flag=@unlink($filename);
	\Seolan\Core\Logs::notice('\Seolan\Field\File\File::chk','unlinking '.$filename);
	if(!$flag) $messages.='unlinking '.$filename.':nok'."\n";
      }
      $dirs=\Seolan\Library\Dir::scan($root, true, false, true);
      $do=true;
      while($do) {
	$do=false;
	foreach($dirs as $i=>$dirname) {
	  if(@rmdir($dirname)) {
	    unset($dirs[$i]);
	    $do=true;
	  }
	}
      }
    }
  }

  // Transformation de fichier multivalué en monovalué
  protected function chkChangedSimple() {
    $table = $this->table;
    $field = $this->field;

    if($this->translatable) {
      $toRepair = getDB()->fetchAll("SELECT KOID, $field, LANG FROM $table WHERE $field like '{\"dir\"%'");
    }
    else {
      $toRepair = getDB()->fetchAll("SELECT KOID, $field, LANG FROM $table WHERE $field like '{\"dir\"%' and LANG=?", array(TZR_DEFAULT_LANG));
    }

    if(count($toRepair)) {
      $root = $GLOBALS['DATA_DIR']."$table/$field";
      $files = array();
      $filesScan = \Seolan\Library\Dir::scan($root);
      foreach($filesScan as $i => $file) {
        $names = explode('/', $file);
        $names = array_reverse($names);
        $idx = $names[1];
        $files[$idx] = $file;
      }
    }

    foreach($toRepair as $ors) {
      $oldVal = json_decode($ors[$field]);
      if($oldVal && $oldVal->dir) {
        $diroid = $oldVal->dir;
        if($diroid && $files[$diroid] && file_exists($files[$diroid])) {
          $dirname = $this->dirname($diroid) . '/';

          $file = array();
          if(count($oldVal->files) > 1) {
            foreach($oldVal->files as $key => $file) {
              if($key < count($oldVal->files) - 1) {
                $message = 'Fichier '.$GLOBALS['DATA_DIR'] . $dirname . $file->file . ' supprimé';
                \Seolan\Core\Logs::notice('XFileDef::chkChangedSimple', $message);
                \Seolan\Core\Shell::alert($message);
              }
            }
          } else {
            $file = $oldVal->files[0];
          }

          $oldFilePath = $GLOBALS['DATA_DIR'] . $dirname . $file->file;
          $newFilePath = substr($GLOBALS['DATA_DIR'] . $dirname, 0, -1); // On enleve le "/" de fin
          if(file_exists($oldFilePath)) {
            rename($oldFilePath, TZR_TMP_DIR . $diroid);
            \Seolan\Library\Dir::unlink($GLOBALS['DATA_DIR'] . $dirname);
            rename(TZR_TMP_DIR . $diroid, $newFilePath);

            $newVal = json_encode(array(
              'file' => $diroid,
              'mime' => $file->mime,
              'name' => $file->name,
              'title' => $file->title,
            ));
            getDB()->execute("UPDATE $table SET $field = ? WHERE KOID=? AND LANG=?", array($newVal, $ors['KOID'], $ors['LANG']));
          }
        }
      }
    }
  }

  protected function chkMultiple(&$messages) {

    $this->chkChangedMultiple();

    $root=$GLOBALS['DATA_DIR'].$this->table.'/'.$this->field;
    $files = \Seolan\Library\Dir::scan($root);
    $list=array();
    foreach($files as $i => $file) {
      $names=explode('/',$file);
      $names=array_reverse($names);
      $list[$names[1]][$names[0]] = $file;
    }
    $files =getDB()->fetchAll('SELECT KOID, '.$this->field.' FROM '.$this->table.' ');
    foreach($files as $k => $v) {
      $json = json_decode($v[$this->field], true);
      if(is_array($json) and isset($json['files'])) {
        foreach($json['files'] as $i=>$file) {
          unset($list[$json['dir']][$file['file']]);
        }
      }
    }
    foreach($list as $id=>$files) {
      foreach($files as $name=>$filename) {
        $flag=@unlink($filename);
        if(!$flag) $messages.='unlinking '.$filename.':nok'."\n";
      }
    }
    $dirs=\Seolan\Library\Dir::scan($root, true, false, true);
    $do=true;
    while($do) {
      $do=false;
      foreach($dirs as $i=>$dirname) {
        if(@rmdir($dirname)) {
          unset($dirs[$i]);
          $do=true;
        }
      }
    }
  }

  // Transformation de fichier monovalué en multivalué
  protected function chkChangedMultiple() {

    $table = $this->table;
    $field = $this->field;

    if($this->translatable) {
      $toRepair = getDB()->fetchAll("SELECT KOID, $field, LANG FROM $table WHERE $field not like '{\"dir\"%' and $field != ?", array(TZR_UNCHANGED));
    }
    else {
      $toRepair = getDB()->fetchAll("SELECT KOID, $field, LANG FROM $table WHERE $field not like '{\"dir\"%' and $field != ? and LANG=?", array(TZR_UNCHANGED, TZR_DEFAULT_LANG));
    }

    if(count($toRepair)) {
      $root = $GLOBALS['DATA_DIR']."$table/$field";
      $files = array();
      $filesScan = \Seolan\Library\Dir::scan($root);
      foreach($filesScan as $i => $file) {
	$parts = explode('/',$file);
        $idx = array_pop($parts);
        $files[$idx] = $file;
      }
    }

    foreach($toRepair as $ors) {
      $oldVal = json_decode($ors[$field]);
      if($oldVal && $oldVal->file && $files[$oldVal->file]) {
        rename($files[$oldVal->file], TZR_TMP_DIR.$oldVal->file);
        $dirname = $this->dirname($oldVal->file);
        $this->addToFolder('1',
			   ($fooretval=null),
			   $dirname.'/',
			   TZR_TMP_DIR.$oldVal->file);
        $newVal = json_encode(array(
          'dir' => $oldVal->file,
          'files' => array(
            array(
              'file' => '1',
              'mime' => $oldVal->mime,
              'name' => $oldVal->name,
              'title' => $oldVal->title
            )
          )
        ));
        getDB()->execute("UPDATE $table SET $field = ? WHERE KOID=? AND LANG=?", array($newVal, $ors['KOID'], $ors['LANG']));
      }
    }
  }

  // Repare le champ
  function repair(&$message){
    if($this->multivalued) return;

    $rs=getDB()->select("SELECT KOID,{$this->field},LANG FROM {$this->table} WHERE {$this->field} !=?",[TZR_UNCHANGED]);
    while($rs && ($ors=$rs->fetch())) {
      if(!$ors[$this->field] || !($dec=json_decode($ors[$this->field])) || !isset($dec->w)) continue;
      unset($dec->w,$dec->h);
      getDB()->execute('update '.$this->table.' set UPD=UPD,'.$this->field.'=? where KOID=? and LANG=?',
                       array(json_encode($dec),$ors['KOID'],$ors['LANG']));
    }
  }

  /// Prepare la recherche rapide sur le champ
  function my_quickquery($value,$options=NULL) {
    if($this->multivalued) return parent::my_quickquery($value,$options);

    $r=$this->my_query($value,$options);
    $r->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    return $r;
  }

  /// Prepare la recherche sur le champ
  function my_query($value,$options=NULL) {
    if($this->multivalued) return parent::my_query($value,$options);

    if(is_array($value)){
      $v=$value['name'];
      $mime=$value['mime'];
    }else{
      $v=$value;
      $mime='';
    }
    $v=htmlspecialchars($v);
    $labelin=@$options['labelin'];
    $r=$this->_newXFieldVal($options,true);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $varid=$r->varid;
    $t='<input type="text" id="'.$fname.$varid.'" name="'.$fname.'[name]" size="'.($this->fcount>30?30:$this->fcount).'" value="'.$v.'"/>';
    $t.='<select name="'.$fname.'[mime]">';
    $t.='<option value="">---</option>';
    $t.='<option value="image/*"'.($mime=='image/*'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Mime','image/*').'</option>';
    $t.='<option value="*video*"'.($mime=='*video*'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Mime','video/*').'</option>';
    $t.='<option value="*audio*"'.($mime=='*audio*'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Mime','audio/*').'</option>';
    $t.='<option value="text/html"'.($mime=='text/html'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Mime','text/html').'</option>';
    $t.='<option value="application/pdf"'.($mime=='application/pdf'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Mime','application/pdf').'</option>';
    $t.='</select>';
    if(!empty($labelin)) $t.='<script type="text/javascript">inputInit("'.$fname.$varid.'","'.addslashes($this->label).'");</script>';
    $r->varid=$varid;
    $r->html=$t;
    $r->raw=$value;
    return $r;
  }

  function post_query($o,$ar){
    if(is_array($o->value)) {
      $name = addslashes($o->value['name']);
      $mime = addslashes($o->value['mime']);
      $sql = '';
      if($mime) {
        $mime = str_replace('*', '', $mime);
        $mime = str_replace('/', '\\\\/', $mime);
        $sql .= '.*"mime":".*'.$mime.'.*".*';
      }
      if($name) {
        $sql .= '.*"name":".*'.$name.'.*".*';
      }
      if($sql) {
        $o->op='regexp';
        $o->value=$sql;
      }
    }
    else {
      $o->value='';
    }
    return parent::post_query($o,$ar);
  }

  // Decode les données raw (methode appelée par la methode magique __get de \Seolan\Core\Field\Value)
  function decodeRaw(&$r){
    $r->decoded_raw=json_decode($r->raw);
    return $r->decoded_raw;
  }

  /// Recupere le texte d'une valeur
  public function &toText($r) {
    if($this->multivalued) return parent::toText($r);

    if(!property_exists($r, 'text') || $r->text===NULL){
      $r->text='';
      if(!empty($r->title)){
	$r->text.=$r->title;
	if(!empty($r->originalname) && $r->title!=$r->originalname) $r->text.=' ('.$r->originalname.')';
      }elseif(!empty($r->originalname)){
	$r->text.=$r->originalname;
      }
    }
    return $r->text;
  }

  /// Recupere le type du champ dans un webservice (name : type xml, descr : description du type pour l'ajour d'une type complexe)
  function getSoapType(){
    return array('name'=>'tns:file','descr'=>array('file'=>array(array('name'=>'mime','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string'),
								 array('name'=>'url','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string'),
								 array('name'=>'originalname','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string'))));
  }
  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    if($r->url) return array('mime'=>$r->mime,'url'=>$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$r->url,'originalname'=>$r->originalname);
    else return;
  }
  /**
   * ajout de traitements specifiques à un mime donné
   * -> viewer autodesk si un compte est configuré
   */
  protected function getHtmlViewer($r, $options, $multi=false){
    if ($this->isEmpty($r) || !\Seolan\Core\Shell::admini_mode()) {
      return;
    }
    if (\Seolan\Field\File\Autodesk\AutodeskInterface::active()){
      return \Seolan\Field\File\Autodesk\AutodeskInterface::configureViewer($r, $options, $multi);
    } else {
      return \Seolan\Field\File\Viewer\Viewer::configureViewer($r, $options, $multi);
    }
  }
  /// ajoute le html du viewer au r->html
  protected function configureViewer($r, $options, $multi=false){
    $r->html .= $this->getHtmlViewer($r, $options, $multi);
  }
  protected function deleteViewerData($file){
    if (\Seolan\Field\File\Autodesk\AutodeskInterface::active()){
      \Seolan\Field\File\Autodesk\AutodeskInterface::deleteViewerData($file);
    }
  }
  protected function scanFile(string $fullfilename, $filename='') {
    if($fullfilename && !clamScanFile($fullfilename)) {
      \Seolan\Core\Shell::alert("File <$filename> contains a virus");
      unlink($fullfilename);
      return false;
    }
    return true;
  }
}

function xfiledef_getfilesize(){
  $file=$_REQUEST['file'];
  $s=filesize($GLOBALS['DATA_DIR'].$file);
  echo \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','file_size').' : '.getStringBytes($s);
}

function xfiledef_uploadfiletopreview(){
  $keys=array_keys($_FILES);
  $new=uniqid('file_');
  move_uploaded_file($_FILES[$keys[0]]['tmp_name'],TZR_TMP_DIR.$new);
  $s=getimagesize(TZR_TMP_DIR.$new);
  echo json_encode(array('file'=>$new,'w'=>$s[0],'h'=>$s[1],'r'=>$s[0]/$s[1]));
}

function xfiledef_preview(){
  $file=str_replace('..','',$_REQUEST['file']);
  echo file_get_contents(TZR_TMP_DIR.$file);
}

function xfiledef_enablePlay() {
  sessionStart();

  $isSecure = (!empty($GLOBALS['TZR_SECURE']['_all']) || !empty($GLOBALS['TZR_SECURE'][$_REQUEST['table']][$_REQUEST['field']])
               || (!empty($GLOBALS['TZR_SECURE'][$_REQUEST['table']]) && $GLOBALS['TZR_SECURE'][$_REQUEST['table']] === '_all'));

  if ($isSecure){
    setSessionVar('enable_play', true, 'SECU_VIDEO');
    die('1');
  }

  die('0');
}
function modalHtmlViewer() {

  activeSec();

  $moid = $_REQUEST['moid'];
  $fieldName = $_REQUEST['field'];
  $oid = @$_REQUEST['oid'];
  $table = @$_REQUEST['table'];
  $lang = @$_REQUEST['lang'];
  $filename = @$_REQUEST['filename'];
  $page = @$_REQUEST['page'];
  $getPages = @$_REQUEST['getPages'];
  $action = @$_REQUEST['action'];

  if (empty($moid)) {
    header('HTTP/1.1 403 Forbidden');
    return null;
  }

  $mod = \Seolan\Core\Module\Module::objectFactory($moid);
  $ok = $mod->secure($oid, ':ro');
  if (!$ok) {
    header('HTTP/1.1 403 Forbidden');
    return null;
  }
  if (empty($table)){
    $table = $mod->table;
  }
  if (!$mod->usesTable($table) || empty($fieldName)) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  // display du champ
  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
  if (!$ds || !$ds->fieldExists($fieldName)){
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }

  $close = function($res){
    header('Content-Type: application/json;charset=UTF-8');
    die(json_encode($res));
  };

  if (!$page){
    echo \Seolan\Field\File\Viewer\Viewer::modalHtmlViewer();
  } else {

    $value = getDB()->fetchOne('select '.$fieldName.' from '.$table.' where KOID=? and LANG=?', [$oid,$lang]);
    $ofield = $ds->getField($fieldName);
    if ($ofield->multivalued){
      $r = $ofield->display($value, ($options=['fmoid'=>$moid,'oid'=>$oid]));
      $file = null;
      $i=0;
      $l_count=count($r->catalog);
      while($r->catalog[$i]->filename != $filename && $i < $l_count)
        $i++;
      if ($r->catalog[$i]->filename == $filename){
        $file = $r->catalog[$i];
      }else{
        $close(['image'=>null,
	      'title'=>null]);
      }
    } else {
      $file = $ofield->display($value, ($options=['fmoid'=>$moid,'oid'=>$oid]));
    }
    $image = null;
    $srdoc = null;
    $error = null;
    $response = [];
    $mimeTypeSupport = \Seolan\Field\File\Viewer\Viewer::mimeTypeSupport($file->mime);
    // Conversion de la page demandée en image.
    if ($mimeTypeSupport == 'calc') {
      // Conversion du document calc en pdf
      $pdf = getImageFromOpenOfficeDocument($file->filename, 1, '.pdf', false);
      $response['pages'] = exec('pdfinfo '.escapeshellarg($pdf).' | grep Pages | sed "s/[^0-9]*//"', $o, $res);
      // Conversion de la page du pdf en png
      $image = getImageFromPdfDocument($pdf, $page);
      unlink($pdf);
    } else if( $mimeTypeSupport == 'impress' or $mimeTypeSupport == 'draw') {
      $image = getImageFromOpenOfficeDocument($file->filename, $page);
    } else if ($mimeTypeSupport == 'pdf') {
      $image = getImageFromPdfDocument($file->filename, $page);
      if ($getPages){
        $response['pages'] = exec('pdfinfo '.escapeshellarg($file->filename).' | grep Pages | sed "s/[^0-9]*//"', $o, $res);
      }
    } else if ( $mimeTypeSupport == 'html') {
      $response['type'] = 'html';
      $srcdoc = file_get_contents($file->filename);
    } else if( $mimeTypeSupport == 'default') {
      \Seolan\Core\Logs::notice(__METHOD__." {$file->filename} {$file->mime}");
      if ($getPages){
        $pdf = getImageFromOpenOfficeDocument($file->filename, 1, '.pdf', false);
	\Seolan\Core\Logs::notice(__METHOD__,"pdf size '".filesize($pdf)."'");
        $response['pages'] = exec('pdfinfo '.escapeshellarg($pdf).' | grep Pages | sed "s/[^0-9]*//"', $o, $res);
        $image = getImageFromPdfDocument($pdf, $page);
	\Seolan\Core\Logs::notice(__METHOD__, "$image image size '".filesize($image)."'");
        unlink($pdf);
      } else {
        $image = getImageFromOpenOfficeDocument($file->filename, $page);
      }
    } else {
      // non supporté
      $error = \Seolan\Core\Labels::$LABELS['Seolan_Core_Field_Field']['unsupported_format'];
      \Seolan\Core\Logs::debug('Viewer: file '.$file->filename.' ('.$file->mime.') '.$error);
    }

    if (!$image && !$srcdoc) {
      $error = \Seolan\Core\Labels::$LABELS['Seolan_Core_Field_Field']['unable_to_display'].($page?' '.$page:'');
      \Seolan\Core\Logs::debug(__METHOD__.' '.$file->filename.' ('.$file->mime.') '.$error);
    } else if ($image){
      $response['image']=base64_encode($image);
      $reponse['type'] = 'image';
    } else if ($srcdoc){
      // non ie : srcdoc , MS : src
      $response['srcdoc']=$srcdoc;
      $response['urldoc']=$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$file->url;
      $reponse['type'] = 'html';
    }

    $response['error']=$error;
    $response['title']=$file->originalname;

    $close($response);
    }
}

function autocadViewer(){

  activeSec();

  $moid = $_REQUEST['moid'];
  $fieldName = $_REQUEST['field'];
  $oid = @$_REQUEST['oid'];
  $table = @$_REQUEST['table'];
  $lang = @$_REQUEST['lang'];
  $filename = @$_REQUEST['filename'];

  $mod = \Seolan\Core\Module\Module::objectFactory($moid);
  $ok = $mod->secure($oid, ':ro');
  if (!$ok) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  if (empty($table)){
    $table = $mod->table;
  }

  if (!$mod->usesTable($table) || empty($fieldName)) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }

  // display du champ
  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
  if (!$ds || !$ds->fieldExists($fieldName)){
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  $close = function($res){
    header('Content-Type: application/json;charset=UTF-8');
    die(json_encode($res));
  };
  $value = getDB()->fetchOne('select '.$fieldName.' from '.$table.' where koid=? and lang=?', [$oid,$lang]);
  $ofield = $ds->getField($fieldName);
  if ($ofield->multivalued){
    $r = $ofield->display($value, ($options=['fmoid'=>$moid,'oid'=>$oid]));
    $file = null;
    $i=0;
    $l_count=count($r->catalog);
    while($r->catalog[$i]->filename != $filename && $i < $l_count)
      $i++;
    if ($r->catalog[$i]->filename == $filename){
      $file = $r->catalog[$i];
    }else{
      $close(['win'=>['size'=>'width=800px,height=800px'],
	      'vtoken'=>$viewerToken,
	      'status'=>'error',
	      'urn'=>null,
	      'title'=>null]);
    }
  } else {
    $file = $ofield->display($value, ($options=['fmoid'=>$moid,'oid'=>$oid]));
  }
  if (defined('AUTODESK_BUCKET_PREFIX')){
    $ai = new \Seolan\Field\File\Autodesk\AutodeskInterface(AUTODESK_APP_ID, AUTODESK_APP_SECRET, AUTODESK_BUCKET_PREFIX);
  } else {
    $ai = new \Seolan\Field\File\Autodesk\AutodeskInterface(AUTODESK_APP_ID, AUTODESK_APP_SECRET);
  }
  list($filestatus, $fileurn) = $ai->getViewURN($file);
  $viewerToken = $ai->getViewerToken();

  // $status == 'success' : on ajoute le viewer
  // $status == 'inprogress' : on ajoute une tempo ou try later ?
  // ces 2 ci-dessus : existait ou fait à la volée
  // $status == 'running' : demande en cours d'enregistrement, pars d'urn
 $close(['win'=>['size'=>'width=800px,height=800px'],
	 'vtoken'=>$viewerToken,
	 'status'=>$filestatus,
	 'urn'=>$fileurn,
	 'title'=>$file->originalname]);
}
