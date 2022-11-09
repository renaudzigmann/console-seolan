<?php
namespace Seolan\Field\Icon;

/*
Le champ Icône va chercher dans des fichier (css), les classe css correspondant à une expression régulière donnée
Il permet alors de sélectionner une icone.
*/

/// Gestion des icons
class Icon extends \Seolan\Field\ShortText\ShortText {
  public $multivalued = false;
  public $display_format='<span class="glyphicon %s"></span>'; //affiche de l'icon
  public $regexp_format='^.csico-*'; //l'expresion régulière qui permet l'extration des class d'icon
  public $fichiers_css; //la liste brute des fichier css prise en compte
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  /**
   * Surcharge complète des options pour n'avoir que le minimum géré
   */
  function initOptions() {
    parent::initOptions();
    $mynames = ['theclass','readonly','hidden','comment','acomment','qcomment','fgroup','default','add_browse_class','indexable','RGPD_personalData']; 
    // suppression des options qu'on gère pas
    foreach($this->_options->names() as $name){
      if (!in_array($name, $mynames))
	$this->_options->delOpt($name);
    }
    
    // options spécifiques
    $iconGroup = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', '\seolan\field\icon\icon');
    // options spécifiques
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','display_format'),'display_format','text',[],null, $iconGroup);
    $this->_options->setOpt('Expression régulière pour l\'extraction','regexp_format','text',[], null, $iconGroup);
    $this->_options->setOpt('fichier css exemple: base.css dark-theme.css','fichiers_css','text',[], null, $iconGroup);

  }

  function convertValues($oldftype){
    if ($oldftype != '\Seolan\Field\ShortText\ShortText')
      return;
    // contenus est (du genre) : <span class="glyphicon  csico-communication-01"></span>
    $exp = 'csico-[-a-z0-9]+';
    getDB()->execute("update {$this->table} set {$this->field}=regexp_substr({$this->field}, \"{$exp}\") where {$this->field} REGEXP \"$exp\""); 
  }

