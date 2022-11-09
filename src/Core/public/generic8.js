jQuery(function(){
  jQuery.ajaxSettings.cache=false;
});

TZR.linkAlert=function(url){
  var reg=/&(_bdx|_raw|_ajax|_)=[^&]+/g;
  alert(url.replace(reg,""));
}
// Verifie si un lien demande une confirmation
TZR.checkLinkConfirm=function(a){
  if(jQuery(a).attr('x-confirm')){
    var self=a;
    eval(jQuery(a).attr('x-confirm'));
    if(!ret) return false;
  };
  return true;
}

/// Fonctions de gestion des evenements sur un container
TZR.Event=new Object();
TZR.Event.events=new Object();
// Ajoute un listener (id:uniqid, t:type de l'evenement, f:fonction, p:parametres
TZR.Event.add=function(id,t,f,p){
  if(!TZR.Event.events[t]){
    TZR.Event.events[t]={};
    jQuery(document).bind(t,TZR.Event.execute);
  }
  if(!TZR.Event.events[t][id]) TZR.Event.events[t][id]=[];
  TZR.Event.events[t][id].push({f:f,p:p});
}
TZR.Event.execute=function(e){
  var ret=true;
  for(id in TZR.Event.events[e.type]){
    var evs=TZR.Event.events[e.type][id];
    if(document.getElementById('cv8-uniqdiv-'+id)){
      for(j in evs){
        if(evs[j].f(e,id,evs[j].p)===false){
          ret=false;
          break;
        }
      }
    }else{
      delete TZR.Event.events[e.type][id];
    }
  }
  return ret;
}
// Fonctions de nettoyage des objets
TZR.initObjCleaner=function(){
    if(!TZR.objCleaner) TZR.objCleaner=setInterval(function(){TZR.checkObjCleaner();},TZR.cleanInterval);
}
TZR.stopObjCleaner=function(){
  if(TZR.objCleaner) clearInterval(TZR.objCleaner);
}
TZR.addToObjCleaner=function(id,jso,f){
  if(!TZR.objToClean) TZR.objToClean=new Object();
  if(!f) f=TZR.cleanObj;
  TZR.objToClean[id]={o:jso,f:f};
}
TZR.checkObjCleaner=function(){
  for(var i in TZR.objToClean){
    if(!document.getElementById(i)){
      TZR.objToClean[i].f(TZR.objToClean[i].o);
      delete TZR.objToClean[i];
    }
  }
}
TZR.cleanObj=function(o){
  if (typeof(o.unbinds) !== "undefined"){
    try{
      o.unbinds.forEach(function(el){
	jQuery(el.container).unbind(el.eventname);
      });
    }catch(e){}
  }
  if(!delete o) o=undefined;
}
// Change le titre de la fenetre courante
TZR.setDocumentTitle=function(title,noprefix){
  if(!TZR.title) TZR.title=document.title;
  if(noprefix) document.title=title;
  else document.title=TZR.title+" : "+title;
  if (TZR.maintitle != ''){
    jQuery('#cs-maintitle').html(TZR.maintitle).show();
  } else {
    jQuery('#cs-maintitle').html('').hide();
  }
}

// Redimensionne l'interface (width : taille de la zone centrale ou toggle pour switcher entre plein ecran/taille d'origine
TZR.resizeTo=function(width){
  if(!width) return;
  var jc=jQuery('#cv8-content');
  var jc2=jQuery('#cv8-container');
  if(width=='toggle'){
    var toadd=jQuery(window).width()-jc2.width();
    toadd-=5;
    if(toadd!=0){
      var w1=jc.width()+toadd;
      var w2=jc2.width()+toadd;
    }
    if(toadd==0 || w1<750){
      var w1='';
      var w2='';
    }
  }else{
    var w1=width;
    var w2=jc2.width()+width-jc.width();
    var sw=screen.availWidth;
    if(w1+jc.offset().left>sw){
      TZR.resizeTo('toggle');
      return;
    }
  }
  jc.width(w1);
  jc2.width(w2);
  jQuery('div.cv8-contenu-haut,#cv8-top').width('100%');
  return w1;
}

// Fonction pour appliquer l'alernance de couleur et le hover sur les tables .cv8_tablelist
TZR.applyAlternate=function(what,table){
}



// Met à jour le texte d'information
TZR.setInfoText=function(text){
  if(text!==undefined && text!==null) jQuery('#cv8-infotext>div.cv8-infotextg>div.cv8-txtseul').html(text+'&nbsp;');
}
// Recupère le texte d'information
TZR.getInfoText=function(){
  return jQuery('#cv8-infotext>div.cv8-infotextg>div.cv8-txtseul').html();
}
// Efface le chemin actuel
TZR.clearNav=function(){
  jQuery('#cv8-path>ul>li:gt(0)').remove();
}
// Ajoute une entrée au chemin
TZR.addNav=function(title,url){
  jQuery('#cv8-path>ul').append('<li><a data-linkoptions=\'{"noautocreate":true}\' class="cv8-ajaxlink" href="'+url+'">'+title+'</a></li>');
}


TZR.executeFunctionByName=function(functionName, context /*, args */) {
  var args = [].slice.call(arguments).splice(2);
  var namespaces = functionName.split(".");
  var func = namespaces.pop();
  for(var i = 0; i < namespaces.length; i++) {
    context = context[namespaces[i]];
  }
  if (typeof context[func] === "function")
    return context[func].apply(context, args);
}
// Fonction de chargement via jQuery.load avec callback de gestion de l'authentification,overlay automatique
// Parametres : url, target (cible du chargement et de l'overlay), cb (callback)
//              noautocreate (par defaut, la fonction creee les div module-container si la cible est #cv8-content)
//              nocheckauth (desactive la verification de l'authentification), mode (mode de chargement : load, post)
//              noautoscroll (desactive le scroll automatique), nocache (desactivation du cache navigateur, true par defaut)
TZR.jQueryAjax=function(obj){
  if(!TZR.ajaxQueue) TZR.ajaxQueue=0;
  TZR.ajaxQueue++;
  var url=obj.url.replace(/&amp;/g, '&').replace(/%26amp%3B/g, '%26');
  var data=obj.data;
  var cb=obj.cb;
  var cb_args=obj.cb_args;
  var cb_context=obj.cb_context;
  var mode=obj.mode;
  var nocheckauth=obj.nocheckauth;
  var noautocreate=obj.noautocreate;
  var noautoscroll=obj.noautoscroll;
  var dataType=obj.dataType;
  if(dataType==undefined) dataType="arraybuffer";
  var nocache=obj.nocache;
  var statetarget=obj.statetarget;
  var nowaitcursor=obj.nowaitcursor;
  if(nocache==undefined) nocache=true;
  if(!obj.target) obj.target='#cv8-content';
  var jt=jQuery(obj.target);
  if (jt==undefined) jt=JQuery('#cv8-content');
  jt.context = 'document';
  if(obj.overlay) var jo=jQuery(obj.overlay);
  else var jo=jt;
  if(!nowaitcursor) jQuery('body,a').css('cursor','progress');
  // Ajoute un overlay le temps du chargement
  if(jt.attr('id')=='cv8-content'){
    if(mode=="load" && !noautocreate){
      jt.html('<div class="cv8-module" id="cv8-module-0"><div class="cv8-module-bg"><div class="cv8-module-container" id="cv8-module-container-0"></div></div></div>');
      var jt=jQuery('#cv8-module-container-0');
    }
    var overlay=TZR.setOverlay(jo);
  }else if(obj.overlay != "none") {
    if (typeof(obj.overlayOpts) != "undefined")
      var overlay=TZR.setOverlay(jo, obj.overlayOpts);
    else
      var overlay=TZR.setOverlay(jo);
  }else {
    var overlay="";
  }
  // Overlay de confirmation sur un objet du DOM
  if(statetarget){
    var stateoverlay=TZR.setOverlay(statetarget,{content:'<img src="/csx/public/jtree/spinner.gif">',oclass:'overlay-confirm',css:{opacity:0.8}});
  }
    // Scroll le contenu si necessaire
    var pos = Number.MAX_VALUE;
    try{
	pos=jt.position().top;
    } catch(e){}

  if(!noautoscroll && jQuery(document).scrollTop()>pos) jQuery(document).scrollTop(pos);
  // Activation/desactivation du cache
  var currentajaxsettings=jQuery.extend({},jQuery.ajaxSettings);
  if(nocache) jQuery.ajaxSettings.cache=false;
  else jQuery.ajaxSettings.cache=true;
  // Options ajax selon contexte
  if(TZR.supportFormData() && data instanceof FormData){
    jQuery.ajaxSettings.processData=false;
    jQuery.ajaxSettings.contentType=false;
  }
  // Complete l'url
  if(url.indexOf(' ')!=-1){
    var url2=url.substr(url.indexOf(' '));
    url=url.substr(0,url.indexOf(' '));
  }else{
    var url2='';
  }
  if (url.indexOf('?') == -1) url += '?';
  if(url==url.replace('_raw=','')) url+='&_raw=1';
  if(url==url.replace('_ajax=','')) url+='&_ajax=1';
  //skip history
  if(obj.skip) url+='&skip=1';
  url=url+url2;
  if(typeof(data)=='function'){
    cb=data;
    data=undefined;
  }
  // Construit la callback (desactivation overlay + callback personnalisable)
  var callback=function(responseText,textStatus,XMLHttpRequest){
    TZR.ajaxQueue--;
    TZR.unsetOverlay(overlay);
    if(!nowaitcursor) jQuery('body,a').css('cursor','');
    if(!nocheckauth){
      if(XMLHttpRequest.status==401){
        TZR.authAlert(TZR.jQueryAjax,obj);
        return false;
      }
    }
    // Overlay de confirmation sur un objet du DOM
    if(statetarget){
      TZR.unsetOverlay(stateoverlay);
      stateoverlay=TZR.setOverlay(statetarget,{content:(textStatus=='success'?'OK':'ERROR'),
                                                 oclass:(textStatus=='success'?'overlay-confirm':'overlay-error'),css:{opacity:0.8}});
      setTimeout(function(){stateoverlay.fadeOut(800,function(){TZR.unsetOverlay(stateoverlay);})},500);
    }
    if (mode == "append"){
      jt.append(responseText);
    }
    if(cb){
      if (typeof(cb_args) === 'string') cb_args = cb_args.split(",");
      if(typeof(cb) === 'string'){
        var args = [cb,window,responseText,textStatus,XMLHttpRequest];
        if (typeof(cb_args) !== undefined) { // faux : c'est un string, toutjours 
          var a = args.slice(0,2);
          var b = args.slice(2);
          args = a.concat(cb_args).concat(b);
        }
        return TZR.executeFunctionByName.apply(this,args);
      }
      if (typeof(cb) === 'function') {
        var args = [responseText,textStatus,XMLHttpRequest];
        if (cb_args !== undefined && cb_args !== null) {
          var a = [];
          args = a.concat(cb_args).concat(args);
        }
	if (typeof cb_context == 'object'){
	  return cb.apply(cb_context,args);
	} else {
	  // en v8 on avait appel direct de la cb donc , ici le this c'est ? 
          return cb.apply(this,args);
	}
      }
    }
  }
  if(mode=="load") jt.load(url,data,callback);
  if(mode=="get") jQuery.ajax({dataType:dataType,type:'GET',data:data,url:url,success:callback});
  if(mode=="post") jQuery.post(url,data,callback);
  if(mode=="native") {
    jQuery.ajax({dataType: 'native', type : 'POST', data:data, url: url,xhrFields: {responseType: dataType},success:callback});
  };
  if (mode == 'append'){
    jQuery.ajax({dataType:"html",type:'GET',data:data,url:url,success:callback});
  }
  jQuery.ajaxSettings=currentajaxsettings;
  return overlay;
}
TZR.jQueryAppendToTarget = function(obj){
   obj.mode = 'append';
   TZR.jQueryAjax(obj);
}
TZR.jQueryLoad=function(obj){
  obj.mode="load";
  TZR.jQueryAjax(obj);
}
TZR.jQueryPost=function(obj){
  if(obj.mode == undefined) obj.mode="post";
  TZR.jQueryAjax(obj);
}
// Soumet un formulaire via ajax (div : element cible pour l'overlay / loadresult : charge le resultat dans div)
TZR.ajaxSubmitForm=function(form,div,loadresult,cplt,confirmmessage){
  if(TZR.ajaxSubmitFormOngoing==form) return true;
  if(confirmmessage && !confirm(confirmmessage)) return false;
  TZR.ajaxSubmitFormOngoing=form;
  if((form.enctype=="multipart/form-data" || form.ENCTYPE=="multipart/form-data") && !TZR.supportFormData()){
    var ret=TZR.iframeSubmitForm(form,div,loadresult,cplt);
    if(ret) jQuery(form).submit();
    return TZR.ajaxFormSubmitted();
  }
  if(loadresult==undefined) loadresult=true;
  if(!TZR.isFormValidWithFocus(form,true)){
    return TZR.ajaxFormSubmitted();
  }
  if(div==undefined) div=jQuery(form).parents('div.cv8-module-container')[0];
  var param={target:div,url:jQuery(form).attr('action')};
  if(TZR.supportFormData()){
    TZR.updateCKEditorElements(form);
    param.data=new FormData(form);
  }else{
    param.data=jQuery(form).serializeArray();
  }
  param.cb = jQuery(form).attr("data-cb");
  param.cb_args = jQuery(form).attr("data-cb_args");
  if (!param.cb && cplt && typeof cplt.cb != "undefined"){
	param.cb = cplt.cb;
	param.cb_args = (typeof cplt.cb_args != "undefined")?cplt.cb_args:null;
  }
  if(cplt!=undefined) jQuery.extend(param,cplt);
  if(loadresult) TZR.jQueryLoad(param);
  else TZR.jQueryPost(param);
  return TZR.ajaxFormSubmitted();
}
// Rétablit la configuration du précédent formulaire dans le cadre d'une soumission AJAX d'ajout de sous-fiche à une fiche en cours d'édition
TZR.ajaxFormSubmitted = function() {
  TZR.ajaxSubmitFormOngoing='';
  // TZR.parentFormValidator est copié à partir de TZR.validator lors de l'ajout d'un onglet AJAX dans Module/Table.edit.html
  if (typeof TZR.parentFormValidator != 'undefined' && TZR.parentFormValidator != null) {
    jQuery.extend(TZR.validator, TZR.parentFormValidator);
    TZR.parentFormValidator = null;
  }
  return false;
}
// Soumet un formulaire via iframe (div : element cible pour l'overlay / loadresult : charge le resultat dans div)
TZR.iframeSubmitForm=function(form,div,loadresult,cplt){
  if(loadresult==undefined) loadresult=true;
  if(div==undefined) div=jQuery(form).parents('div.cv8-module-container')[0];
  if(cplt==undefined) cplt={};
  var jdiv=jQuery(div);
  var nowaitcursor=cplt.nowaitcursor;
  var overlay='';
  return TZR.iframeSubmitFormKernel.submit(form,{onStart:function(){
    var ret=TZR.isFormValidWithFocus(form,true);
    if(!ret) return false;
    if(!nowaitcursor) jQuery('body,a').css('cursor','progress');
    overlay=TZR.setOverlay(jdiv);
    return true;
  },onComplete:function(html){
    if(!nowaitcursor) jQuery('body,a').css('cursor','');
    if(loadresult){
      if(html=="401 Unauthorized"){
        TZR.unsetOverlay(overlay);
        cplt.force=true;
        TZR.authAlert(TZR.iframeSubmitForm,form,div,loadresult,cplt);
      }else{
        jdiv.html(html);
        TZR.unsetOverlay(overlay);
        // Scroll le contenu si necessaire
	var pos = Number.MAX_VALUE;
	try{
            pos=jdiv.position().top;
	}catch(e){}
        if(jQuery(document).scrollTop()>pos) jQuery(document).scrollTop(pos);
      }
    }else{
      TZR.unsetOverlay(overlay);
    }
    if(cplt){
      if(cplt.cb) cplt.cb(html);
    }
  },force:cplt.force});
}
// Force la mise à jour de l'element recevant le texte de CKEditor
TZR.updateCKEditorElements=function(f){
  if(typeof CKEDITOR !== 'undefined'){ // pas de console.log si pas de CKEDITOR
  try
  {
    for (instance in CKEDITOR.instances){
      if(f && CKEDITOR.instances[instance].element.$.form!=f) continue;
      CKEDITOR.instances[instance].updateElement();
    }
  }catch(e){
    if (typeof console !== "undefined" || typeof console.log !== "undefined") {
      console.log(e);
    }
  }
}
}
// Teste si le navigateur support formData
TZR.supportFormData=function(){
  return !!window.FormData;
}
// Ajout/suppression d'un overlay sur une div
if(!TZR.defaultOverlay){
  TZR.defaultOverlay='<span class="glyphicon csico-loader csico-spin"></span>';
}
TZR.setOverlay=function(o,opts){
  if(!opts) opts=new Object();
  if(!opts.css) opts.css=new Object();
  if(!opts.content) opts.content=TZR.defaultOverlay;
  if(!opts.oclass) opts.oclass='overlay';
  // Génération du HTML de l'overlay
  var jo=jQuery(o);
  if(jo.length==0) return;
  offset=jo.offset();
  var overlay = jQuery(TZR.sprintf('<div class="%s" style="">%s</div>', opts.oclass, opts.content));
  // CSS de l'overlay
  var w=jo.outerWidth();
  var h=jo.outerHeight();
  var css=jQuery.extend({position:'absolute',top:offset.top,left:offset.left,opacity:0.3,width:w,'z-index':1051},opts.css);//z-index d'une modal = 1050
  // CSS du fond
  var css2={opacity:css.opacity,width:'100%',height:'100%',position:'absolute',top:0,left:0};
  delete css.opacity;
  // Applique les css
  overlay.css(css);
  jQuery('div:first',overlay).css(css2);
  // Aoute l'overlay et verifie la hauteur
  jQuery('body').append(overlay);
  var ch=overlay.height();
  if(h>ch){
    overlay.css({height:h,'line-height':h+'px'});
    jQuery('span.cv8_inlineblock',overlay).css('line-height',h+'px');
  }
  // Timer pour replacement automatique
  overlay.data('_timer',setInterval(function(){
    var offset=jo.offset();
    var h=jo.outerHeight();
    var css3={top:offset.top,left:offset.left,width:jo.outerWidth()};
    if(h>ch){
      css3.height=h;
      css3['line-height']=h+'px';
    }
    overlay.css(css3);
  },100));
  // Timer pour eviter les deconnexion
  if(TZR._sessid){
    overlay.data('_timersess',setInterval(function(){
      TZR.keepAlive();
    },300000));
  }
  return overlay;
}
TZR.unsetOverlay=function(o){
  var jo=jQuery(o);
  if(jo.length==0) return;
  clearInterval(jo.data('_timer'));
  if(TZR._sessid) clearInterval(jo.data('_timersess'));
  jo.remove();
}


