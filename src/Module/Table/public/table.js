/* Gestion selection */
// Met à jour la selection
TZR.SELECTION=new Object();
TZR.SELECTION.topcart = 'Core.layout/top/cart.html';
TZR.SELECTION.Modal = {};
TZR.SELECTION.Modal.elem = '#cvx-selection';
TZR.SELECTION.Modal.alertContainer = '#cvx-selection-alert';
TZR.SELECTION.Modal.confirmContainer = '#cvx-selection-confirm';
TZR.SELECTION.init = function(){
  jQuery("#cvx-panier>a").off(TZR.click_event+".refreshSelection");
  var that = this;
  jQuery("#cvx-panier>a").on(TZR.click_event+".refreshSelection", function(){
    if (!jQuery(this).parent().hasClass('open')){
      that.refresh.call(TZR.SELECTION);
    }
  });
};
TZR.SELECTION.refresh = function(){
  TZR.jQueryLoad({url:TZR.sprintf("%s&moid=%s&function=refreshSelection&_skip=1&template=Core.layout/top/cart.html",
				  TZR._self,TZR._sysmods_xmoduser2),
		  skip:1,
		  target:'#cvx-panier',
		  overlay:"#cvx-panier>ul"});
};
TZR.SELECTION.Modal.show = function(moid) {
  var url=TZR._self+'&moid='+TZR._sysmods_xmoduser2+'&function=browseSelection&template=Core.cart.html&tplentry=br&_moid='+moid;
  TZR.jQueryLoad({url:url,skip:1,target:'#cvx-selection', cb:function() {
    if (jQuery("#cvx-selection span.emptyselection").length != 0){
      jQuery(TZR.SELECTION.Modal.elem).modal('hide');
    } else {
      jQuery(TZR.SELECTION.Modal.elem).modal('show');
    }
  }});
}

// Met à jour le nombre de fiches cochées pour un module
TZR.SELECTION.updateChecked=function(moid){
  jQuery('#selection-nbchecked'+moid).html(jQuery('#selection-sc'+moid+' :checkbox:checked[name^="_selected"]').length);
}
TZR.SELECTION.alert=function(message){
    if (message == undefined){
	message = '';
    }
    var jelemAlert = jQuery(TZR.SELECTION.Modal.alertContainer);
    jelemAlert.hide();
    jQuery('.cvx-alert-body', jelemAlert).html(message);
    jelemAlert.show(100);
}
// Supprime les fiches selectionnées de la selection
TZR.SELECTION.delTo=function(moid,all){
  var f=document.forms['selectionform'+moid];
  if(all) {
    //Cache Modale, Mise à jour Session, selection dropdown + checkboxes du browse si affiché sous modale
    jQuery(TZR.SELECTION.Modal.elem).modal('hide');
    TZR.Table.emptySelection(moid);
    return;
  }
  var jelemAlert = jQuery(TZR.SELECTION.Modal.alertContainer);
  jelemAlert.hide();
  var f=document.forms['selectionform'+moid];
  if(jQuery(':checkbox:checked',f).length==0){
    jQuery('.cvx-alert-body', jelemAlert).html(TZR._noobjectselected);
    jelemAlert.show(100);
    return false;
  }
  //mise à jour de la session et de la dropdown
  f._function.value="delToUserSelection";
  f.template.value=TZR.SELECTION.topcart;
  TZR.ajaxSubmitForm(f,'#cvx-panier');
  //mise à jour de la modale
  TZR.SELECTION.Modal.show(moid);
  return false;
}
TZR.SELECTION.alertOk = function(){
  var jelemAlert = jQuery(TZR.SELECTION.Modal.alertContainer).hide(100);
}
// Applique une fonction sur les elements de la selection
// use param.applyToAll=1 pour appliquer à tous les elements
// si le gabarit et refresh ne sont pas spécifiés implicitement,
// on force le refresh du contenu et de la selection
TZR.SELECTION.applyTo=function(moid,func,next, param,confirm,ftarget,refresh){
  if (TZR.SELECTION.applyToCheck(moid,func,next, param,confirm,ftarget,refresh)){
    return TZR.SELECTION.applyToGo(moid,func,next, param,ftarget,refresh);
  }
  return false;
}
TZR.SELECTION.confirmAction = function(go){
  if (!go){
    jQuery(TZR.SELECTION.Modal.confirmContainer).hide(100);
    return;
  }
  var elem = jQuery(TZR.SELECTION.Modal.elem);
  var go = elem.data('go');
  return TZR.SELECTION.applyToGo(go.moid,go.func,go.next,go.param,go.ftarget,go.refresh);
}
// controle si on peut appliquer la fonction
TZR.SELECTION.applyToCheck = function(moid,func,next, param,confirm,ftarget,refresh){
  var jelemAlert = jQuery(TZR.SELECTION.Modal.alertContainer);
  var jelemConfirm = jQuery(TZR.SELECTION.Modal.confirmContainer);
  jelemAlert.hide();
  jelemConfirm.hide();
  var f=document.forms['selectionform'+moid];
  if(jQuery(':checkbox:checked',f).length==0){
    if(!param || param.applyToAll!=1){
      jQuery('.cvx-alert-body', jelemAlert).html(TZR._noobjectselected);
      jelemAlert.show(100);
      return false;
    }else TZR.checkBoxes(f,true);
  }
  if(confirm){
    if (typeof(confirm)=='function' && !confirm.call()) return false;
    if (typeof(confirm)=='boolean'){
      jQuery(TZR.SELECTION.Modal.elem).data('go', {moid:moid, func:func, next:next, param:param, ftarget:ftarget, refresh:refresh});
      jQuery('.cvx-alert-body', jelemConfirm).html(TZR._confirm_delete_mess);
      jelemConfirm.show(100);
      return false;
    }
  }
  return true;
}
// applique la fonction directement au formulaire de la sélection
TZR.SELECTION.submitTo = function(moid,func){
  var f=document.forms['selectionform'+moid];
  f.elements['_function'].value=func;
  jQuery(TZR.SELECTION.Modal.elem).modal('hide');
  f.submit();
}
// applique la fonction
TZR.SELECTION.applyToGo = function(moid,func,next,param,ftarget,refresh){
  var f=document.forms['selectionform'+moid];
  if(ftarget===undefined || ftarget===null){
    ftarget='#cvx-panier';
  }
  var jf=jQuery(f);
  jf.find('input.applyToInput').remove();
  f._function.value=func;
  param = jQuery.extend({}, {template:TZR.SELECTION.topcart}, param);
  for(var i in param){
    if (jQuery('input[name="'+i+'"]', jf).length){
      jQuery('input[name="'+i+'"]', jf).val(param[i]);
    } else {
      jf.append('<input class="applyToInput" type="hidden" name="'+i+'" value="'+param[i]+'">');
    }
  }
  jQuery(TZR.SELECTION.Modal.elem).modal('hide');
  if(!ftarget){
    f.submit();
  } else {
    if (refresh){
      cplt = {cb:function(){
	jQuery('#cv8-content div.cv8-module-container').each(function(){
          if(jQuery(this).data('tzrobj')) {
            jQuery(this).data('tzrobj').refresh();
          }
	});
      }};
      refresh = false;
    } else {
      cplt = null;
    }
    TZR.ajaxSubmitForm(f,ftarget, true, cplt);
  }
  if(refresh || refresh === undefined){
    jQuery('#cv8-content div.cv8-module-container').each(function(){
      if(jQuery(this).data('tzrobj')) {
        jQuery(this).data('tzrobj').refresh();
      }
    });
  }
  return false;
}
TZR.SELECTION.ModalClose=function(){
  jQuery(TZR.SELECTION.Modal.elem).modal('hide');
}
TZR.SELECTION.applyToInContentDiv=function(moid,func,next,param,confirm){
  param = jQuery.extend({}, param);
  param._bdxnewstack=1;
  return TZR.SELECTION.applyTo(moid,func,next,param,confirm,'#cv8-content', false);
}
TZR.SELECTION.viewselected=function(moid, browseFunction, template){
  if (template == undefined){
    template =  'Module/Table.browse.html';
  }
  if (browseFunction == undefined){
    browseFunction =  'procQuery';
  }
  var i=jQuery('#selection-sc'+moid).find('input[name^="_selected"]');
  i.each(function(){this.name=this.name.replace('_selected','oids');});
  TZR.SELECTION.applyToInContentDiv(moid,browseFunction,null,{clearrequest:1,template:template,tplentry:'br',_bdxnewstack:1},false);
  i.each(function(){this.name=this.name.replace('oids','_selected');});
}
TZR.SELECTION.exportSelection=function(moid) {
  var form=document.forms['selectionform'+moid];
  var sel=TZR.checkBoxesIsChecked(form);
  if(!sel){
    TZR.SELECTION.alert(TZR._noobjectselected);
    return false;
  };
  form['elements']['_next'].value='';
  form['elements']['_function'].value='preExportBrowse';
  form['elements']['template'].value='Module/Table.preexportbrowse.html';
  form['elements']['tplentry'].value='br';
  if (typeof(form['elements']['selectedfields']) != "undefined"){
    form['elements']['selectedfields'].value='';
  }
  form.method='post';
  TZR.Dialog.openFromForm(form, null, {sizeClass:"modal-export"});

  TZR.SELECTION.ModalClose.call(TZR.SELECTION);

 }

