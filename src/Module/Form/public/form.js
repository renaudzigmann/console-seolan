/*  
script spécifiques Module/Form 
*/
TZR.Form = {};
// editform
TZR.Form.addQuestion=function(vuniqid, after){
  var jnew=jQuery(jQuery('#question-'+vuniqid.uniqid).html().replace(/xxx/g,"F"+vuniqid.num));
  if(!after) jQuery('#questions-'+vuniqid.uniqid).append(jnew);
  else jQuery(after).parents('div.cv8-form-question-edit:first').after(jnew);
  vuniqid.num++;
}
// Titre dans la toolbar (si languser)
TZR.Form.changeQuestion=function(vuniqid, o){
  var exp = /^.*\[([A-Z][A-Z])\]$/;
  var name = jQuery(o).attr("name");
  var r = exp.exec(name);
  var lang = r[1];
  if (lang == TZR._lang_user){
    jQuery(o).parents('div.cv8-form-question-edit:first').find('>div:first>small:first>span:eq(1)').html(o.value);
  }
}
TZR.Form.changeQType=function(vuniqid, o,num){
  var t=o.value;
  if(t=="separator" || t=="longtext" || t=="shorttext" || t=="date" || t=="file" || t == 'image' || t == 'boolean'){
    jQuery("#answerlist-"+num+"-"+vuniqid.uniqid).hide();
  }else{
    jQuery("#answerlist-"+num+"-"+vuniqid.uniqid).show();
  }
  if (t == 'objectlink') {
    jQuery("#answerlist-"+num+"-"+vuniqid.uniqid).hide();
    jQuery(o).parents('table.list2').find('.objectlink_field_option').show();
  } else {
    jQuery(o).parents('table.list2').find('.objectlink_field_option').hide();
  }
  if (t == 'separator') {
    jQuery("#answerlist-"+num+"-"+vuniqid.uniqid).hide();
    // mise en forme spécifique
    var jp = jQuery(o).parents('table.list2');
    jp.find('tr[data-field-type="query-title"]').hide();
    jp.find('tr[data-field-type="query-compulsory"]').hide();
    jQuery(o).parents('div.cv8-form-question-edit:first').find('>div:first>small:first>span:eq(1)').hide();
  } else {
    var jp = jQuery(o).parents('table.list2');
    jp.find('tr[data-field-type="query-title"]').show();
    jp.find('tr[data-field-type="query-compulsory"]').show();
    jQuery(o).parents('div.cv8-form-question-edit:first').find('>div:first>small:first>span:eq(1)').show();
  }
  jQuery(o).parents('div.cv8-form-question-edit:first').find('>div:first>small:first>span:first').html(o.options[o.selectedIndex].text);
}
TZR.Form.doDelQuestion=function(params){
    jQuery(params.questionToDel).parents('div.cv8-form-question-edit:first').remove();
}
TZR.Form.delQuestion=function(vuniqid, o){
  TZR.Modal.confirm_delete.config("TZR.Form.doDelQuestion", 
				  {
				    questionToDel:o,
				  },
				  '', 
				  vuniqid.delquestionmess);

  TZR.Modal.confirm_delete.show();
}
TZR.Form.moveQuestion=function(vunidiq,o,to){
  var jq=jQuery(o).parents('div.cv8-form-question-edit:first');
  if(to=='first'){
    jq.parent().prepend(jq);
  }else if(to=='last'){
    jq.parent().append(jq);
  }else if(to==-1){
    var s=jq.prev();
    if(s.length>0) s.before(jq);
  }else{
    var s=jq.next();
    if(s.length>0) s.after(jq);
  }
}
TZR.Form.addAnswer=function(vuniqid, a){
  var j=jQuery('div:first-child',a.parentNode.parentNode).clone().insertAfter(jQuery(a.parentNode)).find('input').val('');
  var randomnumber=Math.floor(Math.random()*1000)
  j.each(function(){
    this.name=this.name.replace(/(\[foo\])/,'[foo'+randomnumber+']');
    this.name=this.name.replace(/(\[[_A-Za-z0-9]+:[A-Za-z0-9]+\])/,'[foo'+randomnumber+']')
  });
}
TZR.Form.delAnswer=function(vuniqid,a){
  if(jQuery(a.parentNode).siblings().length>0) jQuery(a.parentNode).remove();
}
TZR.Form.moveAnswer=function(vuniqid,o,to){
  var jq=jQuery(o).parent();
  if(to==-1){
    var s=jq.prev();
    if(s.length>0) s.before(jq);
  }else{
    var s=jq.next();
    if(s.length>0) s.after(jq);
  }
}
TZR.Form.checkForm=function(f){
  var ok=true;
  if(ok) return TZR.ajaxSubmitForm(f);
  else return false;
}
TZR.Form.initEdit=function(vuniqid){
  var questionsContainer = jQuery('#questions-'+vuniqid.uniqid);
  jQuery(questionsContainer).sortable({axis:"y"});

   jQuery('select[name*="qtype"]', questionsContainer).each(function(index, item) {
     TZR.Form.changeQType(vuniqid, item, jQuery(item).data('field'));
   });

   this.changeGroup(vuniqid);

};
// collecte des valeurs des groupes de champs
TZR.Form.collectGroups = function(vuniqid){
  var exp = /^.*\[([A-Z][A-Z])\]$/;
  var groupsValues = {}
  var groups = jQuery('input[name^="fgroup"]');
  groups.each(function(i, o){
    var o = jQuery(o);
    var v = o.val()
    var name = o.attr("name")
    var r = exp.exec(name);
    var lang = r[1];
    if (v != ""){
      if (typeof(groupsValues[lang]) == "undefined"){
        groupsValues[lang] = [];
      }
      if (groupsValues[lang].indexOf(v) == -1){ 
        groupsValues[lang].push(v);
      }
    }
  });
  return groupsValues;
};
// mise à jour data-list des groupes
TZR.Form.updateGroups = function(vunidid, values){
  var groups = jQuery('input[name^="fgroup"]');
  var exp = /^.*\[([A-Z][A-Z])\]$/;
  groups.each(function(i, o){
    var o = jQuery(o);
    var name = o.attr("name")
    var r = exp.exec(name);
    var lang = r[1];
    if (o.attr("list") == null){
      var list = "datalistgroup"+name.replace(/[\[\]]/g, '-'); // ajouter uniqid
      o.attr("list", list)
    } else {
      var list = o.attr("list"); 
    }
    var datalist = jQuery("#"+list);
    if (datalist.length == 0){
      datalist = jQuery("<datalist id='"+list+"'/>");
      o.parent().append(datalist);
    }
    datalist.html("");
    if(typeof(values[lang]) != "undefined"){
      for(var i=0; i<values[lang].length; i++){
        datalist.append("<option value='"+values[lang][i]+"'>");
      }
    }
  });
};

// dashboard
TZR.Form.doDelAnswers = function(params){
  TZR.jQueryPost({url:TZR._self+"moid="+params.moid+"&template=Core.empty.txt&function=delAnswers&oid="+params.oid,
		  overlay:params.modulecontainer,
		  cb:function(){
		    TZR.Tabs.load(TZR.Tabs.activeTag(jQuery("#tzr-tablist-"+params.uniqid)), {refresh:true});
		  }});
};
TZR.Form.delAnswers=function(vuniqid){
  
  TZR.Modal.confirm_delete.config("TZR.Form.doDelAnswers", 
				  {
				    uniqid:vuniqid.uniqid,
				    moid:vuniqid.moid, 
				    oid:vuniqid.oid,
				    modulecontainer:vuniqid.modulecontainer
				  },
				  vuniqid.delanswerstitle, 
				  vuniqid.delanswersmess);
  
  TZR.Modal.confirm_delete.show();
  
};