// Enregistre un cookie
TZR.setCookie=function(name,value,expire,path,domain,secure) {
  document.cookie=name+"="+escape(value)+((expire)?"; expires="+expire.toGMTString():"")+((path)?"; path="+path:"")+((domain)?"; domain="+domain:"")+((secure)?"; secure":"");
}
// Recupere un cookie
TZR.getCookie=function(name,isfloat,isbool){
  var deb=document.cookie.indexOf(name+"=")
  if(deb>=0) {
    deb+=name.length+1;
    var fin=document.cookie.indexOf(";",deb);
    if(fin<0) fin=document.cookie.length;
    var val=unescape(document.cookie.substring(deb,fin));
    if(isbool){
      if(!val || val=="false" || val=="0") return false;
      else return true;
    }
    if(isfloat) return parseFloat(val);
    return val;
  }
  return "";
}

// Accordeon sur des fieldset (obj=>noeud contenant les fieldset à traiter, open=index à ouvrir (0 par defaut, 'all' pour tout ouvrir))
TZR.fieldsetAccordion=function(obj,open,selector){
  if(!open) open=0
  if(!selector) selector='fieldset';
  jQuery(obj).find(selector).addClass('fieldsetAccordion').each(function(i){
    var jt=jQuery(this);
    jQuery('>legend',jt).click(function(){
      var l=jQuery(this);
      var c=jQuery('>div,>table,>section',this.parentNode);
      var f=function(){
        l.parents('fieldset:first').toggleClass('fieldsetAccordionClose fieldsetAccordionOpen');
        c.slideToggle(200);
      };
      if(!c.text() && l.attr('href')){
        c.load(l.attr('href'),f);
      }else{
        f();
      }
      // ? chromium linux .... pas grave ? et a faire mieux de toute façon ?
      if (jQuery(this.parentNode).is(':visible')){
	  //TZR.resetXFileUploader(this.parentNode);
      }
    });
    if(((open!='all' && i!=open) || jt.hasClass('fieldsetAccordionClose')) && !jt.hasClass('fieldsetAccordionOpen')){
      jt.addClass('fieldsetAccordionClose');
      jQuery('>div,>table,>section',jt).hide();
    }else{
      jt.addClass('fieldsetAccordionOpen');
    }
  });
}


/* Soumet un formulaire (peut avoir des champs fichier) sans rechargement de la page */
/* Utilisation : TZR.iframeSubmitFormKernel.submit(form,{onStart:function,onComplete:function},true/false); */
TZR.iframeSubmitFormKernel={
  frame:function(c) {
    var n = 'iframe' + Math.floor(Math.random() * 99999);
    var d = document.createElement('DIV');
    d.innerHTML='<iframe src="about:blank" initialized="0" style="display:none" id="'+n+'" name="'+n+'" onload="TZR.iframeSubmitFormKernel.loaded(\''+n+'\');"></iframe>';
    document.body.appendChild(d);
    var i = document.getElementById(n);
    if (c && typeof(c.onComplete) == 'function') {
      i.onComplete = c.onComplete;
    }
    return n;
  },
  form : function(f, name) {
    f.setAttribute('target',name);
    return name;
  },
  submit : function(f, c) {
    var ret=false;
    if (c && typeof(c.onStart) == 'function') {
      ret=c.onStart();
    } else {
      ret=true;
    }
    if(!ret) return false;
    var id=this.form(f, TZR.iframeSubmitFormKernel.frame(c));
    jQuery(f).append('<input type="hidden" name="_iframeencode" value="1">');
    document.getElementById(id).initialized=1;
    if(c.force) f.submit();
    return true;
  },
  loaded : function(id) {
    var i = document.getElementById(id);
    if(i.initialized!=1) return false;
    if (i.contentDocument) {
      var d = i.contentDocument;
    } else if (i.contentWindow) {
      var d = i.contentWindow.document;
    } else {
      var d = window.frames[id].document;
    }
    if(d.location.href != "about:blank" && typeof(i.onComplete) == 'function') {
      if(jQuery('#_iframeencode',d.body).length==1){
        i.onComplete(jQuery('#_iframeencode',d.body).val());
      }else{
        i.onComplete(d.body.innerHTML);
      }
    }
    setTimeout(function(){jQuery(i).parent().remove()},500);
  }
}

// Garde en vie la session en cours
TZR.keepAlive=function(url){
  if(url) TZR.file_get_contents(url,true);
  else TZR.file_get_contents(TZR._sharescripts+'keepalive.php');
}


/* selecteur du champ user */
TZR.UserSelector = {
  activateField:function(widget) {
    var params = widget.data('params');
    widget.simpleTree({
      autoclose:false,
      drag:false,
	animate:true,
	closeroot:true,
      afterClick:function(node){
	TZR.UserSelector.afterClick.call(TZR.UserSelector, node, params);
      },
      afterAjax:function(node){
	TZR.UserSelector.afterAjax.call(TZR.UserSelector, node, params);
      }
    });
  },
  afterClick:function(node, params){
    var span=jQuery("span:first span:first", node);
    if(span.hasClass("selected")){
      jQuery("span.selected",node).removeClass("hselected selected").addClass("unselected");
      TZR.UserSelector.unSelectUser.call(TZR.UserSelector, node, params);
    }else{
      jQuery("span.unselected,span.hselected",node).removeClass("hselected unselected").addClass("selected");
      TZR.UserSelector.selectUser.call(TZR.UserSelector, node, params);
    }
    // maj des styles parents si nécessaire
    if(node.attr('x-type')=='doc'){
      var pul=node.parents("ul:first");
      var pli=node.parents("li:first");
      var spans=jQuery("span:first span:first",pli);
      if(jQuery("span.unselected",pul).length==0) spans.removeClass("unselected hselected").addClass("selected");
      else if(jQuery("span.selected",pul).length==0) spans.removeClass("selected hselected").addClass("unselected");
      else spans.removeClass("selected unselected").addClass("hselected");
    }
    if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields) {
      // à voir var container = jQuery("#selectedusers-"+params.varid);
      //TZR.Table.saveSomeLangsCheckFields(container.find('input')[0]);
    }
  },
  afterAjax:function(node, params){ 
    // applique la selection aux users si le groupe est selectionné lors du dépliage
    // ici node = le ul container
    var pli = node.parent()
    if(jQuery("span:first span:first",pli).hasClass('selected')){
      jQuery("span.unselected",pli).each(function(i, o){
	jQuery(o).trigger(TZR.click_event);
      });
    }
    var listcontainer = this.getListContainer(this.getInput(params.varid));
    // styles des éléments actuellement selectionnés
    var that = this;
    if(true /* le hselected est pas positionné sur ajout autocompletion */
      || jQuery("span:first span:first",pli).hasClass('hselected')){
      jQuery("li[x-type='doc']", node).each(function(i, o){
	var oid = jQuery(this).attr('x-value');
	var nodeli = jQuery(this);
	if (jQuery(TZR.sprintf("div[data-oid='%s']", oid), listcontainer).length>0){
	  if (typeof(params.multivalued) == "undefined" || params.multivalued != "1"){
	    nodeli.addClass("node-active");
	    jQuery("span>span", nodeli).addClass("selected").removeClass("unselected");
	    that.setHasSelected(params);
	  } else {
	    jQuery("span:first span:first", jQuery(this)).trigger(TZR.click_event);
	  }
	}
      });
    }
  },
  getInput:function(id){
    return jQuery("#_INPUT"+id+"autocomplete");
  },
  getListContainer:function(field){
    return field.siblings("ul.selectedusers");
  },
  getTree:function(id){
    return jQuery("#"+id);
  },
  getTreeviewSelectedList:function(id){
    var list = [];
    jQuery(`#${id}selected > div[data-oid]`).each(function(i,o){
      list.push(jQuery(this).text());
    });
    return {names:list};
  },
  getSelectedList:function(id){
    var container = this.getListContainer(this.getInput(id));
    var list = [];
    jQuery("div[data-oid]", container).each(function(i,o){
      list.push(jQuery(this).text());
    });
    return {names:list};
  },
  unSelectUserFromList:function(node, oid){
    var field = node.siblings("input.tzr-link.ui-autocomplete-input").first();
    var params = field.data("autocomplete").params;
    var tree = this.getTree(params.varid.replace('autocomplete',''));
    var nodeli = jQuery("li[x-value='"+oid+"']", tree);
    if (nodeli.length == 0){
      // objet sélectionné pas chargé encore dans le treeview
      jQuery("div[data-oid='"+oid+"']", this.getListContainer(field)).remove();
    } else {
      jQuery("span>span", nodeli).trigger('click');
    }
  },
  unSelectUser:function(node, params){
    var field = this.getInput(params.varid);
    var oid = node.attr("x-value");
    if (node.attr('x-type') == 'doc'){
      node.removeClass('doc-active');
      jQuery("div[data-oid='"+oid+"']", this.getListContainer(field)).remove();
      jQuery("span>span.selected", node).removeClass('selected');
    }else {
      node.removeClass('folder-active');
      jQuery("li[x-type='doc']>span>span.selected", node).each(function(i, o){
	jQuery(o).trigger(TZR.click_event);
      });
    }
  },
  // reset selection pour le cas monovalué
  resetSelected:function(params){
    var container = this.getListContainer(this.getInput(params.varid));
    var tree = this.getTree(params.varid.replace('autocomplete',''));
    var that = this;
    jQuery("div[data-oid]", container).each(function(i, o){
      var oid = jQuery(this).data('oid');
      jQuery(this).remove();
      var nodeli = jQuery(TZR.sprintf("li[x-value='%s']", oid), tree);
      nodeli.removeClass("doc-active");
      jQuery("span", nodeli).removeClass("selected").addClass("unselected");
    });
    that.setHasSelected(params);
  },
  // has selected pour un dossier
  setHasSelected:function(params){
    var tree = this.getTree(params.varid.replace('autocomplete',''));
    jQuery("li[x-type='folder']", tree).each(function(i, o){
      var node = jQuery(this);
      var hasSelected = false;
      var hasAll = true;
      node.find("ul>li>span>span").each(function(i,o){
	if (jQuery(this).hasClass('selected'))
	  hasSelected = true;
	else
	  hasAll = false;
      });
      var span = node.find('>span>span');
      if (hasSelected && hasAll)
	span.removeClass('hselected').addClass('selected');
      else if (hasSelected)
	span.removeClass('selected').addClass('hselected');
      else
	span.removeClass('selected').removeClass('hselected');
    });
  },
  // nettoyage eventuel avant ajout
  prepareUserSelection:function(params){
    if (typeof(params.multivalued) == "undefined" || params.multivalued != "1"){
      this.resetSelected(params);
    }
  },
  // user/groupe selectionné = un 'dest[]' avec son oid en valeur
  selectUser:function(node, params){
    this.prepareUserSelection(params);
    var field = this.getInput(params.varid);
    if (node.attr('x-type') == 'doc'){
      node.addClass('doc-active');
      this.addToSelectedList(field,
			     params.varid,
			     node.attr('x-value'),
			     node.find("span>span").html());
    } else if (params.multivalued){
      node.addClass('folder-active');
      var users = jQuery("ul>li[x-type='doc']", node);
      if (users.length>0){
	jQuery("ul>li[x-type='doc']", node).each(function(i, o){
	  TZR.UserSelector.selectUser.call(TZR.UserSelector, jQuery(o), params);
	});
      } else {
	// on charge et sélectionne le détail
	jQuery("img.trigger", node).trigger('click');
      }
    }
  },
  unSelectAll:function(elt){
    var params = jQuery(elt).parents('ul.userSelector').data('params');
    var container = this.getListContainer(this.getInput(params.varid));
    jQuery("div[data-oid]>button", container).trigger('click');
    this.setSelectedNumber(params.varid);
    if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields) {
      // à voir var container = jQuery("#selectedusers-"+params.varid);
      // à voir TZR.Table.saveSomeLangsCheckFields(container.find('input')[0]);
    }
    return; 
  },
  addToSelectedList:function(field, id, oid, label){
    var container = this.getListContainer(field);
    if (container.find("div[data-oid='"+oid+"']").length>0)
      return;
    var fieldname = field.data('autocomplete').params.field;
    var button=" <button onclick=\"TZR.UserSelector.unSelectUserFromList.call(TZR.UserSelector, jQuery(this).parents('ul.selectedusers'), '"+oid+"');\" class=\"btn btn-default btn-md btn-inverse\" type=\"button\"><span class=\"glyphicon csico-delete\" aria-hidden=\"true\"></span></button>";
    var input = TZR.sprintf("<input name=\"%s[]\" type=\"hidden\" value=\"%s\">", fieldname, oid);
    jQuery(container).append(TZR.sprintf("<div data-oid=\"%s\">%s %s %s</div>", oid, button, label, input));
    
  },
  // sélection depuis la boite d'autocomplétion
  autoCompleteSelection:function(id, oid, label){
    
    var field = jQuery("#_INPUT"+id);
    field.val('');
    
    var params = field.data("autocomplete").params;

    params.varid = id.replace('autocomplete','');
    this.prepareUserSelection(params);

    this.addToSelectedList(field, params.varid, oid, label);

    var tree = this.getTree(params.varid);
    var nodeli = jQuery("li[x-value='"+oid+"']", tree);
    if (nodeli.length > 0)
      jQuery("span>span", nodeli).trigger('click');
  },
  isValid:function(id, fmt, color){
    var selectedcontainer = this.getListContainer(this.getInput(id));
    var selected = [];
    jQuery("div[data-oid]", selectedcontainer).each(function(){
      selected.push(jQuery(this).data('oid'));  
    });
    selected = selected.join();
    var exp = new RegExp(fmt);
    if (!exp.test(selected)){
      TZR.setElementErrorState(jQuery(selectedcontainer).parent(),false,color);
      TZR.isFormOk = false;
      return false;
    }
    return true;
  }
};

/* Gestion des champs thesaurus */
// Active le thesaurus
TZR.activeThesaurus=function(varid, optimizeWith) {
  var tree = jQuery("#treecontainer"+varid),
      form = tree.parents('form'),
      nodeData = tree.data(),
      filter;
  if (nodeData.quickquery == 1)
    filter = ''
  else {
    filter = [];
    jQuery('input[name^="'+optimizeWith+'"]', form).each(function(i, elt){
      if (elt.value)
        filter.push(elt.value);
    });
    filter.sort();
  }
  tree.data({oldfilter: JSON.stringify(filter), cache: {}});
  form.on('change', 'input[name^="'+optimizeWith+'"]', function(e){
    jQuery("#treecontainer"+varid+">ul.treefiltered").show();
    // timeout pour remove
    setTimeout(function(){
      TZR.thesaurusRefrechFiltered(varid, false);
    }, 500);
  })
}
TZR.thesaurusClick = function(varid, event) {
  jQuery.Event(event).stopPropagation();
  var jsrc = jQuery(event.target || event.srcElement),
      li = (jsrc.is('li') ? jsrc : jsrc.parents('li:first')),
      oid = li.attr('tzroid');
  if (jsrc.is('.root')) {
    if (jsrc.is('.close') && jsrc.parent().is('.quickquery'))
      return TZR.openThesaurusFolder(jsrc, varid, '');
    jsrc.toggleClass('open').toggleClass('close').children('ul').toggle();
    if (jsrc.is('.open') && jsrc.parent().is('.treefiltered'))
      return TZR.thesaurusRefrechFiltered(varid, true);
    return false;
  }
  if (oid == undefined)
    return false;
  if (jsrc.is('.ico, .trigger') && li.is('.folder'))
    return TZR.openThesaurusFolder(li, varid, oid);
  else
    return TZR.selectThesaurusItem(li, varid, oid);
}
TZR.thesaurusRefrechFiltered = function(varid, ajax) {
  var tree = jQuery("#treecontainer"+varid),
      nodeData = tree.data(),
      treefiltered = tree.children('.treefiltered'),
      filter = [],
      optimizeWithInputs = jQuery('input[name^="'+nodeData.optimizewith+'\[\]"]');
  if (optimizeWithInputs.length) // optimizewith present
    optimizeWithInputs.each(function(i, elt){
      if (elt.value)
        filter.push(elt.value);
    });
  else //quickquery & optimizewith absent
    filter = nodeData.optimizevalues.split(' ');
  filter.sort();
  var filterString = JSON.stringify(filter);
  if (nodeData.oldfilter != undefined && filterString != nodeData.oldfilter) {
    if (!filter.length && tree.children('.full').length)
      treefiltered.hide();
    nodeData.cache[nodeData.oldfilter] = treefiltered.html();
    if (nodeData.cache[filterString] != undefined) {
      treefiltered.html(nodeData.cache[filterString]);
    tree.data({oldfilter: filterString})
      return;
    }
    if (!ajax)
      return jQuery("li.root", treefiltered).removeClass('open').addClass('close').children('ul').hide();
    var values = [];
    jQuery('#table'+nodeData.varid+' input[name^="'+nodeData.field+'"]').each(function(i, elt){
      if (elt.value)
        values.push(elt.value);
    });
    jQuery('li.root ul:first', treefiltered).addClass('ajax').html('').show();
    jQuery.ajax({
      url:nodeData.reloadUrl,
      data: {value:values, filter:filter,justfiltered:1},
      success: function(data){
        if (data) {
          if (treefiltered.length == 0)
            tree.prepend(data);
          else
            treefiltered.replaceWith(data);
        }
        tree.data({oldfilter: filterString});
        if (nodeData.quickquery)
          tree.find(">ul").addClass("treefiltered");
        tree.find(">ul.treefiltered").show()
        .children("li.root").removeClass('close').addClass('open')
        .children('ul').show();
      }
    });
  }
}
// (un)select thesaurus entry
TZR.selectThesaurusItem = function(li, varid, oid) {
  var parentDiv = li.parents('#div'+varid);
  var tree = jQuery("#treecontainer"+varid, parentDiv);
  var table = jQuery('#table'+varid.replace('filtered', ''), parentDiv);
  var span = jQuery("span:first span:first", li);
  if (span.hasClass("selected")) {
    jQuery('li[tzroid="'+oid+'"] > span > span.selected',"#div"+varid).removeClass("selected");
    jQuery('input[value="'+oid+'"]',table).trigger('change').parents('tr:first').remove();
  } else {
    if (jQuery('input[value="'+oid+'"]',table).length == 1){
      return;
    }
    if(!tree.data('multivalued')){
      TZR.thesaurusClick(varid,{target:tree.find('span.selected')});
    }
    var tds = jQuery('tr.model',table).clone().appendTo(table).removeClass('model none').find('td');
    if (tds.length==0)
      return;
    var parents = span.parentsUntil('li.root');
    var text='';
    jQuery('li[tzroid="'+oid+'"]>span>span',"#div"+varid).addClass("selected");
    jQuery('input',tds[0]).val(oid).trigger('change');
    for (var i=parents.length-1;i>=0;i--) {
      if (parents[i].nodeName == "LI") {
        text += jQuery('span:first span:first',parents[i]).html()+" > ";
      }
    }
    tds[1].innerHTML = text.substr(0,text.length-3);
  }
  if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields) {
    TZR.Table.saveSomeLangsCheckFields(table.find('input')[0]);
  }
  return false;
}

