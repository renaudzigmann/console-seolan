// ! langues
TZR.Form.collectGroups = function(groups){
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
}
// mise à jour data-list des groupes
TZR.Form.updateGroups = function(values){
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
              console.log(lang+" "+values[lang][i]);
        datalist.append("<option value='"+values[lang][i]+"'>");
      }
    }
  });
}

var values = TZR.Form.collectGroups();
  
TZR.Form.updateGroups(values);

