// Configuration TZR
CKEDITOR.editorConfig = function( config ){
  // Define changes to default configuration here. For example:
  // config.language = 'fr';
  // config.uiColor = '#AADC6E';
  config.contentsCss = "../css/ckeditor.css";
  config.scayt_autoStartup = false ;
  config.scayt_sLang = "fr_FR";
  config.skin="moono";
  config.resize_minWidth=100;
  config.toolbarStartupExpanded=false;
  config.toolbar_Basic = [
			  ['Bold','Italic','Underline','-','Scayt','-','NumberedList','BulletedList','-','Outdent','Indent','-','Link','Unlink','-','RemoveFormat','-','Maximize','Source','-','About']
  ];
  config.toolbar_Accessibility = [
				  ['Source','Cut','Copy','Paste','PasteText','PasteFromWord','SelectAll','RemoveFormat','-','Scayt'],
				  ['Bold','Italic','Underline'],
				  ['NumberedList','BulletedList','-','Outdent','Indent'],
				  ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
				  ['Link','Unlink','Anchor','Table','SpecialChar','PageBreak'],
				  '/',
				  ['Styles','Format','Font','FontSize','TextColor','BGColor'],
				  '/',
				  ['Maximize','-','About']
  ] ;
  config.toolbar_Complete = [
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
			     '/',
			     ['Styles','Format','Font','FontSize'],
			     ['TextColor','BGColor'],
			     ['Maximize', 'ShowBlocks','-','About']
  ];
};