// open thesaurus folder, if empty populate with json data
TZR.openThesaurusFolder = function(li, varid, oid) {
  var subNode = li.find('>ul').toggle();
  li.toggleClass('open').toggleClass('close');
  if (!subNode.length)
    return false;
  if (!subNode.html().length) {
    var tree = jQuery("#treecontainer"+varid),
        nodeData = tree.data();
    if (li.is('.root') && nodeData.quickquery && nodeData.optimizewith)
      return TZR.thesaurusRefrechFiltered(varid, true);
    var values = [];
    jQuery('input[name^="'+nodeData.field+'"]').each(function(i, elt){
      if (elt.value)
        values.push(elt.value);
    });
    subNode.addClass('ajax').show();
    jQuery.ajax({
      url:nodeData.reloadUrl,
      data: {value:values, justfiltered:0, top:oid, quickquery:nodeData.quickquery},
      success: function(data){
        if (data) {
          subNode.removeClass('ajax').html(data);
        }
      }
    });
  }
  return false;
};
//
TZR.initThesaurusContextMenu = function(){
    jQuery(document).on("contextmenu", '.thesaurustree', function(e){
	var jTarget = jQuery(e.target),
	tree = jQuery(e.currentTarget);
	if(!tree.data('rw')) return;
	e.stopPropagation();
	TZR.thesaurusMenu(jTarget);
	TZR.thesaurusShowMenu(jTarget, e);
	return false;
    });
};
// menu contextuel
jQuery(document).on("contextmenu", '.thesaurustree', function(e){
  var jTarget = jQuery(e.target),
      tree = jQuery(e.currentTarget);
  if(!tree.data('rw')) return;

  e.stopPropagation();
  TZR.thesaurusMenu(jTarget);
  TZR.thesaurusShowMenu(jTarget, e);
  return false;
});

TZR.thesaurusShowMenu = function(node,e){
  var event = jQuery.Event("mousedown");
  event.button = 2;
  var event2 = jQuery.Event("mouseup");
  event2.button = 2;
  event2.pageX=e.pageX;
  event2.pageY=e.pageY;
  node.trigger(event);
  node.trigger(event2);
    return false;
  setTimeout(function(){
    node.trigger(event2);
  },50);
  return false;
}
TZR.thesaurusMenu = function(node) {
  if(jQuery(node).is('.hasContextMenu')) return;
  var nodeData = jQuery(node).parents('.thesaurustree').data();
  var contextMenu = jQuery(node).contextMenu({
    menu:'xthesaurusmenu',
    beforeShow:function(o) {
      var m=jQuery(node);
      if (!m.hasClass('simpleTree'))
          m = m.parents('ul.simpleTree:first');
      if(jQuery(o).parents('li:first').hasClass('root')) {
        this.disableContextMenuItems('#del,#edit,#cut');
      } else {
        this.enableContextMenuItems('#del,#edit,#cut');
      }
      if (m.data('topaste')) {
        this.enableContextMenuItems('#paste');
      } else {
        this.disableContextMenuItems('#paste');
      }
    }},
    function(action, el, pos) {
      if (action.lastIndexOf('#') !== -1)
        action = action.substr(action.lastIndexOf('#')+1);
      var jtree=el.parents('ul.simpleTree:first');
      var tree=jtree[0];
      var jli=el.parents('li:first');
      var oid=jli.attr('tzroid');
      // ajout un noeud, retourne le li
      var addNode = function() {
        if (jli.is(".folder")) {
          var tmpNode = jQuery('<li class="line">&nbsp;</li><li class="doc"><div style="position:absolute;"><div class="ico"></div></div><span class="text"><span></span></span></li><li class="line"></li>');
          tmpNode.prependTo(jQuery('>ul', jli));
          jQuery('>ul', jli).show();
          jli.removeClass('close').addClass('open');
          var li = jQuery(tmpNode[1]);
        } else {
          if (!jli.is(".root"))
            jli.addClass("folder open").removeClass('doc');
          var tmpNode = jQuery('<ul><li class="line">&nbsp;</li><li class="doc last"><div style="position:absolute;"><div class="ico"></div></div><span class="text"><span></span></span></li><li class="line-last"></li></ul>');
          tmpNode.appendTo(jli);
          var li = jQuery('li.doc', tmpNode);
        }
          return li;
      }
	var url = TZR._sharescripts+'ajax8.php';
	var data =  {'class':'Seolan_Field_Thesaurus_Thesaurus',
		     'moid':nodeData.moid,
		     'table':nodeData.xtable,
		     'field':nodeData.field,
		     'value':null,
		     'parentoid':null,
		     'oid':oid
		    };
      if (action == 'copy') {
        jtree.data('topaste',{oid:oid,text:el.text(),mode:'copy',node:jli});
      } else if (action == 'cut') {
        jtree.data('topaste',{oid:oid,text:el.text(),mode:'cut',node:jli});
        jli.addClass('tocut');
      } else if (action == "paste") {
          var param = jtree.data('topaste');
	  data.value = param.value;
	  data.parentoid = param.oid;
	  data.oid = null;
        if (param.mode =='copy') {
	    data['function'] =  'xthesaurusdef_copyvalue';
            jQuery.ajax({url:url,async:false,data:data, success: function(data){
            if (data) {
              var li = addNode();
              li.attr('tzroid', data);
              jQuery('>span>span', li).html(param.text);
            }
          }});
        } else {
	    data['function']='xthesaurusdef_cutvalue';
          jQuery.ajax({url:url,async:false,data:data,success:function(data){
            if (data) {
              var li = addNode();
              li.attr('tzroid', data);
              jQuery('>span>span', li).html(param.text);
              jQuery(param.node).remove();
            }
          }});
        }
        jtree.data('topaste','');
      } else if(action == "del") {
	  data['function'] = 'xthesaurusdef_delvalue';
          jQuery.ajax({url:url,data:data,async:false,success:function(data){
          if (data == "ok") {
            var parentLi = jli.parents('li:first');
            TZR.removeThesaurusValue(nodeData.varid,oid);
            if (jQuery('.doc, .folder', parentLi).length == 1)
              parentLi.removeClass('folder').addClass('doc');
            else if (jQuery('.doc, .folder', parentLi).length == 2) {
              jQuery('.doc, .folder', parentLi).addClass('last');
            }
            jli.prev().remove();
            jli.remove();
         }
        }});
      } else if (action == "edit") {
        var input=jQuery('<input type="text" name="value">');
        input.val(el.html());
        input.keypress(function(e){
          if (e.which==0 || e.which==27) {
            jQuery(this).parent().find('span:first').show();
            jQuery(this).remove();
          } else if (e.which==13) {
              var _this=this;
	      data.value=this.value;
	      data['function'] = 'xthesaurusdef_editvalue';
            jQuery.ajax({url:url,data:data,success:function(data){
              var span=jQuery(_this).parent().find('span:first');
              var li=span.parents('li:first');
              if(data=="ok"){
                span.html(_this.value);
                span.click();
            }
            span.show();
            jQuery(_this).remove();
            }});
              return false;
          }
        }).bind('click dblclick mousedown mouseup mousemove',function(e){e.stopPropagation();});
        el.hide().parent().append(input);
        input[0].focus();
      } else if(action=='add') {
        var input=jQuery('<input type="text" name="value">');
        input.keypress(function(e){
          if (e.which==0) {
            jQuery(this).parent().parent().remove();
          } else if(e.which==13) {
            var _this=this;
	      data['function'] = 'xthesaurusdef_addvalue';
	      data.value = this.value;
	      data.parentoid=el.parents('li:first').attr('tzroid');
            jQuery.ajax({url:url,async:false,data:data,success:function(data){
              if(data) {
                jQuery(_this).parent().html(_this.value).parents('li:first').attr('tzroid',data);
                jQuery(_this).remove();
              } else {
                jQuery(_this).parent().parent().remove();
              }
            }});
            return false;
          }
        }).bind('click dblclick mousedown mouseup mousemove',function(e){e.stopPropagation();});
        var li = addNode();
        jQuery('>span>span', li).html(input);
      }
    }).addClass('hasContextMenu');
}
// Supprime une valeur du thesaurus de la selection
TZR.removeThesaurusValue=function(varid,oid){
  if(typeof(oid)=='object'){
    var table = jQuery(oid).parents('#table'+varid);
    oid=jQuery(oid).parents('tr:first').find('input').val();
  } else
    var table=jQuery('#table'+varid);
  jQuery('input[value="'+oid+'"]',table).change().parents('tr:first').remove();
  jQuery('li[tzroid="'+oid+'"] span.selected',"#div"+varid).removeClass("selected");
  if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields) {
    TZR.Table.saveSomeLangsCheckFields(table.find('input')[0]);
  }
}
// Ajoute une valeur suite à une saisie via le champ en autocomplete
TZR.autocompleteThesaurus=function(varid,oid,v){
  if(oid && v){
    var tree = jQuery("#treecontainer"+varid);
    if(!tree.data('multivalued')){
      TZR.thesaurusClick(varid,{target:tree.find('span.selected')});
    }
    var table=jQuery('#table'+varid);
    if(table.find('input[value="'+oid+'"]').length>0) return;
    var tds=jQuery('tr:first',table).clone().appendTo(table).show().find('td');
    if(tds.length==0) return;
    jQuery('input',tds[0]).val(oid).trigger('change');
    tds[1].innerHTML=v;
    jQuery('li[tzroid="'+oid+'"]>span>span',"#div"+varid).addClass("selected");
    jQuery('#_INPUT'+varid).val('');
  }
}

// Fonctions d'ajout de ligne, de colonne et d'application de tablesorter à un champ \Seolan\Field\Table\Table
TZR.XTableAddLine=function(tableid,fname){
  var jt=jQuery('#'+tableid);
  var tr=jt[0].tBodies[0].rows[jt[0].tBodies[0].rows.length-1];
  var jnewtr=jQuery(tr).clone(true);
  var reg=new RegExp(fname+"\\[(\\d+)\\]","g");
  var reg2=new RegExp("\\[_rlabels\\]\\[(\\d+)\\]","g");
  var regres=reg.exec(tr.innerHTML);
  var newnum=parseInt(regres[1])+1;
  jnewtr.html(tr.innerHTML.replace(reg,fname+"["+newnum+"]").replace(reg2,"[_rlabels]["+newnum+"]").replace('>'+newnum+'</td>','>'+(newnum+1)+'</td>'));
  jnewtr.insertAfter(tr).find('input').keyup(function(){jQuery("#"+tableid).trigger("update");}).bind('click mousedown',function(e){e.stopPropagation();}).val('');
  jt.trigger("update");
}
TZR.XTableAddColumn=function(tableid,fname){
  var jt=jQuery('#'+tableid);
  var trs=jt.find('tr')
  trs.each(function(i){
    if(this.cells.length>1){
      var reg=/(\[[^\]]+\])\[(\d+)\]/g;
      var td=this.cells[this.cells.length-2];
      var jnewtd=jQuery(td).clone(true);
      var regres=reg.exec(td.innerHTML);
      var newnum=parseInt(regres[2])+1;
      jnewtd.html(td.innerHTML.replace(reg,"$1["+newnum+"]"));
      jnewtd.insertAfter(td).find('input').keyup(function(){jQuery("#"+tableid).trigger("update");}).bind('click mousedown',function(e){e.stopPropagation();}).val('');;
    }
  });
  jt.trigger("update");
}
TZR.XTableSorter=function(tableid){
  jQuery('#'+tableid).find('input').keyup(function(){jQuery("#"+tableid).trigger("update");}).bind('click mousedown',function(e){e.stopPropagation();});
  jQuery('#'+tableid).tablesorter({textExtraction:function(node){
    var i=jQuery('input',node);
    if(i.length>0) return i[0].value;
    else return node.innerHTML;
  }});
}

/* Champ image */
TZR.Image=new Object();

/* Agenda */
TZR.Calendar=new Object();
// Ajoute/deplace/supprime un evenement/note dans le tableau des evenements/notes selon l'heure de debut/fin
TZR.Calendar.orderEvent=function(uniqid,obj,tomove,del){
  var found=false;
  var notes=obj.notes;
  var evs=obj.evs;
  var added=false;
  if(!tomove.allday || tomove.allday=='0'){
    for(var i in evs){
      var e=evs[i];
      if(e.id==tomove.id){
        evs.splice(i,1);
        break;
      }
    }
    if(del==undefined){
      for(var i in evs){
        var e=evs[i];
        if(e._isod>tomove._isod || (e._isod==tomove._isod && (e._bh>tomove._bh || (e._bh==tomove._bh && e._eh>=tomove._eh) || e._bd>tomove._bd))){
          evs.splice(i,0,tomove);
          added=true;
          break;
        }
      }
      if(!added) evs.push(tomove);
    }
  }else{
    if(del){
      for(var i in notes){
        var n=notes[i];
        if(n.id==tomove.id){
          notes.splice(i,1);
          break;
        }
      }
    }
  }
},
// Caclule la position et la taille des evenements (les evenements doivent etre trié par heure de debut)
TZR.Calendar.calculatePosition=function(uniqid,obj){
  var dates=obj.dates;
  var evs=obj.evs;
  var max=obj.max;
  for(var num in dates){
    var date=dates[num];
    max[date]=0;
  }
  var rows=new Object();
  var cols=new Object();
  var groupinfos={max:1,rows:{},cols:{},end:-1,hour:0,date:"1000-01-01"};
  for(var i in evs){
    var e=evs[i];
    var ok=false;
    var l=TZR.Calendar.getEventLimit(e);
    var ee=l.e;
    var eb=l.b;
    var nbcase=l.d;
    var c=0;
    if(groupinfos.hour<=eb || e._isod!=groupinfos.date){
      groupinfos={max:1,rows:{},cols:{},hour:ee,date:e._isod};
      var newgroup=true;
    }else{
      var newgroup=false;
    }
    while(!ok && groupinfos.rows!=undefined && groupinfos.rows[eb]!=undefined && groupinfos.rows[eb][c]!=undefined){
      ok=true;
      for(var j=0;j<nbcase;j++){
        if(groupinfos.rows[eb+j]!=undefined && groupinfos.rows[eb+j][c]!=undefined){
          c++;
          ok=false;
          break;
        }
      }
    }
    if(!newgroup){
      if(c+1>groupinfos.max) groupinfos.max=c+1;
      if(ee>groupinfos.hour) groupinfos.hour=ee;
    }
    e.groupinfos=groupinfos;
    e.col=c;
    e.row=eb;
    e.h=nbcase;
    e.w=1;
    for(j=0;j<nbcase;j++){
      if(groupinfos.rows[eb+j]==undefined) groupinfos.rows[eb+j]=new Object();
      if(groupinfos.cols[c]==undefined) groupinfos.cols[c]=new Object();
      groupinfos.rows[eb+j][c]=e;
      groupinfos.cols[c][eb+j]=e;
    }
  }
  for(var i in evs){
    e=evs[i];
    var stop=false;
    var totest=new Array();
    for(j=0;j<e.h;j++){
      totest.push(e.row+j);
    }
    for(j=e.col+1;j<e.groupinfos.max;j++){
      for(k in totest){
        k=totest[k];
        if(e.groupinfos.cols[j]!=undefined && e.groupinfos.cols[j][k]!=undefined){
          stop=true;
          break;
        }
      }
      if(stop) break;
      e.w++;
    }
  }
}
// Lance la mise à jour de l'agenda
TZR.Calendar.drawAgenda=function(uniqid,obj){
  for(var e in obj.notes){
    e=obj.notes[e];
    TZR.Calendar.createNoteDiv(uniqid,obj,e);
  }
  var nl=0;
  for(var i in obj.dates){
    var l=jQuery('#agad'+obj.dates[i]+'-'+uniqid+'>div.note').length+1;
    if(l>nl){
      document.getElementById('agallday'+uniqid).style.height=(l*obj.noteHeight)+"px";
      nl=l;
    }
  }
  for(var e in obj.evs){
    e=obj.evs[e];
    TZR.Calendar.createEventDiv(uniqid,obj,e);
  }
}

// Créé/met à jour la div d'une note
TZR.Calendar.createNoteDiv=function(uniqid,obj,e){
  e.id=e.id.replace(':','_');
  var ag=document.getElementById('agdaysallday-'+uniqid);
  var jdiv=jQuery('#'+e.id+"-"+uniqid);
  var div=jdiv[0];
  if(div==undefined){
    div=document.createElement("div");
    div.innerHTML='<div class="event-w"></div><div class="event-e"'+(e.cat?' style="background-color:'+e.cat+';"':'')+'></div><div class="event-s"></div><div class="event-n"></div><div class="event-content"></div>';
    div.style.backgroundColor=e.color;
    div.id=e.id+"-"+uniqid;
    div.style.height=obj.noteHeight+"px";
    div.style.position="relative";
    div.className="note";
    document.getElementById('agad'+e._isod+"-"+uniqid).appendChild(div);
    jdiv=jQuery(div);
    div.tzrevent=e;
    // Setter : met à jour le texte html de l'evenement (heure + titre)
    div.setEventText=function(pos){
	jQuery('.event-content',this).html(TZR.Calendar.getEventText(uniqid,obj,this.tzrevent,pos));
    }
    // Active deplacement
    if(e.rw && e._obd==e._bd && e._oed==e._ed){
      jdiv.draggable({grid:[1,999],containment:'parent',axis:"y",start:function(event,ui){
        TZR.Calendar.startDrag(uniqid,obj,event);
        obj.actEvent="drag";
        obj.actTarget=event.target;
        jQuery('div.agadday',ag).mouseenter(function(event){
          jdiv.mousemove(function(){return false;})
          jQuery(obj.actTarget).appendTo(this);
          obj.actTarget.tzrevent._isod=this.id.substr(4,10);
          setTimeout(function(){jdiv.unbind('mousemove');},100);
        });
      },stop:function(event,ui){
        TZR.Calendar.stopDrag(uniqid,obj,event);
        jQuery('div.agadday',ag).unbind('mouseenter');
      }});
    }
    // Tooltip
    TZR.Calendar.applyHottip(uniqid,obj,jdiv);
    jdiv.mousedown(function(event){
      // Stoppe la propagation de l'evenement (sinon ie active le selected quand on veut le deplacer)
      event.stopPropagation();
    });
  }
  div.setEventText();
}

