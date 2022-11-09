var click_event;
click_event = (TZR.click_event !== undefined) ? TZR.click_event : "click";

// =====================
//  -AdminTable
// =====================
TZR.AdminTable = new Object();
TZR.AdminTable.defaultSettings = {
  //responsive: true,
  //fixedHeader: true,
  "paging": false,
  "info": false,
  //"scrollX": "100%",
  "columns": [
  { "orderable": false },
  { "orderDataType": "text"},
  { "orderDataType": "dom-text", "type": "numeric" },
  { "orderDataType": "text"},
  { "orderDataType": "text"},
  { "orderDataType": "dom-text", "type": "numeric" },
  { "orderDataType": "dom-checkbox" },
  { "orderDataType": "dom-checkbox" },
  { "orderDataType": "dom-checkbox" },
  { "orderDataType": "dom-checkbox" },
  { "orderDataType": "dom-checkbox" },
  { "orderDataType": "dom-checkbox" }
  ],
  "order": [[ 2, "asc" ]]
};
TZR.AdminTable.columnOrderTypeMapping = {
  "default": { "orderDataType": "dom-select" },
  "image_crop_ratio": { "orderDataType": "dom-text", "type": "numeric" },
  "type": { "orderDataType": "text"},
  "addrFields": { "orderDataType": "text"},
  "theclass": { "orderDataType": "text"},
  "fgroup": { "orderDataType": "multilang-dom-text", "type":"text" },
  "comment": { "orderDataType": "multilang-dom-textarea", "type":"text" },
  "acomment": { "orderDataType": "multilang-dom-textarea", "type":"text" },
  "datemin": { "orderDataType": "text"},
  "datemax": { "orderDataType": "text"},
  "exif_source": { "orderDataType": "text"},
  "audio_bitrate": { "orderDataType": "dom-text", "type": "numeric" },
  "filter": { "orderDataType": "text"},
  "display_format": { "orderDataType": "text"},
  "edit_format": { "orderDataType": "text"},
  "display_text_format": { "orderDataType": "text"},
  "query": { "orderDataType": "text"},
  "separator": { "orderDataType": "text"},
};
TZR.AdminTable.id = "";
TZR.AdminTable.addedCol = "";
TZR.AdminTable.dt = undefined;
TZR.AdminTable.destroy = function(id) {
  if (TZR.AdminTable.id == id) {
    if (TZR.AdminTable.dt !== undefined) TZR.AdminTable.dt.destroy();
    TZR.AdminTable.id = "";
    TZR.AdminTable.dt = undefined;
  }
  //eventuellement détruire toutes les $.fn.dataTable.tables() mais cela n'empêche pas les evts "non attendus"
//   while($.fn.dataTable.tables().length > 0) {
//     $.fn.dataTable.tables().pop();
//     jQuery($.fn.dataTable.tables().pop()).DataTable().destroy();
//   }
//   for(tab in $.fn.dataTable.tables()) {
//     jQuery(tab).DataTable().destroy();
//   }
  
};
jQuery(document).on('destroy.dt', function (e, settings) {
  TZR.AdminTable.id = "";
});
TZR.AdminTable.hideDefaultSearch = function(wrapperSel) {
  /* L'utilisation de dataTable.search() requiert le setting "searching" qui déclenche l'affichage
   * d'un champ de recherche pra défaut qui ne nous convient pas */
  //var defaultSearchRow = jQuery(wrapperSel+ " .row:first");
  var defaultSearchRow = jQuery(wrapperSel+ " .dataTables_filter");
  if ((defaultSearchRow !== undefined) && defaultSearchRow.length>0) {
    defaultSearchRow.attr("style","display:none;");
    TZR.AdminTable.suppressUselessRows(wrapperSel);
  } else {
    setTimeout(function(){
      var item=jQuery(wrapperSel+ " .row:first"); 
      if ((item!==undefined) && item.length>0) {item.attr("style","display:none;");TZR.AdminTable.suppressUselessRows(wrapperSel);}
    },300);
  }
};
TZR.AdminTable.suppressUselessRows = function(wrapperSel) {
  jQuery(wrapperSel + " .row").each(function() {
    jQuery(this).attr("class","");
    jQuery(this).children('[class^="col-"]').each(function() {
      jQuery(this).attr("class","");
    });
  });
};
TZR.AdminTable.init = function(id,settings,force) {
  if (! jQuery().dataTable)
    return;
  var tableSel = '#table' + id;
  var wrapperSel = tableSel + '_wrapper';
  
  var elem = jQuery(tableSel);
  if ((elem === undefined) || (elem.length == 0)) {
    TZR.AdminTable.destroy(tableSel);
    return;
  }
  if ( $.fn.DataTable.isDataTable(elem) ) {
    if (force === undefined || force != 1) {
      TZR.AdminTable.hideDefaultSearch(wrapperSel);
      return;
    }
  } else if (settings !== undefined) {
    settings.destroy = false;
  }
  // customize classes?      
  //       $.extend( $.fn.DataTable.ext.classes, {
  //         sWrapper:      "dataTables_wrapper form-inline dt-bootstrap",
  //         sFilterInput:  "form-control input-sm form-group",
  //         sLengthSelect: "form-control input-sm"
  //       } );
  //define Order functions
  $.fn.dataTable.ext.order['dom-text'] = function (settings, col) {
    if (settings.aoColumns[col].type == "numeric") {
      return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
        if ($('input', td) !== undefined)
          return $('input', td).val() * 1;
        else
          return 0;
      });
    } else {
      return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
        if ($('input', td) !== undefined)
          return $('input', td).val();
        else
          return "";
      });
    }
  };
  $.fn.dataTable.ext.order['dom-textarea'] = function (settings, col) {
    return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
      if ($('textarea', td) !== undefined)
        return $('textarea', td).val();
      else
        return "";
    });
  };
  $.fn.dataTable.ext.order['dom-checkbox'] = function (settings, col) {
    return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
      if ($('p input', td) !== undefined)
        return $('p input', td).prop("checked") ? 1 : 0;
      else
        return 0;
    });
  };
  $.fn.dataTable.ext.order['dom-select'] = function (settings, col) {
    return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
      if ($('select option:selected', td) !== undefined)
        return $('select option:selected', td).val();
      else
        return "";
    });
  };
  $.fn.dataTable.ext.order['multilang-dom-text'] = function (settings, col) {
    return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
      if ($('table tbody tr:first td input', td) !== undefined)
        return $('table tbody tr:first td input', td).val();
      else
        return "";
    });
  };
  $.fn.dataTable.ext.order['multilang-dom-textarea'] = function (settings, col) {
    return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
      if ($('table tbody tr:first td textarea', td) !== undefined)
        return $('table tbody tr:first td textarea', td).val();
      else
        return "";
    });
  };
    // like deep copy below (initAddCol), always clone defaultSettings
    // <- object is modified by DataTable and may be wrong for further use (aoColumns invalid)
    var dtSettings = (settings === undefined) ? jQuery.extend(true, {}, TZR.AdminTable.defaultSettings) : settings;
    
  TZR.AdminTable.dt = elem.DataTable(dtSettings);
  TZR.AdminTable.id = tableSel;
  
  /* Activation de notre champ de recherche */
  TZR.AdminTable.hideDefaultSearch(wrapperSel);
  var dtsearch = jQuery("#" + elem.attr("id") + "_search" + " input");
  dtsearch.on( 'keyup ' + click_event, function () {
    if (TZR.AdminTable.dt.search() == dtsearch.val()) return;
              TZR.AdminTable.dt.search(dtsearch.val()).draw();
  });
};

