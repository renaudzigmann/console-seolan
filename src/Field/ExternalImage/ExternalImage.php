<?php
namespace Seolan\Field\ExternalImage;
/// Champ image externe, utilise pour garder un cache d'une image donnee par une URL
class ExternalImage extends \Seolan\Field\Image\Image {
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    $r->raw=$value;
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field."[$o]";	
      $hiddenname=$this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field."_HID";
    }	
    $this->_checkDir();
    $txt = "File:&nbsp;<input type=\"text\" name=\"$fname"."[url]\" size=30 value=\"".$r->decoded_raw->url."\"/>";
    $txt .= "Refresh rate (secs): <input type=\"text\" name=\"".$fname."[refresh]\" size=5 value=\"".$r->decoded_raw->refresh."\"/>";
    $r->html = $txt;
    return $r;
  }
  function my_display(&$value,&$options,$genid=false) {
    global $DATA_DIR;
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    $toupdate=false;
    if(!$r->decoded_raw) return $r;
    $files=$this->filename($r->decoded_raw->file,true);
    if($files) {
      $filename=$DATA_DIR.$files;
      $file_date = stat($filename);
      $file_date = $file_date[10];
      // age du fichier en secondes
      $age = (time()-$file_date);
      if($age > $r->decoded_raw->refresh) {
	$toupdate=true;
      } else {
	if($_REQUEST["forcecache"] || $_REQUEST["nocache"]) $toupdate=true;
      }
    } else {
      $files=$this->filename($r->decoded_raw->file);
      $filename=$DATA_DIR.$files;
      $toupdate=true;
    }
    if($toupdate && !empty($r->decoded_raw->url)) {
      $content = @file_get_contents($r->decoded_raw->url);
      if($content===0) $skip=true;
      else {
	$fp = fopen($filename,'w+');
	fwrite($fp, $content);
	fclose($fp);
      }
    }
    $r->decoded_raw->mime='image/jpeg';
    return parent::my_display(json_encode($r->decoded_raw),$options,$genid);
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    $oid=$options['oid'];
    $refresh=$value['refresh'];
    list($t,$o)=explode(":",$oid);
    $r->raw = json_encode((object)array('file'=>$o,'refresh'=>$refresh,'url'=>$value['url']));
    return $r;
  }
}
?>