// Créé/met à jour la div d'un evenement
TZR.Calendar.createEventDiv=function(uniqid,obj,e){
  e.id=e.id.replace(':','_');
  var ag=document.getElementById('agdays-'+uniqid);
  var jdiv=jQuery('#'+e.id+"-"+uniqid);
  var div=jdiv[0];
  if(div==undefined){
    div=document.createElement("div");
    div.innerHTML='<div class="event-w"></div><div class="event-e"'+(e.cat?' style="background-color:'+e.cat+';"':'')+'></div><div class="event-s"></div><div class="event-n"></div><div class="event-content"></div>';
    div.style.backgroundColor=e.color;
    div.id=e.id+"-"+uniqid;
    div.className="event";
    document.getElementById('ag'+e._isod+"-"+uniqid).appendChild(div);
    jdiv=jQuery(div);
    div.tzrevent=e;
    // Setter : met à jour le texte html de l'evenement (heure + titre)
    div.setEventText=function(pos){
      jQuery('.event-content',this).html(TZR.Calendar.getEventText(uniqid,obj,this.tzrevent,pos));
    }
    // Active deplacement
    if(e.rw && e._obd==e._bd && e._oed==e._ed){
      jdiv.draggable({grid:[1,obj.lineHeight],containment:'parent',axis:"y",drag:function(event,ui){
        event.target.setEventText(TZR.Calendar.positionToHour(uniqid,obj,event.target));
      },start:function(event,ui){
        TZR.Calendar.startDrag(uniqid,obj,event);
        obj.actEvent="drag";
        obj.actTarget=event.target;
        event.target.style.width="100%";
        event.target.style.left="0px";
        jQuery('div.agday',ag).mouseenter(function(event){
          jdiv.mousemove(function(){return false;});
          jQuery(obj.actTarget).appendTo(this);
          obj.actTarget.tzrevent._isod=this.id.substr(2,10);
          setTimeout(function(){jdiv.unbind('mousemove');},100);
        });
      },stop:function(event,ui){
        TZR.Calendar.stopDrag(uniqid,obj,event);
        jQuery('div.agday',ag).unbind('mouseenter');
      }});
    }

    // Active redimmenssionement
    if(e.rw && (e._obd==e._bd || e._oed==e._ed)){
      if(e._obd==e._bd && e._oed==e._ed) var handles='s,n';
      else if(e._obd==e._bd) var handles='n';
      else if(e._oed==e._ed) var handles='s';
      jdiv.resizable({grid:obj.lineHeight,containment:document.getElementById('ag'+e._isod+"-"+uniqid),handles:handles,start:function(event,ui){
        obj.actEvent="resize";
        TZR.Calendar.startDrag(uniqid,obj,event);
      },stop:function(event,ui){
        TZR.Calendar.stopDrag(uniqid,obj,event);
      },resize:function(event,ui){
        if(ui.size.height!=ui.originalSize.height){
          var pos="";
          if(obj.actEvent=="" || obj.actEvent=="resize"){
            if(ui.position.top!=ui.originalPosition.top) obj.actEvent="resizen";
            else obj.actEvent="resizes";
          }
          if(obj.actEvent=="resizen") pos=TZR.Calendar.positionToHour(uniqid,obj,event.target,"begin");
          else pos=TZR.Calendar.positionToHour(uniqid,obj,event.target,"end");
          event.target.setEventText(pos);
        }
      }});
    }
    // Tooltip
    TZR.Calendar.applyHottip(uniqid,obj,jdiv);
    jdiv.click(function(event){
      var _now=new Date().getTime();
      if(this.lastclick && _now-this.lastclick<300){
        obj.vuniqid.jQueryLoad(e.url+"&display="+obj.display);
        return;
      }
      this.lastclick=_now;
    }).mousedown(function(event){
      // Stoppe la propagation de l'evenement (sinon ie active le selected quand on veut le deplacer) et supprime un eventuel hottip
      event.stopPropagation();
    });
  }
  // Position
  var l=Math.floor(100/e.groupinfos.max)*e.col;
  if(e.col+e.w==e.groupinfos.max) var w=100-Math.floor(100/e.groupinfos.max)*e.col;
  else w=Math.floor(100/e.groupinfos.max)*e.w;
  div.style.position="absolute";
  div.style.left=l+"%";
  div.style.top=(e.row*obj.lineHeight-obj.agStart*obj.lineHeight*4)+"px";
  div.style.width=w+'%';
  div.style.height=(e.h*obj.lineHeight)+'px';
  div.setEventText();
}

// Initialise un drag/resize : ecouteur touche pour annuler via ECHAP
TZR.Calendar.startDrag=function(uniqid,obj,e){
  jQuery(jQuery.bt.vars.closeWhenOpenStack).btOff();
  var o=e.target;
  var jo=jQuery(o);
  var pos=jo.position();
  jQuery(document).bind('keydown',{parent:o.parentNode,target:o,top:jo.css('top'),left:jo.css('left'),width:jo.css('width'),height:jo.css('height'),obj:obj},TZR.Calendar.cancelDrag);
}

// Termine un drag/resize : efface ecouteur touche pour annuler via ECHAP, sauvegarde des nouvelles heures/actualise agenda
TZR.Calendar.stopDrag=function(uniqid,obj,e){
  var o=e.target;
  jQuery(document).unbind('keydown',TZR.Calendar.cancelDrag);
  if(obj.actEvent!=""){
    if(!o.tzrevent.allday || o.tzrevent.allday=='0'){
      var hours=TZR.Calendar.positionToHour(uniqid,obj,o);
      if(obj.actEvent!="resizes") o.tzrevent._bh=hours.b;
      if(obj.actEvent!="resizen") o.tzrevent._eh=hours.e;
    }
    TZR.Calendar.ajaxSaveEvent(uniqid,obj,o.tzrevent,true);
  }
  obj.actEvent="";
}

// Annule un drag/resize
TZR.Calendar.cancelDrag=function(event){
  if(event.keyCode==27){
    event.data.obj.actEvent="";
    // La div a changée de colonne, on la remet
    if(event.data.target.parentNode!=event.data.parent){
      jQuery(event.data.target).appendTo(event.data.parent);
      event.data.target.tzrevent._isod=event.data.parent.id.substr(2,10);
    }
    // Retablissement position dans la colonne
    jQuery(event.data.target).css({top:event.data.top,left:event.data.left,width:event.data.width,height:event.data.height});
    jQuery(event.data.target).mouseup();
    event.data.target.setEventText();
  }
}

// Annule une création
TZR.Calendar.cancelNew=function(event){
  if(event.keyCode==27){
    event.data.obj.actEvent="";
    jQuery(event.data.target).mouseup();
  }
}

// Rafrachi l'agenda
TZR.Calendar.refreshAgenda=function(uniqid,obj,event,del){
  if(del) jQuery(jQuery.bt.vars.closeWhenOpenStack).btOff();
  if(event) TZR.Calendar.orderEvent(uniqid,obj,event,del);
  TZR.Calendar.calculatePosition(uniqid,obj);
  TZR.Calendar.drawAgenda(uniqid,obj);
  if(!del) jQuery(jQuery.bt.vars.closeWhenOpenStack).btOn();
}

// Sauvegarde un evenement. Envoi des données au serveur et le serveur renvoie les données enregistrées que l'on met dans event afin que le traitement js porte sur les vrai données
TZR.Calendar.ajaxSaveEvent=function(uniqid,obj,event,refresh,cb){
  var reg=/<.?br.?.?>/;
  if(reg.test(event.descr))
    event.descr=event.descr.replace(/\n/g,"").replace(/<.?br.?.?>/g,"\n");

  var param={oid:obj.oid,koid:event.oid,text:event.text,descr:event.descr,place:event.place,allday:event.allday};
  if(!event.oid || event._obd==event._bd){
    param["begin[date]"]=event._isod;
    param["begin[hour]"]=event._bh;
  }
  if(!event.oid || event._oed==event._ed){
    param["end[date]"]=event._isod;
    param["end[hour]"]=event._eh;
  }
  param['skip']=1;
  jQuery.getJSON(TZR._self+"&moid="+obj.moid+"&function=ajaxEdit",param,function(data,status){
    if(data){
      jQuery.extend(event,data);
      if(refresh) TZR.Calendar.refreshAgenda(uniqid,obj,event);
      if(cb) cb.call(TZR.Calendar,uniqid,obj,event);
    }else{
      alert('Error');
    }
  });
  return true;
}

// Créer un evenement
TZR.Calendar.createEvent=function(uniqid,obj,event){
  if(event.text==undefined) event.text="";
  if(event.id==undefined)  event.id="ev-"+obj.evs.length+"-"+event._isod;
  if(event.descr==undefined) event.descr='';
  if(event.place==undefined) event.place='';
  var l=TZR.Calendar.getEventLimit(event);
  event.col=0;
  event.w=1;
  event.groupinfos={max:1};
  event.row=l.b;
  event.h=l.d;
  event.rw=1;
  event.color=obj.color;
  event.dname=obj.name;
  event.create=1;
  TZR.Calendar.createEventDiv(uniqid,obj,event);
  var jdiv=jQuery('#'+event.id+"-"+uniqid);
  setTimeout(function(){
    jdiv.click();
    jQuery('#bt-edittext').triggerHandler('click');
  },100);
}

// Supprime un evenement sur le serveur
TZR.Calendar.ajaxDelEvent=function(uniqid,obj,event){
  if(event.oid==undefined) return;
  jQuery.getJSON(TZR._self+"&moid="+obj.moid+"&function=ajaxDel&oid="+obj.oid+"&koid="+event.oid+"&noalert=1",function(data,status){
    if(data!="ok"){
      alert('Error');
    }else{
      TZR.Calendar.delEvent(uniqid,obj,event);
    }
  });
  return true;
}

// Supprime une div evenement
TZR.Calendar.delEvent=function(uniqid,obj,event){
  jQuery(jQuery.bt.vars.closeWhenOpenStack).btOff();
  jQuery('#'+event.id+'-'+uniqid).remove();
  if(obj.display!="displayMonth") TZR.Calendar.refreshAgenda(uniqid,obj,event,true);
  TZR.Dialog.closeDialog();
}

// Ecouteur clavier pour un agenda
TZR.Calendar.keyListener=function(event){
  var uniqid=event.data.uniqid;
  var monthly = event.data.monthly;
  var obj=event.data.obj;
  if (!monthly){
    var ags=[document.getElementById('agenda'+uniqid),
             document.getElementById('agglobal'+uniqid),
             document.getElementById('agallday'+uniqid)
    ];
    for(var ag=0; ag<ags.length; ag++) {
      if(!ags[ag]){
	jQuery(document).unbind('keydown',TZR.Calendar.keyListener);
	return;
      }
    }
  } else {
    var ags=[document.getElementById('agenda'+uniqid)];
  }
  if(event.keyCode==46 && obj.actEvent=="edit"){
    jo=jQuery('div.event-selected',ags[ag]);
    if(jo.length==1 && confirm('Supprimer?')){
      TZR.Calendar.ajaxDelEvent(uniqid,obj,jo[0].tzrevent);
    }
  }else if(event.keyCode==27 && obj.actEvent=="edit"){
    jQuery('#bt-cancel').click();
  }

}

// Retourne les limites et le durée d'un evenemtn au format decimal
TZR.Calendar.getEventLimit=function(e){
  var foo=e._bh.split(':');
  var bdec=parseFloat(foo[0])+parseFloat(foo[1])/60;
  var brounddec=Math.floor(bdec*4)/4;
  foo=e._eh.split(':');
  var edec=parseFloat(foo[0])+parseFloat(foo[1])/60;
  var erounddec=Math.ceil(edec*4)/4;
  var eb=brounddec*4;
  var ee=erounddec*4;
  return {b:eb,e:ee,d:ee-eb};
}

// Recupere l'heure a afficher d'un evenement (en prenant en compte une eventuelle position calculée par positionToHour)
TZR.Calendar.getEventText=function(uniqid,obj,event,pos){
  var ret="",b,e;
  if(!event.allday || event.allday=="0"){
    if(typeof(pos)=='object'){
      b=pos.b;
      e=pos.e;
    }else{
      b=event._bh;
      e=event._eh;
    }
    if(event._obd!=event._bd && event._oed!=event._ed) ret=".. > ..";
    else if(event._obd!=event._bd) ret=".. > "+e;
    else if(event._oed!=event._ed) ret=b+" > ..";
    else ret=b+" > "+e;
    ret='<span class="event-hour">'+ret+'</span> <span class="event-title">'+event.text+'</span>';
  }else{
    ret='<span class="event-title">'+event.text+'</span>';
  }
  return ret;
}

// Initialise la tooltip d'une div evenement
TZR.Calendar.applyHottip=function(uniqid,obj,jo,cb){
  jo.on("click", function(e) {
    var self = this;
    if(self.tzrevent.rw){
	jQuery('.displayQuickInput').each(function(){jQuery(this).hide();});
	jQuery('.editQuickInput').each(function(){jQuery(this).show();});
    } else {
	jQuery('.displayQuickInput').each(function(){jQuery(this).show();});
	jQuery('.editQuickInput').each(function(){jQuery(this).hide();});
    }
    self.waitForDbClick = self.waitForDbClick ? false : true;
    if (jQuery(self).hasClass('event-selected') ) {
      TZR.Calendar.unselect(self);
      setTimeout(
	function() {
	  if (!self.waitForDbClick)
	    return;
	  self.waitForDbClick = false;
	} , 400);
    } else {
      jQuery(self).addClass('event-selected');
      setTimeout(
	function() {
	  if (!self.waitForDbClick)
	    return;
	  self.waitForDbClick = false;
	  var content = TZR.Calendar.getHottipContent(self);
	  TZR.Dialog.show(content, {sizeClass:'modal-calendar', initCallback:null, closeCallback:{_function:'TZR.Calendar.unselect', _param:{uniqid:uniqid,obj:obj,target:jo[0]}}});
	  // Rend titre/descr/lieu de l'helper editable inline
	  if(self.tzrevent.rw){
            TZR.Calendar.applyEditable(uniqid,obj,self,cb);
	  }else{
	    jQuery('#bt-modify, #bt-del, #bt-cancel, #bt-save, #bt-edit').hide();
	  }
	},
	400);
    }
  });
},

TZR.Calendar.unselect=function(parms) {
  parms = parms[0]; 
  jQuery(parms.target).removeClass('event-selected');
  var event = parms.target.tzrevent;
  if (!event || typeof(event.oid) == "undefined"){
    jQuery(parms.target).remove();
  }

}
// Configure les contrôles de saisie d'un event (edition,consultation)
TZR.Calendar.applyEditable=function(uniqid,obj,div,cb){
  var focus = false;
  var myFunction = function() {
    if(this.innerHTML=="") this.innerHTML="&nbsp;";
    obj.actEvent="edit";
    this.oldvalue=this.innerHTML;
    if(this.innerHTML==" " || this.innerHTML=="&nbsp;") this.innerHTML="";
    var event = div.tzrevent;
    jQuery(this).find('input,select,textarea').attr('name',this.id.substr(7));
    if(this.id=="bt-edittext"){
      jQuery(this).find('input,select,textarea').val(event.text);
    } else if(this.id=="bt-editplace"){
      // stringset versus texte court
      var inputs = jQuery(this).find('input[type=radio],input[type=checkbox]');
      if (inputs.length != 0){
	for (var i=0;i<inputs.length;i++){
	  if (inputs[i].value == event.place){
	    inputs[i].checked = true;
	  }
	}
      } else {
	inputs = jQuery(this).find('option');
	if (inputs.length != 0){
	  for (i=0;i<inputs.length;i++){
	    if (inputs[i].value == event.place){
	      inputs[i].selected = true;
	    }
	  }
	} else {
	  inputs = jQuery(this).find('input[type=text]');
	  if (inputs.length != 0){
	    inputs.val(event.place);
	  }
	}
      }
    }else{
	jQuery(this).find('input,select,textarea').val(event.descr.replace(/<.?br.?.?>/g,""));
    }
    if (!focus){
      jQuery('input,textarea','#'+this.id).first().focus();
      focus = true;
    }
    return true;
  }; 
  // activer/désactiver les contrôles (champs, boutons)
  // #bt-edittext,#bt-editplace,#bt-editdescr
  // #bt-modify, #bt-del, #bt-view, #bt-cancel, #bt-save, #bt-edit
  if(div.tzrevent.create && typeof(div.tzrevent.oid) == "undefined"){
    jQuery('#bt-edittext,#bt-editplace,#bt-editdescr').each(myFunction);
    jQuery('#bt-modify, #bt-del, #bt-view, #bt-cancel, #bt-edit').hide();
    jQuery('#bt-save').show();
  } else if(div.tzrevent.rw) {
    jQuery('#bt-edittext,#bt-editplace,#bt-editdescr').each(myFunction);
    jQuery('#bt-modify, #bt-view, #bt-cancel').hide();
    jQuery('#bt-del,#bt-save,#bt-edit').show();
  } else {
    jQuery('#bt-modify, #bt-del, #bt-view, #bt-cancel, #bt-save, #bt-edit').hide();
  }
  jQuery('#bt-save').click(function(){
    jQuery('#bt-edittext,#bt-editplace,#bt-editdescr').each(function(){
      if(typeof(this.oldvalue)!="undefined"){
	if (this.id == 'bt-editplace'){
	  inputs = $(this).find('input[type=radio],input[type=checkbox]');
	  if (inputs.length != 0){
	    for (i=0;i<inputs.length;i++){
	      if (inputs[i].checked){
                val = inputs[i].value;
	        break;
              }
	    }
	  } else {
	    inputs = $(this).find('option');
	    for (i=0;i<inputs.length;i++){
	      if (inputs[i].selected){
                val = inputs[i].value;
	        break;
              }
	    }
	  }
	} else {
	  val = jQuery('input,select,textarea',this).val();
	}
        div.tzrevent[this.id.substr(7)]=val;
        this.innerHTML=this.oldvalue;
      }
    });
    obj.actEvent="";
    TZR.Calendar.ajaxSaveEvent(uniqid,obj,div.tzrevent,(obj.display!="displayMonth"),cb);
  });
  return ;
}

// Retourne le contenu de la tooltip d'un evenement
TZR.Calendar.getHottipContent=function(div){
  var event = div.tzrevent, d="";
  var txt = jQuery(div).parents('div.cv8-module-container:first').find('div.cv8-agendabtcontent:first').html();
  if(event.allday && event.allday!="0"){
    if(event._obd==event._oed) d=event._obd;
    else d=event._obd+" - "+event._oed;
  }else{
    if(event._obd==event._oed) d=event._obd+" "+event._bh+" - "+event._eh;
    else d=event._obd+" "+event._bh+" - "+event._oed+" "+event._eh;
  }
  event.descr = event.descr.replace(/<.?br.?.?>/g,"");
  txt=txt.replace('_hour_',event.dname+" : "+d);
  txt=txt.replace('_title_',event.text).replace('_place_',event.placehtml).replace(/_descr_/g,event.descr).replace(/_url_/g,event.url).replace(/_bt/g,"bt");
  if(!event.oid){ 
    var tmpdiv=jQuery('<div>'+txt+'</div>');
    tmpdiv.find('#bt-view,#bt-edit,#bt-del').remove();
    tmpdiv.find('#bt-cancel').show();
    txt=tmpdiv.html();
    delete tmpdiv;
  }
  return txt;
}

