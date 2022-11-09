<?php
namespace Seolan\Field\Image;
use \Seolan\Core\Labels;
/// Champ image, permettant la gestion des images et en particulier des éléments spécifiques aux images
class Image extends \Seolan\Field\File\File {
  public $geometry='2048x2048';
  public $usemimehtml=true;
  public $viewlink=false;
  public $image_edition=false;
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Editer l\'image', 'image_edition','boolean', NULL, true);
    $this->_options->setComment('Editer, appliquer des effets, etc sur l\'image','image_edition');
  }
  /**
   * bouton ouverture WIE
   */
  protected function editAction($r,$value,$options,$disp,$fname,$hiddenname){
    if (!\Seolan\Core\Shell::admini_mode() || !$this->image_edition || $this->isEmpty($disp))
      return '';
    $downloader = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/'.TZR_DOWNLOADER.'?tmp=1&del=0&filename=';
    $html .= <<<EOT
      <script type="text/javascript">
        if(jQuery().imageEditor) {
          jQuery().imageEditor.personaliseLang = {
            title:'Retouche d\'image',
            upload_button:'Sauver',
            cancel_button:'Annuler'
          };
        }
      </script>
EOT;
    $html .= '<span class="image-edition"><button type="button" class="btn btn-default btn-md btn-inverse" id="image_edition_'.$r->varid.'">'.Labels::getSysLabel('Seolan_Core_General', 'edit').'</button></span><input type="hidden" value="" id="'.$r->varid.'-updated" name="'.$r->fielddef->field.'_HID[editflag]">';
    $html .= '<script type="text/javascript">jQuery("#image_edition_'.$r->varid.'").on("click", function(){TZR.imageEditorOpen({path:"/csx/Vendor/wie/",downloader:"'.$downloader.'",varid:"'.$r->varid.'",formatImageSave:"jpeg", onUpload:function(data){TZR.imageEditorUploaded(data, "'.$r->varid.'");}, imageName:"'.addslashes($r->originalname).'", urlImage:"'.$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/'.$r->url.'", urlServeur:"'.TZR_SHARE_SCRIPTS.'image-uploader.php?moid='.$options['fmoid'].'&field='.$this->field.'&oid='.$options['oid'].'"});});</script>';
    return $html;
  }
  
  /**
   * Complete l'objet d'edition dans le cas d'une image
   * @note : gardée pour compatibilité éventuelle
   */
  function editImage(&$r,&$value,&$options,&$disp,$fname,$hiddenname){
    parent::editImage($r, $value, $options, $disp,$fname, $hiddenname);

    // en BO uniquement a ce stade de image-uploader
    if (\Seolan\Core\Shell::admini_mode()) {
      if ($this->image_edition && !empty($r->raw)){
	// imageEditor(), dans cette version : 
	// par default : la langue est fr, le format est png, la dim. max : 4096x4096
	// to do : intégrer le téléchargement, remonter dans un script general, labels
	
	$r->html .= <<<EOT
      <script type="text/javascript">
        if(jQuery().imageEditor) {
          jQuery().imageEditor.personaliseLang = {
            title:'Retouche d\'image',
            upload_button:'Sauver',
            cancel_button:'Annuler'
          };
        }
      </script>
EOT;
	$downloader = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/'.TZR_DOWNLOADER.'?tmp=1&del=0&filename=';
	$r->html .= '<div class="image-edition"><input id="image_edition_'.$r->varid.'" type="button" value="Modifier"></div><input type="hidden" value="" id="'.$r->varid.'-updated" name="'.$r->fielddef->field.'_HID[editflag]">';
	$r->html .= '<script type="text/javascript">jQuery("#image_edition_'.$r->varid.'").on("click", function(){TZR.imageEditorOpen({path:"/csx/Vendor/wie/",downloader:"'.$downloader.'",varid:"'.$r->varid.'",formatImageSave:"jpeg", onUpload:function(data){TZR.imageEditorUploaded(data, "'.$r->varid.'");}, imageName:"'.addslashes($r->originalname).'", urlImage:"'.$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/'.$r->url.'", urlServeur:"'.TZR_SHARE_SCRIPTS.'image-uploader.php?moid='.$options['fmoid'].'&field='.$this->field.'&oid='.$options['oid'].'"});});</script>';
      }
    }
  }
}
?>
