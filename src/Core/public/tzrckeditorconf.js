// Ajout d'un bouton parcourir les rubrique dans le dialogue des liens
CKEDITOR.on('dialogDefinition',function(e){
    var el = e;
    if(el.data.name=='image'){
      el.data.definition.contents[0].elements[0].children[0].children[1].onClick = function() {
        var url = this.filebrowser.url + '&CKEditor='+el.editor.name+'&CKEditorFuncNum='+el.editor._.filebrowserFn+'&langCode='+el.editor.langCode;
        var c = this.getDialog().getParentEditor();
        c._.filebrowserSe = this;
        TZR.Dialog.openURL(url, {}, {initCallback:{_function:'hideCKEDITOR'}, closeCallback:{_function:'showCKEDITOR'}});
      };
      el.data.definition.contents[1].elements[1].onClick = function() {
        var url = this.filebrowser.url + '&CKEditor='+el.editor.name+'&CKEditorFuncNum='+el.editor._.filebrowserFn+'&langCode='+el.editor.langCode;
        var c = this.getDialog().getParentEditor();
        c._.filebrowserSe = this;
        TZR.Dialog.openURL(url, {}, {initCallback:{_function:'hideCKEDITOR'}, closeCallback:{_function:'showCKEDITOR'}});
      };
    }
    if(el.data.name=='link'){
      el.data.definition.contents[0].elements[1].children[1].onClick = function() {
        var url = this.filebrowser.url + '&CKEditor='+el.editor.name+'&CKEditorFuncNum='+el.editor._.filebrowserFn+'&langCode='+el.editor.langCode;
        var c = this.getDialog().getParentEditor();
        c._.filebrowserSe = this;
        TZR.Dialog.openURL(url, {}, {initCallback:{_function:'hideCKEDITOR'}, closeCallback:{_function:'showCKEDITOR'}});
      };
	el.data.definition.contents[0].elements[1].children.push({
	      type:'button',
	      id:'browsealias',
	      filebrowser:{
		  action:'Browse',
		  url:el.editor.config.filebrowserAliasBrowseUrl,
		  target:'info:url'
	      },
	  label:'Parcourir les rubriques',
          onClick: function() {
              var url = this.filebrowser.url + '&CKEditor='+el.editor.name+'&CKEditorFuncNum='+el.editor._.filebrowserFn+'&langCode='+el.editor.langCode;
	      var c = this.getDialog().getParentEditor();
	      c._.filebrowserSe = this;
	      TZR.Dialog.openURL(url, {}, {initCallback:{_function:'hideCKEDITOR'}, closeCallback:{_function:'showCKEDITOR'}});
	  }
      });
  }
});

function hideCKEDITOR() {
    jQuery('.cke_dialog_background_cover, .cke_dialog').hide();
}

function showCKEDITOR() {
    jQuery('.cke_dialog_background_cover, .cke_dialog').show();
}

var toolbar_Basic = [
      ['Bold','Italic','Underline','-','Scayt','-','NumberedList','BulletedList','-','Outdent','Indent','-','Link','Unlink','-','RemoveFormat','-','Maximize','Source','-','About']
];
var toolbar_Accessibility = [
        ['Source','Cut','Copy','Paste','PasteText','PasteFromWord','SelectAll','RemoveFormat','-','Scayt'],
        ['Bold','Italic','Underline'],
        ['NumberedList','BulletedList','-','Outdent','Indent'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['Undo','Redo'],
        ['Link','Unlink','Anchor','Image','Table','SpecialChar','PageBreak'],
        '/',
        ['Styles','Format','Font','FontSize','TextColor','BGColor'],
        '/',
        ['Maximize','-','About']
] ;
var toolbar_Complete = [
         ['Source','-',/*'Save','NewPage','Preview','-'*/,'Templates'],
         ['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','Scayt'],
         ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
         ['Form','Checkbox','Radio','TextField','Textarea','Select','Button','ImageButton','HiddenField'],
         '/',
         ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
         ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote'],
         ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
         ['Link','Unlink','Anchor'],
         ['Image',/*'Flash',*/'Table','HorizontalRule','Smiley','SpecialChar','PageBreak'],
         ['Iframe'],
         '/',
         ['Styles','Format','Font','FontSize'],
         ['TextColor','BGColor'],
         ['Maximize', 'ShowBlocks','-','About']
];
// Configuration TZR
CKEDITOR.editorConfig = function( config ){
  // Define changes to default configuration here. For example:
  // config.language = 'fr';
  // config.uiColor = '#AADC6E';
    config.filebrowserWindowWidth = '300';
    config.filebrowserWindowWidth = '600';
  config.contentsCss = "/csx/src/Core/public/css/ckeditor.css";
  config.scayt_autoStartup = false ;
  config.scayt_sLang = "fr_FR";
  config.skin="moono";
  config.resize_minWidth=100;
  config.toolbarStartupExpanded=false;
  config.toolbarCanCollapse=true;
  config.allowedContent = true;
  config.resize_dir = 'both';
  config.toolbar_Basic = toolbar_Basic;
  config.toolbar_Accessibility = toolbar_Accessibility;
  config.toolbar_Complete =toolbar_Complete;
};