TZR.AdminTable.initAddCol = function(id,colInfo) {
  //filtrer les événments indésirables
  var elem = jQuery('#table' + id);
  if ((elem === undefined) || (elem.length == 0)) {
    TZR.AdminTable.destroy('#table' + id);
    return;
  }
  var colInfo = colInfo.split(":");
  var addedCol = elem.find('td [name^="addOptions"]:first');
  if ((addedCol === undefined) || (addedCol.length == 0))
    return;
  var colNameAttr = addedCol.attr("name");
  if (colNameAttr === undefined)
    return;
  var colName = colInfo[0].replace('__','');
  if (colNameAttr.indexOf(colName) <= 0)
    return;
  if ($.fn.DataTable.isDataTable(elem) && TZR.AdminTable.addedCol == colInfo[0]) 
    return;
  
  //deep copy to keep initial settings unchanged
  var settings = jQuery.extend(true, {}, TZR.AdminTable.defaultSettings);
  settings.destroy = true;
  
  //settings.columns.splice(5,0,{ "orderDataType": "multilang-dom-text", "type":"text" });
  if (TZR.AdminTable.columnOrderTypeMapping[colName] !== undefined)
    settings.columns.splice(5,0,TZR.AdminTable.columnOrderTypeMapping[colName]);
  else
    settings.columns.splice(5,0,TZR.AdminTable.columnOrderTypeMapping["default"]);

  TZR.AdminTable.addedCol = colInfo[0];
};