TZR.Table = new Object();
TZR.Table.browse = {};

TZR.Table.addToSelection=function(moid,id) {
  var form=document.forms['browse'+id];
  if (typeof form.elements['_next'] != 'undefined')
    form.elements['_next'].value='';
  form.elements['_function'].value='addToUserSelection';
  form.elements['template'].value='Core.layout/top/cart.html';
  form.elements['fromfunction'].value = TZR.Table.browse[id].fromfunction;
  jQuery("#cvx-panier").show();
    TZR.ajaxSubmitForm(form,'#cvx-panier',null,{_overlay:"none", noautoscroll:true, overlayOpts:{oclass:'overlay top light'}});
}
 // vidrer completement la selection (ou un module)
 TZR.Table.emptySelection=function(moids) {
   if (moids == ''){
     var selmoid = '';
   } else {
     amoids = moids.split(';');
     var selmoid = '&selmoid[]='+amoids.join('&selmoid[]=');
   }
   var url=TZR._self + '&moid='+TZR._sysmods_xmoduser2+'&function=emptySelection&template=Core.layout/top/cart.html'+selmoid;
   TZR.jQueryLoad({url:url,skip:1,target:'#cvx-panier',overlay:"none"});
 }

 TZR.Table.getConfirmDeleteMessage = function(id){
    var br = TZR.Table.browse[id];
    if (typeof(br.deleteConfirmMessage) != "undefined"){
	return br.deleteConfirmMessage;
    }
    return null;
 }
 // Suppression "physique" des éléments sélectionnés
