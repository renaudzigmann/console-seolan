<%*
   js commun au gabarits de la base doc
   voir Module/Table/public/table.js
   work in progress
*%>

<script type="text/javascript">

if (typeof(TZR.DocMngt) == "undefined")
  TZR.DocMngt = {browse:{}};
/*
<%* 
collecte infos et confirmation avant suppression 
~= preDel
*%>
*/
TZR.DocMngt.preFullDelete=function(uniqid, oid, options){
  var formnames = {
    index:'browse',
    index2:'browse',
    display:'editform', 
    edit:'editform'
  };
  var formname = `${formnames[options.fromFunction]}${uniqid}`;
  var form = document.forms[formname];
  if (!form)
    return;

  // suppression des documents sélectionnnés
  if (options.onlySelected){
    if(options.onlySelected && !TZR.checkBoxesIsChecked(form))
      return;
  } else {
  }
  // suppression d'un répertoire 
  TZR.Dialog.openFromForm(form,{_function:'preFullDelete',
				_next:'',
				template:'Module/DocumentManagement.preDel.html'}
  );
  TZR.Dialog.setData({form:form,
		      options:options,
		      done:function(data){
			TZR.DocMngt.fullDeleteWithForm.call(TZR.DocMngt, data);
		      }
  });
  
}
/*
<%* appel de la fonction de suppression complete *%>
*/
TZR.DocMngt.fullDeleteWithForm = function(data){
  data.form.elements['_function'].value = 'fullDelete';
  TZR.ajaxSubmitForm(data.form);
  return false;
}
/*
   <%* recherche des infos de suppression *%>
*/
TZR.DocMngt.preDel = function(form, options){
  options = jQuery.extend({}, {onlyselected:false,next:null},options);
  if(options.onlyselected && !TZR.checkBoxesIsChecked(form))
    return;
  TZR.Dialog.openFromForm(form,{_function:'preDel',
				_next:'',
				template:'Module/DocumentManagement.preDel.html'}
  );
  TZR.Dialog.setData({form:form,
		      options:options,
		      done:function(data, physical){
			TZR.DocMngt.deleteWithForm.call(TZR.DocMngt, data, physical);
		      }
  });
};

TZR.DocMngt.preDelFiche = function(options){
  TZR.Dialog.openURL(TZR._self,{_function:'preDel',
				_next:'',
				tplentry:'br',
				oid:options.oid,
				_parentoid:options.parentoid,
				moid:options.moid,
				template:'Module/DocumentManagement.preDel.html'}
  );
  TZR.Dialog.setData({options:options,
		      done:function(data, physical){
			TZR.DocMngt.deleteItem.call(TZR.DocMngt, data, physical);
		      }});
};

TZR.DocMngt.confirmDelete = function(physical){
  var data = TZR.Dialog.getData();
  TZR.Dialog.closeDialog();
  data.done(data, physical);
};
TZR.DocMngt.deleteItem = function(data, physical){
  var next = null;
  var phyval = 0;
  if (physical.length == 1 ){
    if (physical.prop('checked'))
      phyval = 1;
    else if(physical.attr('type') == 'hidden')
      phyval = physical.val();
  }
  if (data.options.next != null)
    next = data.options.next;

  TZR.jQueryLoad({
    url:TZR._self,
    dataType:'html',
    data:{
      function:'del',
      _skip:1,
      moid:data.options.moid,
      physical:phyval,
      _next:next,
      oid:data.options.oid,
      _parentoid:data.options.parentoid
    }

  });

};
TZR.DocMngt.deleteWithForm = function(data, physical){
  if (physical.length == 1 ){
      if(physical.attr('type') == 'hidden')
	     data.form.elements['physical'].value = physical.val();
      else if (physical.prop('checked'))
	     data.form.elements['physical'].value = 1;
      else
	     data.form.elements['physical'].value = 0;
  }
  if (data.options.next != null)
    data.form.elements['_next'].value = data.options.next;

  data.form.elements['_function'].value = 'del';
  TZR.ajaxSubmitForm(data.form);
};
  if (typeof TZR.DocMngt == "undefined"){
    TZR.DocMngt = {browse:{}};
  }
  TZR.DocMngt.addToSelection=function(moid, id) {
    var form=document.forms['browse'+id];
    if (typeof form.elements['_next'] != 'undefined') form.elements['_next'].value='';

    form.elements['_function'].value='addToUserSelection';
    form.elements['template'].value='Core.layout/top/cart.html';
    form.elements['fromfunction'].value = TZR.DocMngt.browse[id].fromfunction;
    TZR.ajaxSubmitForm(form,'#cvx-panier');
  };
/*<%* affichage des fichiers multivalués *%>*/
TZR.DocMngt.Docs = {
  toggleLimit:5,
  /*<%* on la liste originale est masquée, on duplique les 5 premiers visibles et les
  suivant masqués, on ajoute le lien ouvrir/ferme *%>*/
  init:function(formname, oid, nbfiles, label){
    var containers = jQuery(TZR.sprintf("div[data-oid='%s']", oid),
			    jQuery(TZR.sprintf("form[name='%s']", formname)));
    var files = jQuery("div", containers);
    var listcontainer = jQuery("<div class=\"fileslistviewer\"></div>");
    listcontainer.insertAfter(containers.last());
    var that = this;
    files.each(function(i, o){
      if (i < that.toggleLimit){
	jQuery(o).detach().appendTo(listcontainer).addClass('firstsFiles');
      } else {
	jQuery(o).detach().appendTo(listcontainer).addClass('more').hide();
      }
    });
    if (nbfiles > this.toggleLimit){
      var anchor = jQuery(TZR.sprintf(" <a class='toggle' href='#'><span class='glyphicon csico-ellipsis-h'></span> %s %s</a>", nbfiles, label));
      anchor.appendTo(listcontainer);
      anchor.on('click', function(evt){
	jQuery('.more', listcontainer).toggle();
	return false;
      });
      containers.first().parents('.tzr-docmgt-doc1').find('span[data-toggle]').on('click', function(evt){
	jQuery('.more', listcontainer).toggle();
	return false;
      });
    }
  },
  asyncInit:function(oid, nbfiles, formname, label, async){
    if (async){
      setTimeout(function(){
	TZR.DocMngt.Docs.init(formname, oid, nbfiles, label);
      }, 1000);
    } else {
      TZR.DocMngt.Docs.init(formname, oid, nbfiles, label);
    }
  }
};
</script>
