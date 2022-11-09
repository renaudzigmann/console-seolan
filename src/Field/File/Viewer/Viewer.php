<?php 
namespace Seolan\Field\File\Viewer;
class Viewer {

  public static $pdftoppmIsOk = null;
  public static $csxofficeconverterIsOk = null;
  public static $sofficeIsOk = null;
    
  public static function configureViewer($r, $options, $multi=false){
    // cas fichier vide à fixer, pas par r->raw : vide en multi
      $type = self::mimeTypeSupport($r->mime);
      if (!$type || empty($options['fmoid'])) {
	return '';
      }

      if(self::$pdftoppmIsOk == null) {
          exec('which pdftoppm', $o, $res);
          self::$pdftoppmIsOk = $res === 0 ? true : false ;
      }

      if(self::$csxofficeconverterIsOk == null) {
          exec('pgrep -f '.TZR_VIEWER_SPOOL_BIN, $pids);
          self::$csxofficeconverterIsOk = count($pids) > 1 && file_exists(TZR_VIEWER_SPOOL_DIR.'in') ? true : false ;
      }

      if(self::$sofficeIsOk == null) {
          exec('pgrep -f soffice.bin', $pids);
          self::$sofficeIsOk = count($pids) > 1 ? true : false ;
      }
      if (($type == 'pdf' and self::$pdftoppmIsOk) 
	or (self::$sofficeIsOk and self::$csxofficeconverterIsOk)
      ) {
          $uniqid = \Seolan\Core\Shell::uniqid();

          $params = json_encode(['varid'=>$r->varid,
          'uniqid'=>$uniqid,
          'url'=>TZR_AJAX8.'?'.http_build_query(['field'=>$r->field,
          'table'=>$r->table,
          'lang'=>\Seolan\Core\Shell::getLangData(),
          'moid'=>$options['fmoid'],
          'class'=>'\Seolan\Field\File\File',
          'function'=>'modalHtmlViewer',
          '_silent'=>1,
          '_skip'=>1,
          'filename'=> ($multi?$r->filename:false),
          'oid'=>$options['oid']??null])]);
	  // maintient d'une classe pour désactiver via css ?
	  return '<button type="button" class="btn btn-default btn-md btn-inverse btn-viewer" onclick="TZR.Viewer.load('.htmlspecialchars($params).');"><span class="glyphicon csico-view"></span></button>';
      }
  }

  public static function modalHtmlViewer(){
    $html='<div class="viewerError"></div>'.
	  '<div class="viewer">'.      
	  '<div id="viewerImage" class="viewerImage"><a onclick="TZR.Viewer.next();"><img id="viewerImg" alt="" src=""></img></a></div>'.
	  '<div class="embed-responsive embed-responsive-4by3" id="viewerHtml"><iframe sandbox class="embed-responsive-item" width="100%" height="100%" src="" srcdoc=""/></div>'.
	  '<script type="text/javascript">(function() { TZR.Viewer.open(); } )();</script>'.
	  '</div>'.
	  '<div class="tzr-action">'.
          '<div class="alert alert-light viewerHelp" style="display:none">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','viewer_help').'</div>'.
	  '<a class="viewerPrevious btn btn-info"  style="display:none" onclick="TZR.Viewer.previous();" title="&larr;"><span class="glyphicon csico-arrow_left"></span></a>'.
	  '<a class="viewerNext btn btn-info"  style="display:none" onclick="TZR.Viewer.next();" title="&rarr;"><span class="glyphicon csico-arrow_right"></span></a>'.
	  '<button class="btn btn-default" data-dismiss="modal">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','close').'</button>'.
	  '</div>'
	  ;
    return $html;
  }

  public static function mimeTypeSupport($mime) {
      $result = false;
      if (in_array($mime , [
          'application/vnd.ms-excel',
          'application/vnd.ms-excel.sheet.macroEnabled.12',
          'application/vnd.oasis.opendocument.spreadsheet',
          'application/vnd.oasis.opendocument.spreadsheet-template',
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      ])) {
          $result = 'calc';
      } else if (in_array($mime , [
          'application/vnd.ms-powerpoint',
          'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
          'application/vnd.ms-powerpoint.template.macroEnabled.12',
          'application/vnd.openxmlformats-officedocument.presentationml.presentation',
          'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
          'application/vnd.oasis.opendocument.presentation',
          'application/vnd.oasis.opendocument.presentation-template'
      ])) {
          $result = 'impress';
      } else if (in_array($mime , [
          'application/vnd.oasis.opendocument.graphics',
          'application/vnd.oasis.opendocument.graphics-template',
      ])) {
          $result = 'draw';
      } else if ($mime == 'application/pdf') {
          $result = 'pdf';
      }  else if (in_array($mime, [
	'text/plain','text/html'
      ])){
        $result = 'html';
      } else if( in_array($mime, [          
          'application/vnd.ms-word.document.macroEnabled.12',
          'application/vnd.oasis.opendocument.text',
          'application/vnd.oasis.opendocument.text-template',
          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          'application/msword',
          'application/xml',
          'application/rtf',
          'text/csv',
          '__text/plain',
	  '__text/html'
      ])) {
	$result = 'default';
      } else {
          $result = 'default';
      }
      return $result;
  }
}