TZR.Table.deleteSelectedComplete=function(id, title){
  if(!TZR.checkBoxesIsChecked(document.forms['browse'+id], false, false, /_selected\[/)){
    TZR.alert(TZR._noobjectselected, title);
    return false;
  }
  var title = TZR.Table.browse[id].confirmFullDeleteTitle;
  var message = TZR.Table.browse[id].confirmFullDeleteMessage;
  title = title||"Suppression complète";
  message = message||"Suppression des éléments sélectionnés et de toutes archives et fichiers associés)";
  TZR.Modal.confirm_delete.config(function(){
    TZR.Table.applyfunction.call(TZR.Table, id, 'fullDelete', false, false, false, false);
  },null, title, message);
  TZR.Modal.confirm_delete.show();
}
 // Supression des fiches selectionnées (from ToolBar)
 TZR.Table.deleteselected=function(id,fct) {
   if(!TZR.checkBoxesIsChecked(document.forms['browse'+id], false, false, /_selected\[/)){
     TZR.alert(TZR._noobjectselected, '');
     return false;
   }
   var args = id+","+fct+",,,true";
   TZR.Modal.confirm_delete.config('TZR.Table.applyfunction',args, null, TZR.Table.getConfirmDeleteMessage(id));
   TZR.Modal.confirm_delete.show();
 }
// Suprresion des fiches issues de la recherche en cours (from Menu Edit/DellAll)
 TZR.Table.deletequeried=function(id,fct) {
   var args = id+","+fct+",,,false";
   var action = "TZR.Table.applyfunction";
   TZR.Modal.confirm_delete.config(action,args,null,TZR.Table.getConfirmDeleteMessage(id));
   TZR.Modal.confirm_delete.show();
 }
 //from Browse item action
 TZR.Table.deleteItem=function(moid,url,a) {
   var parent = jQuery('[id^="sc-'+moid+'"]');
   if (parent == undefined || parent.length == 0) {
     //Browse Module
     var divId = $(a).closest('.cv8-module-container').find('[id^="cv8-uniqdiv-"]');
     var id = divId.attr("id").replace("cv8-uniqdiv-","");
     TZR.jQueryPost({target:TZR.getModuleContainer(id),url:url,cb:TZR.updateModuleContainer,cb_args:[TZR._refresh,null]});
   } else {
     var parentId = parent.attr("id");
     TZR.jQueryPost({target:"#"+parentId,url:url, cb:function(){
	 jQuery('#'+parentId).empty();
	 jQuery('#'+parentId.replace('sc-','li-')+'>a').trigger(TZR.click_event);
     }});
   }
 }

 TZR.Table.go_procQuery=function(id,form,fct){
   if(fct) form._function.value=fct;
   TZR.ajaxSubmitForm(form);
 }
// pour gérer les passages d'arguments par une chaine toto,titi,false,2 qui transmet au final uniquement des string
TZR.Table.parseBool=function(value){
return !(!value || value == "0" || value == "false");
}
 // Applique une fonction au formulaire
 TZR.Table.genericApplyFunction=function(prefix, id, f, conf, data, selectonly, nonext, popup) {
   var form=document.forms[prefix+id];
   if(!data) data={};
   data._function=f;
   // utilise les fonctions 'here', 'back', etc de Core/Shell de la pile d'historique
   if(!this.parseBool(nonext))
     data._next=TZR._refresh; // ex TZR._uri qui est pas toujours juste
   else
     data._next='';
   //cast du selectonly compte tenu du passage des args en texte
   TZR.applyFunction(form,data,this.parseBool(selectonly),conf,popup);
 }
TZR.Table.applyfunction=function(id,f,conf,data,selectonly,nonext,popup) {
  var form=document.forms['browse'+id];
  if(!data) data={};
  // transformation du data string en tableau/objet avant appel apply function
  // voir TZR.Record.delete par exemple
  if (typeof(data) == 'string') {
    try{
      data = JSON.parse(data);
    } catch(e) {}
  }
  var next = TZR._refresh; // ex TZR._uri qui est pas toujours juste
  if (data.hasOwnProperty('ssmoid_delete') && data.ssmoid_delete) {
    form=document.forms['editssmodform'+id+'-'+data.ssmoid_delete];
    next +="&_tabs="+data.current_tabs;
    delete data.current_tabs;
    delete data.ssmoid_delete;
  } else if (data.hasOwnProperty('ssmoid_editselection') && data.ssmoid_editselection) {
    form=document.forms['editssmodform'+id+'-'+data.ssmoid_editselection];
    delete data.ssmoid_editselection;
  }
  data._function=f;
  // utilise les fonctions 'here', 'back', etc de Core/Shell de la pile d'historique
  if(!this.parseBool(nonext))
    data._next=next;
  else
     data._next='';

  //cast du selectonly compte tenu du passage des args en texte
  TZR.applyFunction(form,data,this.parseBool(selectonly),conf,popup);
}
 TZR.Table.printselected=function(id) {
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   var fct =  br.fromfunction;
     TZR.applyFunction(document.forms['browse'+id],{_next:'',_function:'prePrintBrowse',fromfunction:fct,template:'Module/Table.preprintbrowse.html',selectedfields:''},false,false,{dialog:true, options:{id:id, allowMove:false,backdrop:'static'}});
 }
TZR.Table.exportselected=function(id,options) {
  var procid = null;
  if (options && typeof(options.storedprocedureid) != "undefined"){
    procid = options.storedprocedureid;
  }
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   var fct =  br.fromfunction;
    TZR.applyFunction(document.forms['browse'+id],{_next:'',_function:'preExportBrowse',fromfunction:fct,template:'Module/Table.preexportbrowse.html',selectedfields:'',storedprocedureid:procid},false,false,{dialog:true, options:{id:id, sizeClass:'modal-export', allowMove:false,backdrop:'static', closeCallback:{_function:'TZR.Table.Downloader.downloadEnd', _param:"downloader"+id}}});
 };
TZR.Table.filledreporting=function(id,options) {
  var procid = null;
  if (options && typeof(options.storedprocedureid) != "undefined"){
    procid = options.storedprocedureid;
  }
  var br = TZR.Table.browse[id];
  if (br === undefined) return;
  var fct =  br.fromfunction;
  TZR.applyFunction(document.forms['browse'+id],{_next:'',_function:'preFilledReporting',fromfunction:fct,template:'Module/Table.prefilledreporting.html',selectedfields:'',storedprocedureid:procid},false,false,{dialog:true, options:{id:id, sizeClass:'modal-export', allowMove:false, backdrop:'static'}});
};
 TZR.Table.sendSelected=function(id,moid){
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   var fct =  br.fromfunction;
   var form=document.forms['browse'+id];
   if(TZR.checkBoxesIsChecked(form)){
     TZR.applyFunction(document.forms['browse'+id],{_next:'',_function:'genSend',fromfunction:fct,template:'Module/MailingList.xmodmaillist.html',selectedfields:''});
   }else{
     TZR.jQueryLoad({target:this.modulecontainer,url:TZR._self+"moid="+moid+"&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br"});
   }
 };
 TZR.Table.sendSelectedSMS=function(id,moid){
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   var fct =  br.fromfunction;
   var form=document.forms['browse'+id];
   if(TZR.checkBoxesIsChecked(form)){
     TZR.applyFunction(document.forms['browse'+id],{_next:'',_function:'genSend',fromfunction:fct,template:'Module/MailingList.sendsms.html',selectedfields:'',sms:'1'});
   }else{
     TZR.jQueryLoad({target:this.modulecontainer,url:TZR._self+"moid="+moid+"&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br&sms=1"});
   }
 }
// abandonner/fin d'export - ? canceler l'action ?
// a faire mieux ...
// downloader
 /********/
 TZR.Table.field_in_list=function(id,f){
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   for(i=0;i<br.nb_selectedfields;i++)
     if(br.selectedfields[i]==f) return true;
   return false;
 };
 TZR.Table.add_field=function(id,f) {
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   if(TZR.Table.field_in_list(id,f)) {
     found=false;
     i=0;
     while(!found && (i<br.nb_selectedfields)) {
       if(br.selectedfields[i]==f) found=true;
       else i++;
     }
     i++;
     while(i<br.nb_selectedfields) {
       br.selectedfields[i-1]=br.selectedfields[i];
       i++;
     }
     br.nb_selectedfields--;
   } else {
     br.selectedfields[br.nb_selectedfields]=f;
     br.nb_selectedfields++;
   }
 };
 TZR.Table.go_browse=function(id,command, pagesizediff, urlonly) {
   if (!pagesizediff)
     pagesizediff = 0;
   var br = TZR.Table.browse[id];
   if (br === undefined) return;
   var pagesize = br.g_pagesize;
   var first = br.first;
   var last = br.last;
   var order = br.order;
   var f = br.f;
   var url = br.url;
   if (urlonly == undefined)
     urlonly=false;
   var editfield='';
   if(command=='start') {
     first='0';
     if(typeof(pagesizediff)=='number') pagesize=pagesize+pagesizediff;
     else pagesize=eval(pagesize+pagesizediff);
     pagesize=parseInt(pagesize);
   } else if(command=='clear') {
     f='browse';
   } else if(command=='urlonly') {
     urlonly=true;
   } else if(command=='end') {
     first=br.firstlastpage;
     pagesize=pagesize+pagesizediff;
   } else if(command=='prev') {
     first=br.firstprev;
     pagesize=pagesize+pagesizediff;
   } else if(command=='next') {
     first=br.firstnext;
     pagesize=pagesize+pagesizediff;
   } else if(command=='seek') {
     first=pagesizediff;
   } else if(command=='edit') {
     editfield=pagesizediff;
   } else if(command=='order') {
     first= 0;
     order=pagesizediff;
   }
   if(pagesize<=0) pagesize=0;
   url=url+'&function='+f;
   url=url+'&first='+first;
   if(command!='clear') {
     url=url+'&last='+last;
   }
   if (order != null)
     url=url+'&order='+escape(order);
   url=url+'&pagesize='+pagesize;
   url=url+'&template='+br.template;
   if(editfield!='') {
     if(editfield=='all') {
       url=url+'&editfields=all';
     } else {
       url=url+'&editfields[]='+editfield;
     }
   }
   for(i=0;i<br.nb_selectedfields;i++)
     url=url+'&selectedfields['+i+']='+br.selectedfields[i];
   var container;
   if(br.modulecontainer != undefined && jQuery(br.modulecontainer).length) {
     container = jQuery(br.modulecontainer);
   }
   if(urlonly) return url;
   else TZR.updateModuleContainer(url, container);
 };
 /// modale de selection des langues pour enregistrement
 TZR.Table.saveSomeLangsSave=function(form, settings){
     if(confirm(settings.allLangsConfirmMess)){
           jQuery(form).append('<input type="hidden" name="procEditAllLang" value="1">');
           jQuery("#modal-langs-list-"+settings.uniqid).modal('hide')
           return TZR.ajaxSubmitForm(form);
       }
       return false;
 };
 TZR.Table.saveSomeLangsInitialize = function(container, settings){
    var alllangs = jQuery("div.all-langs>input", container);
    var allfields = jQuery("div.all-fields>input", container);
    jQuery("div.lang input[type='checkbox']", container).on('change', function(){
        if (!jQuery(this).is(":checked")){
            alllangs.attr("checked", false);
        }
    });
    jQuery("div.field input[type='checkbox']", container).on('change', function(){
        if (!jQuery(this).is(":checked")){
            allfields.attr("checked", false);
        }
    });
    alllangs.on('click', function(e){
        jQuery("div.lang input[type='checkbox']").not("[readonly='readonly']").attr('checked', alllangs.is(":checked"));
    });
    allfields.on('click', function(e){
        jQuery("div.field input[type='checkbox']").not("[readonly='readonly']").attr('checked', allfields.is(":checked"));
    });

    jQuery('div[id^="cont-"] input, div[id^="cont-"] select, div[id^="cont-"] textarea').on("change", TZR.Table.saveSomeLangsCheckFields);

    jQuery("div.tzr-action input[type='button']", container).on('click', function(evt){
        return TZR.Table.saveSomeLangsSave.call(TZR.Table, this.form, settings);
    });
};

TZR.Table.saveSomeLangsCheckFields = function(obj) {
  if(!obj) {
    return false;
  }
  if(obj.type=="change") {
    obj = this;
  }
  var name = jQuery(obj).attr('name');
  if(name=='foo') {
    name = jQuery(obj).parent().find("table input").attr('name');
  }
  if(name) {
    name = name.replace(/\[.*\]|_unselected|_HID|_del|_title/g, '');
    if(jQuery('#selectfield-'+name+' input').length) {
      jQuery('#selectfield-'+name+' input').attr('checked', true);
    }
  }
};

//========================//
// downloader
//========================//
 TZR.Table.Downloader = function(uniqid, baseurl){
   this.uniqid = uniqid;
   this.statusInterval = null;
   this.statusBaseUrl = baseurl;
   this.currentStatusUrl = null;
   this.container = null;
   this.exportoverlay = null;
   this.statusbar = null;
   this.recordcount = null;
   this.totalrecord = null;
 };
 // not in prototype ! to be callable with TZR.executeFunctionByName
 TZR.Table.Downloader.downloadEnd = function(downloaderVarName){
   if (typeof window[downloaderVarName[0]] != "undefined"){
     window[downloaderVarName[0]].exportEnd();
   }
 };
 TZR.Table.Downloader.prototype.exportStart = function(dform){

   this.recordcount.html('0');
   this.toggleMode(true);

   this.jqxhr = jQuery.ajax({
     async: true,
     data: dform.serialize() + '&_ajax=1',
     dataType: 'json',
     type: 'POST',
     url: dform.attr('action')
   });
   var that = this;
   this.statusInterval = setInterval(
     function(){
       that.refreshStatus();
     },
     1500);
 };

 TZR.Table.Downloader.prototype.exportEnd = function(){
   try{
     this.jqxhr.abort();
     clearInterval(this.statusInterval);
   } catch(alreadyClosed){}
 };
 TZR.Table.Downloader.prototype.init = function(){
   this.container = jQuery("#preexportbrowse"+this.uniqid);
   this.exportoverlay = jQuery("#exportoverlay"+this.uniqid);
   this.statusbar = jQuery("div.progress-bar", this.exportoverlay);
   this.recordcount = jQuery(".recordcount", this.exportoverlay);
   this.totalrecord = jQuery(".totalrecord", this.exportoverlay);
 };
 TZR.Table.Downloader.prototype.toggleMode = function(hideactions){
   this.container.toggle();
   this.exportoverlay.toggle();
   if (hideactions){
     TZR.Dialog.hideActions();
   }
 };
 TZR.Table.Downloader.prototype.checkStatus = function(data){
   if(!data){
     return true;
   }
   if (data.error) {
     clearInterval(this.statusInterval);
     this.toggleMode(false);
     alert(data.message);
     return false;
   }
   if (data.done) {
     clearInterval(this.statusInterval);
     this.statusInterval = null;
     var that = this;
     setTimeout(function(){
       that.statusbar.css("width", "100%");
       that.toggleMode(false);
       document.location = data.url;
       TZR.Dialog.closeDialog();
     }, 1000);
   }
   if (data.linesProcessed) {
     this.recordcount.html(data.linesProcessed);
     this.totalrecord.html(data.total);
     var pourcent = Math.round(100*parseInt(data.linesProcessed)/parseInt(data.total))+"%";
     this.statusbar.css("width", pourcent).html(pourcent);
     if (typeof data.done == "undefined" || !data.done)
       return true;
   }
 };
 TZR.Table.Downloader.prototype.refreshStatus = function() {
   var that = this;
   jQuery.getJSON(this.currentStatusUrl, function (data) {
     return that.checkStatus(data);
   });
 };

// </Table

TZR.Record = {
  save:function(id,funcName){
    if (!funcName){
      funcName = 'procEdit';
    }
    var f = document.forms["editform"+id];
    f.elements["_function"].value=funcName;
    return TZR.ajaxSubmitForm(f);
  },
  delete:function(id,moid,fct,oid, options){
    options = jQuery.extend({message:null}, options);

    var current_tab = jQuery('#tzr-tablist-'+id+' li.active');
      var ssmoid = null;
    // quand on est sur un sous module
    if (current_tab && current_tab.attr('id')) {
      var current_tab_id = current_tab.attr('id');
      var current_tab_index = current_tab.index()+1;
      ssmoid = current_tab_id.replace(/^li-([0-9]+)-[0-9a-zA-Z]+$/g, '$1');
    }

    //Si des cases sont cochées dans l'onglet actif d'un sous module alors on supprime les fiches du sous module. Sinon on supprime la fiche en cours.
    if(document.forms['editssmodform'+id+'-'+ssmoid] && TZR.checkBoxesIsChecked(document.forms['editssmodform'+id+'-'+ssmoid], 'selectstart_'+ssmoid, 'selectend_'+ssmoid, /_selected\[/)) {
      //var args = id+',del,,{"ssmoid_delete":'+ssmoid+',"_tabs":"'+current_tab_index+'"},false';
      var args = [id,'del',null,{ssmoid_delete:ssmoid,current_tabs:current_tab_index},false];
      TZR.Modal.confirm_delete.config('TZR.Table.applyfunction',args, null, TZR.Table.getConfirmDeleteMessage(id));
      TZR.Modal.confirm_delete.show();
    } else {
      var br = TZR.Table.browse[id];
      if (br===undefined) return;
      var next = br.browseurl;
      var url  = TZR._self+'&function='+fct+'&skip=1&moid='+moid+'&oid='+oid+'&_next='+next;
      TZR.Modal.confirm_delete.config("TZR.updateModuleContainer", url, null, options.message);
      TZR.Modal.confirm_delete.show();
    }
  },
  printselected:function(moid,oid, archive) {
    TZR.Dialog.openURL(TZR._self+'&function=prePrintDisplay&moid='+moid+'&template=Module/Table.preprintview.html&tplentry=br&oid='+oid+(archive?"&_archive="+escape(archive):""));
  },
  exportselected:function(moid,oid) {
    TZR.Dialog.openURL(TZR._self+'&function=preExportDisplay&moid='+moid+'&template=Module/Table.preexportview.html&tplentry=br&oid='+oid);
  },
  update:function(id,url){
    var modulecontainer = TZR.getModuleContainer(id);
    TZR.jQueryLoad({target:modulecontainer,url:url});
  },
  updateSubModule:function(url,link){
    TZR.jQueryLoad({target:jQuery(link).parents('div.tzr-tabcontent'),url:url});
  },
  ssmodsave:function(id,moid,url,button){
    var f=document.forms['editssmodform'+id+'-'+moid];

    if ('moid' in f && url.indexOf('moid') > -1) {
      jQuery('form[name=editssmodform'+id+'-'+moid+'] input[name=moid]').remove();
      delete f.moid;
    }
    if(!TZR.isFormValidWithFocus(f,true)){
      alert('Some data are not valid.');
      return false;
    }
    TZR.jQueryLoad({target:jQuery(button).parents('div.tzr-tabcontent'),url:url,data:jQuery(f).serializeArray()});
  },
  // Ajout un onglet ajax "sous-module: ajouter"
  addTabs:function(id,moid,url,label){
    var tabId = 'li-'+moid+'-'+id+'-add';
    var tabContentId = 'sc-'+moid+'-'+id+'-add';
    if(!document.getElementById(tabId)){
      TZR.parentFormValidator = {};
      jQuery.extend(TZR.parentFormValidator, TZR.validator);
      var tabLi = '<li id="'+tabId+'" role="presentation"><a href="#'+tabContentId+'" aria-controls="#'+tabContentId+'" data-url="'+url+'" data-overlay="none" role="tab" data-toggle="tabajax" aria-expanded="false">'+label+'</a></li>';
      var tabDiv = '<div id="'+tabContentId+'" class="tzr-tabcontent tab-pane active" role="tabpanel"></div>';
      jQuery('#tzr-tablist-'+id+' > ul').append(tabLi);
      jQuery('#tzr-tabcontentcontainer-'+id).append(tabDiv);
      jQuery('#'+tabId+'>a').trigger(click_event);
    }else{
      jQuery('#'+tabId+'>a').trigger(click_event);
    }
  },
  // Retour d'une popup de creation de fiche pour edition champ lien
  setObjectSelection:function(varid, title, oid){
    var elm = jQuery("#"+varid);
    if (jQuery(elm).is('select')) {
      jQuery(elm).prepend('<option value="'+oid+'" selected>'+title+'</option>');
      jQuery('option[value="'+oid+'"]').attr('selected', 'selected');
    } else {
      if (elm.length>0){
	elm.val(oid);
	jQuery("#_INPUT"+varid).val(title);
      }
      try{
	TZR.autoCompleteMultipleValue(varid, oid, title);
      }catch(e){}
    }
  },
  // Preparation dun formulaire insertion en popup
  newRecordInit:function(varid){
    var vobj = window['v'+varid];
    var elm = jQuery("#newrecordcontainer"+vobj.uniqid);
    var form = document.forms[vobj.formname];
    var jform = jQuery(form);
    jQuery("a[data-toggledialog]", elm).hide();
    // champs cachés du bloc tzr-action remontés dans le formulaire
    jform.append("<input type='hidden' name='template' value='Module/Table.popinsert.html'>");
    jform.append("<input type='hidden' value='"+vobj.varid+"' name='myvarid'>");

    jQuery("#tzr-action"+vobj.uniqid).append("<button class='btn btn-default' data-dismiss='modal' type='button'>"+vobj.closelabel+"</button>");
    jQuery("input[name='_next']", jform).remove();
  },
  displayJsonData : function(moid, oid) {
    var url = TZR._self+'&moid='+moid+'&oid='+oid+'&tplentry=jsondata&function=displayJsonData&template=Module/Table.displayJsonData.html&_ajax=1&_raw=1';
    TZR.Dialog.openURL(url, {}, {allowMove : false, id:'jsondatadialog'});
  }
};

 TZR.SMod = new Object();
 //
 TZR.SMod.findBrowseTab=function(addForm,smoid) {
   var tabContainer = jQuery(addForm).parents('div .tzr-tabcontentcontainer');
   var id = tabContainer.attr("id").replace('tzr-tabcontentcontainer-','');
   TZR.SMod.srctabContentId = '#sc-'+smoid+'-'+id;
   return TZR.SMod.srctabContentId;
 }
 // Supprime un onglet "sous-module: ajouter" et rafraichi l'onglet liste associé
 TZR.SMod.removeAddTab=function(smoid){
   var srctabContentId = TZR.SMod.srctabContentId;
   var addedtabContentId = srctabContentId + '-add';
   var addedtabId = addedtabContentId.replace('sc-','li-');  //'li-'+smoid+'-'+id+'-add';
   var srctabId = srctabContentId.replace('sc-','li-');
   jQuery(addedtabId).remove();
   jQuery(addedtabContentId).remove();
   jQuery(srctabContentId).empty();
     jQuery(srctabId+'>a').trigger(click_event);
 }
 TZR.SMod.setCount=function(smoid,id,count,event, xhr, settings) {
   var nbspan=jQuery('#li-'+smoid+'-'+id+'>a>span');
   nbspan.html(count);
 }

/**
 * script spécifiques au mode traduction
 * settings = ancien vuniqid
 */
TZR.EDITTRANSLATION = {};
/*<%* copy des données de droite *%>*/
TZR.EDITTRANSLATION.copyValuesToLeft  = function(settings){
  for(var i in settings.rawLeftData){
    var field = settings.rawLeftData[i];
    // ingnorer les champs systeme (UPD), non traduisibles, RO
    if (field.fielddef.sys == 1 || field.fielddef.translatable != 1 || !this.copyAvailable(field)){
      continue;
    }
    this.copyFieldValueToLeft(field, settings);
  }
}

/**/
TZR.EDITTRANSLATION.copyAvailable  = function(field){
  return new RegExp(/Field\\ShortText|Field\\Text|Field\\RichText|Field\\Real|Field\\Boolean/).test(field.fielddef.ftype);
}
/*<%* copy d'un champ *%>*/
TZR.EDITTRANSLATION.copyFieldValueToLeft  = function(field, settings){
    var jtargetField = jQuery("*:input[name='"+field.field+"']", settings.editcontainer);
    switch(field.fielddef.ftype){
    case "\\Seolan\\Field\\ShortText\\ShortText":
    case "\\Seolan\\Field\\Text\\Text":
    case "\\Seolan\\Field\\Real\\Real":
	jtargetField.val(field.raw);
	break;
    case "\\Seolan\\Field\\Boolean\\Boolean":
	jtargetField.attr("checked", 1==field.raw?true:false);
	break;
    case "\\Seolan\\Field\\RichText\\RichText":
	jtargetField = jQuery("textarea[name='"+field.field+"']", settings.editcontainer);
	CKEDITOR.instances[jtargetField.attr('id')].setData(field.raw);
    }
}
/*<%* initialisation de la copie champ par champ *%>*/
TZR.EDITTRANSLATION.initFieldsCopy = function(settings){
    for(var i=0; i< settings.rawLeftData.length; i++){
	var field = settings.rawLeftData[i];
	if (field.fielddef.translatable != "1" || field.fielddef.sys == "1"  || !TZR.EDITTRANSLATION.copyAvailable(field)){
	    continue;
	}
	var jfieldlabel = jQuery("#cont-"+field.field+">div>div>label", settings.viewcontainer);
	jfieldlabel.addClass("copylangdatafield");
	jfieldlabel.on('click', field, function(evt){
	    TZR.EDITTRANSLATION.copyFieldValueToLeft(evt.data, settings);
	});
    }
};
TZR.EDITTRANSLATION.setNextAction  = function(event, elem, settings){
    var checked = null;
    if (elem.checked){
      settings.editform._next.value=(elem.value=="")?settings.savenext:elem.value;
      checked= settings;
    }
    if (checked){ // un seul ou aucun
      jQuery("input[name='nextaction']").filter("input[value!='"+elem.value+"']").attr('checked', false);
    } else {
      settings.editform._next.value = this.savenext;
    }
}
TZR.EDITTRANSLATION.setPublishStatus  = function(published, container){
  if (published == 2){
    jQuery("div[id^='tzr-tablist-']  ul", container).addClass('nappHeader');
    jQuery("div[id^='tzr-tablist-']  ul", container).removeClass('appHeader');
  } else if (published == 1){
    jQuery("div[id^='tzr-tablist-']  ul", container).removeClass('nappHeader');
    jQuery("div[id^='tzr-tablist-']  ul", container).addClass('appHeader');
  } else {
    jQuery("div[id^='tzr-tablist-']  ul", container).removeClass('nappHeader');
    jQuery("div[id^='tzr-tablist-']  ul", container).removeClass('appHeader');
  }
}
/*<%* mise en forme des composants pour ajuster les vues cote à cote *%>*/
TZR.EDITTRANSLATION.formatContents  = function(settings){
  this.syncHeights(settings);
};
/*<%* synchro des hautes du pannel de gauche / panel de droite *%>*/
TZR.EDITTRANSLATION.syncHeights  = function(settings){
 var viewcontainer = jQuery("div.details", settings.viewcontainer);
  jQuery("tr[id^='cont-']", settings.editcontainer).each(function(i, o){
    var jeditobj = jQuery(o);
    var jviewobj = jQuery("#"+jeditobj.attr('id'), viewcontainer);
    if (jviewobj.height() <= jeditobj.height()){
      jviewobj.height(jeditobj.height());
    } else {
      jeditobj.height(jviewobj.height());
    }
  });
};
TZR.EDITTRANSLATION.changeLangEdit=function(selectedOption, settings){
    if (settings.confirmMessage || (settings.confirMessage && confirm(settings.confirmMessage))){
        this.loadEdit(selectedOption.attr('value'), settings);
    }
}
/*<%* changement de la langue de traduction, via le traitement std global  *%>*/
TZR.EDITTRANSLATION.loadEdit  = function(langcode){
    var url = new URL(window.location.href);
    var moid = url.searchParams.get("moid");
    var newlocation = "/scripts/admin.php?moid="+moid+"&template=Core.layout/main.html&function=portail&LANG_DATA="+langcode+"&_setlang=1"+top.location.hash;
    document.location = newlocation;
}
/*<%* changement de la langue du display dans une langue donnée *%>*/
TZR.EDITTRANSLATION.loadView  = function(langcode, settings){
  var data = {"_edituniqid":settings.uniqid};
  if (langcode != null){
    data.langtrad = langcode;
  }
  TZR.jQueryAjax({mode:'load', url:settings.displayurl, data:data, target:jQuery('.details', settings.viewcontainer), nocache:1});
}
/*<%* construction d'un select de langues *%>*/
TZR.EDITTRANSLATION.mkLangSelect = function(langs, select){
  if (select == null){
   select = jQuery('<select></select>');
  }
  var alang, jopt;
  for(var i in langs){
    alang =langs[i];
    jopt = jQuery("<option value='"+alang.code+"'>"+alang.text+"</option>").appendTo(select);
    jopt.data('tzr-lang', alang);
    this.setLangBg(jopt, alang.long);
  }
  return select;
};

TZR.EDITTRANSLATION.setSelectedLang = function(container, langcode){
  jQuery("option[value='"+langcode+"']", jQuery('.langselection > select', container)).attr('selected', true);
};
/*<%* positionne un background avec l'image de la langue donnée *%>*/
TZR.EDITTRANSLATION.setLangBg = function(jobj, ico){
    // tant que select et pas un composant bootstrap stylable
    return;
  var imgurl = jQuery(ico).attr('src');
  jQuery(jobj).css({"background-repeat":"no-repeat",
		    "background-position":"right center",
		    "background-image":"url("+imgurl+")"}
		  );
};
/*<%* initialisation du splitter edit/view %*>*/
TZR.EDITTRANSLATION.initSplitter = function(vuniqid){
    vuniqid.splitDimensions = {
	rightInitialWidth:parseInt(vuniqid.editcontainer.width()),
	leftInitialWidth:parseInt(vuniqid.viewcontainer.width()),
  };
/* ne pas fixer -> bloque l'auto redimensionnement en hauteur sur l'ouverture des fieldset
  var ec = jQuery(vuniqid.editcontainer);
  var vc = jQuery(vuniqid.viewcontainer);
  var height = Math.max(parseInt(ec.height()),parseInt(vc.height()));
  jQuery(vc).height(height);
  jQuery(ec).height(height);
*/
    vuniqid.resizableParms = {
    handles:"e",
    containment: "parent",
    maxWidth:Math.round(0.80 * parseInt(vuniqid.editcontainer.parent().width())),
    minWidth:Math.round(0.20 * parseInt(vuniqid.editcontainer.parent().width())),
    resize:function(event, ui){TZR.EDITTRANSLATION.moveSplit(event, ui, vuniqid);}
};
  vuniqid.editcontainer.resizable(vuniqid.resizableParms);
  }
/* splitter  */
TZR.EDITTRANSLATION.moveSplit = function(event, ui, settings){
      if (settings.cks == null){
           settings.cks = jQuery("textarea.xrichtext", settings.editcontainer).next().css("width", "100%");
      }
    var split = settings.splitDimensions.leftInitialWidth-parseInt(ui.size.width);
    settings.viewcontainer.css("width", (settings.splitDimensions.rightInitialWidth+split)+"px");
};
/* field selector */
TZR.Table.FieldSelector = {
  'methodSave':'procEditBrowseProperties',
  'methodEdit':'editBrowseProperties',
  'templateEdit':'Module/Table.modalBrowseProperties.html',
  'tplentry':'bp'
};
 TZR.Table.FieldSelector.open = function(browseid){
   var br = TZR.Table.browse[browseid];
   var url = TZR._self+"&moid="+br.moid+"&function="+this.methodEdit+"&template="+this.templateEdit+"&tplentry="+this.tplentry+"&_ajax=1&_skip=1";
   TZR.Dialog.openURL(url, {browseid:browseid}, {});
 };
 TZR.Table.FieldSelector.setFieldState = function(fieldname){
   var fieldid = "statusselector"+fieldname;
   var jfield = jQuery("#"+fieldid);
   var fielddef = jfield.data('fieldprops');
   var hifieldstatus = jfield.siblings("input[name^='fieldstatus']");
   var field = {browse:false,none:false,search:false};
   ["browse","search","none" ].forEach(function(key){
     field[key] = jQuery(">span.csico-"+key, jfield);
   });
   if (field['none'].css('display') != "none"){
     field['none'].hide();
     field['browse'].show();
     hifieldstatus.val('browse');
   } else if (field['browse'].css('display') != "none" && fielddef.queryable){
     field['browse'].hide();
     field['search'].show();
     hifieldstatus.val('search');
   } else if ( field['browse'].css('display') != "none" && !fielddef.queryable){
     field['browse'].hide();
     field['none'].show();
     hifieldstatus.val('none');
   } else if (field['search'].css('display') != "none"){
     field['search'].hide();
     field['none'].show();
     hifieldstatus.val('none');
   }
 };
 TZR.Table.FieldSelector.reset=function(formname){
   var form = document.forms[formname];
   var browseid = jQuery(form).data('browseid');
   TZR.Dialog.openURL(TZR._self, {
     _skip:1,
     _ajax:1,
     template:this.templateEdit,
     function:this.methodEdit,
     tplentry:this.tplentry,
     _reset:1,
     browseid:browseid,
     moid:TZR.Table.browse[browseid].moid
   }, {overlay:"auto"});
 };
 TZR.Table.FieldSelector.save=function(formname){
   // save new browse properties before page refresh
   var form = document.forms[formname];
   var browseid = jQuery(form).data('browseid');
   var data = jQuery(form).serializeArray();
   this.saveAndGoBrowse(browseid, data);
 };
 TZR.Table.FieldSelector.saveAndGoBrowse = function(browseid, newData){
   var moduleContainer = jQuery("#cv8-uniqdiv-"+browseid);
   var br = TZR.Table.browse[browseid];

   var obj = {
     mode:'post',
     cache:false,
     // target à voir ?
     dataType:"text/html",
     url:this.saveUrl(br.moid),
     data:newData,
     cb_args : [browseid],
     overlyay:moduleContainer,
     cb:function(browseid, responseText, status, xhrObject){
       var br = TZR.Table.browse[browseid];
       br.g_pagesize=0;
       // keep actual page position
       // ? see : pagesize modification
       TZR.Table.go_browse(browseid,'refresh');
     }
   };
   TZR.Dialog.closeDialog();
   TZR.jQueryAjax(obj);
 };
 TZR.Table.FieldSelector.saveUrl = function(moid){
   return TZR._self+"&moid="+moid+"&template=Core.empty.html&function="+this.methodSave+"&_skip=1&_ajax=1";
 }
/*</ field selector */
TZR.Table.submitSelectedFields = function(uniqid){
  TZR.Table.FieldSelector.open(uniqid);
 };
TZR.Table.updateBrowseProperties = function(browseid, action, options){
   var br = TZR.Table.browse[browseid];
   if (action == "pagesize"){
     br.first='0';
     var pagesizediff = options.pagesizediff;
     var pagesize = br.g_pagesize;
     if(typeof(pagesizediff)=='number')
       pagesize=pagesize+pagesizediff;
     else
       pagesize=eval(pagesize+pagesizediff);
     pagesize=parseInt(pagesize);
     TZR.Table.FieldSelector.saveAndGoBrowse(browseid,
					     {propsnames:['pagesize'],
					      pagesize:pagesize});
   }
   if (action == "order"){
     br.first='0';
     br.order = null;
     TZR.Table.FieldSelector.saveAndGoBrowse(browseid,
					     {propsnames:['order'],
					      order:options.order});
   }
};
 TZR.Table.loadQuickQuery = function(container){
   var qqform = jQuery('div.quick-query-form', container);
   if (qqform.length>0)
     return;
   var params = container.data('quickqueryParameters');

   var qqoptions = "";
   if (typeof params.options.order != "undefined")
    qqoptions += `&_options[order]=${params.options.order}`;
  if (typeof params.options.pagesize != "undefined")
    qqoptions +=`&_options[pagesize]=${params.options.pagesize}`;
  if (typeof params.options._function != "undefined")
    qqoptions += `&_options[_function]=${params.options._function}`;
  if (typeof params.options._persistent != "undefined")
    qqoptions += `&_options[_persistent]=${params.options._persistent}`;
  if (typeof params.options._queryparametersbutton != "undefined")
    qqoptions += `&_options[_queryparametersbutton]=${params.options._queryparametersbutton}`;
  if (typeof params.options._querytemplate != "undefined")
    qqoptions += `&_options[_querytemplate]=${params.options._querytemplate}`;
   var url =
   `${TZR._self}&_uniqid=${params.uniqid}&moid=${params.moid}`
   +`&function=prepareQuickquery&template=Module/Table.quickquery-form.html`
   +`&tplentry=br${qqoptions}&_skip=1`;
   var querydata = {}, i, qf;
   querydata["_FIELDS"]={};
   for(i in params.queryfields){
     qf = params.queryfields[i];
     querydata[qf.field] = qf.value;
     querydata[qf.field+"_op"] = qf.op;
     querydata[qf.field+"_FMT"] = qf.fmt;
     querydata["_FIELDS"][qf.field]=qf.field;
   }

   if (params.oids != null){
     querydata['oids'] = params.oids;
   }

   if (params.submodules_searchselected != null){
     querydata['ssmods_search'] = params.submodules_searchselected;
   }

   if (params.langstatus)
     querydata['_langstatus'] = params.langstatus;
   
   var cb = null;
   if(params.modalMode == true){
     // configuration du mode en retour du chargement
     cb = (()=>{
        this.linkedlistQuickqueryConfigure(params.modalParams);
     });
     url+="&_modalMode=1";
   }
   TZR.jQueryLoad({
     target:container,
     url:url,
     data:querydata,
     cb:cb
   });
 };
 TZR.Table.formatWithObject=function(pattern, values){
   var exp = new RegExp("\\\$\\\{([A-Za-z0-9.-_]+)\\\}");
   var c=0, done = false, res = null;
   while(!done && c<=100){
     res = exp.exec(pattern);
     if (!res){
       done=true;
       break;
     }
     c++;
     pattern = pattern.replace(res[0], values[res[1]]);
   }
   return pattern;
 };
   TZR.Table.linkedlistQuickqueryConfigure=function(params){
     var uniqid = params.uniqid;
     var form = document.forms[`quicksearch${uniqid}`];
     form.elements['template'].value="Core.linkedobjectselection.html";
     form.onsubmit = (()=>{
       var openeroptions = this.browse[uniqid].vuniqid.g_openeroptions;
       var selectedFields = this.browse[uniqid].vuniqid.g_selectedfields;
       for(let i=0; i<selectedFields.length; i++){
         var elmid = `${uniqid}_selectedfield_${i}`;
         var elm = document.getElementById(elmid);
         if (!elm){
           elm = document.createElement("input");
           elm.setAttribute("type","hidden");
           elm.setAttribute("name", `selectedfields[${i}]`);
           elm.setAttribute("id",elmid);
           form.appendChild(elm);
         }
         elm.setAttribute("value", selectedFields[i]);
       }
       for(let o in openeroptions){
         var elmid = `${uniqid}_${o}`;
         var elm = document.getElementById(elmid);
         if (!elm){
           elm = document.createElement("input");
           elm.setAttribute("type","hidden");
           elm.setAttribute("name", `openeroptions[${o}]`);
           elm.setAttribute("id",elmid);
           form.appendChild(elm);
         }
         elm.setAttribute("value", openeroptions[o]);
       }

       ["_skip","tlink","_modalMode"].forEach((e)=>{
         let elmid = `${uniqid}_${e}`;
         let elm = document.getElementById(elmid);
         if (!elm){
           elm = document.createElement("input");
           elm.setAttribute("type","hidden");
           elm.setAttribute("name", e);
           elm.setAttribute("id",elmid);
           form.appendChild(elm);
        }
          elm.setAttribute("value",1);
        });
       TZR.Dialog.openFromForm(form,{},{overlay:"auto"});
       return false;
     });
   };
//# sourceURL=Module/Table.table.js
