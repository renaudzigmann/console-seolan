jQuery(document).ready(function() {						
    jQuery(window).scroll(function() {
	if(jQuery(window).scrollTop() == 0){
	    jQuery('#scrollToTop').fadeOut("fast");
	} else {
	    if(jQuery('#scrollToTop').length == 0){
		jQuery('body').append('<div id="scrollToTop">'+
				      '<a href="#"></a>'+
				      '</div>');
	    }
	    jQuery('#scrollToTop').fadeIn("slow");
	}
    });
    jQuery('#scrollToTop a').on('click', function(event){
	event.preventDefault();
	jQuery('html,body').animate({scrollTop: 0}, 'slow');
    });
    //replace icon by fontawesome
    jQuery('.tzr-file[href*="application/pdf"] img').replaceWith('<i class="fa fa-file-pdf-o"></i> ');
    jQuery('.tzr-file[href*="application/vnd.oasis.opendocument.text"] img').replaceWith('<i class="fa fa-file-text-o"></i> ');
    jQuery('.tzr-file[href*="application/vnd.oasis.opendocument.spreadsheet"] img').replaceWith('<i class="fa fa-file-excel-o"></i> ');
    jQuery('.tzr-file[href*="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"] img, .tzr-file[href*="application/vnd.ms-excel"] img').replaceWith('<i class="fa fa-file-excel-o"></i> ');
    jQuery('.tzr-file[href*="application/msword"] img').replaceWith('<i class="fa fa-file-word-o"></i> ');

    jQuery('.nyroModal,.nyro').nm(
	{closeButton:'<a href="#" class="nyroModalClose nyroModalCloseButton nmReposition" title="close"><i class="fa fa-times-circle fa-2x"></i></a>',
	 callbacks:{
	     afterShowCont:function(){
		 jQuery('a.nyroModalPrev').show().html('<i class="fa fa-backward fa-2x"></i>');
		 jQuery('a.nyroModalNext').show().html('<i class="fa fa-forward fa-2x"></i>');
	     }
	 }
	}
    );
});
function bookmark(title, url) {
    if(document.all) { // ie
        window.external.AddFavorite(url, title);
    }
    else if(window.sidebar) { // firefox
        window.sidebar.addPanel(title, url, "");
    }
    else if(window.opera && window.print) { // opera
        var elem = document.createElement('a');
        elem.setAttribute('href',url);
        elem.setAttribute('title',title);
        elem.setAttribute('rel','sidebar');
        elem.click(); // this.title=document.title;
    }
}
