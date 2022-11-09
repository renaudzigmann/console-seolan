
 /**
  * composant pour manipuler le menu des modules 
  */
 TZR.moduleMenu = {
   elm:null,
   filterActive:false,
   formatMenuGroup : function(contents, cliul){
     contents.each(function(i, o){
       var li = jQuery("<li>");
       cliul.append(li);
       var a = jQuery(o).clone();
       a.html('');
       a.on('click', function(evt){
	 return home_activatelink(jQuery(this));
       });
       a.append('<span class="haut"><span class="icon"><span class="glyphicon '+jQuery(o).data('moduleicon')+'"></span></span><span class="title">'+jQuery("span:nth-child(2)", jQuery(o)).text()+'</span></span>');
       li.append(a);
     });
   },
   getAccentsMap : function() {
     return [["A", "Á|À|Ã|Â|Ä"],
	     ["a", "á|à|ã|â|ä"],
	     ["E", "É|È|Ê|Ë"],
	     ["e", "é|è|ê|ë"],
	     ["I", "Í|Ì|Î|Ï"],
	     ["i", "í|ì|î|ï"],
	     ["O", "Ó|Ò|Ô|Õ|Ö"],
	     ["o", "ó|ò|ô|õ|ö"],
	     ["U", "Ú|Ù|Û|Ü"],
	     ["u", "ú|ù|û|ü"],
	     ["C", "Ç"],
	     ["c", "ç"],
	     ["N", "Ñ"],
	     ["n", "ñ"]];
   },
   firstLetter : function(str){
     var letter = str.substring(0,1).toLowerCase();
     var map = this.getAccentsMap();
     var nletter = letter;
     var i = 0;
     while(nletter==letter && i<map.length){
       nletter = letter.replace(new RegExp(map[i][1]), map[i][0]);
       i++;
     }
     return nletter;
   },
   formatOtherMenuGroup:function(jgroup, cliul){
     var that = this;
     jQuery("li.modulemenugroup", jgroup).each(function(i, o){
       var li = jQuery("<li>");
       cliul.append(li);
       // liste des modules du groupe
       var modules = [];
       jQuery("ul>li>a[data-togglepage]", jQuery(o)).each(function(im,io){
	 var jo = jQuery(io);
	 modules.push({icon:jo.data('moduleicon'),
		       href:jo.attr('href'),
		       togglepage:jo.data('togglepage'),
		       label:jQuery("span:nth-child(2)", jo).text()});
       });
       var groupname = jQuery("a[data-togglegroup]>span:nth-child(2)", jQuery(o)).text();
       var letter = that.firstLetter(groupname);
       var a = jQuery("<a href=\"#\"></a>");
       a.on('click', function(evt){
	 that.toggleIconGroup(this);
	 return false;
       });
       a.append('<span class="haut"><span class="icon"><span class="glyphicon csico-letter-'+letter+'"></span></span><span class="title">'+groupname+'</span></span>');
       a.data('modules', modules);
       li.append(a);
     });
   },
   toggleIconGroup:function(groupAnchor){
     var ja = jQuery(groupAnchor);
     var modules = ja.data('modules');
     jQuery("#cs-maintitle").html(ja.text()).show();
     var cli = jQuery("#cv8-content");
     cli.html("");
     
     var cliul = jQuery("<ul class='menu-list clearfix disphomepagerub' style='display:none'></ul>");
     cli.append(cliul);

     modules.forEach(function(mod){
       var li = jQuery("<li>");
       cliul.append(li);
       a = jQuery("<a></a>");
       a.on('click', function(evt){
	 return home_activatelink(jQuery(this));
       });
       a.attr("href", mod.href);
       a.append(`<span class="haut"><span class="icon"><span class="glyphicon ${mod.icon}"></span></span><span class="title">${mod.label}</span></span>`);
       a.data('togglepage', mod.togglepage);
       li.append(a);
     });

     cliul.show(500);

     return true;
     
   },
   /**
    * copie les entrees de menu d'un groupe dans la zone de contenu
    * et les rends activables
    */
   toggleMenuGroup : function(group){
     var jelem = jQuery(group);
     // selon menu ferme / ouvert
     if (!jelem.hasClass("active")){
       return true;
     }
     var contents = jQuery("ul>li>a", jelem.parent());
     if (contents.length>0){
       
       jQuery("#cs-maintitle").html(jQuery("span:nth-child(2)", jelem).text()).show();
       var cli = jQuery("#cv8-content");
       cli.html("");

       var cliul = jQuery("<ul class='menu-list clearfix disphomepagerub' style='display:none'></ul>");
       cli.append(cliul);
       
       if (group.id == "other-modules-item"){
	 this.formatOtherMenuGroup(jelem.parent() /* li */, cliul);
       } else {
	 this.formatMenuGroup(contents, cliul);
       }
	   
       cliul.show(500);
       return true;
     }
   },
   filter:function(value){
     if (value=='')
       this.reset();
     if (value.length<1)
       return;
     this.filterActive = true;
     var first = null;
     var matchedItems = [];
     // groupes de menu : parcours
     jQuery("li.modulemenugroup").each(function(i,o){
       var groupMatch = jQuery("a>span:nth-child(2)", this).html().toUpperCase().indexOf(value.toUpperCase())>-1;
       var active = false;
       // recherche du texte : items du groupe de menu
       jQuery("span.modulemenuitem", this).each(function(i, o){
	 if ((this.innerText.toUpperCase().indexOf(value.toUpperCase())>-1)
	     || (groupMatch)){
	   jQuery(this).parents('li').first().removeClass('filtered');
	   active = true;
	   matchedItems.push(this);
	 } else {
	   jQuery(this).parents('li').first().addClass('filtered');
	 }
       });
       // container des items du groupe de menu
       var modsul = jQuery("ul.nav", this);
       var togglerBlock = jQuery(this);
       var toggler = jQuery("a[data-togglegroup]", this);
       if (active){
	 togglerBlock.show();
	 toggler.addClass("in");
	 modsul.css('display','block');
	 if (first == null)
	   first = toggler;
       }
       if (!active){
	 togglerBlock.hide();
	 toggler.removeClass("active in");
	 modsul.css('display', 'none');
       }
     });
     if (first != null){
       first.addClass('active');
       if (matchedItems.length == 1 && (value.toUpperCase() == matchedItems[0].innerText.toUpperCase())){
	 jQuery(matchedItems[0]).parent().trigger(TZR.click_event);
       }
     }
       
   },
   reset:function(){
     this.filterActive = false;
     this.elm.val('');
     jQuery("li.modulemenugroup").each(function(i,o){
       jQuery(this).show();
       jQuery("a[data-togglegroup]", this).removeClass("active in");
       jQuery("span.modulemenuitem").each(function(i, o){
	 jQuery(this).parents('li').first().removeClass('filtered');
       });
       jQuery("ul.nav", this).css('display', 'none');
     });
   },
   init:function(){
     this.elm = jQuery("#csx-modulesearch");
     this.resetBtn = jQuery("#csx-modulesearch-close");
     var that = this;
     this.resetBtn.on(TZR.click_event, function(){
       that.elm.value="";
	 that.reset();
	 return false;
     });
     /* pour capturer la frappe et la sélection par la datalist */
     this.elm.on('input', function(evt){
       that.filter(this.value);
     });
   },
   activateMenuItm:function(){
   }
 };
