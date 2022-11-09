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
  }
};
</script>