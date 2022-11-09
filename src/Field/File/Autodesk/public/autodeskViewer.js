
TZR.AutodeskViewer = {
  url:null,
  viewerWin:null,
  uniqid:null,
  load:function(options){
    // vérifier le statut du fichier
    this.uniqid = options.uniqid,
    this.url=options.url;
    TZR.jQueryAjax({url:options.url,
		    mode:'get',
		    dataType:'json',
		    cb:this.view,
		    cb_context:this
    });
  },
  // afficher le fichier (en popup)
  view:function(data, status, xrequest){
    if (data.status == 'running'){
      alert("Error, document view not available try later.");
      try{
	this.viewerWin.close();
      }catch(e){}
      this.viewerWin = null;
      return;
    }
    try{
	  this.viewerWin.focus();
	this.setDocument(data, true);
    }catch(e){
      this.viewerWin = window.open('', "viewer", data.win.size);
      if (this.viewerWin == null){
	alert('You may accept popup from this location and retry.');
      } else {
	this.setDocument(data, true);
      }
    }
  },
  // mise à jour du document de la popup
  setDocument:function(data, set){
    if (data.status == "success"){
	this.data = data;
	this.viewerWin.document.location  = '/csx/src/Field/File/Autodesk/public/autodeskViewer.html?_'+data.title+''+escape(new Date());
    } else if (data.status == "inprogress"){
      if (set){
	this.viewerWin.document.location  = '/csx/src/Field/File/Autodesk/public/autodeskViewerPreparing.html';
      }
      // on relance si on en encore sur la page initiale
      if (this.viewerWin != null){
	if (document.getElementById("cv8-uniqdiv-"+this.uniqid) != null){ 
	  setTimeout(function(){TZR.AutodeskViewer.checkProgress();}, 4000);
	} else {
	  this.viewerWin.close();
	}
      }
    }
  },
  checkProgress:function(){
    TZR.jQueryAjax({url:this.url,
		    mode:'get',
		    dataType:'json',
		    nowaitcursor:true,
		    overlay:'none',
		    cb:TZR.AutodeskViewer.refreshUI,
		    cb_context:TZR.AutodeskViewer
    });
  },
  refreshUI:function(data, status, xrequest){
    this.setDocument(data,false);
  },
  stopPrepare:function(){
    this.viewerWin = null;
  },
  getViewerParameters:function(){
    return this.data;
  }

};

