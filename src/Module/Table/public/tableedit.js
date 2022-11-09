/*
tableedit jspreadsheet components
*/
(function(){

    TZR.JSpreadsheet={
      vues:[],
      base:"/csx/VendorJS/jspreadsheet/",
      // chargement des ressources : js, css
      initialize:function(){

      return new Promise((resolve, reject)=>{
        this.setCss();
        this.loadScripts(resolve, reject);
      });
    },
    loadScripts:function(resolve, reject){

        jQuery.getScript(this.base+"vue.2.6.10.min.js")
         .done(()=>{
          jQuery.getScript(this.base+"jsuites.v4.js")
          .done(()=>{
            jQuery.getScript(this.base+"jexcel.v4.js").done(()=>{
              resolve("JSpreadsheet components loaded");
            }).fail(()=>reject("TZR.JSpreadsheet::initialize, error loading jexcel"));
          }).fail(()=>reject("TZR.JSpreadsheet::initialize, error loading jsuites"));
        }).fail(()=>reject("TZR.JSpreadsheete::initialize, error loading vue js"));

      },
      setCss:function(){
        let headElm = document.getElementsByTagName("head")[0];
          for(let href of [this.base+"jsuites.v4.css",
                            this.base+"google-materials-icon.css",
                            this.base+"jexcel.v4.css",
                          "/csx/src/Module/Table/public/tableedit.css"]){


          let link = document.querySelector(`link[href="${href}"]`);

          if (!link){
            let newcsslink = document.createElement('link');
            newcsslink.setAttribute("rel","stylesheet");
            newcsslink.setAttribute("type","text/css");
            newcsslink.setAttribute("href",href);
            headElm.appendChild(newcsslink);
          }

        }

    },
      // le spreadsheet en fait, via vuejs
      vueManager:function(uniqid, options){
        if (typeof this.vues[uniqid] == "undefined" && options){
          this.vues[uniqid] = new Vue({
            el: "#spreadsheet-browse-"+uniqid,
            mounted: function() {
              let spreadsheet = jspreadsheet(this.$el, options);
              // mémoriser dans l'instance les oid des lignes supprimées
              spreadsheet._updatedOids = [];
              spreadsheet._deletedOids = [];
              spreadsheet._updated = false;
              spreadsheet._uniqid = uniqid;
              Object.assign(this, spreadsheet);
            }
          });
      }
      return this.vues[uniqid];
      },
    /// nécessite des styles custom, voir tableedit.css
    prepareLineActions:function(uniqid, oid, actions){
      let actionsAll = actions.join("</li><li>");
      return `<div class="dropdown"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="glyphicon csico-ellipsis-h" aria-hidden="true"></span></button><ul class="dropdown-menu"><li>${actionsAll}</li></ul></div>`;
    },

    newLine:function(obj){
      let oid = '_newlineoid_:'+Math.floor(Math.random()*10000);
      let uniqid = obj._uniqid;
      let newActions = `<a href="#" onclick="TZR.JSpreadsheet.deleteLine.call(TZR.JSpreadsheet, '${oid}', '${uniqid}'); return false;" class="btn btn-delete-line"><span class='glyphicon csico-delete'></span></a>`;
      newActions = `<div class="dropdown"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="glyphicon csico-ellipsis-h" aria-hidden="true"></span></button><ul class="dropdown-menu"><li>${newActions}</li></ul></div>`;
      let newRowData = [
        newActions,
        oid
      ];
      obj.insertRow(newRowData,0,true);
    },
    resetLineSelection:function(uniqid){
      let div = document.getElementById("selecteddiv"+uniqid);
      let childNodes = [];
      // on prend les ref, puis on efface
      for(let child of div.children){
        childNodes.unshift(child);
      }
      for(let child of childNodes)
        child.remove();
    },
    updateLineSelection:function(uniqid, linesnumbers){
      let vueManager = this.vueManager(uniqid);

      this.resetLineSelection(uniqid);

      let div = document.getElementById("selecteddiv"+uniqid);

      let startel = document.createElement("input");
      startel.setAttribute("type","hidden");
      startel.setAttribute("value","selectstart");
      div.appendChild(startel);

      for(let linenumber of linesnumbers){
        let oid = vueManager.getValueFromCoords(this.oidColIndex, linenumber);
        let newselected = document.createElement("input");
        newselected.setAttribute("name",`_selected[${oid}]`);
        newselected.setAttribute("type","checkbox");
        newselected.setAttribute("checked",true);
        newselected.style.visibility = "hidden";
        div.appendChild(newselected);
      }
    },
    // ! historyIndex n'est pas à jour lors des appels via onchange
    setState:function(vueManagerOrUniqid, obj, force){
      if (typeof(vueManagerOrUniqid) == "string")
        var vueManager = this.vueManager(vueManagerOrUniqid);
      else
        var vueManager = vueManagerOrUniqid;

      if (!obj)
        obj = vueManager.$el.jspreadsheet; // pas beau tout ça
;

      if (obj._deletedOids.length>0){
        vueManager._updated = true;
        this.setActionsState("enabled");
        return;
      }
      if (obj.historyIndex>=0 || force){
        vueManager._updated = true;
        this.setActionsState("enabled");
      }
    },

    setActionsState:function(state){
      if (state == "disabled")
        for (let b of document.querySelectorAll("button.save-spreadsheet")){
          b.setAttribute("disabled",true);
        } else {
          for (let b of document.querySelectorAll("button.save-spreadsheet")){
            b.removeAttribute("disabled");
          }
      }
      },

    deleteLine:function(oid, uniqid){
      let vueManager = this.vueManager(uniqid);
      //recherche de la ligne à partir de l'oid
      let lineIndex = vueManager.getColumnData(TZR.JSpreadsheet.oidColIndex).indexOf(oid);
      if (lineIndex !== -1){
        // ? confirm before ?
        vueManager._deletedOids.push(oid);
        vueManager.deleteRow(lineIndex, 1);
        // à voir les paramèrtes ? pas bons
        this.setState(vueManager);
      }

    },
      displayLineDetails:function(oid, uniqid){
	if (!TZR.unloadMgt.checkoutContainerUnload(uniqid))
	  return;
	let moid = TZR.Table.browse[uniqid].moid;
	TZR.Dialog.openURL(`${TZR._self}&openeruniqid=${uniqid}&moid=${moid}&oid=${oid}&function=display&template=Module/Table.popsimpleview.html&tplentry=br`);	
      },
  editLineDetails:function(oid, uniqid){
    if (!TZR.unloadMgt.checkoutContainerUnload(uniqid))
      return;
    let moid = TZR.Table.browse[uniqid].moid;
    TZR.Dialog.openURL(`${TZR._self}&openeruniqid=${uniqid}&moid=${moid}&oid=${oid}&function=edit&template=Module/Table.popsimpleedit.html&tplentry=br`);
  },

  save:function(obj){

    // passer par le traitement ajax console !!!
    var jsonData = obj.getJson(false);
    var container = jQuery(document.getElementById(`cv8-uniqdiv-${obj._uniqid}`).closest(".cv8-module-container"));
    var overlay = TZR.setOverlay(jQuery(container));

    let moid = TZR.Table.browse[obj._uniqid].moid;
    let pagesize = TZR.Table.browse[obj._uniqid].pagesize;
    let vueManager = this.vueManager(obj._uniqid);

    jQuery.ajax({
      type:'POST',
      data:{spreadsheetData:jsonData,
      _updated:obj._updatedOids,
      _deleted:obj._deletedOids},
      url:TZR._self+`&moid=${moid}&function=procEditSpreadsheet&_skip=1&noCheckUnload=1`,
      success:function(){
         TZR.unsetOverlay(overlay);
          vueManager._updated = false;
          TZR.updateModuleContainer(
            `${TZR._self}moid=${moid}&function=browse&template=Module/Table.browseSpreadsheetJSpreadsheetWithVuejs.html&tplentry=br&pagesize=${pagesize}&noCheckUnload=1`,
            container);
       }
     });
  },

  refresh:function(uniqid){
    var container = jQuery(document.getElementById(`cv8-uniqdiv-${uniqid}`).closest(".cv8-module-container"));
    if (!container || container.length == 0)
      return;
    let pagesize = TZR.Table.browse[uniqid].pagesize;
    let moid = TZR.Table.browse[uniqid].moid;
    TZR.updateModuleContainer(
      `${TZR._self}moid=${moid}&pagesize=${pagesize}`+
      "&function=browse&template=Module/Table.browseSpreadsheetJSpreadsheetWithVuejs.html"+
      "&tplentry=br&noCheckUnload=1",
      container);
  }

};

})();