// active un lien du menu de l'admin ou du contenu
function home_activatelink(janchor){
  var parms = jQuery(janchor).data("togglepage");
  if(janchor.hasClass('dropdown-collapse') && !janchor.hasClass('active')){
    return true;
  }
  if (parms !== undefined){
    window[parms.action].apply(janchor, parms.arguments);
    // on laisse bubbler selon que l'on est sur un menu group ou pas
    return (typeof parms.bubble != "undefined" && parms.bubble == "true");
  }
}
// activation d'un groupe du menu modules
 function home_toggleMenuGroup(group){
   TZR.moduleMenu.toggleMenuGroup.call(TZR.moduleMenu, group);
 }
  // Recharge l'arbo de l'admin
  function home_reloadMenu(oid){
    var adminTree = jQuery('#cv8-infotreemenu');
    if (adminTree.attr('class') == 'inlineTreeMenuAjax') {
      adminTree.nextUntil('#cv8-infotreemenu-after').remove();
      TZR.Menu.inlineTreeLoad(adminTree);
    }
  }
  // Recharge l'arbo des modules
  function home_reloadModules(){
    TZR.Menu.load('#cv8-navigation-allmodules >a',true);
  }

  // Affiche le contenu du page du gestionnaire de rubrique
  function home_viewpage(oid,alias,title,nbssrub,nbsec){

      var target = jQuery("#cv8-content");
      TZR.jQueryLoad({noautocreate:true,target:'#cv8-content',url:TZR._self+'&_bdxnewstack=1&moid='+TZR._sysmods_xmodbackofficeinfotree+'&function=viewpage&tplentry=it&template=Core.content/infotree/main.html&skip=1&_nav=0&_path=1&oidit='+oid+'&_notrad=1&LANG_DATA='+TZR._lang_user});
      home_activateMenuItem(oid);
    // on referme si écran étroit
      if (nbssrub == 0 && window.matchMedia("(max-width : 767px)").matches){
          home_closeNavBar();
      }
  }