// Recupere la decimal d'un nombre
TZR.Calendar.decimal=function(num){
  return num-Math.floor(num);
}
// Trasnforme un flaot en heur HH:mm
TZR.Calendar.floatToHour=function(num){
  var h=Math.floor(num);
  if(h<10) h="0"+h;
  var m=TZR.Calendar.decimal(num)*60;
  if(m<10) m="0"+m;
  return h+":"+m;
}
// Transforme une position en heure HH:mm
TZR.Calendar.positionToHour=function(uniqid,obj,o,what){
  var jo=jQuery(o);
  var t=parseInt(jo.css('top'));
  var h=parseInt(jo.css('height'));
  var dec=obj.agStart+(t/obj.lineHeight/4);
  if(what=='end') var b=o.tzrevent._bh;
  else var b=TZR.Calendar.floatToHour(dec);
  dec=dec+(h/obj.lineHeight/4);
  if(what=='begin') var e=o.tzrevent._eh;
  else var e=TZR.Calendar.floatToHour(dec);
  return {b:b,e:e};
}

// Création de la grille d'un agenda
TZR.Calendar.makeGrid=function(uniqid,obj){
  var agglob=document.getElementById('agglobal'+uniqid);
  var aghead=document.getElementById('agdayshead'+uniqid);
  var agdays=document.getElementById('agdays'+uniqid);
  var aghour=document.getElementById('aghour'+uniqid);
  var agdaysallday=document.getElementById('agdaysallday'+uniqid);
  var div,div2,h,ht,date,i,num,date,l,w,agw,tmp;
  // Heures
  if(obj.dates.length==1){
    tmp=document.getElementById('aghourallday'+uniqid);
    date=obj.dates[0];
    tmp.innerHTML='<a class="cv8-ajaxlink" href="'+TZR._self+'oid='+obj.oid+'&function=addEvt&moid='+obj.moid+'&tplentry=br&template=Module/Calendar.addEvt.html&day='+date.substr(8,2)+'&month='+date.substr(5,2)+'&year='+date.substr(0,4)+'&display=displayDay&allday=1">'+tmp.innerHTML+'</a>';
  }
  h=((obj.agEnd-obj.agStart)*4)*obj.lineHeight;
  agglob.style.height=(h+10)+"px";
  agdays.style.height=h+"px";
  for(i=obj.agStart;i<obj.agEnd;i=i+0.25){
    if(Math.floor(i)==i){
      ht=TZR.Calendar.floatToHour(i);
      div=document.createElement("div");
      if(Math.floor(i+1)%2) div.className="bgodd";
      else div.className="bgeven";
      if(obj.dates.length==1){
        date=obj.dates[0];
        div.innerHTML='<a class="cv8-ajaxlink" href="'+TZR._self+'oid='+obj.oid+'&function=addEvt&moid='+obj.moid+'&tplentry=br&template=Module/Calendar.addEvt.html&day='+date.substr(8,2)+'&month='+date.substr(5,2)+'&year='+date.substr(0,4)+'&display=displayDay&hour='+ht+'">'+ht+'</a>';
      }else{
        div.innerHTML=ht;
      }
      aghour.appendChild(div);
      // Fonds
      div=document.createElement("div");
      div.style.position="absolute";
      div.style.left="0px";
      div.style.top=(i*4*obj.lineHeight-obj.agStart*4*obj.lineHeight)+"px";
      if(Math.floor(i+1)%2) div.className="bgodd";
      else div.className="bgeven";
      agdays.appendChild(div);
    }
  }
  // Colonnes dates
  agw=Math.floor(100/obj.dates.length);
  for(num in obj.dates){
    num=parseInt(num);
    date=obj.dates[num];
    if(num==obj.dates.length-1) w=(100-agw*num)+"%";
    else w=agw+"%";
    l=(num*agw)+"%";
    // Div d'entete
    div=document.createElement("div");
    div.className="aghead";
    div.style.left=l;
    div.style.width=w;
    if(obj.dates.length>1){
      div.innerHTML='<a class="cv8-ajaxlink" href="'+TZR._self+'oid='+obj.oid+'&function=addEvt&moid='+obj.moid+'&function=displayDay&tplentry=br&template=Module/Calendar.displayDay.html&day='+date.substr(8,2)+'&month='+date.substr(5,2)+'&year='+date.substr(0,4)+'">'+jQuery.datepicker.formatDate("D dd/mm",jQuery.datepicker.parseDate('yy-mm-dd',date))+'</a>';
    }else{
      div.innerHTML=jQuery.datepicker.formatDate("DD dd MM yy",jQuery.datepicker.parseDate('yy-mm-dd',date));
    }
    aghead.appendChild(div);
    // Div des notes
    div=document.createElement("div");
    if(num+1==obj.dates.length) div.className="agadday agaddaylast";
    else div.className="agadday";
    div.id="agad"+date+"-"+uniqid;
    div.style.left=l;
    div.style.width=w;
    agdaysallday.appendChild(div);
    // Div des evenements
    div=document.createElement("div");
    if(num+1==obj.dates.length) div.className="agday agdaylast";
    else div.className="agday";
    div.id="ag"+date+"-"+uniqid;
    div.style.left=l;
    div.style.width=w;
    agdays.appendChild(div);

    // Création de la grille (div selectionnable pour creation)
    for(i=obj.agStart;i<obj.agEnd;i=i+0.25){
      div2=document.createElement("div");
      div2.className="aggrid";
      div2.id=date+"-"+TZR.Calendar.floatToHour(i)+"-"+uniqid;
      div2.setAttribute("x-dechour",i);
      div2.setAttribute("x-date",date);
      div.appendChild(div2);
    }

    // Active la selection pour creation à la volée
    if(obj.rw){
      jQuery(div).selectable({autoRefresh:true,filter:"div.aggrid",distance:2,start:function(event){
        obj.actEvent="new";
        obj.newStart=undefined;
        obj.newEnd=undefined;
      },stop:function(event,ui){
        jQuery(document).unbind('keydown',TZR.Calendar.cancelNew);
        if(obj.actEvent!=""){
          obj.newEnd+=0.25;
          TZR.Calendar.createEvent(uniqid,obj,{_bh:TZR.Calendar.floatToHour(obj.newStart),_eh:TZR.Calendar.floatToHour(obj.newEnd),_bd:obj.newDate,_ed:obj.newDate,_obd:obj.newDate,_oed:obj.newDate,_isod:obj.newDate});
        }
        obj.actEvent="";
      },selected:function(event,ui){
        var t=ui.selected;
        var jt=jQuery(t);
        var dechour=parseFloat(jt.attr("x-dechour"));
        if(obj.newStart==undefined) obj.newStart=dechour;
        if(obj.newEnd==undefined || obj.newEnd<dechour) obj.newEnd=dechour;
        obj.newDate=jt.attr("x-date");
      },selecting:function(event,ui){
        jQuery(document).unbind('keydown',TZR.Calendar.cancelNew);
        jQuery(document).bind('keydown',{target:event.target,obj:obj},TZR.Calendar.cancelNew);
        jQuery('.ui-selectable-helper').css({"border":"0px solid black","background-color":"transparent"});
      }
      });
    }
  }

  // Ecouteur pour supression
  if(obj.rw){
    jQuery(document).unbind('keydown',TZR.Calendar.keyListener);
    jQuery(document).bind('keydown',{uniqid:uniqid,obj:obj},TZR.Calendar.keyListener);
  }
}

// Créé une div pour l'affichage mensuel
TZR.Calendar.monthCreateDiv=function(uniqid,obj,e){
  e.id=e.id.replace(':','_');
  var div=document.getElementById(e.id+"-"+uniqid);
  if(!div){
    div=document.createElement('div');
    div.tzrevent=e;
    div.id=e.id+"-"+uniqid;
    div.className="tzr-cal-month-div";
    div.style.backgroundColor=e.color;
    jQuery('#ag'+e._isod+"-"+uniqid).append(div);
    var jdiv=jQuery(div);
    TZR.Calendar.applyHottip(uniqid,obj,jdiv,TZR.Calendar.monthCreateDiv);
    if(e.rw && e._obd==e._oed){
      jdiv.mousedown(function(event){
        event.currentTarget.tzrevent.modified=false;
        jdiv.addClass('ui-draggable-dragging');
        jQuery('.tzr-cal-display-in').mouseover(function(){
          if(event.currentTarget.tzrevent._isod!=this.id.substr(2,10)){
            jQuery(jQuery.bt.vars.closeWhenOpenStack).btOff();
            jQuery(event.currentTarget).appendTo(this);
            event.currentTarget.tzrevent._isod=this.id.substr(2,10);
            event.currentTarget.tzrevent.modified=true;
          }
        });
        jQuery(document).bind('mouseup',{jdiv:jdiv,uniqid:uniqid,obj:obj,event:event.currentTarget.tzrevent},TZR.Calendar.monthStopDrag).bind('keydown',{parent:div.parentNode,target:div},TZR.Calendar.monthCancelDrag);
        return false;
      });
    }
    jdiv.click(function(event){
      var _now=new Date().getTime();
      if(this.lastclick && _now-this.lastclick<300){
        obj.vuniqid.jQueryLoad(e.url+"&display="+obj.display);
        return;
      }
      this.lastclick=_now;
    });
  }
  div.innerHTML='<div class="event-w"></div><div class="event-e"'+(e.cat?' style="background-color:'+e.cat+';"':'')+'></div><div class="event-s"></div><div class="event-n"></div><div class="tzr-cal-month-paddingdiv"><div class="tzr-cal-display-event-hour">'+TZR.Calendar.getEventText(null,null,e)+'</div></div>';
}
TZR.Calendar.monthStopDrag=function(e){
  if(e.data.event.modified) TZR.Calendar.ajaxSaveEvent(e.data.uniqid,e.data.obj,e.data.event,false);
  e.data.jdiv.removeClass('ui-draggable-dragging');
  jQuery('.tzr-cal-display-in').unbind('mouseover');
  jQuery(document).unbind('mouseup',TZR.Calendar.monthStopDrag);
  return false;
}
// Annule un drag dans l'affichage mensuel
TZR.Calendar.monthCancelDrag=function(e){
  if(e.keyCode==27){
    // La div a changée de colonne, on la remet
    if(e.data.target.parentNode!=e.data.parent){
      jQuery(e.data.target).appendTo(e.data.parent);
      e.data.target.tzrevent._isod=e.data.parent.id.substr(2,10);
    }
    e.data.target.tzrevent.modified=false;
    jQuery(document).mouseup();
    jQuery(document).unbind('keydown',TZR.Calendar.monthCancelDrag);
  }
}
// champs liens lies //
TZR.linkedfields = {notifsStack:[], stacktimeout:null, fields:{}};
// traite les requetes de mise à jour
function link2field_popStack(){
  // pile vide
  if (TZR.linkedfields.notifsStack.length == 0)
    return;
  // paquet suivant si paquet en cours vide
  if (TZR.linkedfields.notifsStack[0].length == 0){
    TZR.linkedfields.notifsStack.shift();
    TZR.linkedfields.stacktimeout = setTimeout(link2field_popStack, 1);
    return;
  }
  var dest = TZR.linkedfields.notifsStack[0].shift();
  // appel du traitement pour le champ cible en cours
  dest.field.refresh(dest.value, dest.options);
  TZR.linkedfields.stacktimeout = setTimeout(link2field_popStack, 1);
}
TZR.linkedfields.init = function(f, reset){
  reset=reset||false;
  for(var id in this.fields){
    var el = document.getElementById(this.fields[id].properties.varid);
    // ne traiter que les champs du formulaire en cours
    if (el != null && el.form == f){
      this.fields[id].init1(reset);
    } else  { 
      //champ lien parent des sous fiches (input hidden)
      //voir / autres champ ro ?
      var el = f.elements[this.fields[id].properties.field];
      if (el != null && el.nodeName == 'INPUT' && el.getAttribute('type') == 'hidden' && el.value != ''){
	this.fields[id].ro = true;
	this.fields[id].roElem = el;
	this.fields[id].roValue = el.value;
	this.fields[id].init1(reset);
      }
    }
  }
  // initialisation : force un change les champs déjà renseignées pour filter les dépendants
  if (!reset) {
    for (var id in this.fields){
      if (this.fields[id].val().length != 0) { // ... val retourne un tableau
        this.fields[id].grouplocked = true;
	this.fields[id].triggerChange();
        this.fields[id].grouplocked = false;
      }
    }
  } else { // refresh : reload pour les champs contraints par un champ ro
    for (var id in this.fields){
      var field = this.fields[id];
      for(var i=0; i<field.parentfields.length; i++){
	var pfield = field.parentfields[i].field;
	if (pfield.ro && pfield.roValue != '') {
	  pfield.grouplocked = true;
	  pfield.triggerChange();
          pfield.grouplocked = false;
	}
      }
    }
  }
};

// reset des champs liés
TZR.linkedfields.reset = function(ori){
  var el = document.getElementById(ori.properties.varid);
  this.init(el.form, true);
}
TZR.linkedfields.add = function(params, linkedfields){
  if (params.varid == ""){
    console.log(['empty varid', params]);
    return;
  }
  var newfield = null;
  if (typeof(TZR.linkedfields.fields[params.varid]) == "undefined"){
    TZR.linkedfields.fields[params.varid] = newfield = new link2field(params);
  }else{
    newfield = TZR.linkedfields.fields[params.varid];
  }
  newfield.addLinkedFields(linkedfields);
  linkedfields = newfield.linkedfields;
  newfield.linkedfields = [];
  // consolidation des champs parents
  for(var i=0; i<linkedfields.length; i++){
    if (typeof(TZR.linkedfields.fields[linkedfields[i].properties.varid]) == "undefined"){
      // ajout d'un nouveau champ à la liste complète
      TZR.linkedfields.fields[linkedfields[i].properties.varid] = linkedfields[i];
    } else {
      // champ deja connu : ajout d'un parent
      var done = false;
      var f = TZR.linkedfields.fields[linkedfields[i].properties.varid];
      for(var j=0; j<f.parentfields.length; j++){
        if (f.parentfields[j].field.properties.varid == newfield.properties.varid){
          done = true;
          break;
        }
      }
      if (!done)
        TZR.linkedfields.fields[linkedfields[i].properties.varid].parentfields.push({field:newfield, query:linkedfields[i].properties.query});
    }
    newfield.linkedfields.push(TZR.linkedfields.fields[linkedfields[i].properties.varid]);
  }
}
// classe des champs liées
function link2field(params){
  this.parentfields = [];
  this.grouplocked = false;
  this.properties = params;
  this.ui = params.uitype;
  this.ro = false;
  this.roValue = null;
  this.roElem = null;
  this.roParents = null;
  this.jqobj = jQuery("#"+params.varid);
  if (this.ui == 'double'){
    this.jqobjUnselected = jQuery("#unselected"+params.varid);
  }
  this.linkedfields = [];
  this.addLinkedFields = link2field_addLinkedFields;
  this.init1 = link2field_init1;
  this.init2 = link2field_init2;
  this.refreshui = link2field_refreshui;
  this.refresh = link2field_refresh;
  this.change = link2field_change;
  this.addResetUi = link2field_addResetUi;
  if (this.ro){
    this.val = link2field_ro_val;
  } else {
    if (this.ui == 'double'){
      this.val = link2field_doublebox_val;
    } else {
      this.val = link2field_val;      
    }
  }
  this.initialVal = link2field_initialVal;
  this.locked = link2field_locked; // ?
  this.groupLock = link2field_groupLock;
  this.isGroupLocked = link2field_isGroupLocked;
  this.triggerChange = link2field_triggerChange;
}
function link2field_triggerChange(){
  if (this.ro){
    this.change({}, {field:this});
  } else {
    this.jqobj.change();
  }
}
function link2field_addLinkedFields(linkedfields){
  for(var i in linkedfields){
    var newfield = new link2field(linkedfields[i]);
    newfield.parentfields.push({field:this, query:newfield.properties.query});
    this.linkedfields.push(newfield);
  }
}
function link2field_locked(){
  var resetobj = jQuery("#reset"+this.properties.varid);
  if (resetobj.length == 0)
    return false;
  var disp = resetobj.css('display');
  return (disp != "none");
}
function link2field_groupLock(v){
  for(var i=0; i<this.parentfields.length; i++)
    this.parentfields[i].field.grouplocked = v;
  for(var i=0; i<this.linkedfields.length; i++)
    this.linkedfields[i].grouplocked = v;
  this.grouplock = v;
}
function link2field_isGroupLocked(){
   return this.grouplocked;
}
function link2field_init1(reset){
  this.refresh([], {init:reset?false:true, filter:'off'});
  return;
  // init du champ tenant compte de la valeur en cours
  // valeurs initiales
  jQuery.ajax({method:"get",
               type: "POST",
               data:data,
               dataType:"json",
               context:this,
               async:false,
               url:this.properties.url,
               success:function(data, textstatus, xmltruc /*verifier cela*/){
                 this.refresh(data, {init:reset?false:true, filter:'off'});
               },
               error:function(){alert('Erreur initialisation des champs(1)');}});
  
}
function link2field_init2(){
  if (this.ro){
    return;
  }
  if (this.ui == 'double'){
    // TZR.doubleAdd (generic.js) lance le change sur la dest
    // depuis le doubleclick avec des options (jquery)
    this.jqobj.on('change', {field:this}, function(event, options){
      // les change natif sont ignorés
      if (options){
	if (options == 'doubleboxchange'){
	  options = null;
	}
	options = options||{init:false};
	event.data.field.change(event, options);
      }
    });
  } else {
    this.jqobj.on('change', {field:this},function(event, options){
      options = options||{init:false};
      event.data.field.change(event, options);
    });
  }
}
// recup des data du champ
function link2field_refresh(value, options){
  if (this.ro){
    return;
  }
  var filter = options.filter||'on';
  var data = {table:this.properties.table,
              options:{fmoid:this.properties.fmoid},
              field:this.properties.field,
              linkqueries:[]
             };
  // recherche des valeurs des parents
  if (filter == 'on'){
      for(var i=0; i<this.parentfields.length; i++){
        var aparent = this.parentfields[i];
        if (!options.fv || options.fv.field != aparent.field.properties.field){
          var vals = aparent.field.val();
        } else {
          var vals = options.fv.values;
        }
        // on pourrauit prendre tout (cas contraint)
        if (vals.length != 0)
          data.linkqueries.push({
            field:aparent.field.properties.field,
            value:vals,
            query:aparent.query
          });
      }
  }
  jQuery.ajax({
	       method: "POST",
	       data:data,
	       dataType:"json",
	       async:false,
	       context:this,
	       url:this.properties.url,
	       success:function(data, textstatus, xmlhttprequest/*?*/){
		 this.refreshui(data, options);
	       },
	       error:function(){
		 alert('erreur initialisation des champs(2)');
	       }});
}
// recupere les valeurs en cours du champ et retourne un tableau
function link2field_ro_val(){
  return this.rovalue;
}
function link2field_doublebox_val(){
  values = [];
  this.jqobj.find('option').each(function(i,o){
    values.push(o.getAttribute('value'));
  });
  return values;
}
function link2field_val(){
  var val = this.jqobj.val();
  if (val == null || val == "")
    return [];
  if (typeof val != 'object')
    return [val];
  else
    return val;
}
function link2field_initialVal(){
  if (this.ro){
    return this.roValue;
  }
  var current = this.jqobj.attr('data-value');
  if (current == null)
    current = '';
  return current;
}
function link2field_refreshui(data, options){
  if (this.ro){
    return;
  }
  if (this.ui == "double"){
    return refresh_ui_double.call(this, data, options);
  }
  return refresh_ui_select.call(this, data, options);
};
function refresh_ui_double(data, options){
  var target = this.jqobj;
  var selvals = this.val();
  var current = "";

  this.jqobjUnselected.children().remove();
  this.jqobj.children().remove();

  // ajout des autres champs ! cas champs multivalués
  if (options.init == true  || (options.filter && options.filter=='off')){
    current = this.initialVal();
    if (options.init){
      this.init2();
    }
  } else {
    if (selvals.length > 0)
      current = selvals.join('||');
  }

  jQuery("#"+this.properties.varid+"_nb").html(" nb : "+data[0].items.length);
  var newvalues = [];
  for(var i in data[0].items){
    var selected = "";
    if (data[0].items[i].koid == current || current.indexOf(data[0].items[i].koid)>=0){
      selected = "selected";
    }
    newvalues.push(data[0].items[i].koid);
    if (selected){
      this.jqobj.append("<option value=\""+data[0].items[i].koid+"\">"+data[0].items[i].label+"</option>");
    } else {
      this.jqobjUnselected.append("<option value=\""+data[0].items[i].koid+"\">"+data[0].items[i].label+"</option>");      
    }
  }
  // répercution  aux champs liés si pas déja concernés, avec mes valeurs en cours (data.items ...)
  for(var i=0; i<this.linkedfields.length; i++){
    if (options.updfields && options.updfields.indexOf(this.linkedfields[i].properties.field) == -1){
      this.linkedfields[i].refresh([], {updfields:options.updfields+";"+this.linkedfields[i].properties.field,
					filter:"on", fv:{field:this.properties.field, values:newvalues}});

    }
  }
};
function refresh_ui_select(data, options){
  var target = this.jqobj;
  var first = target.children(":first");
  var selvals = this.val();
  var current = "";
  if (first.attr('value') != '')
    first = null;
  target.children().remove();
  if (first != null)
    target.append(first);
  // ajout des autres champs ! cas champs multivalués
  if (options.init == true  || (options.filter && options.filter=='off')){
    current = this.initialVal();
    if (current == '' && first != null){
      first.attr('selected', 'selected');
    } else {
      if (first != null)
        first.attr('selected', 'selected');
    }
    if (options.init)
      this.init2();
  } else {

    if (selvals.length > 0)
      current = selvals.join('||');
  }

  jQuery("#"+this.properties.varid+"_nb").html("  nb : "+data[0].items.length);
  var newvalues = [];
  for(var i in data[0].items){
    var selected = "";
    if (data[0].items[i].koid == current || current.indexOf(data[0].items[i].koid)>=0){
      selected = "selected";
    }
    newvalues.push(data[0].items[i].koid);
    target.append("<option "+selected+" value=\""+data[0].items[i].koid+"\">"+data[0].items[i].label+"</option>");
  }
  // répercution  aux champs liés si pas déja concernés, avec mes valeurs en cours (data.items ...)
  for(var i=0; i<this.linkedfields.length; i++){
    if (options.updfields && options.updfields.indexOf(this.linkedfields[i].properties.field) == -1){
      this.linkedfields[i].refresh([], {updfields:options.updfields+";"+this.linkedfields[i].properties.field, filter:"on", fv:{field:this.properties.field, values:newvalues}});

    }
  }
}
function link2field_addResetUi(){
  if(this.ui == "select"){
    this.jqobj.after("<button title='Réinitialiser' id='reset"+this.properties.varid+"' class='btn btn-warning' type='button'><span  style='background-color:transparent;' class='csico-reset glyphicon'></span></button>");
  } else {
    var jtable = this.jqobj.parents('table.doublebox');
    jQuery("<br><button type='button' title='Réinitialiser' id='reset"+this.properties.varid+"' class='btn btn-warning'><span class='csico-reset glyphicon'></span></button>").appendTo(jQuery("tr",this.jqobj.parents('table.doublebox')).children()[1]);
  }
}
function link2field_change(event, options){
  var value = this.val();
  var newstack = [];
  var linkedfieldslocked = false;
  var updfields = options.updfields||'';
  options.updfields = '';
  updfields += this.properties.field;
  for(var i=0; i<this.linkedfields.length; i++)
      updfields += ";"+this.linkedfields[i].properties.field;

  for(var i=0; i<this.linkedfields.length; i++){
    if (this.linkedfields[i].isGroupLocked())
        linkedfieldslocked = true;
    if (this.linkedfields[i].locked())
      continue;
    options['updfields'] = updfields;
    newstack.push({field:this.linkedfields[i],
                   value:value,
                   options:options
                  });
  }
  if (newstack.length == 0)
      return;
  if (!this.isGroupLocked() && !linkedfieldslocked){
    this.groupLock(true);
    var target = this.jqobj;
    var resetobj = jQuery("#reset"+this.properties.varid);
    if (resetobj.length == 0){
      this.addResetUi();
      jQuery("#reset"+this.properties.varid).bind('click', {field:this}, function(event){
          jQuery("#reset"+event.data.field.properties.varid).css('display', 'none');
          event.data.field.groupLock(false);
          TZR.linkedfields.reset(event.data.field);
          return false;
      });
    } else {
      resetobj.css('display','');
    }
  }
  TZR.linkedfields.notifsStack.push(newstack);
  TZR.linkedfields.stacktimeout = setTimeout(link2field_popStack, 1);
}
// </link2field>

