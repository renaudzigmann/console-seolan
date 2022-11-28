//DEBUG
jQuery.ajaxSettings.cache=false;

var click_event;
click_event = (jQuery.support.touch ? "tap" : "click");
TZR.click_event = click_event; // = 'click'; ?

TZR.getModuleContainer = function(id) {
  var module = jQuery('#cv8-uniqdiv-'+id).parents('div.cv8-module:first')[0];
  return jQuery('div.cv8-module-container:first',module)[0];
};
//


 if (typeof(TZR.unloadMgt) == "undefined"){
  TZR.unloadMgt = {
    containers:[],
    unloadMessage:(typeof(TZR._unloadMessage) != "undefined")?TZR._unloadMessage:"\\o/",
    registerContainer:function(id, func, divid){
      if (!divid)
	divid = `cv8-uniqdiv-${id}`;
      this.containers.push({id:id,func:func,divid:divid,active:true});
    },
    removeRegistered:function(id){
      for(var i=0; this.containers[i].id!=id && i<this.containers.length; i++){}
      if (i<this.containers.length){
	this.containers.splice(i, 1);
      }
    },
    onBeforeUnloadPage:function(evt){
      if (!this.pageHasUpdates())
	return null;
      let mess = this.unloadMessage;
      evt.returnValue = mess;     // Gecko, Trident, Chrome 34+
      return mess;                // Gecko, WebKit, Chrome <34
    },
    pageHasUpdates:function(){
      let updated = false;
      for(let bu of this.containers){
	if (!bu.active)
	  continue;
	let elm = document.getElementById(bu.divid);
	if (elm){
	  updated = bu.func(bu.id);
	} else {
	  bu.active = false;
	}
      }
      return updated;
    },
    containerHasUpdates:function(uniqid){
      let updated = false;
      for(let bu of this.containers){
	if (!bu.active)
	  continue;
	if (bu.id === uniqid){
	  let elm = document.getElementById(bu.divid);
	  if (elm)
	    updated = bu.func();
	  break;
	}
      }
      return updated;
    },
    checkoutPageUnload:function(){
      if (!this.pageHasUpdates())
	return true;
      else
	return confirm(this.unloadMessage);
    },
    checkoutContainerUnload:function(uniqid){
      if (!this.containerHasUpdates(uniqid))
	return true;
      else
	return confirm(this.unloadMessage);
    }
  };
  TZR.onBeforeUnloadPage = function(evt){
    return TZR.unloadMgt.onBeforeUnloadPage.call(TZR.unloadMgt, evt);
  };
  window.addEventListener('beforeunload', TZR.onBeforeUnloadPage);
 }



//Tooltips and popovers
// =====================
TZR.initToolsTipsAndPopOvers = function(){
  if(jQuery('[data-toggle="tooltip"]').tooltip) {
    jQuery('[data-toggle="tooltip"]').tooltip();
  }
  if(jQuery('[data-toggle="popover"]').popover) {
    jQuery('[data-toggle="popover"]').popover();
  }
}
// =====================
//  -Menu
// =====================
TZR.Menu = new Object();
TZR.Menu.load = function(obj,force) {
  if (force === undefined){
    force=false;
  }
  var $this = $(obj);
  var target = $this.attr("data-target");
  if ($(target).children().length > 0 && !force) {
    return false;
  }
  TZR.jQueryLoad({url:$this.attr("data-url"), target:target, overlay:$this.attr("data-overlay")});
  return true;
};
TZR.Menu.inlineTreeRelocate = function(target,responseText,textStatus,XMLHttpRequest){
  $(target).after(responseText);
  $(target).empty();
};
TZR.Menu.inlineTreeLoad = function(obj) {
  var $this = $(obj);
  var target = $this.attr("data-target");
  var callback = $this.attr("data-cb") ? $this.attr("data-cb") : "TZR.Menu.inlineTreeRelocate";
  var callbackArgs = $this.attr("data-cbargs") ? $this.attr("data-cbargs") : target;
  TZR.jQueryLoad({url:$this.attr("data-url"), target:target, overlay:$this.attr("data-overlay"), cb:callback, cb_args:callbackArgs});
};
jQuery(document).ready(function($) {
    // MENU
    $("#main-nav").on(click_event, ".treeMenuAjax", function(e) {
      return TZR.Menu.load(this);
    });
    $("#main-nav .inlineTreeMenuAjax").each(function(e) {
      TZR.Menu.inlineTreeLoad(this);
    });
    var body, content, nav, nav_toggler;
    nav_toggler = $("header .toggle-nav");
    nav = $("#main-nav");
    content = $("#content");
    body = $("body");

    $("#main-nav").on(click_event, ".dropdown-collapse", function(e) {
      var link, childrenList;
      e.preventDefault();
      link = $(this);
      childrenList = link.closest("li").children("ul:first");
      parentLink = link.closest("ul").closest("li").children("a:first");

      //lien final: fermer tous les autres dropdown (limite: menu à 2 niveaux)
      if (childrenList.length == 0) {
        $("#main-nav").find('a.active').removeClass('active');
        $("#main-nav .dropdown-collapse.in").each(function() {
          if (link[0] == $(this)[0] || parentLink[0] == $(this)[0])
            return;
          var openedList = $(this).closest("li").children("ul:first");
          if (openedList.length == 0)
            return;
          $(this).removeClass("in");
          openedList.slideUp(300);
        });
        link.addClass("active");
        return;
      }
      //dropdown ouverte
      if (childrenList.is(":visible")) {
        if (body.hasClass("main-nav-closed") && link.parents("li").length === 1) {
          return false;
        } else {
          link.removeClass("in");
          link.removeClass("active");
          childrenList.slideUp(300);
        }
      }
      //dropdown fermée
      else {
        if (childrenList.parents("ul.nav.nav-stacked").length === 1) {
          $(document).trigger("nav-open");
        }
        $("#main-nav").find('a.active').removeClass('active');
        link.addClass("in");
        link.addClass("active");
        childrenList.slideDown(300);
      }
      return false;
    });
    if (jQuery.support.touch) {
      nav.on("swiperight", function(e) {
        return $(document).trigger("nav-open");
      });
      nav.on("swipeleft", function(e) {
        return $(document).trigger("nav-close");
      });
    }
    nav_toggler.on(click_event, function() {
      if (nav_open()) {
        $(document).trigger("nav-close");
      } else {
        $(document).trigger("nav-open");
      }
      return false;
    });
    $(document).bind("nav-close", function(event, params) {
      var nav_open;
      body.removeClass("main-nav-opened").addClass("main-nav-closed");
      return nav_open = false;
    });
    $(document).bind("nav-open", function(event, params) {
      var nav_open;
      body.addClass("main-nav-opened").removeClass("main-nav-closed");
      return nav_open = true;
    });

    function nav_open() {
      return $("body").hasClass("main-nav-opened") || $("#main-nav").width() > 50;
    };


    // -ToolbarX
    $('#content-wrapper').on(click_event,'div.toolbar-nav .btn', function(obj) {
      var $this = $(obj.currentTarget);
      if ($this.attr("data-url") == undefined) return;
        TZR.jQueryLoad({url:$this.attr("data-url"), cb:$this.attr("data-cb")});
    });

    // =====================
    $('#content-wrapper').on(click_event,'[data-toggle="tabajax"]', function(e) {
	TZR.Tabs.load($(this));
    });


    //Tooltips and popovers
  // =====================
  TZR.initToolsTipsAndPopOvers();
});