// referme la barre principage de menu
  function home_closeNavBar(){
    if (jQuery("body").hasClass("main-nav-opened")){
      jQuery("header>nav.navbar>a.toggle-nav").trigger(TZR.click_event);
    }
  }
  function home_activateMenuItem(oid) {

    jQuery('#cv8-navigation').find('a.active').removeClass('active');

    var li = jQuery('#cv8-navigation').find('[data-oid="'+oid+'"]');
    if (li == undefined || li.length == 0)
      return;
    li.find('a').addClass('active');
    if (li.parent().closest('li').length) li.parent().closest('li').click();
  }

  // Affiche le contenu d'un module
  function home_viewmodule(a){
    var ja;
    if (a == undefined){
      ja = jQuery(this);
    } else {
      ja=jQuery(a);
      }
    var href=ja.attr('href');
    var url=href.substr(href.indexOf('#')+1)+'&_bdxnewstack=1';
    TZR.mainurl='';
    TZR.maintitle=ja.find('>span:first').text();
    TZR.setDocumentTitle(TZR.maintitle);
    TZR.setInfoText(ja.attr('bt-xtitle'));
    TZR.clearNav();
    TZR.addNav(TZR.maintitle,url);
    TZR.jQueryLoad({target:'#cv8-content',url:url});
  }
  // Affiche le contenu d'un signet
  function home_viewbookmark(key){
    TZR.jQueryLoad({noautocreate:true,target:'#cv8-content',url:TZR._self+'&_bdxnewstack=1&moid='+TZR._sysmods_xmoduser2+'&function=getBookmark&tplentry=br&template=Core.content/bookmark.html&skip=1&key='+key});
  }

  // Prepare l'ajout d'un signet à la volée
  function home_addBookmark(){
    var urls=new Array();
    var titles=new Array();
    var comments=new Array();
    jQuery('#cv8-content div.cv8-module-container').each(function(){
      var tzrobj=jQuery(this).data('tzrobj');
      if (!tzrobj)
	return;
      if(tzrobj.modulecontainer._method=='GET'){
        urls.push(tzrobj.modulecontainer._uri);
      } else {
        urls.push('mod' + tzrobj.moid + 'query');
      }
      var title=jQuery('div.cv8-module-comment:first>h2',tzrobj.module).html();
      if(!title) title='';
      titles.push(title);
      var comm=jQuery('div.cv8-module-comment:first>span',tzrobj.module).html();
      if(!comm) comm='';
      comments.push(comm);
    });
    TZR.mainurl='';
    TZR.maintitle='';
    TZR.setInfoText('');
    TZR.jQueryLoad({target:'#cv8-content',url:TZR._self+'&_bdxnewstack=1&moid='+TZR._sysmods_xmoduser2+'&function=insertBookmark&template=Module/User.bookmarks-new.html&tplentry=br',data:{urls:urls,titles:titles,comments:comments}});
  }
  // Lance une recherche via le moteur de recherche
  function home_search(f){
    TZR.mainurl='';
    TZR.maintitle='';
    TZR.setDocumentTitle(TZR.maintitle);
    TZR.setInfoText('');
    TZR.clearNav();
    return TZR.ajaxSubmitForm(f);
  }

  // Affiche un menu d'admin de l'espace
  function admin_espaceMenu(url){
    TZR.jQueryLoad({target:'#cv8-content',url:url});
  }

  //Reload Page via Historique
  function page_reload() {
    jQuery('#cv8-content>div').remove();
    jQuery('#cv8-content').show();
    jQuery(window).trigger('hashchange');
  }

  // Actions effectuées après le chargement de la page
  jQuery(window).load(function(){
    TZR.initObjCleaner();
    TZR.lasthid='';
    TZR.mainurl='';
    TZR.maintitle='';
    TZR.historymode='normal';
    // Events "console"
    // menu items du menu gauche
    jQuery("#main-nav").on(click_event, "a[data-togglepage]", function(evt){
      return home_activatelink(jQuery(this));
    });
    jQuery('.table-responsive').floatingScroll();
    // Dialog/Popup/Modal
    jQuery(document).on(click_event, "a[data-toggledialog],button[data-toggledialog]", function(e){
      return TZR.Dialog.openFromAnchor.call(TZR.Dialog, this);
    });
    // Affichage à droite des modules d'un groupes
    jQuery("#main-nav").on(click_event, "a[data-togglegroup]", function(){
      TZR.moduleMenu.toggleMenuGroup.call(TZR.moduleMenu, this);
    });
    // Modification des liens type ajax
    jQuery(document).on(click_event, '.cv8-ajaxlink',function(e){
      // Confirmation eventuelle
      if(!TZR.checkLinkConfirm(this)) return false;
      // Depuis une modale ? qu'il faut alors ferùer
      var dialog = jQuery(this).parents(".modal");
      if (dialog.length ==1){
	try{
	  if (dialog.attr('id') == "cs-default-dialog"){
	    TZR.Dialog.closeDialog();
	  } else {
	    jQuery(dialog).modal('hide');
	  }
	}catch(e){}
      }
      // Charge la nouvelle page et empeche l'execution par defaut du lien
      var params=jQuery(this).data('linkoptions');
      if(!params) params={};
      // Recupere le container
      if(!params.target){
	var cont=jQuery(this).parents('div.cv8-module-container');
	params.target=cont[0];
      }
      params.url=this.href;
      TZR.jQueryLoad(params);
      e.preventDefault();
    //Open new tab when clicked with wheel button or middle button
    }).on('mouseup', '.cv8-ajaxlink',function(e){
    if(e.which==2){
      var h=this.href;
      var t=this.target;
      var a=this;
      a.target="_new";
      a.href=TZR._self+"&moid="+TZR._sysmods_xmodadmin+"&template=Core.layout/main.html&function=portail&gopage="+escape(a.href+'&_bdxnewstack=1');
      setTimeout(function(){a.href=h;a.target=t;},500);
    }
    });

    //Validateurs Html5
    jQuery(document).on(click_event, ':submit', function(){
      var firstinvalid = jQuery(this).parents('form').find(':invalid').first();
      if (firstinvalid.length == 0)
        return;
      var tabid = jQuery(firstinvalid).parents('.tzr-tabcontent').attr('id');
      if (typeof(tabid) != "undefined" ){
        jQuery('li>a[href="#'+tabid+'"]').click();
      }
      // si invalid repercuté dans les fieldsets
      if (firstinvalid.hasClass('fieldsetAccordionOpen')){
        return;
      }
      if (firstinvalid.hasClass('fieldsetAccordionClose')){
        firstinvalid.find('>legend').click();
        return;
      }
      // sinon (champ simple, et pas fait dans validfrom), container fieldset parent fermé
      jQuery(firstinvalid).parents('fieldset.fieldsetAccordionClose').find('>legend').click();
    });

  //HISTORIQUE
    // Ajoute une entrée dans l'historique navigateur
    TZR.addNavHistory=function(title,nowaitajax){
      // limite les doublons d'entrées mais PB avec le back
      // if (title == TZR.lastTitle)
      // return;
      // Attend la fin du traitement de la pile ajax
      if(!nowaitajax && TZR.ajaxQueue){
        setTimeout(function(){TZR.addNavHistory(title,nowaitajax);},200);
        return false;
      }
      var jh=jQuery('#cv8-history-list');
      // Prepare la liste des historique à effacer
      var htodel=new Array();
      if(TZR.historymode=='loadnav'){
        jh.find('li').each(function(i){
          if(this.id=="_"+location.hash.substring(1)) return false;
                           jQuery(this).remove();
          htodel.push(this.id.substring(1));
        });
        TZR.historymode='normal';
      }
      jh.find('li').each(function(i){
        if(i>TZR.historysize-2){
          jQuery(this).remove();
          htodel.push(this.id.substring(1));
        }
      });
      // Prepare les parametres à enregistrer
      var hid='history-'+Math.floor(Math.random()*9999999);
      var container=new Array();
      jQuery('div.cv8-module-container').each(function(){
        if(typeof(this._here)=='string') container.push(this._here);
      });
      if(TZR.maintitle && TZR.maintitle!=title && title.indexOf(TZR.maintitle+" > ")!=0)
        title=TZR.maintitle+" > "+title;
      jQuery.post(TZR._sharescripts+'addHistory.php',{hid:hid,maintitle:TZR.maintitle,title:title,url:TZR.mainurl,comment:TZR.getInfoText(),'container':container,'todel':htodel});
      TZR.lasthid=hid;
      TZR.lastTitle=title;
      TZR.setDocumentTitle(title);
      jQuery(window).history('add',hid);
      //jh.find('li.active').removeClass('active');
      jh.find('a.active').removeClass('active');
      if(title.length>35) var stitle=title.substr(0,32)+"...";
      else stitle=title;
      var histli = jQuery('<li id="_'+hid+'"><a href="#" class="active" onclick="TZR.loadNavHistory(\''+hid+'\');return false;"><span class="csico-triangle-right glyphicon"></span><span>'+stitle+'</span></a></li>').prependTo(jh);
      if(stitle != title){
    	  histli.attr('title',title.replace(new RegExp(' > ','g'), ','));
    	  histli.attr('data-toggle','tooltip');
      }
    }

    // Charge une page de l'historique navigateur
    TZR.loadNavHistory=function(hid,nav){
      if(nav){
        var jh=jQuery('#cv8-history-list');
        //jh.find('li.active').removeClass('active');
        jh.find('a.active').removeClass('active');
        jQuery('#_'+hid).find('a').addClass('active');
        TZR.historymode='loadnav';
      }
      TZR.jQueryLoad({noautocreate:true,url:TZR._self+"class=Seolan\\Core\\Session&function=goHistory&template=Core.content/history.html&skip=1&hid="+hid,cb:function(){
        if(nav) TZR.historymode='normal';
      }});
    }

    // Gestion de l'historique navigateur pour ajax
    jQuery(window).bind('hashchange',function(e,ui){
	try{
	    TZR.Modal.dismissAll();
	}catch(ExNotyetDefined){}
	var hregex=/^history-[0-9]+$/;
	if(ui) var hid=ui.value;
	else var hid=top.location.hash.replace('#','');
	if(hid!=TZR.lasthid && hid=='') location.reload();
	if(hid==TZR.lasthid || !hregex.test(hid)) return false;
	TZR.loadNavHistory(hid,true);
	TZR.lasthid=hid;
    });

    if(top.location.hash) page_reload();
    // traiter une demande page donnée
    TZR.handleRequestedPage();

  }); // </load