// =====================
//  -Field
// =====================
TZR.Field = new Object();
TZR.Field.groupFilter = function(id,v) {
  if(v=='') jQuery('#table'+id+' tr').show();
  else{
    jQuery('#table'+id+'>tbody>tr[tzrgroup!="'+v+'"]').hide();
    jQuery('#table'+id+'>tbody>tr[tzrgroup="'+v+'"]').show();
  }
};
TZR.Field.addopts=function(moid,fprefix,tprefix,boid,id,v){
  var curl = v ? '&addOption='+v : '';
  //var tableId = id.replace(/^v/g,'#table');
  TZR.AdminTable.destroy(TZR.AdminTable.id);
  TZR.jQueryLoad({url:TZR._self+'moid='+moid+'&function='+fprefix+'BrowseFields&template='+tprefix+'browseFields.html&boid='+boid+curl});
};
TZR.Field.delete = function(id,field) {
  var moid = $('#form'+id+' input[name="moid"]').val();
  var fprefix = $('#form'+id+' input[name="fprefix"]').val();
  var tprefix = $('#form'+id+' input[name="tprefix"]').val();
  var boid = $('#form'+id+' input[name="boid"]').val();
  var module = jQuery('#cv8-uniqdiv-'+id).parents('div.cv8-module:first')[0];
  var target = jQuery('div.cv8-module-container:first',module)[0];
  var current = TZR._self+'moid='+moid+'&function='+fprefix+'BrowseFields&template='+tprefix+'browseFields.html&boid='+boid;
  //window['v'+id].jQueryLoad(TZR._self+'moid='+moid+'&function='+fprefix+'DelField&boid='+boid+'&_next='+escape(current)+'&field='+field);
  TZR.jQueryLoad({url:TZR._self+'moid='+moid+'&function='+fprefix+'DelField&boid='+boid+'&_next='+escape(current)+'&field='+field,target:target,overlay:"none"});
};
TZR.Field.editSec = function(id, moid, oid, selectedfrom, typename) {
    if (!typename)
	typename = 'field';
  if(typeof(selectedfrom)!='undefined'){
    if(typeof(selectedfrom)=='object'){
      var f=document.forms['browse'+selectedfrom.uniqid];
    }else{
      var f=document.forms[selectedfrom];
    }
    if(!f || !TZR.checkBoxesIsChecked(f)){
      if(oid){
        oid="oid="+oid;
      }else{
        alert(TZR._noobjectselected);
        return;
      }
    }else{
      oid=jQuery(f).find('input:checked[name^=_selected]').serialize()+'&_selectedok=ok';
    }
  }else{
      oid=typename+"="+oid;
  }
  TZR.Dialog.openURL(TZR._self+'&function=secEditSimple&template=Core/Module.edit-sec.html&moid='+moid+'&tplentry=br&'+oid);
  return false;
};
TZR.Field.editSecField = function(id, moid, fieldname, selectedfrom){
    this.editSecField(id, moid, fieldname, selectedfrom, "field");
};
TZR.Field.changeOrder = function(id,field) {
  var oo=parseInt(jQuery(field).data('order'));
  var no=parseInt(field.value);
  if(isNaN(no)) return;
  var modulecontainer = TZR.getModuleContainer(id);
  jQuery(modulecontainer).find('input.forder').each(function(){
    var o=parseInt(this.value);
    if(isNaN(o) || this==field) return;
    if(no<oo && o>=no && o<oo) this.value=o+1;
    if(no>oo && o<=no && o>oo) this.value=o-1;
    jQuery(this).data('order',this.value);    
  });
  jQuery(field).data('order',no);
};



