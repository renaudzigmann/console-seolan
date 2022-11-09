/* Mediatheque */
// Inverse la selection d'une fiche via un des elements du DOM
TZR.Media = {};
TZR.Media.cleanKeyboardNavigation = function(uniqid){
  jQuery("body").off("media-"+uniqid);
};
TZR.Media.initKeyboardNavigation = function(uniqid){
  jQuery("body").on("keydown.media-"+uniqid, {uniqid:uniqid}, function(e){
    if(!document.getElementById("cv8-uniqdiv-"+e.data.uniqid)){
      return;
    }
    if(e.keyCode == 37) { 
      if (jQuery("#"+e.data.uniqid+"prev").length>0){
	jQuery("#"+e.data.uniqid+"prev").trigger('click');
      }
    } else if(e.keyCode == 39) { 
      if (jQuery("#"+e.data.uniqid+"next").length>0){
	jQuery("#"+e.data.uniqid+"next").trigger('click');
      }
    }
  });
};
TZR.Media.loadInfos=function(options, responseText){
   jQuery(responseText).appendTo(jQuery(options.target));
 };
TZR.Media.open=function(url, param){
    var overlay=null;
  if (param){ // prev or next
      overlay="auto";
  }
    
    TZR.Dialog.openURL(url, null, {overlay:overlay, backdrop:true});
};
TZR.Media.infosInit=function(container){
  jQuery('a.mediainfos', container).on('click', function(event, param){
    TZR.Media.open(jQuery(this).data("infosurl"), param);
    return false;
  });
};
TZR.Media.previewInit=function(container){
    jQuery('a.previewmedia', container).on('click', function(event, param){
	if (event.shiftKey) {// sélection de ligne
	    return true;
	}
	TZR.Media.open(jQuery(this).data("previewurl"), param);
	return false;
    });
};
TZR.Media.selectMedia=function(obj){
  li=jQuery(obj).parents('.imagelist-item')[0];
  var cb=jQuery(':checkbox',li)[0];
  this.highlightImage(li, cb.checked);
};
TZR.Media.highlightImage = function(li, selected, tests){
    var jtext = jQuery('div.imagelist-text', li);      
    if (selected)
	jtext.addClass('active');
    else
	jtext.removeClass('active');
    return true;
};
TZR.Media.setListSlide = function(uniqid, oid, target){
   var pli = jQuery("li[data-oid='"+oid+"']");
   if (pli.length == 1){
     var next = pli.next();
     if (next.length == 1){
       jQuery("#"+uniqid+"next").show().off('tzr-nav').on('click.tzr-nav', function(){
	 jQuery(target, next).trigger("click", "next");
       });
     }
     var prev = pli.prev();
     if (prev.length == 1){
       jQuery("#"+uniqid+"prev").show().off('click.tzr-nav').on('click.tzr-nav', function(){
	 jQuery(target, prev).trigger("click", "previous");
       });
     }
   }
};
TZR.Media.selectMediaPage=function(obj){
    var jli=jQuery(obj).parents('li.imagelist-item');
    var cb=jQuery(':checkbox',jli)[0];
    TZR.Media.highlightImage.call(TZR.Media, jli[0], cb.checked);
    jli.siblings().each(function(){
	TZR.Media.highlightImage.call(TZR.Media, this, cb.checked);
	jQuery(':checkbox',this)[0].checked=cb.checked;
    });
};
// Inverse la selection des fiches d'une ligne via un des elements du DOM
// et à partir de la position du bloc
TZR.Media.selectMediaLine=function(obj){
    var jli=jQuery(obj).parents('li.imagelist-item');
    var cb=jQuery(':checkbox',jli)[0];
    TZR.Media.highlightImage.call(TZR.Media, jli[0], cb.checked);
    var y=jli.offset().top;
    jli.prevAll().each(function(){
	var _y=jQuery(this).offset().top;
	if(y!=_y) return false;
	TZR.Media.highlightImage.call(TZR.Media, this, cb.checked);
	jQuery(':checkbox',this)[0].checked=cb.checked;
    });
    jli.nextAll().each(function(){
	var _y=jQuery(this).offset().top;
	if(y!=_y) return false;
	TZR.Media.highlightImage.call(TZR.Media, this, cb.checked);
	jQuery(':checkbox',this)[0].checked=cb.checked;
    });
};

TZR.SELECTION.printsheetselected=function(moid){
   var form=document.forms["selectionform"+moid];
   var sel=TZR.checkBoxesIsChecked(form);
   if(!sel){ 
     TZR.SELECTION.alert(TZR._noobjectselected);
     return false;
   };
   form._function.value='prePrintContactSheet';
   form.template.value='Module/Media.prePrintContactSheet-modal.html';
   form.method='post';
   TZR.Dialog.openFromForm(form);
   TZR.SELECTION.ModalClose.call(TZR.SELECTION);
 }
TZR.Media.downloadMedia=function(moid,oid){
  jQuery.ajax({dataType:"json",url:TZR._self+"&moid="+moid+"&function=chooseDownloadFormat&skip=1",data:{'oid[]':oid}}).success(function(data){
    if(data.url){
      location.href=data.url;
    }else{
      var popup=jQuery(data.content);
      popup.find("#closeselectformatdialog").on("click", function(){
	popup.dialog('destroy').remove();
      });
      popup.find('#downselectedbutton').on("click", function(){
	if (jQuery(this).parents('form:first').find('input[name=dlfmt]:checked').val() === 'custom'){
	  var width = parseInt(jQuery(this).parents('form:first').find('input[name=width]').val());
	  var heigth = parseInt(jQuery(this).parents('form:first').find('input[name=height]').val());
	  
	  if (width <= 0 || heigth <= 0 || isNaN(width) ||isNaN(heigth)) {
	    alert('Custom size error !');
	    return false;
	  }
	}
        jQuery(this).parents('form:first').submit();
        popup.dialog('close');
      });
      popup.dialog({__title:"", 
		    dialogClass:"mediaSelectFormat",
		    width:350,
		    modal:true,
		    resizable:false,
		    close:function(e,ui){popup.dialog('destroy').remove();},
		    buttons:[]
      });
    }
  });
  return false;
}
