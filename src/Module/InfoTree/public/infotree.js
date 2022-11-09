
TZR.Infotree = {
  labels:[],
  gopage:function(oidit,complement, options/*vuniqid*/) {
    if(!complement) complement='&function=viewpage&template=Module/InfoTree.viewpage.html';
    this.jQueryLoad(TZR._self+"&moid="+options.moid+"&oidit="+oidit+"&tplentry=it"+complement);
    return false;
  },
  pgs_go:function(f,i) {
    if(f._function.value=='newsection') f._next.value='';
    f.tabledst.value=this.pgs_table[i];
    TZR.ajaxSubmitForm(f);
    return false;
  },
  selectDest:function(titlediv, targetoid, uniqid){
    var vuniqid = window["v"+uniqid];
    var url = TZR._self+"skip=1&function=home&_publishedonlyselectable=1&template=Module/InfoTree.diagaction.html&tplentry=mit&do=showtree&action=no&maxlevel=1&titlediv=";
    var data = {
      targetoid:targetoid,
      titlediv:titlediv,
      diag_title:"move_text",
      formname:"sendform"+vuniqid.uniqid,
      moid:vuniqid.newslettermoid
    };
    TZR.Dialog.openURL(url, data, {});
  },
  exportselected:function(uniqid) {
    // from index a finir
    TZR.Dialog.openURL('<%$self%>&_function=export&template=Module/InfoTree.export.html&moid=<%$smarty.request.moid%>&tplentry=br');
  },
  // deplacement rubrique
  moveselected:function(uniqid) {
    var vuniqid = window["v"+uniqid];
    if(!this.checkSelected(uniqid)) return false;
    var url = TZR._self+"_skip=1&_raw=1&_ajax=1&function=home&template=Module/InfoTree.diagaction.html";
    url += "&tplentry=mit&action=moveSelectedCat&maxlevel=1&norubric=1&nosub=1&rootauth=1";

    // todo !!!!
    // <%if $sysmods.xmodbackofficeinfotree==$_moid%>&cb=opener.home_reloadMenu<%/if%>&'+jQuery('.cv8_tablelist input[name^="_selected"]'
    url += "&"+jQuery('.cv8_tablelist input[name^="_selected"]').serialize(); /*<%* voir ... le serialize / encodage *%>*/
    var data = {
      diag_title:"move_text",
      from:vuniqid.oidit,
      formname:"myform"+vuniqid.uniqid,
      moid:vuniqid.moid
    };
    TZR.Dialog.openURL(url, data, {});
  },
  checkSelected:function(uniqid){
    var f=document.forms['myform'+uniqid];
    if(!TZR.checkBoxesIsChecked(f)){
      alert(this.labels["Seolan_Core_General.no_obj_selected"]);
      return false;
    }
    return true;
  },
  // deplacement section
  moveSelectedSection:function(d, uniqid) {
    var vuniqid = window["v"+uniqid];
    var f=document.forms['myform'+uniqid];
    if(!TZR.checkBoxesIsChecked(f,null,null,/^_itoidselected/)){
      alert(vuniqid.emptySelection);
      return false;
    }
    f._function.value="moveSection";
    f.dir.value=d;
    if(d=="to") {
      var url = TZR._self+"_skip=1&_raw=1&_ajax=1&function=home&template=Module/InfoTree.diagaction.html";
      url += "&tplentry=mit&do=showtree&action=moveSection&maxlevel=1&norubric=1&nosub=1&rootauth=1";
      var data = {
	diag_title:"move_text",
	from:vuniqid.oidit,
	formname:"myform"+vuniqid.uniqid,
	moid:vuniqid.moid
      };
      TZR.Dialog.openURL(url, data, {});
      return false;
    }
    TZR.ajaxSubmitForm(f);
    return false;
  }
};