// Déplace une ou plusieurs options d'un select multiple vers le haut
TZR.doubleSelectOptionUp = function(selectObj) {
  var cancelMove = false;
  jQuery('option:selected', selectObj).each( function() {
    if (cancelMove) return;
    var newPos = jQuery('option', selectObj).index(this) - 1;
    if (newPos > -1) {
      jQuery('option', selectObj).eq(newPos).before("<option value='"+jQuery(this).val()+"' selected='selected'>"+jQuery(this).text()+"</option>");
      jQuery(this).remove();
    } else {
      cancelMove = true;
      return;
    }
  });
};
// Déplace une ou plusieurs options d'un select multiple vers le bas
TZR.doubleSelectOptionDown = function(selectObj) {
  var countOptions = jQuery('option', selectObj).size();
  var countOptionsSelected = jQuery('option:selected', selectObj).size();
  var cancelMove = false;
  jQuery(jQuery('option:selected', selectObj).get().reverse()).each( function() {
    if (cancelMove) return;
    var newPos = jQuery('option', selectObj).index(this) + 1;
    if (newPos < countOptions) {
      jQuery('option', selectObj).eq(newPos).after("<option value='"+jQuery(this).val()+"' selected='selected'>"+jQuery(this).text()+"</option>");
      jQuery(this).remove();
    } else {
      cancelMove = true;
      return;
    }
  });
};
// champ GmapPoint
TZR.geocode = function(id) {
  var address = jQuery("#gmap"+id+" .gmap-address").val(),
      geocoder = new google.maps.Geocoder(),
      error_div = jQuery("#gmap"+id+" .gmap-error");
  error_div.html('').hide();
  var latlng = /(\d+\.\d+),(\d+\.\d+)/.exec(address);
  if (latlng != null) { // we have latlng
    var loc = new google.maps.LatLng(latlng[1], latlng[2]);
    TZR.gmap.setCenter(loc);
    TZR.marker.setPosition(loc);
    return;
  }
  geocoder.geocode({'address': address}, function(results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      TZR.gmap.setCenter(results[0].geometry.location);
      TZR.marker.setPosition(results[0].geometry.location);
    } else {
      error_div.html("Geodode error: " + status).show();
    }
  });
}
TZR.osmGeocode = function(id, geocodingUrl) {
  var address = jQuery("#gmap"+id+" .gmap-address").val(),
      geocoder = new L.Control.Geocoder.Nominatim({serviceUrl : geocodingUrl}),
      error_div = jQuery("#gmap"+id+" .gmap-error");
  error_div.html('').hide();
  
  var latlng = /(-?\d+\.-?\d+),(-?\d+\.-?\d+)/.exec(address);
  if (latlng != null) { // we have latlng
    var oLatLng = new L.LatLng(latlng[1], latlng[2]);
    TZR.osm.panTo(oLatLng);
    TZR.marker.setLatLng(oLatLng);
    return;
  }
  geocoder.geocode(address, function(results) {
    if (results.length > 0) {
      TZR.osm.panTo(results[0].center);
      TZR.marker.setLatLng(results[0].center);
    } else {
      error_div.html("Geodode error").show();
    }
  });
};
gmaplocalize = function() {
  TZR.localize(TZR.gmap_options);
}
// display edit
TZR.localize = function(options) {
  if (window.google == undefined || google.maps == undefined) {
    var script = document.createElement("script");
    script.type = "text/javascript";
    script.src = "https://maps.googleapis.com/maps/api/js?callback=gmaplocalize";
    if (typeof TZR._gmap_api_key != 'undefined')
      script.src += "&key=" + TZR._gmap_api_key;
    document.body.appendChild(script);
    TZR.gmap_options = options;
    return false;
  }
  TZR.gmapremove();
  var params = jQuery.extend({
    edit: 0,
  }, options);
  TZR.input_lat = jQuery("#"+params.id+"-lat");
  TZR.input_lng = jQuery("#"+params.id+"-lng");
  var defaultLocation = params.defaultLocation.split(/,|;/),
      lat = TZR.input_lat.val(),
      lng = TZR.input_lng.val(),
      latlng,
      width = params.mapGeometry.split('x')[0],
      height = params.mapGeometry.split('x')[1],
      addrFields = jQuery("#"+params.id+"-addrFields").val(),
      address;
  if (params.edit) {
    if (lat != undefined && lat.length && lng != undefined && lng.length)
      address = lat+','+lng;
    else if (jQuery("#"+params.id+"-address").val()) // intable edit
      address = jQuery("#"+params.id+"-address").val();
    else
      address = '';
    var uiui = false;
    if (uiui){
      TZR.gmap_div = jQuery('<div id="gmap'+params.id+'" class="gmap"  style="height:'+height+'px;width:'+width+'px"><div class="close" onclick="jQuery(\'#gmap'+params.id+'\').remove()"></div><div class="move"></div><input type="button" value="OK" class="gmap-submit" onclick="TZR.gmapsetPosition();return false;"><div class="gmap-form"><input type="text" class="gmap-address" value="'+address+'"><input type="image" class="gmap-search" src="/tzr/templates/ico/general/query.png" onclick="TZR.geocode(\''+params.id+'\');return false;"></div><div id="map'+params.id+'" class="map-container" style="height:'+(height-30)+'px;width:'+width+'px"></div><div class="gmap-error"></div></div>');
    } else {
      var htmlcontents = '<div class="google-map" id="gmap'+params.id+'"><div class="title">'+options.title+'</div><div class="form-group">';
      htmlcontents    += '<input type="text" class="gmap-address" value="'+address+'"><button class="btn btn-default" onclick="TZR.geocode(\''+params.id+'\');return false;"><span class="csico-search"></span></button></div>';
      htmlcontents    += '<div style="width:100%;height:'+height+'px" id="map'+params.id+'"></div></div>';
      htmlcontents    += '<div class="tzr-action"><div class="form-group"><button class="btn btn-primary" onclick="TZR.gmapsetPosition();TZR.Dialog.closeDialog();"><span class="csico-published"></span></button></div></div>';
    }
  } else {
    if (uiui){
      TZR.gmap_div = jQuery('<div id="gmap'+params.id+'" class="gmap" style="height:'+height+'px;width:'+width+'px" ><div class="close" onclick="jQuery(\'#gmap'+params.id+'\').remove()"></div><div class="move"></div><div id="map'+params.id+'" class="map-container" style="height:'+(height-30)+'px;width:'+width+'px"></div></div>');
    } else {
      var htmlcontents =  '<div class="google-map" id="gmap'+params.id+'"><div class="title">'+options.title+'</div>';
      htmlcontents += '<div style="width:100%;height:'+height+'px" id="map'+params.id+'"></div></div>';
    }
  }
  
  if (uiui){
    TZR.gmap_div.insertAfter('#'+params.id+'-loc');
    
    jQuery("#gmap"+params.id)
      .resizable({
	minHeight: height-30,
	minWidth: width,
	stop: function(event, ui) {
          jQuery('.map-container', ui.element).css({height:(ui.size.height-30)+'px', width:ui.size.width+'px'});
          google.maps.event.trigger(TZR.gmap, 'resize')
	}})
      .draggable({containment:'document', handle: '.move'})
      .css({position: 'absolute'});
  } else {
    TZR.Dialog.show(htmlcontents, {allowMove:true,backdrop:true});
    TZR.gmap_div = jQuery('#gmap'+params.id);
  }

  if (lat != undefined && lat.length && lng != undefined && lng.length) {
    // existing position
    latlng = new google.maps.LatLng(lat, lng);
  } else {
    // default position
    latlng = new google.maps.LatLng(defaultLocation[0], defaultLocation[1]);
    if (params.edit) {
      if (addrFields != undefined) { // edit
        address = '';
        jQuery.each(addrFields.split(','), function(index, value) {
          if (value.charAt(0) == "'")
            address += value.replace(/'/g, "") + ' ';
          else if (jQuery('input[name="_INPUT'+value+'"]').val()) // link
            address += jQuery('input[name="_INPUT'+value+'"]').val() + ' ';
          else if (jQuery('select[name="' + value + '"]').length) // select link
            address += jQuery('select[name="' + value + '"] option:selected').html() + ' ';
          else if (jQuery('textarea[name="'+value+'"]').val()) // text
            address += jQuery('textarea[name="'+value+'"]').val().replace(/\n/g, ' ') + ' ';
          else if (jQuery('input[name="'+value+'"]').val()) // shorttext
            address += jQuery('input[name="'+value+'"]').val() + ' ';
        });
      }
      if (address.length) {
        TZR.gmap_div.find(".gmap-form").show().find(".gmap-address").val(address);
        TZR.geocode(params.id);
      }
    }
  }
  TZR.gmap = new google.maps.Map(document.getElementById('map'+params.id), {
      zoom: parseInt(params.zoom),
      center: latlng,
      mapTypeControl: true,
      mapTypeId: google.maps.MapTypeId.ROADMAP
  });
  TZR.marker = new google.maps.Marker({
      map: TZR.gmap,
      position: latlng
  });
  if (params.edit) {
    TZR.marker.setDraggable(true);
    google.maps.event.addListener(TZR.marker, 'dblclick', function(event){
      return TZR.gmapsetPosition();
    });
  }
  if (params.bounds) {
    var readBounds = function () {
      var bounds = TZR.gmap.getBounds();
      jQuery("#"+params.id+"-bounds").val(bounds.getSouthWest().lat()+';'+bounds.getSouthWest().lng()+';'+bounds.getNorthEast().lat()+';'+bounds.getNorthEast().lng());
    };
    google.maps.event.addListener(TZR.gmap, 'idle', function(event){
      readBounds();
    });
    google.maps.event.addListener(TZR.gmap, 'bounds_changed', function(event){
      readBounds();
    });
    let coords = jQuery("#"+params.id+"-bounds").val();
    coords = coords.split(';');
    if (coords.length == 4) {
      let min = new google.maps.LatLng(coords[0], coords[1]);
      let max = new google.maps.LatLng(coords[2], coords[3]);
      let bounds = new google.maps.LatLngBounds(min, max);
      TZR.gmap.fitBounds(bounds);
    }
  }
  if (!uiui){
    TZR.gmap_div.click(function(e){
      e.stopPropagation();
    });
    setTimeout(function(){
      jQuery("html").one('click.gmaplocalize', function(e){
	TZR.gmapremove();
      })
    },10);
  }
  return false;
};
TZR.localizeOSM = function(options) {
  if (window.L == undefined) {
    console.error('Leaflet manquant !');
    return false;
  }
  
  var params = jQuery.extend({
                               edit: 0,
                             }, options);
  TZR.input_lat = jQuery("#"+params.id+"-lat");
  TZR.input_lng = jQuery("#"+params.id+"-lng");
  var defaultLocation = params.defaultLocation.split(/,|;/),
  lat = TZR.input_lat.val(),
  lng = TZR.input_lng.val(),
  height = params.mapGeometry.split('x')[1],
  addrFields = jQuery("#"+params.id+"-addrFields").val(),
  address;
  if (typeof(options.labels) == "undefined"){
    var closebutton=null;
    var savebutton='<span class="csico-close"></span>';
  } else {
    var closebutton=options.labels.close;
    var savebutton=options.labels.save;
  }
  if (params.edit) {
    if (lat != undefined && lat.length && lng != undefined && lng.length)
      address = lat+','+lng;
    else if (jQuery("#"+params.id+"-address").val()) // intable edit
      address = jQuery("#"+params.id+"-address").val();
    else
      address = '';
    var htmlcontents = '<div class="google-map" id="gmap'+params.id+'"><div class="title">'+options.title+'</div><div class="form-group">';
    htmlcontents    += '<input type="text" class="gmap-address" value="'+address+'"><button class="btn btn-default" onclick="TZR.osmGeocode(\''+params.id+'\', \''+params.geocodingUrl+'\');return false;"><span class="csico-search"></span></button></div>';
    htmlcontents    += '<div style="width:100%;height:'+height+'px" id="map'+params.id+'"></div></div>';
    htmlcontents    += '<div class="tzr-action"><div class="form-group">';
    htmlcontents    += '<button class="btn btn-primary" onclick="TZR.osmSetPosition();">'+savebutton+'</button>';
    if (closebutton != null)
      htmlcontents    += '<button data-dismiss="modal" class="btn btn-default">'+closebutton+'</button>';
    htmlcontents    += '</div></div>';
  } else {
    var htmlcontents =  '<div class="google-map" id="gmap'+params.id+'"><div class="title">'+options.title+'</div>';
    htmlcontents += '<div style="width:100%;height:'+height+'px" id="map'+params.id+'"></div></div>';
    if (closebutton != null)
      htmlcontents    += '<div class="tzr-action"><button data-dismiss="modal" class="btn btn-default">'+closebutton+'</button></div>';
  }
  
  
  TZR.Dialog.show(htmlcontents, {allowMove:true,backdrop:true});
  TZR.gmap_div = jQuery('#gmap'+params.id);
  
  var center = defaultLocation;
  
  if (lat != undefined && lat.length && lng != undefined && lng.length) {
    // existing position
    center = [lat, lng];
  }
  else {
    if (params.edit) {
      if (addrFields != undefined) { // edit
        address = '';
        jQuery.each(addrFields.split(','), function(index, value) {
          if (value.charAt(0) == "'")
            address += value.replace(/'/g, "") + ' ';
          else if (jQuery('input[name="_INPUT'+value+'"]').val()) // link
            address += jQuery('input[name="_INPUT'+value+'"]').val() + ' ';
          else if (jQuery('select[name="' + value + '"]').length) // select link
            address += jQuery('select[name="' + value + '"] option:selected').html() + ' ';
          else if (jQuery('textarea[name="'+value+'"]').val()) // text
            address += jQuery('textarea[name="'+value+'"]').val().replace(/\n/g, ' ') + ' ';
          else if (jQuery('input[name="'+value+'"]').val()) // shorttext
            address += jQuery('input[name="'+value+'"]').val() + ' ';
        });
      }
      if (address.length) {
        TZR.gmap_div.find(".gmap-form").show().find(".gmap-address").val(address);
        TZR.osmGeocode(params.id, params.geocodingUrl);
      }
    }
  }
  
  TZR.osm = L.map('map'+params.id).setView(center, parseInt(params.zoom));
  
  TZR.osmFeatureGroup = L.featureGroup().addTo(TZR.osm);
  
  L.tileLayer(params.tilesURL, { attribution : 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="https://opendatacommons.org/licenses/odbl/">ODbL</a>, Imagery &copy; <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>' }).addTo(TZR.osm);
  
  if (params.scrollWheelZoom) {
    TZR.osm.scrollWheelZoom.enable();
  } else {
    TZR.osm.scrollWheelZoom.disable();
  }
  
  TZR.marker = L.marker(center);
  
  TZR.marker.addTo(TZR.osmFeatureGroup);
  
  if (params.edit) {
    TZR.marker.dragging.enable();
    TZR.marker.addEventListener('dblclick', function(event) {
      return TZR.osmSetPosition();
    });
  }
  if (params.bounds) {
    var readBounds = function () {
      var bounds = TZR.osm.getBounds();
      jQuery("#"+params.id+"-bounds").val(bounds.getSouthWest().lat+';'+bounds.getSouthWest().lng+';'+bounds.getNorthEast().lat+';'+bounds.getNorthEast().lng);
    };
    TZR.osm.on('moveend', readBounds);
    TZR.osm.on('zoomend', readBounds);
    let coords = jQuery("#"+params.id+"-bounds").val();
    coords = coords.split(';');
    if (coords.length == 4) {
      TZR.osm.fitBounds([
        [coords[0], coords[1]],
        [coords[2], coords[3]]
      ]);
    } else {
      readBounds();
    }
  }
  TZR.gmap_div.click(function(e){
    e.stopPropagation();
  });
  
  return false;
};
TZR.gmapsetPosition = function() {
  var pos = TZR.marker.getPosition();
  TZR.input_lat.val(pos.lat()).change();
  TZR.input_lng.val(pos.lng()).change();
  TZR.gmapremove();
  return false;
};
TZR.osmSetPosition = function(){
  var pos = TZR.marker.getLatLng();
  TZR.input_lat.val(pos.lat);
  TZR.input_lng.val(pos.lng);
  TZR.osmRemove();
  return false;
};

TZR.gmapremove = function(){
  if (TZR.gmap_div != undefined) {
    TZR.gmap_div.remove();
    TZR.gmap_div = null;
    jQuery("html").unbind('click.gmaplocalize');
  }
};
TZR.osmRemove = function(){
  if (TZR.gmap_div != undefined) {
    TZR.gmap_div.remove();
    TZR.gmap_div = null;
  }
  TZR.Dialog.closeDialog();
};
/*sélection d'un objet en popup - champ mono*/
TZR.xlinkdefCancelValue = function(varid, options){
  TZR.xlinkdefValueSelected(varid, {tlink:"", oid:"", openeroptions:{multivalued:0}});
}
TZR.xlinkdefValueSelected = function(varid, data){
  data = jQuery.extend({objectproperties:null}, data);
  if (data.openeroptions.multivalued==1)
    TZR.xlinkdefMultipleValueSelected(varid, data);
  /*<%* mono valué *%>*/
  var container = jQuery("#browsesourcelink"+varid);
  jQuery("#"+varid, container).val(data.oid);
  jQuery("span.title", container).html(data.tlink);
  if ( typeof(TZR.local_xlinkdef_value_selected) == 'function'){
    TZR.local_xlinkdef_value_selected(varid, data);
  }
  if (data.oid != null && data.oid != ""){
    jQuery("span.cancel", container).show();
  } else {
    jQuery("span.cancel", container).hide();
  }
  TZR.Dialog.closeDialog();
};
TZR.xlinkdefMultipleValueSelected = function(id, options){
  if(jQuery('#table'+id).find('input[value="'+options.oid+'"]').length>0) return;
  var tr=TZR.addTableLine("table"+id,[undefined,options.tlink],0,false);
  jQuery('input',tr.cells[0]).val(options.oid);
  jQuery(tr).show();
  TZR.Dialog.closeDialog();
};
TZR.xlinkdefSelectionPopup = function(options){
  options.url += "&"+jQuery.param({'openeroptions':options});
  TZR.Dialog.openURL(options.url);
}
// FO
gmapdisplay = function() {
  for (i=0; i<TZR.gmap_params.length; i++)
    TZR.gmapdisplay(TZR.gmap_params[i]);
}
TZR.gmapdisplay = function(params) {
  if (window.google == undefined || google.maps == undefined) {
    if (TZR.gmap_params == undefined) {
      TZR.gmap_params = [params];
      var script = document.createElement("script");
      script.type = "text/javascript";
      script.src = "https://maps.googleapis.com/maps/api/js?callback=gmapdisplay";
      if (typeof TZR._gmap_api_key != 'undefined')
        script.src += "&key=" + TZR._gmap_api_key;
      document.body.appendChild(script);
    }
    else
      TZR.gmap_params.push(params);
    return false;
  }
  if (jQuery('#'+params.id).is(':visible'))
    TZR.gmapshow(params);
  else
    params.timer = setInterval(function(){TZR.gmapshow(params)}, 500);
  return false;
};
TZR.osmDisplay = function(params) {
  if (window.L == undefined) {
    console.error('Leaflet manquant !');
    return false;
  }
  
  if (jQuery('#'+params.id).is(':visible'))
    TZR.osmShow(params);
  else
    params.timer = setInterval(function(){TZR.osmShow(params)}, 500);
  return false;
};
TZR.gmapshow = function(params) {
  if (!jQuery('#'+params.id).is(':visible'))
    return;
  if (params.timer)
    clearInterval(params.timer);
  var location = params.defaultLocation.split(','),
      latlng = new google.maps.LatLng(location[0], location[1]);
  var gmap = new google.maps.Map(document.getElementById(params.id), {
      zoom: params.zoom,
      center: latlng,
      mapTypeControl: true,
      mapTypeId: google.maps.MapTypeId.ROADMAP
  });
  var marker = new google.maps.Marker({
      map: gmap,
      position: latlng
  });
};
TZR.osmShow = function(params) {
  if (!jQuery('#'+params.id).is(':visible'))
    return;
  if (params.timer)
    clearInterval(params.timer);
  var location = params.defaultLocation.split(',');
  
  var osm = L.map(params.id).setView(location, parseInt(params.zoom));
  
  var osmFeatureGroup = L.featureGroup().addTo(osm);
  
  L.tileLayer(params.tilesURL, { attribution : 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="https://opendatacommons.org/licenses/odbl/">ODbL</a>, Imagery &copy; <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>' }).addTo(osm);
  
  if (params.scrollWheelZoom) {
    osm.scrollWheelZoom.enable();
  } else {
    osm.scrollWheelZoom.disable();
  }
  
  var marker = L.marker(location);
  
  marker.addTo(osmFeatureGroup);
};

/*
 * Gestion du drag&drop de fichier
 *
 * TZR.addProperDragEventsTo active des évènements de DnD propres (properdragenter/properdragleave) sur un selecteur jQuery.
 * Ajoute aussi automatiquement la classe droppable aux éléments ainsi que la classe dragging au survol en mode DnD.
 * Ainsi properdragenter et properdragleave ne sont déclenchés qu'une seule fois à l'entrée/sortie sur l'élement (à l'image d'un mouseenter/mouseleave).
 * Si pour élément, lui ou un des fils a un écouteur sur dragenter ou dragleave avec un stopPropagation, alors les 2 évènements doivent avoir un écouteur avec stopPropagation, sinon le système ne fonctionne plus.
 * De plus, faire cela permet par exemple de rendre le DnD "actif" sur un element à la fois au sein d'une arborescence d'element
 * Exemple : On active les évènements propres sur body et une div
 *           Par défaut :
 *             Entrée sur la fenêtre : appel de body->properdragenter
 *             Entrée sur la div : appel de div->properdragenter
 *             Sortie de la div : appel de div->properdragleave
 *             Sortie de la fenêtre : appel de body->properdragleave
 *           Avec stopPropagation sur dragenter et dragleave :
 *             Entrée sur la fenêtre : appel de body->properdragenter
 *             Entrée sur la div : appel de div->properdragenter et de body->properdragleave
 *             Sortie de la div : appel de div->properdragleave et de body->properdragenter
 *             Sortie de la fenêtre : appel de body->properdragleave
 */
jQuery.event.props.push("dataTransfer");
TZR.addProperDragEventsTo=function(el,addoverlay){
  var $el=jQuery(el).addClass('droppable');

  $el.on('dragover.proper',function(e){
    // Obligatoire pour pouvoir avoir un drop personnalisé
    e.preventDefault();
  }).on('drop.proper',function(e){
    // Obligatoire pour pouvoir avoir un drop personnalisé
    e.preventDefault();
    $el.trigger('properdragleave');
  }).on('dragenter.proper',function(e){
    var cnt=jQuery(this).data('dragentercounter');
    if(!cnt) cnt=0;
    cnt++;
    // Dans la théorie, pour chaque enter dans un fils, il y a un leave et un enter du parent. De ce fait le compteur ne peut pas dépasser 2.
    // Dans la pratique, il arrive que le leave du parent ne se fasse pas, d'ou la limitation par programme
    if(cnt>2) cnt=2;
    if(cnt==1) jQuery(this).trigger('properdragenter');
    jQuery(this).data('dragentercounter',cnt);
  }).on('dragleave.proper',function(e){
    var cnt=jQuery(this).data('dragentercounter');
    cnt--;
    if(cnt<0) cnt=0;
    if(cnt==0) jQuery(this).triggerHandler('properdragleave');
    jQuery(this).data('dragentercounter',cnt);
  }).on('properdragenter',function(e){
    e.stopPropagation();
    jQuery(this).addClass('dragging');
  }).on('properdragleave',function(e){
    e.stopPropagation();
    jQuery(this).data('dragentercounter',0).removeClass('dragging');
  });

  if(addoverlay){
    if(typeof addoverlay!='string') addoverlay='Déposez vos fichiers dans la zone ci-dessous';
    $el.append('<div class="dropoverlay"><div class="dropoverlay-content"><div class="dropoverlay-txt">'+addoverlay+'</div><div class="dropoverlay-fl"></div></div></div></div>');
  }
  return $el;
}
// Vérifie si un evenement DnD concerne des fichiers
TZR.dragEventContainsFiles=function(e){
  if(e.dataTransfer.types && e.dataTransfer.files && e.dataTransfer.files.length){
    for(var i in e.dataTransfer.types){
      if(e.dataTransfer.types[i]=="Files") return true;
    }
  }
  return false;
}
// Ajoute la gestion des evenements propres DnD sur body
jQuery(function(){
  TZR.addProperDragEventsTo('body');
});


/* XFile */
// Initiliase un champ dossier
TZR.addXFileUploader=function(table,field,uniqid,opts){
  var $cont=jQuery('#'+uniqid+"_cont");
  var $queue=$cont.find("div.uploadQueue");
  var _opts={
    drop_element:$cont[0],
    runtimes:"html5",
    url:TZR._sharescripts+'uploader.php?swf='+uniqid+'*'+table+'*'+field,
    browse_button:$cont.find("input.uploadAddButton")[0],
    file_data_name:'Filedata',
    init:{
      FilesAdded:function(up,files){
        if(typeof(TZR.uploadInProgressFiles[uniqid])=="undefined") TZR.uploadInProgressFiles[uniqid]=0;

        if(!up.settings.multi_selection){
          if($queue.find('>div.uploadQueueItem').length){
            TZR.removeXFileFile(uniqid,$queue.find('>div.uploadQueueItem').attr('id'));
          }
          if(files.length>1){
            files=files.slice(0,1);
          }
        }
        TZR.uploadInProgressFiles[uniqid]+=files.length;
        var size_total=0;
        plupload.each(files,function(file){
          if(file.name.length>30) var name=file.name.substr(0,27)+'...';
          else var name=file.name;
          size_total+=file.size;
          $queue.append('<div class="uploadQueueItem" id="'+file.id+'">'+
			'<div class="uploadCancel"><a class="btn btn-default btn-md" href="#" onclick="TZR.removeXFileFile(\''+uniqid+'\',\''+file.id+'\'); return false;">'+
			'<span class="glypicon csico-close"></span>'+
			'</a></div>'+
			'<span class="uploadFileName">'+name+' ('+plupload.formatSize(file.size)+')</span><span class="uploadPercentage"></span>'+
			'<div class="uploadProgress"><div class="uploadProgressBar" style="width:0%;"></div></div>'+
			'</div>');
        });
        up.start();
	
        if(TZR.uploadAutoSaveMessage && size_total>(10*1024*1024)) setTimeout(function(){TZR.uploadAutoSave=confirm(TZR.uploadAutoSaveMessage)},500);
        if(!TZR.uploadKeepAliveTimer) TZR.uploadKeepAliveTimer=setInterval(TZR.keepAlive,300000);
      },
      FilesRemoved:function(up,files){
	TZR.uploadInProgressFiles[uniqid]-=files.length;
      },
      UploadFile:function(up,file){
        file.percentagespan=jQuery('#'+file.id+' span.uploadPercentage');
        file.progressbardiv=jQuery('#'+file.id+' div.uploadProgressBar');
        if(up.runtime=="html4") var t='Uploading...';
        else var t="0%";
        file.percentagespan.html(" - "+t);
      },
      UploadProgress:function(up,file){
        file.percentagespan.html(" - "+file.percent+"%");
        file.progressbardiv.css('width',file.percent+'%');
      },
      FileUploaded:function(up,file){
        jQuery('#'+file.id).addClass('uploadComplete').find('div.uploadCancel').remove();
        TZR.uploadXFileCompleted[uniqid] = true;
        file.percentagespan.html(" - Complete");
        if(file.timer){
          clearInterval(file.timer);
          delete file.timer;
        }
        TZR.uploadInProgressFiles[uniqid]--;
      },
      UploadComplete:function(up){
        if(TZR.uploadKeepAliveTimer){
          clearInterval(TZR.uploadKeepAliveTimer);
          delete TZR.uploadKeepAliveTimer;
        }
        if(TZR.uploadAutoSave) {
          var $form=jQuery(up.settings.browse_button).closest('form');
          $form.submit();
          TZR.uploadAutoSave=false;
        }
        if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields) {
          TZR.Table.saveSomeLangsCheckFields(jQuery(up.settings.browse_button).closest(".row").find("input")[0]);
        }
      },
      Error:function(up,error){
        if(error.file && error.file.percentagespan){
          error.file.percentagespan.html(" - "+error.message).addClass('tzr-message');
	  try{
	    var mess = error.file.percentagespan.html();
	    error.file.percentagespan.html(mess+" "+error.code+" - "+error.status+"");
	    console.log(error);
	  }catch(noconsolelog){};
          error.file.progressbardiv.css('width',0);
          jQuery('#'+error.file.id).addClass('uploadError').find('div.uploadCancel').remove();
        }else{
          var _alert = typeof TZR.alert == 'function' ? TZR.alert : alert;
          if(error.file){
            if(error.code && error.code === plupload.FILE_SIZE_ERROR) {
              _alert(error.file.name+' : '+error.message+' ('+sizeToString(opts.filters.max_file_size.substr(0,opts.filters.max_file_size.length-1))+' max)');
            }else{
              _alert(error.file.name+' : '+error.message);
            }
          }else{
            _alert(error.message);
          }
        }
      }
    }
  };
  if(typeof opts=="object") jQuery.extend(_opts,opts);
  var uploader=new plupload.Uploader(_opts);
  $cont.data('uploader',uploader);
  uploader.init();
  // Gestion du DnD
  TZR.addProperDragEventsTo($cont);
  // Stockage des uploads valides (un fichier au moins) et des uploads en cours
  if(typeof(TZR.uploadXFileCompleted)=="undefined") TZR.uploadXFileCompleted={};
  if(typeof(TZR.uploadInProgressFiles)=="undefined") TZR.uploadInProgressFiles={};
}
TZR.removeXFileFile=function(uniqid,file){
  var $cont=jQuery('#'+uniqid+"_cont");
  $cont.data('uploader').removeFile(file);
  $cont.find('#'+file).remove();
}
TZR.resetXFileUploader=function(container){
  var elements = jQuery("*", container).each(function(){
    var elm = jQuery(this);
    if (elm.data('uploader')){
      if (elm.is(':visible')){
	var uploader = elm.data('uploader');
	uploader.init();
      }
    }
  });
}

TZR.Xinterval = {shiftKey: false, start: null, add: true};

TZR.Xinterval.init = function (varid, options){
  if (!options)
    options = {title:''};
  var interval = jQuery('#' + varid);
  interval.data('options', options);
  interval.data('initialval', interval.val());
  interval.data('dates', interval.val().split(';').filter(function (v) {
    return v.length;
  }));
  interval.data('curinterval', []);
}
TZR.Xinterval.show = function (el, varid){
  var options = jQuery('#' + varid).data('options');
  var container = jQuery("#intervaldiv"+varid+"_");
  var html = container.html();
  var exp = new RegExp(varid+"_", "g");
  html = '<div id="intervaldiv'+varid+'">'+html.replace(exp, varid)+'</div>';
  // modal dédiée dans l'immédiat
  var elm = jQuery("#cvx-daterange-modal");
  jQuery(".modal-body", elm).html(html);
  jQuery(elm).modal({backdrop:false,show:false});
  jQuery(elm).modal('show', {});
  jQuery(".modal-header", elm).css('cursor','move');
  elm.draggable({handle: "div.modal-header"});

  // reactiver le scroll d'une fenêtre modale parent
  jQuery(document).on('hidden.bs.modal', '.modal', function () {
    jQuery('.modal:visible').length && $(document.body).addClass('modal-open');
  });

  jQuery("#intervaldiv"+varid).show();
  if (options.title != '')
    jQuery(".modal-header>h4", elm).html(options.title);
  if (!jQuery('#datePicker' + varid).html().length) {
    TZR.Xinterval.datePicker(varid);
  } else {
    jQuery('#datePicker'+varid).datepicker("refresh");
  }
  TZR.Xinterval.initKeysHandlers.call(TZR.Xinterval, varid);
}
TZR.Xinterval.initKeysHandlers = function(varid){
  jQuery(document).off('.xinterval');
  var that = this;
  jQuery(document).on('keydown.xinterval', function (e) {
    that.shiftKey = e.shiftKey;
  });
  jQuery(document).on('keyup.xinterval', function (e) {
    that.keyUp(e, varid);
  });
}
TZR.Xinterval.keyUp = function(e, varid){
  this.shiftKey = false;
  var interval = jQuery('#' + varid);
  var date, index;
  for (i = 0; i < interval.data('curinterval').length; i++) {
    date = interval.data('curinterval')[i];
    if (date == this.start)
      continue;
    index = interval.data('dates').indexOf(date);
    if (this.add) {
      if (index == -1)
        interval.data('dates').push(date);
    } else {
      interval.data('dates').splice(index, 1);
    }
  }
  jQuery('#datepicker' + varid).datepicker('refresh');
  interval.data('curinterval', []);
  this.updateRecap(varid);
  this.start = null;
}
// Fonction de mise à jour du contenu texte
TZR.Xinterval.updateRecap = function (varid, init) {
  var interval = jQuery('#' + varid), recap = '', dates = interval.data('dates').sort();
  interval.val(dates.join(';'));
  if(!init) {
    interval.change();
  }
  // le recap
  var periods = TZR.datesToPeriods(dates);
  var dateFormat = interval.data('format')
  for (i in periods) {
    if (periods[i][0] == periods[i][1]) {
      recap += jQuery.datepicker.formatDate(dateFormat, new Date(periods[i][0])) + '<br>';
    } else {
      recap += TZR.Xinterval.fromLabel +" "+ jQuery.datepicker.formatDate(dateFormat, new Date(periods[i][0]))
          + " " + TZR.Xinterval.toLabel + " " + jQuery.datepicker.formatDate(dateFormat, new Date(periods[i][1])) + '<br>';
    }
  }
  jQuery('#recap' + varid).html(recap);
}

// Initialisation global datepicker
TZR.Xinterval.datePicker = function (varid) {
  var interval = jQuery('#' + varid),
      nbMonths = parseInt(jQuery('#nbMonths' + varid).val()),
      numberOfMonths;
  if (nbMonths < 4) {
    numberOfMonths = nbMonths;
  } else {
    numberOfMonths = [Math.ceil(nbMonths / 4), 4];
  }
  jQuery('#datePicker' + varid).datepicker("destroy").datepicker({
    inline: true,
    showOtherMonths: true,
    selectOtherMonths: true,
    changeMonth:true,
    changeYear:true,
    minDate: new Date(interval.data('datemin')),
    maxDate: new Date(interval.data('datemax')),
    defaultDate: new Date(interval.data('dates')[0]),
    hideIfNoPrevNext: true,
    direction: "down",
    showAnim:"slideDown",
    showOn: "both",
    stepMonths: nbMonths > 4 ? 4 : 1,
    numberOfMonths: numberOfMonths,
    showButtonPanel: true,
    onSelect: function (dateText, inst) {
      if (interval.data('readonly'))
        return;
      var date = TZR.isoDate(new Date(inst.selectedYear, inst.selectedMonth, inst.selectedDay));
      if (TZR.Xinterval.shiftKey && TZR.Xinterval.start != null) {
        TZR.Xinterval.pushInterval(varid, date);
        return;
      }
      var index = interval.data('dates').indexOf(date);
      TZR.Xinterval.start = date;
      if (index == -1) {
        interval.data('dates').push(date);
        TZR.Xinterval.add = true;
      } else {
        interval.data('dates').splice(index, 1);
        TZR.Xinterval.add = false;
      }
      TZR.Xinterval.updateRecap(varid);
      inst.dpDiv.datepicker('refresh');
      return;
    },
    beforeShowDay: function (date) {
      var isodate = TZR.isoDate(date);
      // On vérifie si le timestamp de la date courante est sélectionné
      var index = interval.data('dates').indexOf(isodate);
      var indexi = interval.data('curinterval').indexOf(isodate);
      if (isodate == TZR.Xinterval.start) {
        if (!TZR.Xinterval.add)
          return [true, ''];
        else
          return [true, 'dates-selected'];
      }
      if (indexi != -1) {
        if (!TZR.Xinterval.add)
          return [true, ''];
        else
          return [true, 'dates-selected'];
      }
      if (index != -1)
        return [true, 'dates-selected'];
      return [true, ''];
    }
  });
}

TZR.Xinterval.pushInterval = function (varid, date) {
  var interval = jQuery('#' + varid),
      dateStart = new Date(TZR.Xinterval.start < date ? TZR.Xinterval.start : date),
      endDate = new Date(TZR.Xinterval.start < date ? date : TZR.Xinterval.start);
  interval.data('curinterval', []);
  while (dateStart <= endDate) {
    interval.data('curinterval').push(TZR.isoDate(dateStart));
    dateStart.setDate(dateStart.getDate() + 1);
  }
}

// Fonction de ré-initialisation
TZR.Xinterval.reset = function (varid) {
  var interval = jQuery('#' + varid);
  interval.val(interval.data('initialval'));
  interval.data('dates', interval.val().split(';'));
  interval.data('curinterval', []);
  TZR.Xinterval.updateRecap(varid);
  jQuery('#datePicker' + varid).datepicker('refresh');
}
TZR.Xinterval.clear = function (varid) {
  jQuery('#' + varid).data('dates', []);
  TZR.Xinterval.updateRecap(varid);
  jQuery('#datePicker' + varid + ':visible').datepicker('refresh');
}

TZR.datesToPeriods = function (dates) {
  dates.sort();
  var periods = [];
  var startDate = dates[0], nextDate;
  for (i = 0; i < dates.length; i++) {
    nextDate = new Date(dates[i]);
    nextDate.setDate(nextDate.getDate() + 1);
    if (dates[i + 1] != TZR.isoDate(nextDate)) {
      periods.push([startDate, dates[i]]);
      startDate = dates[i + 1];
    }
  }
  return periods;
}

TZR.isoDate = function (date) {
  return date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
}

////////////////////////////////
TZR.updateModuleContainer=function(url,modulecontainer){
    if (modulecontainer == undefined)
      modulecontainer = jQuery('div.cv8-module-container:first');
    if (url == undefined || url.length==0)
      url=TZR._self;
  
    var id = modulecontainer.find('div[id^=cv8-uniqdiv]').attr('id').replace(/cv8-uniqdiv-/g, '');
    var current_tab = $('#tzr-tablist-'+id+' li.active').attr('id');
    var ssmoid = null;
    if (current_tab) {
      ssmoid = current_tab.replace(/^li-([0-9]+)-[0-9a-zA-Z]+$/g, '$1');
    }
    //Si l'action est edit et que des cases sont cochées dans l'onglet actif d'un sous module alors on fait une edition par lot des fiches du sous module. Sinon on edite la fiche en cours.
    if (url.indexOf('function=edit') > -1 && document.forms['editssmodform'+id+'-'+ssmoid] && TZR.checkBoxesIsChecked(document.forms['editssmodform'+id+'-'+ssmoid], 'selectstart_'+ssmoid, 'selectend_'+ssmoid, /_selected\[/)) {
      TZR.Table.applyfunction(id,"editSelection","",{ssmoid_editselection : ssmoid, template:"Module/Table.editSelection.html"},false,true);
    } else {
      TZR.jQueryLoad({target: modulecontainer, url: url});
    }
 }

/**
 * XSiteApp
 * Choix du sous-site administré
 * @constructor
 */
TZR.XSiteApp = function() {};

/**
 * Ecouter les choix de sous-site à administrer.
 * @param select: Element HTML select permettant le choix de l'APP
 */
TZR.XSiteApp.prototype.listenChoices = function(select) {
    var that = this;
    var $select = jQuery(select);

    $select.change(function(){
        var $option_selected = jQuery(jQuery('option:selected', $select));
        var topic_manager_moid = $option_selected.data('xmodinfotree_moid');
        that.setTopicManagerLink(topic_manager_moid);
        that.setForcedApp($select.val(), function(){
            that.refreshContents();
        });
    });

    // On affiche dés le départ un lien vers le gestionnaire de rubrique
    var $option_selected = jQuery(jQuery('option:selected', $select));
    var topic_manager_moid = $option_selected.data('xmodinfotree_moid');
    that.setTopicManagerLink(topic_manager_moid);
};

/**
 * Notifie à Seolan le choix de l'app.
 * @param app_koid: KOID de l'app choisis.
 * @param callback: callback executé après l'appel ajax et si il as réussis
 */
TZR.XSiteApp.prototype.setForcedApp = function (app_koid, callback) {
    jQuery.ajax({
        url: "admin.php?class=\Seolan\Module\Application\Application&function=setForcedApps&apps_koids[]="+app_koid,
        context: document.body
    }).done(callback);
};

/**
 * Rafraichissement des différentes zones rafraichissables
 */
TZR.XSiteApp.prototype.refreshContents = function () {
    jQuery('a.cv8-refresh').trigger('click');
};

/**
 * Permet l'affichage d'un lien vers le gestionnaire de rubrique correspondant à l'app choisis.
 * @param topic_manager_moid_to_show: moid du gestionnaire de rubrique correspondant au sous-site choisis.
 */
TZR.XSiteApp.prototype.setTopicManagerLink = function (topic_manager_moid_to_show) {
    jQuery('.cv8-subsite_current_topic_manager').remove();
    var module_link = "/csx/scripts/admin.php?_bdx=5_3&moid="+topic_manager_moid_to_show+"&function=home&tplentry=mit&template=Module/InfoTree.index.html";

    var topic_manager_selected_menu_element =
        '<span class="cv8-subsite_current_topic_manager">' +
            '<li class="line">&nbsp;</li>' +
              '<li id="node_CS8_sub_site_current_topic_manager_'+topic_manager_moid_to_show+'" data-oid="'+topic_manager_moid_to_show+'" class="doc">' +
                '<div style="position:absolute;">' +
                  '<div class="ico">' +
                  '</div>' +
                '</div>' +
                '<a href="'+module_link+'" onclick="home_viewmodule(this); return false;">Gestion des pages</a>' +
            '</li>' +
            '<li class="line-last"></li>' +
        '</span>'
    ;

    jQuery(topic_manager_selected_menu_element).insertAfter(jQuery('span#cv8-infotreemenu'));
};
// Label Field
TZR.FieldLabel = {};
TZR.FieldLabel.ckeditor = function(id, fname, config){
  var container = document.getElementById(id);
  var content = container.innerHTML;
  container.innerHTML = '';
  document.getElementById(id+"_unchanged").remove();
  jQuery(container).append("<textarea name='"+fname+"' id='ta"+id+"'>"+content+"</textarea>");
  document.getElementById("edit"+id).disabled = true;
  CKEDITOR.replace("ta"+id,config);
};

// Securisation des videos
TZR.XFileDef = {};

TZR.XFileDef.defaultOptions = {
  table : 'T009',
  field : 'media',
  urlAjax : '',
  element : ''
};

TZR.XFileDef.options = {};

TZR.XFileDef.secureVideo = function (params) {
  TZR.XFileDef.options = jQuery.extend({}, TZR.XFileDef.defaultOptions, params);

  var $element = jQuery(TZR.XFileDef.options.element);
  $element.on('play', function(event) {
    jQuery.ajax({
      url : TZR.XFileDef.options.urlAjax,
      async : false,
      data : {
        class : '\\Seolan\\Field\\File\\File',
        function : 'xfiledef_enablePlay',
        table : TZR.XFileDef.options.table,
        field : TZR.XFileDef.options.field
      },
      method : 'GET',
      success: function(data){
        if (data != 1) {
          $element.get(0).remove();
          $element.get(0).setSrc('');
        }
      }
    });
  });
};

// Crée une grille d'édition pour le champ de type Table.
TZR.SlickGrid = function(_fieldName, _columns, _data, _options, _pluginOptions) {
  // deep copy des données avec jQuery.extend pour éviter de modif les objet passés en paramètre
  var columns       = jQuery.extend(true, [], _columns);
  var data          = jQuery.extend(true, [], _data);
  var options       = jQuery.extend(true, {}, _options);
  var pluginOptions = jQuery.extend(true, {}, _pluginOptions);
  var fieldName     = _fieldName;
  var idGrid        = "grid_"+fieldName;
  var idDatas       = "datas_"+fieldName;
  var idContextMenu = "contextMenu_"+fieldName;
  var lastColNumber = columns.length;
  var grid;

  // Il faut au moins un container ayant pour id "grid_"+fieldName
  if(!jQuery("#"+idGrid).length) return;

  if(!options.length) {
    // Options générales
    options = {
      editable: true,
      enableCellNavigation: true,
      autoEdit: false,
      autoHeight: true,
      forceFitColumns: true,
    };
  }

  if(!pluginOptions.length) {
    // Options du plugin pour copier/coller depuis excel
    pluginOptions = {
      includeHeaderWhenCopying : false,
      bodyElement : document.getElementById(idGrid)
    };
  }

  options.editCommandHandler = function(item, column, editCommand){ grid.undoRedoBuffer.queueAndExecuteCommand.call(grid.undoRedoBuffer,editCommand); }
  pluginOptions.clipboardCommandHandler = function(editCommand){ grid.undoRedoBuffer.queueAndExecuteCommand.call(grid.undoRedoBuffer,editCommand); },

  grid = new Slick.Grid("#"+idGrid, data, columns, options);
  grid.setSelectionModel(new Slick.CellSelectionModel());
  grid.registerPlugin(new Slick.CellExternalCopyManager(pluginOptions));

  // Gestion du menu contextuel
  grid.onContextMenu.subscribe(function (e) {
    if(!jQuery("#"+idContextMenu).length) return;
    e.preventDefault();
    var cell = grid.getCellFromEvent(e);
    jQuery("#"+idContextMenu)
        .data("row", cell.row)
        .data("col", cell.cell)
        .css("top", e.pageY-jQuery('#'+idGrid).offset().top)
        .css("left", e.pageX-jQuery('#'+idGrid).offset().left)
        .show();
    jQuery("body").one("click", function () {
      jQuery("#"+idContextMenu).hide();
    });
  });

  // Gestion du tri
  grid.onSort.subscribe(function (e, args) {
    var field = args.sortCol.field;
    var sign = args.sortAsc ? 1 : -1;

    data.sort(function (dataRow1, dataRow2) {
      var value1 = dataRow1[field], value2 = dataRow2[field];
      return (value1 == value2 ? 0 : ((value1 > value2 ? 1 : -1) * sign));
    });

    grid.majDatas();
  });

  // Gestion de annuler/refaire
  grid.undoRedoBuffer = {
      commandQueue : [],
      commandCtr : 0,
      queueAndExecuteCommand : function(editCommand) {
        this.commandQueue[this.commandCtr] = editCommand;
        this.commandCtr++;
        editCommand.execute();
        grid.majDatas();
      },
      undo : function() {
        if (this.commandCtr == 0)
          return;

        this.commandCtr--;
        var command = this.commandQueue[this.commandCtr];

        if (command && Slick.GlobalEditorLock.cancelCurrentEdit()) {
          command.undo();
          grid.majDatas();
        }
      },
      redo : function() {
        if (this.commandCtr >= this.commandQueue.length)
          return;
        var command = this.commandQueue[this.commandCtr];
        this.commandCtr++;
        if (command && Slick.GlobalEditorLock.cancelCurrentEdit()) {
          command.execute();
          grid.majDatas();
        }
      }
  }

  // Gestion des racourcis claviers pour Annuler/Refaire et pour modifier une cellule sans avoir besoin de double cliquer
  jQuery("#"+idGrid).keydown(function(e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
      if (e.which == 90 && (e.ctrlKey || e.metaKey) && e.shiftKey) {
        // Ctrl + Shift + Z ==> Refaire
        grid.undoRedoBuffer.redo();
      }
      else if (e.which == 90 && (e.ctrlKey || e.metaKey)){
        // Ctrl + Z ==> Annuler
        grid.undoRedoBuffer.undo();
      }
    }
    else if(!Slick.GlobalEditorLock.isActive()) {
      // Si on presse une touche simple on edite la cellule en cours si on n'est pas déjà en train d'éditer
      grid.editActiveCell();
    }
  });

  // Actions du context menu
  jQuery("#"+idContextMenu).click(function (e) {
    var col = jQuery(this).data("col");
    var row = jQuery(this).data("row");

    switch(jQuery(e.target).attr("data")) {
      case "addCol" :
        lastColNumber++;
        var colToAdd = {id: "col"+lastColNumber, field: "col"+lastColNumber, editor: Slick.Editors.Text, name: ""};
        columns.splice(col+1, 0, colToAdd);
        data.forEach(function(e){e["col"+lastColNumber]=""});
        break;
      case "addRow" :
        var rowToAdd = jQuery.extend(true, {}, data[row]);
        for(i in rowToAdd) {rowToAdd[i] = '';}
        data.splice(row+1, 0, rowToAdd);
        break;
      case "delCol" :
        columns.splice(col, 1);
        break;
      case "delRow" :
        data.splice(row, 1);
        break;
      case "editCol" :
        return grid.editColumnName(col);
        break;
      case "undo" :
        grid.undoRedoBuffer.undo();
        break;
      case "redo" :
        grid.undoRedoBuffer.redo();
        break;
      default :
        return;
    }

    grid.majDatas();
  });

  // Modification du nom d'une colonne
  grid.editColumnName = function (col) {
    var container = jQuery("#"+idGrid+" .slick-header-column:nth-child("+(col+1)+") .slick-column-name");
    var val = container.html();
    container.html('');

    // On annule l'edit en cours et on empeche un autre edit
    Slick.GlobalEditorLock.cancelCurrentEdit();
    Slick.GlobalEditorLock.activate(grid.getEditController());

    var input = jQuery("<INPUT type=text class='editor-text' />")
      .appendTo(container)
      .bind("keydown.nav", function (e) {
        if (e.keyCode === jQuery.ui.keyCode.ENTER) {
          // Quand on appuie sur entree on sauvegarde la modif
          e.stopPropagation();
          e.preventDefault();
          columns[col].name = input.val();
          Slick.GlobalEditorLock.deactivate(grid.getEditController());
          grid.majDatas();
        }
      })
      .val(val)
      .focus()
      .select();

    jQuery("body").one("click", function () {
      jQuery("body").one("click", function() {
        // Quand on clique n'importe où, on sauvegarde la modif. On ignore le prmier click qui est appellé sur le context menu.
        columns[col].name = input.val();
        Slick.GlobalEditorLock.deactivate(grid.getEditController());
        grid.majDatas();
      });
    });
  }

  // Création des inputs hidden pour envoi du formulaire
  grid.majDatas = function (init) {
    grid.setColumns(columns);
    grid.setData(data);
    grid.render();

    if(!jQuery("#"+idDatas).length) jQuery("#"+idGrid).after('<div id="'+idDatas+'"></div>');

    jQuery("#"+idDatas).html('');
    data.forEach(function(row, i){
      columns.forEach(function(col, j) {
        jQuery("#"+idDatas).append('<input type="hidden" name="'+fieldName+'['+i+']['+j+']" value="'+row[col.id]+'">');
      });
    });
    columns.forEach(function(col, i) {
      jQuery("#"+idDatas).append('<input type="hidden" name="'+fieldName+'[_labels]['+i+']" value="'+col["name"]+'">');
    });

    if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields && !init) {
      TZR.Table.saveSomeLangsCheckFields(jQuery("#"+idDatas).find("input")[0]);
    }
  }

  return grid;
}
