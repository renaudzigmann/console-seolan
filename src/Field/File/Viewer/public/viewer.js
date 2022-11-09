
TZR.Viewer = {
    url:null,
    viewerWin:null,
    uniqid:null,
    page:null,
    load:function(options, e){
	this.uniqid = options.uniqid,
	this.url=options.url;
	this.page=1;
	this.pages=options.pages;
	TZR.Dialog.openURL(options.url, {}, {});
        jQuery("body").unbind('keydown.tzrfileviewer');
	jQuery("body").on('keydown.tzrfileviewer', function(e) {
	    if(e.keyCode == 37) { // left
		TZR.Viewer.previous();
	    }
	    else if(e.keyCode == 39) { // right
		TZR.Viewer.next();
	    }
	});

	jQuery('.close', TZR.Dialog.modalId).on('click', function(){ jQuery("body").unbind("keydown"); });
    },
    open: function(page) {
	if (page == undefined)
	    page = this.page;
	page = Math.max(page, 1);
	if (this.pages){
	    page = Math.min(page, this.pages);
	}
	TZR.jQueryAjax({url:this.url+'&page='+page+(!this.pages?'&getPages=true':''),
			mode:'post',
			dataType:'json',
			overlay:jQuery('.modal-body', TZR.Dialog.modalId),
			cb:function(data, status, xrequest) {
			  jQuery('.viewerNext,.viewerPrevious,.viewerHelp', TZR.Dialog.modalId).hide();
			  if (xrequest.status == 200) {
			    if (data.pages)
			      this.pages = data.pages;
			    jQuery(TZR.Dialog.modalId).find('.modal-title').first().text(data.title+' - '+page+(this.pages?'/'+this.pages:''));
			      if ( (data.image || data.srcdoc) && data.error === null) {
				jQuery('.viewerError', TZR.Dialog.modalId).hide();
				if (data.type == 'html'){
				  var view = jQuery('#viewerHtml');
				  view.parent().removeClass('viewer');
				  jQuery('#viewerImage', TZR.Dialog.modalId).hide();
				  var iframe = jQuery('iframe', view);
				  iframe.attr('srcdoc', data.srcdoc);
				  view.show();
				} else {
				  jQuery('#viewerHtml').hide();
				  var view = jQuery('#viewerImg');
				  view.attr('src',"data:image/png;base64," + data.image);
				  view.show();					
				}
				jQuery('#viewerImg', TZR.Dialog.modalId).attr('alt', data.title);
				if (this.pages){
				  jQuery('.viewerHelp', TZR.Dialog.modalId).show();
				  jQuery('.viewerNext,.viewerPrevious', TZR.Dialog.modalId).show();
				  if (page == 1) {
				    jQuery('.viewerPrevious', TZR.Dialog.modalId).hide();
				  }
				  if (page == this.pages) {
				    jQuery('.viewerNext', TZR.Dialog.modalId).hide();
				  }
				  TZR.Viewer.page = page;
				} else {
				  jQuery('.viewerNext,.viewerPrevious', TZR.Dialog.modalId).hide();
				}
			      } else if(data.error !== null){
				jQuery('.viewerError', TZR.Dialog.modalId).html(data.error+'.');
				jQuery('.viewerError', TZR.Dialog.modalId).show();
			      }
			  }
			},
			cb_context:this
		       });
    },
    previous: function() {
	this.open(this.page-1);
    },
    next: function() {
	this.open(this.page+1);
    }
};
