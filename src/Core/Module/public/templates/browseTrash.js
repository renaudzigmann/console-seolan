<script type="text/javascript">
TZR.Archives = {
  labels:{trash:null,
	  delfromtrash:null,
	  delall:null,
  },
  delArchiveConfirm:function(url){
    var options = {
      done:function(options){
	TZR.Archives.delArchive.call(TZR.Archives, options);
      },
      url:url
    };
    new TZR.Modal.Confirm(this.labels.trash,this.labels.delfromtrash, options).show();
  },
  delArchive:function(params){
    TZR.jQueryPost({target:null, // target par default
		    url:params.url,
		    cb:TZR.updateModuleContainer,
		    cb_args:[TZR._refresh,null]
    });
  },
  delAllConfirm:function(url){
    var options = {
      done:function(options){
	TZR.Archives.delAll.call(TZR.Archives, options);
      },
      url:url
    };
    new TZR.Modal.Confirm(this.labels.trash,this.labels.delAll, options).show();
  },
  delAll:function(params){
    TZR.jQueryLoad({target:null, // target par default
		    url:params.url
    });
  },
  // base doc et doc set
  restoreArchiveTarget:function(url, omoid){
    if (omoid)
      moid=omoid;
    
    var dialogurl = "<%$self%>&skip=1&function=index2Light&template=Module/DocumentManagement.modaltree.html&_raw=1&moid="+moid+"&tplentry=br&title="+escape(TZR.Archives.labels.restore);
    TZR.Dialog.openURL(dialogurl, 
		       null, 
		       {sizeClass:'modal-md',
			initCallback:{_function:"TZR.DocumentManagement.ModalTree.init",
				      _param:{
					selectedcb:function(folderoid, title){
					  TZR.Archives.restoreArchive.call(TZR.Archives, 
									   folderoid,
									   title, 
									   url);
					}
				      }
    }});
  },
  restoreArchive:function(folderoid, title, url){
    
    TZR.Dialog.closeDialog();
    
    
    TZR.jQueryPost({target:null, // target par default
		    url:url+"&_parentoid="+escape(folderoid),
		    cb:TZR.updateModuleContainer,
		    cb_args:[TZR._refresh,null]
    });
    
  }
  
};
</script>