  function my_display_deferred(&$r){
    if (empty($r->raw))
      $r->html="";
    else
      $r->html=sprintf($this->display_format, $r->raw, $r->raw,$r->raw, $r->raw, $r->raw);
    return $r;
  }
  

  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p=new \Seolan\Core\Param($options,[]);
    $r=$this->_newXFieldVal($options,true);
    $r->varid=uniqid('v');
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field.'['.$o.']';	
      $hiddenname=$this->field.'_HID['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }
    $js = '';
    if($this->compulsory){
        $color=\Seolan\Core\Ini::get('error_color');	
        $class = '';
        if ($this->compulsory)
            $class = "tzr-input-compulsory";
        if (@$this->error)
            $class .= " $color";
        if ($class)
            $class = " class=\"$class\"";
        if($this->compulsory)
            $js.='TZR.addValidator(["'.$r->varid.'",/(.+)/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);';
        $html='<input required onblur="TZR.isIdValid(\''.$r->varid.'\');" id="'.$r->varid.'" '.$class.' name="'.$fname.'" value="'.$value.'"><script>'.$js.'</script>';
    } else {//
        $html='<input type="hidden" id="'.$r->varid.'" name="'.$fname.'" value='.$value.'>';
    }
    $r->html=$html;
    $r->raw=$value;
    if (isset($this->regexp_format))
      $extractExp = $this->regexp_format;
    else
      $extractExp = "'^.csico-*'"; //regexp par défaut
    if (isset($this->fichiers_css)) {
      $this->fichiers_css=explode(' ', $this->fichiers_css);
      $css_final="'".$this->fichiers_css[0]."'";
      for ($i = 1; $i < sizeof($this->fichiers_css); $i++) { 
        $css_final.= " , '".$this->fichiers_css[$i]."' ";
      }
      $extract_css_files = $css_final; //pensé a explode les fichiers
    }else {
      $extract_css_files ="'dark-theme.css', 'base.css' "; //feuille de style par défaut, les csico sont dans dark_theme.css
    }
    $js.='
   var ico="'.trim($r->raw).'";
   var current = TZR.sprintf("'.addcslashes($this->display_format, '`"').'",ico, ico, ico, ico);
   jQuery("#dis-ico'.$r->varid.'").append("<span class=\"bordered-icon "+(ico==""?" empty ":"")+"\">"+current+"</span>"); //la div sur laquelle on affiche les icones
   var tab_styleshett= [ '.$extract_css_files.' ] //liste des fichier css prise en compte
   var regex="'.$extractExp.'"; //expresion régulière
   function filtre_ico (tab_styleshett, regex) { //tab_styleshett est un tableau de nom feuille, regex c\'est l\'expresion qui permet de trouvé les classe a extraire doit a terme renvoyé un tableau de classe
         var css_class_tab=[]
         window.css_string'.$r->varid.'=[] //tableau qui stock durablement les classe css de chaque champs
         var styleSheetList = document.styleSheets;//on récupère les feuille de style de la page
         var style_good=false; //booléen qui vérifie si la feuille de style corrspond bien a un des feuille demandé
         var regex=new RegExp(regex); 
         
         $(styleSheetList).each(function(feuille_css) {
             tab_styleshett.forEach(function(st) {
                 if(styleSheetList[feuille_css]["href"]!=null && styleSheetList[feuille_css]["href"].includes(st)) {
                     style_good=true;
                 } 
                 else if (styleSheetList[feuille_css]["href"]!=null ) {
                     $(styleSheetList[feuille_css]["cssRules"]).each(function(import_css) { 
                         test_import=styleSheetList[feuille_css]["cssRules"][import_css]["cssText"];
                         tab_styleshett.forEach(function(stc) {
                             if((test_import).includes(stc)) {
                                 style_good=true;
                             }
                         })
                     })
                 }
             })
             var y=0;
             if(style_good==true) {
                 $(styleSheetList[feuille_css]["cssRules"]).each(function(n) {
                     $(styleSheetList[feuille_css]["cssRules"][n]["styleSheet"]).each(function(op) {
                         $(styleSheetList[feuille_css]["cssRules"][n]["styleSheet"]["cssRules"]).each(function(z) {
                             var css_text=styleSheetList[feuille_css]["cssRules"][n]["styleSheet"]["cssRules"][z]["cssText"];
                             if(regex.test(css_text)==true) {
                               var ico_name=css_text.replace(regex, ""); //ico_name contient la classe css de l\'icon sans préfix
                                var supr_7777=ico_name.indexOf(":");
                                ico_name=ico_name.substr(0,supr_7777);
                                 if(css_text.indexOf(":")) {
                                     supr=css_text.indexOf(":");
                                 }
                                 else if(css_text.indexOf("{")) {
                                     supr=css_text.indexOf("{");
                                 }
                                
                                 css_text=css_text.substr(1,supr-1)
                                 
                                 if(css_text.includes("{")) {
                                     supr2=css_text.indexOf("{");
                                     css_text=css_text.substr(0,supr2-1)
                                 }
                                 else if(css_text.includes(":")) {
                                     supr2=css_text.indexOf(":");
                                     css_text=css_text.substr(0,supr2-1)
                                 }
                                                     
                                 if(css_text.includes(".")) {
                                     
                                     css_tab'.$r->varid.'=css_text.split(".");
                                     css_tab'.$r->varid.'.forEach(function(d) {
                      
                                         if(d.includes(",")) {
                                             supr=css_text.indexOf(",");
                                             d.substr(0,supr-1)
                                         }
                                        $("#dis-ico-body-'.$r->varid.'").append("<span class=\'"+d+"\'></span>");
                                         css_class_tab.push(d);
                                     })
                                 }
                                 else if(css_text.includes(",")) {
                                     css_tab'.$r->varid.'=css_text.split(",");
                                     css_tab'.$r->varid.'.forEach(function(d) {
                                         css_class_tab.push(d);
                                     })
                                     
                                 }
                                 else if(css_text.includes(">")) {
                                     css_tab'.$r->varid.'=css_text.split(">");
                                     css_tab'.$r->varid.'.forEach(function(d) {
                                         css_class_tab.push(d);
                                     })
                                     
                                 }
                                 
                                 else {
                                      css_string'.$r->varid.'.push(String(css_text));
                                      jQuery("#dis-ico-body-'.$r->varid.'  ul").append("<li onclick=\'TZR.IconField.select(jQuery(css_string'.$r->varid.'["+y+"]), '.$r->varid.' );\'><span class=\'glyphicon "+css_text+"\' id=\'"+css_text+"'.$r->varid.'\' ></span><span class=\'glyphicon-class\'>"+ico_name+"</span></li>  "); 
                                      css_class_tab.push(css_text);
                                 }
                               y++;
                                 
                             }
                         })
                     })
                 })
             }
         })                 

         return css_class_tab;
 }
 jQuery("#search-ico'.$r->varid.'").keyup(function(e) {
    var val_filtre=String(jQuery("#search-ico'.$r->varid.'").val());
    var icogroups = jQuery("#dis-ico-body-'.$r->varid.' ul li").show(); 
    icogroups.each(function(i, o){
      var current_icon_class=o.childNodes[1].innerHTML;
      if(current_icon_class.includes(val_filtre)==true)
      jQuery(o).show();
      else
      jQuery(o).hide(); 
    });
});
 var test=filtre_ico(tab_styleshett, regex);';
  $r->html=$html.
  '<script>'.$js.'</script>
   <span data-toggle="modal" data-target="#ico_Modal'.$r->varid.'" id="dis-ico'.$r->varid.'" data-boformat="'.htmlspecialchars($this->display_format).'"></span><button type="button" class="btn btn-default btn-md btn-inverse" data-toggle="modal" data-target="#ico_Modal'.$r->varid.'">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit').'</button>';
  if (!$this->compulsory){
    $r->html .= '<button type="button" onclick="TZR.IconField.unSelect(\''.$r->varid.'\'); return false;" class="btn btn-default btn-md btn-inverse">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete').'</button>';
  }
   $searchLabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'newquery');
   $r->html .= '
   <div class="modal fade" id="ico_Modal'.$r->varid.'" tabindex="-1" role="dialog" aria-hidden="true">
   <div class="modal-dialog modal-lg">
     <div class="modal-content">
       <div class="modal-header">
         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
         <h4 class="modal-title">'.$fname.'</h4>
       </div>
       <div class="modal-body field-icon" id="dis-ico-body-'.$r->varid.'">
       <div class="form-group">
       <input type="text" id='."search-ico".$r->varid.' placeholder="'.$searchLabel.'">
       </div>
       <div class="font-glyphicons clearfix"><ul class="font-glyphicons-list"></ul></div>
       </div>
       <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal"> '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'close').' </button>
       </div>
     </div>
   </div>
 </div> '; 
 return $r;
  }
}