//=====================
//
//=====================
// Ajoute un item à un menu
TZR.toolBarX = {};
TZR.toolBarX.initMenu = function(menubarid){
  jQuery('#'+menubarid+' .btn-group.btn-group-dropdown-menu').each(function(i,o) {
    // groupe seul
    if (jQuery("ul.dropdown-menu li", o).length == 0) {
      jQuery(o).remove();
    }
    // groupe composé
    jQuery("ul.btn-group-ul>li", o).each(function(i, o){
      if (jQuery("ul.dropdown-menu li", o).length == 0) {
	jQuery(o).remove();
      }
    });
  });
};
TZR.toolBarX.addMenuItem=function(mid,group,text,url,conf,newgroup,separator,target,shortkey){
  var datashortkey = '';
  if (!shortkey){
    datashortkey = shortkey = '';
  } else {
    datashortkey = 'data-shortkey="'+shortkey+'"';
    shortkey = ' ('+shortkey+')';
  }
  var li='<li';
  var _class="";
  // a voir ... cf en dessous
  if(separator) _class+=' jd_separator';
  if(_class!="") li+=' class="'+_class.substr(1)+'"';
  if(url=='#' && newgroup != undefined) {
    li+=' id="'+mid+'-'+newgroup+'" class="dropdown-header" ';
  }
  li+='>';
  // Echappe la confirmation
  if(conf) conf=conf.replace(/\'/g,'\\\'').replace(/\"/g,'&quot;');
  // Creation du lien
  if(url==undefined){
    li+=text;
  }else if(url=='#' || newgroup){
    li+='<span>'+text+'</span>';
  }else if(url.substr(0,11)=='javascript:'){
    url=url.replace("javascript:","").replace(/\"/g,'&quot;');
    li+='<a '+datashortkey+' class="accessible" href="#" onclick="'+(conf?'if(!confirm(\''+conf+'\')) return false;':'')+url+'return false;">'+text+'</a>';
  }else{
    li+='<a'+datashortkey+' '+(target?' target="'+target+'"':'')+' class="'+(target?'':'accessible cv8-ajaxlink')+'" href="'+url+'"'+(conf?' x-confirm="var ret=confirm(\''+conf+'\')"':'')+'> '+text+'</a>';
  }
  li+='</li>';
  var p = jQuery('#'+mid+'-'+group);
  if (p.is('ul')){
    jQuery(p).append(li);
  } else {
    jQuery(li).insertAfter(p).addClass('cs-menu-subitem');
  }
};
TZR.toolBarX.addToolBarItem=function(tid,group,order,picto, shortkey){
  var datashortkey = '';
  if (!shortkey){
    datashortkey = shortkey = '';
  } else {
    datashortkey = 'data-shortkey="'+shortkey+'"';
    shortkey = ' ('+shortkey+')';
  }
  if(document.getElementById(tid+'-'+group)==undefined){
    jQuery('#'+tid).append('<div class="btn-group" role="group" id="'+tid+'-'+group+'"></div>');
  }else{
    jQuery('#'+tid+'-'+group).show();
  }
  var li=picto;
  var added=false;
  var jul=jQuery('#'+tid+'-'+group);
  jul.find('button').each(function(){
      var that = jQuery(this);
      if(parseInt(that.attr('morder'))>order){
	  that.before(li);
	  added=true;
	  return false;
      }
  });
  if(!added) jul.append(li);
};
//======================
//Tabs
//======================
TZR.Tabs = new Object();
TZR.Tabs.showTab = function($anchor){
    $anchor.tab('show');
};
TZR.Tabs.loadBy = function(uniqid, selector, options){
  var anchor = null;
    if (typeof(selector) == "string" && !(/^[0-9]+$/.test(selector))){
    var anchor = jQuery("#tzr-tablist-"+uniqid+" a[data-tabname='"+selector+"']");
  } else {
    var anchor = jQuery("#tzr-tablist-"+uniqid+">ul>li:nth-child("+selector+")>a");
  }
  if (anchor && anchor.length){
    TZR.Tabs.load(anchor, options);
  }
};
TZR.Tabs.load = function($anchor, options){
  options = jQuery.extend({refresh:false,cb:null,cbargs:null}, options);
  var target = $anchor.attr("href");
  if (!options.refresh && $(target).children().length > 0 ) {
    $anchor.tab('show');
    return;
  }
  var callback = TZR.Tabs.showTab;
  var callbackArgs = $anchor;
  if ($anchor.attr("data-cb"))
    callback = $anchor.attr("data-cb");
  if ($anchor.attr("data-cbargs"))
    callbackArgs = $anchor.attr("data-cbargs");
  if (options.cb !== null)
    callback = options.cb;
  if (options.cbargs !== null)
    callbackArgs = options.cbargs;

  TZR.jQueryLoad({url:$anchor.attr("data-url"),
		  target:target,
		  overlay:$anchor.attr("data-overlay"),
		  cb:callback,
		  cb_args:callbackArgs});
  //cb: add tooltips?
};
TZR.Tabs.refreshTitle = function(tabid, title){
    jQuery("a[href='#"+tabid+"']").text(title);
};
TZR.Tabs.activeTabUrl = function($anchor){
  return "_tabs="+escape(this.activeTag($anchor).data("tabname"));
};
TZR.Tabs.activeTag = function($anchor){
  return jQuery("ul>li.active>a", $anchor);
};
//Locales / labels en js
TZR.Locales = {};
TZR.Locales.labels = {};
TZR.Locales.addLabels = function(labels){
    for(var i=0; i<labels.length; i++){
	this.labels[labels[i][0]+'.'+labels[i][1]]=labels[i][2];
    }
};
TZR.Locales.getLabel = function(name, lang){
    if (!lang)
	lang = TZR._lang_user;
    if (typeof(this.labels[lang+'.'+name]) != "undefined"){
	return this.labels[lang+'.'+name];
    } else {
	return name;
    }
};

//DateRangePicker requires bootstrap daterangepicker
// =====================
try{
    if (typeof(BSdaterangepicker_locale) == 'undefined')
       BSdaterangepicker_locale = {};
}catch(e){}
TZR.Daterangepicker = {locales:{}};
TZR.Daterangepicker.getLocales = function(code){
  if (typeof(this.locales[code])!="undefined"){
    return this.locales[code];
  }
  else {
    return this.locales['FR'];
  }
};
TZR.Daterangepicker.getInterVal = function(picker){
  var fmt = picker.locale.format;
  return picker.startDate.format(fmt)+picker.locale.separator+picker.endDate.format(fmt);
};
TZR.Daterangepicker.apply = function(elm, ev, picker){
  jQuery(elm).val(this.getInterVal(picker));
}
TZR.Daterangepicker.cancel = function(elm, ev, picker){
  jQuery(elm).val('');
}
TZR.Daterangepicker.queryRanges = function(){
    ranges = {};
    ranges[TZR.Locales.getLabel('Seolan_Field_Date_Date.today')]=[moment(), moment()];
    ranges[TZR.Locales.getLabel('Seolan_Field_Date_Date.yesterday')] = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
    ranges[TZR.Locales.getLabel('Seolan_Field_Date_Date.last7days')] = [moment().subtract(6, 'days'), moment()];
    ranges[TZR.Locales.getLabel('Seolan_Field_Date_Date.last30days')] = [moment().subtract(29, 'days'), moment()];
    ranges[TZR.Locales.getLabel('Seolan_Field_Date_Date.currentmonth')] = [moment().startOf('month'), moment().endOf('month')];
    return ranges;
};
TZR.Daterangepicker.init = function(elem, config) {
    if (!jQuery().daterangepicker) {
      console.log("missing daterangepicker!");
      return;
    }
    if (config){
      if (typeof(config.locale) == "string" ){
	config.locale = TZR.Daterangepicker.getLocales(config.locale);
      }
      jQuery(elem).daterangepicker(config);
      elem.on('apply.daterangepicker', function(ev, picker){
	TZR.Daterangepicker.apply.call(TZR.Daterangepicker, this, ev, picker)
      });
      elem.on('cancel.daterangepicker', function(ev, picker){
	TZR.Daterangepicker.cancel.call(TZR.Daterangepicker, this, ev, picker)
      });
    } else {
      jQuery(elem).daterangepicker(BSdaterangepicker_locale);
    }
};

//==========================================
// Login, access control, "redirection" ajax
//==========================================
// gestion des redirections (ex : liens voir une fiche de la fonction avertir / envoyer à, quand on a &gopage dans l'url
TZR.handleRequestedPage = function(){
  if (TZR._request.gopage != null && !top.location.hash){
    TZR.jQueryLoad({target:'#cv8-content',url:TZR._request.gopage});
  }
  /* bloc original à voir dans : a5a7644319f42392c3e643b745f6e85188cf47e9 et generic8.html de v8
     <%if $xuser->_cur.bohome%>
     if(!top.location.hash) home_viewpage('<%$xuser->_cur.bohome%>');
     <%/if%>
   */
};
// refresh global variables and topbar elements
TZR.authReconnect = function(){
  jQuery.get(TZR._self,
	     {"skip":1,
	      "_mime":"application/json",
	      "_ajax":1,
	      "function":"portail",
	      "template":"Core.layout/top/reconnexion.json",
	      "moid":TZR._sysmods_xmodadmin},
	     TZR.refreshConnexion,
	     "json");
};
TZR.refreshConnexion = function(data, status){
  if (typeof data._infotreeadmin_sec != "undefined"){
    TZR._infotreeadmin_sec = data._infotreeadmin_sec;
  }
  if (typeof data.profile != "undefined"){
    jQuery("#cvx-user-profile").html(data.profile);
  }
};
// redirection CAS (en cas de perte de session, ? pack)
TZR.AuthCAS = {
  authAlert:function(options,cb){
    document.location=options.url;
  }
};
// popup login/password for reconnexion
TZR.authAlert = function(cb){
  if (TZR._authMngt != null){
    TZR.executeFunctionByName(TZR._authMngt+".authAlert", window, TZR._authMngtOptions, cb)
    return;
  }
  var alertArgs = Array.from(arguments);
  alertArgs.shift();
  var authModal = jQuery("#cvx-auth");
  /* suppress previously registerer handlers - case 'goto page' for instance  */
  authModal.off('hidden.bs.modal');
  /* process initial action on modal hide */
  authModal.on('hidden.bs.modal', function () {
    if(authModal.data('callcb') && cb){
      var args = alertArgs;
      cb.apply(TZR,args);
    }
    authModal.data('callcb',false);
  });
  authModal.modal('show');
};
// login form submission, console reconnexion, interrupted action continuation (on modal hide)
TZR.submitLogin=function(form){
  jQuery.post(form.action,jQuery(form).serializeArray(),function(data,status){
    if(data=='ok'){
      jQuery("#cvx-auth-error").css('visibility','hidden');
      TZR.authReconnect();
      jQuery("#cvx-auth").data('callcb',true);
      jQuery("#cvx-auth").modal('hide');
    }else{
      var errmess =  jQuery("#cvx-auth-error");
      var res = /nok\[\[(.+)\]\]/.exec(data);
      if (res != null && res[1]){
	errmess.html(res[1]).css('visibility','visible');
      } else {
	errmess.html(errmess.data('default')).css('visibility','visible');
      }
    }
  });
};
//============================
// Doubles selects, compléments
// la reste est dans TZR.doubleAdd etc
//============================
TZR.DoubleBox = {
  initFilter : function(elm){
    elm.on('change keyup search',function(evt) {
      TZR.DoubleBox.filter.call(TZR.DoubleBox, jQuery(this), evt);
    });
  },
  filter : function(src, evt){
    var select = jQuery(src.data("doublebox-selector"),src.parents("table.doublebox"));
    var text = src.val().toLowerCase();
    var exp = new RegExp(text,'i');
    select.find("option").hide().filter(function(){
      var opt = jQuery(this);
      return (exp.test(opt.val()) || exp.test(opt.html()))
    }).show();
  }
};
//=================
//Liens en treeview
//=================
if (typeof(TZR.xlinkdef_treeview) == "undefined"){
  TZR.xlinkdef_treeview = {
    init:function(selector, namefield, selected, selectedLabels, maxdepth, options){
      var container = jQuery(selector);
      var that=this;
      container.addClass("xlinkdef_treeview");
      container.data("selected", []);
      container.data("namefield", namefield);
      container.data("list", container.prev());
      container.data("maxdepth", maxdepth);
      container.data("options", options);
      for(i in selected){
	this.addSelected(selected[i], selectedLabels[i], container);
      }
      container.simpleTree({
	autoclose:false,
	drag:false,
	animate:true,
	closeroot:true,
	afterClick:function(node){
	  that.afterClick.call(that, node, container);
	},
	afterAjax:function(node){
	  that.afterAjax.call(that, node, container);
	}
      });
    },
    unselectItem:function(item){
      var container = item.parent().next();
      var data = item.data();
      this.deleteItem(data.oid, container);
    },
    deleteItem:function(oid, container){
      var selected = container.data('selected');
      selected.splice(selected.indexOf(oid), 1);
      jQuery('li[data-value="'+oid+'"] span>span', container).removeClass('selected');
      jQuery('input[value="'+oid+'"]', container).remove();
      jQuery('div[data-oid="'+oid+'"]', container.data('list')).remove();
      container.data('selected', selected);
    },
    prepareAdd:function(container){
      var options = container.data("options");
      if (options.multivalued != "1"){
	container.data("selected").map((oid)=>{
	  this.deleteItem(oid, container);
	});
      }
    },
    addSelectedFromAutoCompletion:function(oid, label, container){
      if (!this.isSelectedOid(oid, container)){
	this.addSelected(oid, label, container);
      }
    },
    addSelected:function(oid, label, container){
      this.prepareAdd(container);
      var button=" <button onclick=\"TZR.xlinkdef_treeview.unselectItem.call(TZR.xlinkdef_treeview, jQuery(this).parent());\" class=\"btn btn-default btn-md btn-inverse\" type=\"button\"><span class=\"glyphicon csico-delete\" aria-hidden=\"true\"></span></button>";
      var input = TZR.sprintf("<input name=\"%s[]\" type=\"hidden\" value=\"%s\">", container.data('namefield'), oid);
      jQuery(container.data('list')).append(TZR.sprintf("<div data-oid=\"%s\">%s %s %s</div>", oid, button, label, input));
      container.data('selected').push(oid);
      jQuery('li[data-value="'+oid+'"] span>span', container).addClass('selected');
    },
    afterAjax:function(node, container){
      var selected = container.data('selected');
      jQuery("span:first span:first", node.parent()).addClass("hselected");
      jQuery('li[data-type="leaf"]', node).each(function(i, o){
	var oid = jQuery(o).data('value');
	var li = jQuery(this);
	if (selected.indexOf(oid)!=-1){
	  jQuery("span:first span:first", li).addClass("selected");
	}
      });
      var pli = node.parent();
      if (!pli.hasClass("folder-loaded"))
	pli.addClass('folder-loaded');
    },
    afterClick:function(node,container){
      var span = jQuery("span:first span:first",node);
      if (node.data("type") == "leaf"){
	if (!this.isSelected(node, container)){
	  this.addSelected(node.data('value'), span.html(), container);
	} else {
	  this.deleteItem(node.data("value"), container);
	}
      } else {
	if (!node.hasClass("folder-loaded")){
	  node.addClass('folder-loaded');
	  var that = this;
	  setTimeout(function(){
	    jQuery("img.trigger", node).trigger('click');
	  }, 100);
	} else {
	  var that = this;
	  if (node.data("level") < container.data("maxdepth")){
	    jQuery("img.trigger:first", node).trigger("click");
	  } else if ((node.hasClass('folder-close') || node.hasClass('folder-last'))&& node.hasClass('folder-loaded')){
	    jQuery("img.trigger:first", node).trigger("click");
	  } else {
	    jQuery("span:first span:first", node).toggleClass('folder-selected');
	    var sel = jQuery("span:first span:first", node).hasClass('folder-selected');
	    jQuery('ul>li[data-type="leaf"]', node).each(function(i, o){
	      var li = jQuery(o);
	      if (sel && !that.isSelected(li, container)){
		that.addSelected(li.data('value'), li.find("span:first span:first").html(), container);
	      } else {
		that.deleteItem(li.data('value'), container);
	      }
	    });
	  }
	}
      }
    },
    isSelectedOid(oid, container){
      return container.data('selected').indexOf(oid) !== -1;
    },
    isSelected(leaf, container){
     return this.isSelectedOid(leaf.data('value'), container);
    },
    isValid:function(id, fmt, color){
      var container = jQuery(`#${id}tree`);
      var selected = container.data("selected").join();
      var exp = new RegExp(fmt);
      if (!exp.test(selected)){
	TZR.setElementErrorState(jQuery(container).parent(),false,color);
	TZR.isFormOk = false;
	return false;
      }
      return true;
    },
    getFormInput:function(id){
      var container = jQuery(`#${id}tree`);
      var fieldname = container.data('namefield')
      var o = jQuery(`#${id}selected>input[name="${fieldname}"]`);
      return o[0]; // native element
    },
    addAutoComplete:function(varid){
      TZR.addAutoComplete(varid);
    },
    autoComplete:function(fromvarid, oid, label){
      var datatree = jQuery(`#${fromvarid}`).data("tree");
      var container = jQuery(`#${datatree.varid}`);
      TZR.xlinkdef_treeview.addSelectedFromAutoCompletion.call(TZR.xlinkdef_treeview,
					     oid,
					     label,
					     container);
    }
  };
};

//=================
// miscellaneous
//=================
TZR.copy2clipboard = function(text, mess){
    try{
	var el = jQuery('<input style="position:absolute;">');
	el.val('<a href="'+text+'">Document</a>').appendTo('body').select();
	document.execCommand("copy");
	el.remove();
	if (mess){
	    alert(text+"\n\n"+mess);
	}
    }catch(e){}
}

TZR.copyHTML2Clipboard = function(html, el) {
    var tmpEl;
    if (typeof el !== "undefined") {
        // you may want some specific styling for your content - then provide a custom DOM node with classes, inline styles or whatever you want
        tmpEl = el;
    } else {
        // else we'll just create one
        tmpEl = document.createElement("div");

        // since we remove the element immedeately we'd actually not have to style it - but IE 11 prompts us to confirm the clipboard interaction and until you click the confirm button, the element would show. so: still extra stuff for IE, as usual.
/*        tmpEl.style.opacity = 0;
        tmpEl.style.position = "absolute";
        tmpEl.style.pointerEvents = "none";
        tmpEl.style.zIndex = -1;*/
    }

    // fill it with your HTML
    tmpEl.innerHTML = html;

    // append the temporary node to the DOM
    document.body.appendChild(tmpEl);

    // select the newly added node
    var range = document.createRange();
    range.selectNode(tmpEl);
    window.getSelection().addRange(range);

    // copy
    document.execCommand("copy");

    // and remove the element immediately
    document.body.removeChild(tmpEl);

    alert("Copie terminée");
}



TZR.updateModulePath = function(id, paths, options){
    options = jQuery.extend({}, {full:false}, options);
    var container = jQuery("#chemin"+id+">ol");
    if (options.full){
	container.html("");
    } else {
	var lang = jQuery(">li", container).filter(':nth-child(1)').clone();
	var module = jQuery(">li", container).filter(':nth-child(2)').clone();
	container.html("");
	container.append(lang);
	container.append(module);
    }
    for(var i=0; i<paths.length; i++){
	container.append("<li>"+paths[i]+"</li>");
    }
};
TZR.applyFunction=function(form,data,selectonly,conf,popup){
  if(typeof(form)=="string") form=document.forms[form];
  if(selectonly){
      if(!TZR.checkBoxesIsChecked(form, false, false, /_selected\[/)){
	TZR.alert(TZR._noobjectselected, '');
	return;
      }
  }
  if(conf){
    if(!confirm(conf)) return false;
  }
  if(data){
    for(var attr in data){
      if(form[attr]) form[attr].value=data[attr];
      else jQuery(form).append('<input type="hidden" name="'+attr+'" value="'+data[attr]+'">');
    }
  }
  if (popup != undefined && typeof popup == 'object' && popup.dialog == true){
    TZR.Dialog.openFromForm(form, data, popup.options /* id et options */);
  }else if(popup){
    window.open('','tzrpopup','width=650,height=550,scrollbars=yes,location=no,resizable=yes');
    form.target='tzrpopup';
    form.method='post';
    return form.submit();
  }else{
    return TZR.ajaxSubmitForm(form);
  }
}
//=================
// Modals & Dialogs
//=================
TZR.Dialog = {
  modalId:"#cs-default-dialog",
  currentId:function(){
    return jQuery(this.modalId).data('id');
  },
  getContents:function(url, data, options){
    options = jQuery.extend({overlay:null}, options);
    if (options.overlay == "auto")
      options.overlay = this.modalId+" .modal-content";
    url += "&_dialogid="+options.id;
    TZR.jQueryAjax({
      url:url,
      data:data,
      mode:'post',
      nocache:true,
      noautoscroll : true,
      overlay:options.overlay,
      cb:function(html, status, xhr){
	  // context = TZR.Dialog
          this.show(html, options);
	},
      cb_context:TZR.Dialog
    });
  },
  hideActions:function(){
    jQuery(this.modalId+" div.modal-footer>div.tzr-action").hide();
  },
  showActions:function(){
    jQuery(this.modalId+" div.modal-footer>div.tzr-action").show();
  },
  setTitle:function(elm, titleElm){
    var newTitle = jQuery("div.title", elm).detach();
    titleElm.html(newTitle.html());
  },
  changeTitle:function(title){
    var titleElm = jQuery(".modal-header>h4", jQuery(this.modalId));
    titleElm.html(title);
  },
  setFooter:function(elm, footerElm){
    var newActions = jQuery("div.tzr-action", elm).detach();
    footerElm.append(newActions);
  },
  getOptions:function(){
    return jQuery(this.modalId).data('_options');
  },
  show:function(html, options){

    options = jQuery.extend({sizeClass:'modal-lg', initCallback:null, closeCallback:null, backdrop:'static', allowMove:true}, options);

    var elm = jQuery(this.modalId);

    elm.data('id', options.id);
    elm.data('_options', options);

    //reset des classes à la valeur par defaut, modal-lg modal-sm modal-md
    jQuery('div.modal-dialog', elm).attr("class", "modal-dialog");
    jQuery('div.modal-dialog', elm).addClass(options.sizeClass);

    // les containers
    var bodyElm = jQuery("div.modal-body", elm);
    var titleElm = jQuery(".modal-header>h4", elm);
    var footerElm = jQuery(".modal-footer", elm);

    bodyElm.html('');
    titleElm.html('');
    footerElm.html('');

    // le body
    bodyElm.html(html);
    // initialisation des contenus
    if (options.initCallback != null){
      if (typeof(options.initCallback) == "function"){
	options.initCallback();
      } else {
	TZR.executeFunctionByName(options.initCallback._function, window, [options.initCallback._param]);
      }
    }

    this.setTitle(elm, titleElm);

    this.setFooter(elm, footerElm);

    elm.modal({backdrop:options.backdrop,show:true});

    elm.off('hide.bs.modal', TZR.Dialog.onClose);
    if (options.closeCallback != null){
      elm.on('hide.bs.modal', {closeCallback:options.closeCallback},  TZR.Dialog.onClose);
    } else {
      elm.on('hide.bs.modal', {closeCallback:null},  TZR.Dialog.onClose);
    }

    elm.modal('show');

    if (options.allowMove){
      jQuery(".modal-header", elm).css('cursor','move');
      elm.draggable({handle: "div.modal-header"});
    }

  },
  openFromAnchor:function(srcElm){
    var elm = jQuery(srcElm);
    var url = elm.data("url");
    var options = elm.data("options");
    var data =  elm.data("parameters");
    return this.openURL(url, data, options);
  },
  openURL:function(url, data, options){
    data = jQuery.extend({}, data);
    options = jQuery.extend({}, options);
    this.getContents(url, data, options);
    return false;
  },
  openFromForm:function(form, data, options){
    data = jQuery.extend({}, data);
    options = jQuery.extend({}, options);
    var jform = jQuery(form);
    var formValues = jform.serializeArray();
    for(var i in formValues){
      var pn = formValues[i]["name"];
      if (typeof(data[pn]) != "undefined"){
	formValues[i]["value"] = data[pn];
      }
      // ? ajouter les autres paramètres ?
    }
    this.getContents(TZR._self, formValues, options);
  },
  closeDialog:function(){
    jQuery(this.modalId).modal('hide');
  },
  onClose:function(event){
    if (typeof event.data != "undefined" && typeof event.data.closeCallback != "undefined" && event.data.closeCallback != null){
      if (typeof event.data.closeCallback == 'function')
	event.data.closeCallback(event.data.closeCallback._param);
      else
	  TZR.executeFunctionByName(event.data.closeCallback._function, window, [event.data.closeCallback._param]);
    }
    // permettre le garbage TZR sur id du container : on vide
    var elm = jQuery(TZR.Dialog.modalId);
    jQuery("div.modal-body", elm).html('');
  },
  setData:function(data){
    jQuery(this.modalId).data(data);
  },
  getData:function(){
    return jQuery(this.modalId).data();
  }
};
TZR.Modal = {
  dismissAll:function() {
    //seul le hash de la selection apparait et permet d'implémenter le forward
    if (top.location.hash != TZR.Modal.alert.elem)
      jQuery(TZR.Modal.alert.elem).modal('hide');
    else
      jQuery(TZR.Modal.alert.elem).modal('show');
    if (top.location.hash != TZR.Modal.confirm_delete.elem)
      jQuery(TZR.Modal.confirm_delete.elem).modal('hide');
    else
      jQuery(TZR.Modal.confirm_delete.elem).modal('show');
    if (top.location.hash != TZR.Modal.auth.elem)
       jQuery(TZR.Modal.auth.elem).modal('hide');
    else
      jQuery(TZR.Modal.auth.elem).modal('show');
    if (top.location.hash != TZR.SELECTION.Modal.elem)
      jQuery(TZR.SELECTION.Modal.elem).modal('hide');
    else
      jQuery(TZR.SELECTION.Modal.elem).modal('show');
    if (top.location.hash.slice(0, TZR.Modal.selectedFields.startId.length) != TZR.Modal.selectedFields.startId)
      jQuery(TZR.Modal.selectedFields.elem).modal('hide');
    else
      jQuery(TZR.Modal.selectedFields.elem).modal('show');
  },
  config:function(modalId,action,args,title,message) {
    var m = jQuery('#'+modalId);
    // reset functionnal class (modal-export for instance)
    jQuery('div.modal-dialog', m).attr("class", "modal-dialog");
    m.find('.modal-footer .cvx-confirm').data('args', args);
    m.find('.modal-footer .cvx-confirm').data('action', action);
    if (title)
      m.find('.modal-title').text(title);
    else
      m.find('.modal-title').text('');
    if (message){
      if (m.find('.modal-body p').length>0){
	m.find('.modal-body p').html(message);
      } else {
	m.find('.modal-body').html(message);
      }
    }
  },
  show:function(e, modal) {
    TZR.Modal.relatedTarget = null;
    var modalId = jQuery(modal).attr("id");
    var relatedTarget = jQuery(e.relatedTarget);
    if (relatedTarget != undefined && relatedTarget.data('action') != undefined) {
      var message = relatedTarget.data('message');
      var title = relatedTarget.data('title');
      var args = relatedTarget.data('args');
      var action = relatedTarget.data('action');
      TZR.Modal.config(modalId,action,args,title,message);
      TZR.Modal.relatedTarget = relatedTarget;
    } else if (jQuery(modal).find('.modal-footer .cvx-confirm').data('action') === undefined) {
      e.preventDefault();
      e.stopImmediatePropagation();
      return false;
    }
    jQuery('#'+modalId+' .modal-footer .cvx-confirm').off().on(click_event, function(e) {
      var actionFunction = jQuery(this).data('action');
      if (typeof actionFunction == 'function'){
	actionFunction(jQuery(this).data('args'));
	return;
      }
      var args = [jQuery(this).data('action'),window];
      // data-args = "a1,a2,....,an" ou un objet
      var modalArgs = jQuery(this).data('args');
      if (typeof(modalArgs) == "string"){
	args = args.concat(modalArgs.split(","));
      } else if (Array.isArray(modalArgs)){
	args = args.concat(modalArgs);
      } else {
	args.push(modalArgs);
      }
      if (relatedTarget != undefined && relatedTarget.length > 0)
	args.push(relatedTarget);
      // args : functionName, context, args
      TZR.executeFunctionByName.apply(this,args);
    });
  },
  callerAction:function(e){
    var relatedTarget = TZR.Modal.relatedTarget;
    var args = [jQuery(this).data('action'),window];
    var modalArgs = jQuery(this).data('args');
    // data-args = "a1,a2,....,an" ou un objet
    if (typeof(modalArgs) == "string"){
      args = args.concat(modalArgs.split(","));
    } else {
      args.push(modalArgs);
    }
    if (relatedTarget != undefined && relatedTarget.length > 0)
      args.push(relatedTarget);
    TZR.executeFunctionByName.apply(this,args);
  },
  initialize:function(){
    //modale non incluse dans le main => delegue
    jQuery('#cv8-content').on('show.bs.modal', TZR.Modal.selectedFields.elem, function(e) {
      TZR.Modal.show(e,this);
    });
    var deleteModal = jQuery(TZR.Modal.confirm_delete.elem).on('show.bs.modal', function(e) {
      TZR.Modal.show(e,this);
    });
    jQuery('.modal-footer .cvx-confirm', deleteModal).on('click', TZR.Modal.callerAction);
  }
};
TZR.Modal.confirm_delete = {
  elem:'#cvx-confirm-delete',
  config:function(action,args,title,message) {
    TZR.Modal.config("cvx-confirm-delete",action,args,title,message);
  },
  show:function() {
    jQuery(TZR.Modal.confirm_delete.elem).modal('show');
  }
};
TZR.Modal.alert = {
  elem:'#cvx-alert-message',
  config:function(title,message) {
    TZR.Modal.config("cvx-alert-message",null,null,title,message);
  },
  show:function() {
    jQuery(TZR.Modal.alert.elem).modal('show');
  }
};
TZR.alert = function(message, title){
  TZR.Modal.alert.config(title, message);
  TZR.Modal.alert.show();
};
TZR.Modal.auth = {
  elem : '#cvx-auth',
  errorelem : '#cvx-auth-error'
};
/// Modale "generique" de confirmation
TZR.Modal.Confirm = function(title, message, options){
  this.id = "cvx-confirm-generic";
  this.elem = jQuery("#"+this.id);
  this.options = options;
  var that = this;
  this.config = function(title,message,options) {
    if (title){
      this.elem.find('.modal-title').text(title);
    }
    if (message){
      if (this.elem.find('.modal-body p').length>0){
	this.elem.find('.modal-body p').hml(message);
      } else {
	this.elem.find('.modal-body').html(message);
      }
    }
  };
  this.done = function(event){
    if (typeof(this.options.done) == "function"){
      this.options.done(this.options);
    }
  };
  this.canceled = function(event){
    if (typeof(this.options.canceled) == "function"){
      this.options.canceled(this.options);
    }
  };
  this.show = function(){
    jQuery("#"+that.id).off('show.bs.modal.tzr-confirm').on('show.bs.modal.tzr-confirm', function(){
      if (typeof(that.options.done) == "function"){
	jQuery('.modal-footer .cvx-confirm', that.elem).off();
	jQuery('.modal-footer .cvx-confirm', that.elem).on(TZR.click_event+".tzr-confirm", function(e) {
	  that.done(e);
	});
      }
      if (typeof(that.options.canceled) == "function"){
	jQuery('.modal-footer .cvx-cancel', that.elem).off();
	jQuery('.modal-footer .cvx-cancel', that.elem).on(TZR.click_event+".tzr-cancel", function(e) {
	  that.canceled(e);
	});
      }
    });
    jQuery("#"+that.id).modal('show');
  };

  this.config(title,message,options);

};

TZR.Modal.selectedFields = {};
//id = cvx-selectedFields+uniqid, il peut y avoir potentiellement plusieurs modales
TZR.Modal.selectedFields.elem = '.cvx-selectedFields';
TZR.Modal.selectedFields.startId = '#cvx-selectedFields';

//========
//image edit
//=========
TZR.imageEditorOpen = function(params){
    var jthumb = jQuery("#"+params.varid+"-img");
    jthumb.data('xparams', params);
    if ((workingcopy = jthumb.data('xworkingcopy'))){
	params.urlImage = params.downloader+workingcopy+"&_"+escape(new Date().getTime());
    } else {
	params.urlImage += "&_"+escape(new Date().getTime());
    }
    jQuery().imageEditor(params);
};

TZR.imageEditorUploaded = function(data, varid){
    var data = jQuery.parseJSON(data);
    jQuery("#"+varid+"-updated").val('yes');
    var jthumb = jQuery("#"+varid+"-img");
    var params = jthumb.data("xparams");
    jthumb.attr('src', params.downloader+data.thumb+"&_"+escape(new Date().getTime())).data('xworkingcopy', data.tmp);
    jQuery().imageEditor('hide');
};
//========================
// initialisations modales
//========================
jQuery(document).ready(function($) {
  TZR.Modal.initialize();
});

jQuery( document ).ajaxComplete(function(event, xhr, settings) {
  TZR.initToolsTipsAndPopOvers();
  //DateRangePicker requires bootstrap daterangepicker
  // =====================
try{
  jQuery('[data-toggle="bs-daterangepicker"]').each( function() {
    TZR.Daterangepicker.init(this);
  });
}catch(e){}
});
//====================
// raccourcis clavier
//====================
TZR.ShortKeys = {
  handleKey : function(event, vuniqid){
    var eventkeys = "";
    if (event.ctrlKey === true)
      eventkeys += "CTRL-"
    if (event.altKey === true)
      eventkeys += "ALT-";
    eventkeys += event.key.toUpperCase();
    if (typeof(vuniqid.shortkeys[eventkeys]) !== "undefined"){
      vuniqid.shortkeys[eventkeys].anchor.trigger('click');
    }
  },
  init : function(vuniqid){
    if (typeof(vuniqid.shortkeys) == "undefined")
      vuniqid.shortkeys = {};
    // fait ainsi shortkeys possibles dans le contenu module
    var activate = false;
    jQuery("[data-shortkey]", jQuery(vuniqid.module)).each(function(i, o){
      var jitem = jQuery(o);
      var sk = jitem.data('shortkey').toUpperCase();
      vuniqid.shortkeys[sk] = {shortkeys:sk.split("-"),anchor:jitem};
      activate = true;
    });
    if (activate === true){
      var jmc = jQuery("#cv8-uniqdiv-"+vuniqid.uniqid).parents(".cv8-module");
      jmc.attr("tabindex","0"); // !important pour avoir les events key
      var eventname = "keydown.tzr"+vuniqid.uniqid;
      jmc.unbind(eventname);
      jmc.on(eventname, {vuniqid:vuniqid}, function(event){
	TZR.ShortKeys.handleKey.call(TZR, event, event.data.vuniqid);
      });
      vuniqid.unbinds.push({container:jmc,eventname:eventname});
    }
  }
}

//==========================
// Service de test anti spam
//==========================
TZR.checkSenderMail = function(url,email){
    var res = false;
    jQuery.ajax({
	url : url,
	async : false,
	data : {
	    email : email
	},
	method : 'GET',
	error:function(jqXHR, textStatus){
	    if(jqXHR.status==401){
		TZR.authAlert();
		res = null;
	    }
	    res = null;
	},
	success: function(data){
	    if(data !== 'OK') {
		res = false;
	    } else {
		res = true;
	    }
	}
    });
    return res;
};
TZR.sprintf = function(){
  var model = arguments[0];
  var i=1;
  var exp = new RegExp("%s");
  while(i<arguments.length && exp.test(model)){
    model = model.replace("%s", arguments[i])
    i++;
  }
  return model;
};
// mise à jour du champ avec l'icone choisie
TZR.IconField = {};
TZR.IconField.unSelect=function(varid){
  jQuery('#dis-ico'+varid).html("<span class='bordered-icon empty'></span>");
  jQuery('#'+varid).val('');
};
TZR.IconField.select =function(csico , rvarid) {
  ico=csico.selector;
  var boformat = jQuery('#dis-ico'+rvarid.id).data('boformat');;
  jQuery('#dis-ico'+rvarid.id).html("<span class='bordered-icon'>"+TZR.sprintf(boformat, ico, ico, ico, ico)+"</span>");
  jQuery('#'+rvarid.id).val(ico);
  jQuery('#ico_Modal'+rvarid.id).modal('toggle'); //fermeture de la modal après que l'icon est été selectioner
};
