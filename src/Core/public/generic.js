if(typeof(TZR)=="undefined") TZR=new Object();
if(typeof(TZR._sharescripts)=="undefined") TZR._sharescripts="/scripts/";
if(typeof(TZR._sharetemplates)=="undefined") TZR._sharetemplates="/templates/";
TZR.isFormOk=true;
TZR.formValidation=true;
TZR.validator=new Array();
TZR.dependency=new Object();
TZR.onsubmit=new Array();
// validation des formulaires
TZR.isShortTextValid = function (id,fmt,fieldlabel,color) {
  var o = document.getElementById(id);
  var phrase = o.value;
  var resultat = fmt.test(phrase);
  if(!resultat) {
    TZR.setElementErrorState(o,false,color);
    TZR.isFormOk=false;
    return false;
  } else {
    TZR.setElementErrorState(o,true,color);
    return true;
  }
}
// champ doucment de la base doc
TZR.isDocumentValid = function (val) {
    
  var varid = val[0], color = val[3], inputid = val[5];
  var input = document.getElementById(inputid);
  var doid = document.getElementById(varid).value;
  if(doid=="") {
    TZR.setElementErrorState(input,false,color);
    TZR.isFormOk=false;
    return false;
  } else {
    TZR.setElementErrorState(input,true,color);
    return true;
  }
  
}

// validation des formulaires des champs evalutaiton/rating
TZR.isEvalValid = function (id,fmt,fieldlabel,color,linktext,listid) {
    if (fmt != '/(.+)/'){
	return;
    }
    var o = jQuery("#container"+id);
    if (o.data('clicked') != 1){
	TZR.setElementErrorState(o.parent(),false,color);
	TZR.isFormOk=false;
	return false;
    } else {
	TZR.setElementErrorState(o.parent(),true,color);
	return true;
    }
}
// Validation d'un champ user
TZR.isUserFieldValid = function(inputid, fmt, fieldname, color, varid){
  TZR.UserSelector.isValid.call(TZR.UserSelector, varid, fmt, color);
};
// validation des formulaires
TZR.isLinkValid = function (id,fmt,fieldlabel,color,linktext,listid) {
    if (typeof arguments[5] == "object"
	&& typeof arguments[5].treeviewmode != "undefined" 
	&& arguments[5].treeviewmode === true)
	return TZR.xlinkdef_treeview.isValid.call(TZR.xlinkdef_treeview, arguments[5].id, fmt, color);

    var o = document.getElementById(id);
  var typ=o.type;
  // Cas "radio" ou "checkbox"
  if(typ=="radio" || typ=="checkbox"){
    return TZR.isRadioValid(id,fmt,fieldlabel,color,linktext,listid);
  }else{
    var resultat = true;
    // Cas "un select"
    if(fmt=='compselect'){
      resultat = false;
      for(var i=0;i<o.options.length;i++){
	if(o.options[i]!=null && o.options[i].selected && o.options[i].value!=''){
	  resultat=true;
	  break;
	}
      }
    }else if(fmt=='') { // Cas "2 select non obligatoire"
      for(var i=0;i<o.options.length;i++) if(o.options[i]!=null) o.options[i].selected=true;
    } else if(fmt=='/(.+)/') { // Cas "2 select obligatoire"
      if(o.options.length==0) resultat=false;
      else for(var i=0;i<o.options.length;i++) if(o.options[i]!=null) o.options[i].selected=true;
    } else if(document.getElementById("table"+id)!=undefined){ // Cas autocomplete multiple
      if(document.getElementById("table"+id).tBodies[0].rows.length<2) resultat=false;
      o=document.getElementById(linktext);
    } else { // Autre cas : autocomplete, lien base doc...
      var phrase = o.value;
      var resultat = fmt.test(phrase);
      if(linktext){
	o=document.getElementById(linktext);
      }
    }
    if(!resultat) {
      TZR.setElementErrorState(o,false,color);
      TZR.isFormOk=false;
      return false;
    } else {
      TZR.setElementErrorState(o,true,color);
      return true;
    }
  }
}
TZR.isRadioValid = function (id,fmt,fieldlabel,color,linktext,listid) {
  var resultat=false;
  var o=document.getElementById(id);
  var l=listid.length;
  for(var i=0;i<l;i++){
    if(document.getElementById(listid[i]).checked){
      resultat=true;
      break;
    }
  }
  var tab=TZR.getParent(o,'TABLE');
  if(!resultat){
    TZR.setElementErrorState(o,false,color,tab);
    TZR.isFormOk=false;
    return false;
  } else {
    TZR.setElementErrorState(o,true,color,tab);
    return true;
  }
}
TZR.isThesaurusValid=function(id,fmt,fieldlabel,color){
  var o=document.getElementById("div"+id);
  var ok=false;
  jQuery('#table'+id+" input").each(function(i){
    if(this.value!=''){
      ok=true;
      return false;
    }
  });
  if(!ok){
    TZR.setElementErrorState(o,false,color);
    TZR.isFormOk=false;
    return false;
  } else {
    TZR.setElementErrorState(o,true,color);
    return true;
  }
}
TZR.isConsentValid = function(id,label,color){
  var o=document.getElementById(id);
  return o.checked;
}
TZR.isCaptchaValid = function(id,fmt,fieldlabel,color){
  var response=0;
  o=document.getElementById(id);
  jQuery.ajax({async:false,
	       url:TZR._sharescripts+"ajax8.php?class=_Seolan_Module_Table_Table&function=xmodtable_captcha",
	       cache:false,
	       data:{value:o.value,id:id},
	       success:function(resp){response=resp;}
  });
  if(response=='0') response=false;
  else response=true;
  if(!response) {
    document.getElementById("ca"+id).onclick();
    document.getElementById(id).value="";
    TZR.setElementErrorState(o,false,color);
    TZR.isFormOk=false;
    return false;
  } else {
    TZR.setElementErrorState(o,true,color);
    TZR.isFormOk=true;
    return true;
  }
}
TZR.isUploaderValid = function (id,fmt,fieldlabel,color) {
  var compulsory = document.getElementById(id).dataset.compulsory;
  var o=jQuery('#'+id+"_cont");
  var tocol = o;
  var oldfile = document.getElementById(id+'-old');

  // upload en cours ou
  // obligatoire sans au moins un fichier et pas encore renseigné (old)
  if((TZR.uploadInProgressFiles[id]>0) || (compulsory == '1' && !TZR.uploadXFileCompleted[id] && oldfile && oldfile.value == '')) {
     TZR.setElementErrorState(o,false,color,tocol);
     TZR.isFormOk=false;
     return false;
   }
  TZR.setElementErrorState(o,true,color,tocol);
  return true;
 }

/**
 * Conversion d'une taille de fichier en octets en unité de stockage correspondante
 */
function sizeToString(size,precision) {
  precision = precision || 0;
  var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  var bytes = Math.max(size, 0);
  var pow = Math.min( Math.floor( (bytes ? Math.log(bytes) : 0) / Math.log(1024) ), units.length);
  return Math.round(bytes >>= (10 * pow), precision) + ' ' + units[pow];
}

/**
 * Analyse le header d'un blob (ex: fichier) et retourne le mime type correspondant
 */
function getFileMimeType(blob) {
  var fileReader = new FileReader();
  fileReader.onloadend = function(e) {
    var arr = (new Uint8Array(e.target.result)).subarray(0, 4);
    var header = '';
    for(var i = 0; i < arr.length; i++) {
      header += arr[i].toString(16);
    }
    var type = null;
    switch (header) {
      case '89504e47':
        type = 'image/png';
        break;
      case '47494638':
        type = 'image/gif';
        break;
      case '0000000c6a5020200d0a870a':
        type = 'image/jp2';
        break;
      case 'ffd8':
      case 'ffd8ffe0':
        type = 'image/jpeg';
        break;
      case '424D':
        type = 'image/bmp';
        break;
      case '49492a00':
      case '4d4d002a':
      case '4d4d002b':
      case '492049':
        type = 'image/tiff';
        break;
      case '52494646':
        type = 'image/webp';
        break;
      default:
        type = blob.type;
        break;
    }
    return type;
  };
  fileReader.readAsArrayBuffer(blob);
}
TZR.isFileValid = function (id,fmt,fieldlabel,color,browsemods,allow_externalfile) {
  // Exception : BO + Uploader Async OFF si AJAX_UPLOADER n'est pas défini
  // (En BO, le Async est forcé donc pb de ciblage de fonction de validation : TZR.isFileValid => TZR.isUploaderValid)
  if(document.getElementById(id + '_cont')) {
    return TZR.isUploaderValid(id,fmt,fieldlabel,color);
  }  var o = document.getElementById(id);
  var tocol = o;
  // MaxSize + Mimes
  if(o.files && o.files.length > 0) {
    // MaxSize
    var checkSize = true;
    var maxSize = o.dataset.maxsize;
    if(maxSize !== undefined && maxSize != '') {
      var fileSize = o.files[0].size;
      checkSize = fileSize === undefined || maxSize && fileSize < maxSize;
    }
    // Mimes
    var accept = o.getAttribute('accept');
    var fileType = getFileMimeType(o.files[0]);
    if(fileType === undefined)
      fileType = o.files[0].type;
    var checkType = accept === null || accept === '' || accept.indexOf(fileType) >= 0;
    if( checkSize && checkType) {
     TZR.setCustomValidityMess(id, '');
      TZR.setElementErrorState(o,true,color,tocol);
    } else {
     TZR.setElementErrorState(o,false,color,tocol);
      TZR.isFormOk = false;
      if (!checkType)
        TZR.setCustomValidityMess(id, TZR.uploadFileAllowedType + accept);
      else if (!checkSize)
        TZR.setCustomValidityMess(id, TZR.uploadFileMaxSizeError + sizeToString(maxSize));
      return false;
    }
  }
  // Obligatoire
  var compulsory = o.dataset.compulsory;
  if(compulsory == '1') {
    o.isValid = false;
    if (o.value != '' || document.getElementById(id+'-old').value != '')
      o.isValid = true;
    else if (browsemods && document.getElementById(id+'-from').value != '')
      o.isValid = true;
    else if (allow_externalfile && document.getElementById(id+'-external').value != '')
      o.isValid = true;
    else if (jQuery('#'+id+'_cont').length) {
      tocol = jQuery('#'+id+'_cont');
      if(jQuery('#'+id+'_cont div.uploadQueueItem.uploadComplete:first').length)
        o.isValid = true;
    }
    TZR.setElementErrorStyle(o,color,tocol);
    if (!o.isValid) {
      TZR.isFormOk = false;
    }
  }
  return o.isValid;
}
TZR.isPassWordValid = function(val){
  var isValid=TZR.isShortTextValid(val[0],val[1],val[2],val[3]);
  var o1 = document.getElementById(val[0]);
  if (isValid && val[5] == "1"){ // avec confirmation
    if (typeof(val[6]) == 'string')
      var o = document.getElementById(val[6]);
    else
      var o = document.getElementById(val[0]+'_HID');
    // check si le confirm était en erreur (donc saisi) pour refresh
    if (o != null && typeof(o.isValid) != "undefined" && o.isValid == false){
      TZR.checkConfirmValid(o.getAttribute("id"), val[0], val[3]);
    }
  }
  return isValid;
}
TZR.isConfirmValid=function(id, mainid, color){
  // validité du parent
  if (!TZR.isIdValid(mainid)){
    return;
  }
  return TZR.checkConfirmValid(id, mainid, color);
}
TZR.checkConfirmValid = function(id, mainid, color){
  // égalité du contenu
  var o = document.getElementById(id);
  var reg = new RegExp('^'+RegExp.escape(document.getElementById(mainid).value)+'$');
  o.isValid = reg.test(o.value);
  if (!o.isValid){
    TZR.isFormOk=false;
  }
  TZR.setElementErrorState(o,o.isValid,color);
  return o.isValid;
}
TZR.setElementErrorState=function(o,state,color,tocol){
  o.isValid=state;
  TZR.setElementErrorStyle(o,color,tocol);
}
TZR.setElementErrorStyle=function(o,color,tocol){
  if(!tocol) tocol=o;
  tocol=jQuery(tocol);
  if(!tocol[0]) return;

  if(o.isValid==false){
    if(tocol[0].isError) return;
    tocol[0].isError=true;
    if(color.charAt(0)=='#') {
      tocol[0].obackgroundColor=tocol[0].style.backgroundColor;
      tocol.css('background-color',color);
    } else {
      tocol.addClass(color);
    }
  } else {
    if(!tocol[0].isError) return;
    tocol[0].isError=false;
    if(color.charAt(0)=='#') {
      if(tocol[0].obackgroundColor) tocol.css('background-color',tocol[0].obackgroundColor);
      else tocol.css('background-color','');
    } else {
      tocol.removeClass(color);
    }
  }
}
TZR.isFormValidWithAjax=function(f,method,overlay_target,color,callback){
  if(typeof f!=='object') f=document.getElementById(f);
  // Vide les erreurs précédentes
  var error_el=jQuery('div.error',f);
  error_el.html('');
  // Prepare les données
  var data=jQuery(f).serializeArray();
  for(var i in data){
    if(data[i].name=="function" || data[i].name=="_function") data[i].value=method;
    if(data[i].name=="uniqid") delete data[i];
  }
  data.push({name:'_raw', value:1});
  data.push({name:'_ajax', value:1});
  // fichier (tests types ....)
  jQuery("input[type='file']", jQuery(f)).each(function(i, o){
    data.push({name:o.name, value:o.value});
  });
  // Overlay
  if(overlay_target){
    if(overlay_target=='module-container') overlay_target=jQuery(f).parents('div.cv8-module-container');
    else overlay_target=jQuery(overlay_target);
    var overlay=TZR.setOverlay(overlay_target);
  }else{
    var overlay=null;
  }
  // Envoi
  var ok=false;
  jQuery.ajax({
      dataType:"json",
    url:f.action,
    data:data,
    async:false,
    type:f.method
  }).done(function(data){
    if(overlay) TZR.unsetOverlay(overlay);
    if(data.status=="success"){
      ok=true;
      if(callback) callback(true,data);
    }else{
      TZR.isFormOk=false;
      if(callback){
        if(callback(false,data)===false) return;
      }
      if(data.error){
        for(var i in data.error){
          error_el.append('<div class="error_message">'+data.error[i]+'</div>');
        }
        error_el.show();
      }
    }
  });
  return ok;
}
TZR.isIntervalValid = function (id,fmt,fieldlabel,color) {
  var hiddenField = document.getElementById(id);
  var pictoCalendar = jQuery('#pictoCalendar' + id);
  var phrase = hiddenField.value;
  var resultat = fmt.test(phrase);
  if (!resultat) {
    pictoCalendar.css("border", "1px solid red");
    TZR.isFormOk = false;
    return false;
    }
  pictoCalendar.css("border", "");
    return true;
}
TZR.isJsonValid = function(id, color) {
  var o = document.getElementById(id);
  var str = o.value;
  var isValid = true;
  if(str !== "") {
    try {
      var json = JSON.parse(str);
      isValid = (typeof str === 'string' && typeof json === 'object');
    }
    catch(e) {
      isValid = false;
    }
  }
  TZR.setElementErrorState(o, isValid, color);
  TZR.isFormOk = isValid;
  return isValid;
}
TZR.actualizeCaptcha=function(url,id){
  document.getElementById('cimg'+id).src=url+"?id="+id+"&"+(new Date()).getTime();
}
TZR.getUrlTitle=function(varid,color){
  var urlu=document.getElementById("url"+varid);
  var urll=document.getElementById("label"+varid);
  urlu.style.backgroundImage="url('/tzr/templates/ico/general/indicator.gif')";
  urlu.style.backgroundPosition="right center";
  urlu.style.backgroundRepeat="no-repeat";
  var reg=/^(((?!mailto)(?!\[)).+)$/;
  if(!reg.test(urlu.value)){ // mail ou un alias console
    urlu.obackgroundColor=urlu.style.backgroundColor;
    urlu.style.backgroundColor=color;
  }else{
    var title=TZR.file_get_contents(TZR._sharescripts+'ajax8.php?function=xurldef_getPageTitle&class=\\Seolan\\Field\\Url\\Url&url='+escape(urlu.value));
    if(title=="error"){
      urlu.obackgroundColor=urlu.style.backgroundColor;
      urlu.style.backgroundColor=color;
    }else{
      if(urlu.obackgroundColor) urlu.style.backgroundColor=urlu.obackgroundColor;
      else urlu.style.backgroundColor='';
      urll.value=title;
    }
  }
  urlu.style.backgroundImage="";
  urlu.style.backgroundPosition="";
  urlu.style.backgroundRepeat="";
}
TZR.openPopup = function(url){
  window.open(url,'','menubar=no,status=no,resizable=1,scrollbars=1,width=700,height=500');
  return false;
}
TZR.validFormInPopup = function(f){
  var ret=TZR.isFormValidWithFocus(f,true);
  if(ret){
    window.opener.name=TZR.uniqid('TZR');
    document.editform.target=window.opener.name;
    f.submit();
    self.close();
    return false;
  }else{
    return false;
  }
}
TZR.isFormValid = function (f) {
  return TZR.isFormValidWithFocus(f,false);
}
TZR.isFormValidWithFocus=function(f,foc) {
  TZR.isFormOk=true;
  TZR.errorGiveFocus=true;
  for(var i=0;i<TZR.validator.length;i++){
    if (typeof TZR.validator[i][0] == "function")
      var o = TZR.validator[i][0](TZR.validator[i]);
    else if(typeof TZR.validator[i][0]!=='object')
      var o=document.getElementById(TZR.validator[i][0]);
    else
      var o=TZR.validator[i][0]; // ? contrôle du formulaire en ajax
    
    if(o!=null && (o==f || o.form==f) && !jQuery(o).prop('data-disabled')) {
      if(TZR.validator[i][99]){
	TZR.isIdxValid(i);
	if(foc) {
	  if(!TZR.isFormOk) {
            if(!TZR.errorGiveFocus) return false;
	    if(TZR.validator[i][5]){
	      if(typeof TZR.validator[i][5]=='string') o=document.getElementById(TZR.validator[i][5]);
              else o=TZR.validator[i][5];
            }
	    jQuery(o).parents('fieldset.fieldsetAccordionClose').find('>legend').click();
            var tabid = jQuery(o).parents('.tzr-tabcontent').attr('id');
            if (typeof(tabid) != "undefined" ){
              jQuery('li>a[href="#'+tabid+'"]').click();
            }
	    jQuery(o).focus();
            if(jQuery(o).is(':not(:input),:hidden')) scrollToElement(jQuery(o).closest(':visible'));
	    return false;
	  }
	}
      }
    }else if(!o){
      TZR.validator.splice(i,1);
      i--;
    }
  }
  nb=TZR.onsubmit.length;
  for(i=0; i<nb; i++) {
    eval(TZR.onsubmit[i]);
  }
  if(!TZR.isFormOk) {
    if(typeof(TZR.customValid)!='undefined')
      return TZR.customValid(f);
    else
      return confirm('Some data are not valid. Save anyway ?');
  }
  return true;
}
TZR.isIdxValid = function (i) {
   var isValid=false;
   var val=TZR.validator[i];
   if(val[4]=='Form')
      isValid=TZR.isFormValidWithAjax(val[0],val[1],val[2],val[3],val[6]);
   else if(val[4]=='\Seolan\Field\ShortText\ShortText')
      isValid=TZR.isShortTextValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='\Seolan\Field\Real\Real')
      isValid=TZR.isShortTextValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='\Seolan\Field\Rating\Rating')
      isValid=TZR.isEvalValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='\Seolan\Field\Password\Password')
      isValid=TZR.isPassWordValid(val);
   else if(val[4]=='Confirm')
      isValid=TZR.isConfirmValid(val[0],val[5],val[3],i);
   else if(val[4]=='\Seolan\Field\Document\Document')
      isValid=TZR.isDocumentValid(val);
   else if(val[4]=='\Seolan\Field\Link\Link')
     isValid=TZR.isLinkValid(val[0],val[1],val[2],val[3],val[5],val[6]);
  else if(val[4]=='\Seolan\Field\User\User')
    isValid=TZR.isUserFieldValid(val[0],val[1],val[2],val[3],val[5],val[6]);
   else if(val[4]=='\Seolan\Field\StringSet\StringSet')
      isValid=TZR.isLinkValid(val[0],val[1],val[2],val[3],val[5],val[6]);
   else if(val[4]=='\Seolan\Field\Thesaurus\Thesaurus')
      isValid=TZR.isThesaurusValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='Captcha')
      isValid=TZR.isCaptchaValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='XFileUploader')
      isValid=TZR.isUploaderValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='\Seolan\Field\File\File')
     isValid=TZR.isFileValid(val[0],val[1],val[2],val[3],val[6],val[7]);
   else if(val[4]=='\Seolan\Field\Interval\Interval')
     isValid=TZR.isIntervalValid(val[0],val[1],val[2],val[3],val[6],val[7]);
   else if (val[4]=='Consent')
     isValid=TZR.isConsentValid(val[0],val[1],val[2],val[3]);
   else if(val[4]=='\Seolan\Field\Serialize\Serialize')
     isValid=TZR.isJsonValid(val[0],val[3]);
   return isValid;
}
TZR.autoCompleteCache = {};
TZR.addAutoComplete=function(id) {
  var acfield = jQuery("#_INPUT" + id), hiddenfield;
  if (acfield.length) {
    hiddenfield = document.getElementById(id);
  } else { // cas shorttext edit
    acfield = jQuery("#" + id);
  }
  // init/empty cache
  TZR.autoCompleteCache[acfield.attr('id')] = {};
  var _minLength = acfield.data("autocomplete").minlength!=undefined?acfield.data("autocomplete").minlength:3;
  acfield.keydown(function(event) {
    if(event.keyCode!=13 && event.keyCode!=9 && hiddenfield)
      hiddenfield.value="";
  }).autocomplete({
    autoFocus: true,
    delay: 500,
    minLength:_minLength,
    source : function(request, response) {
      var autocomplete = this.element.data('autocomplete');
      // check cache
      var term = request.term.toLowerCase().trim(),
          cache = TZR.autoCompleteCache[acfield.attr('id')];

      if (cache && cache[term]) {
        response(cache[term]);
        return;
      }
      for (cachedTerm in cache) {
        var regexp = new RegExp((autocomplete.params.prefixSearch ? '^' : '') + cachedTerm);
        if (regexp.test(term) && !cache[cachedTerm]) {
          response();
          return false;
        }
      }

      jQuery.ajax({
          url: autocomplete.url + '&q=' + request.term,
          dataType: "json",
          data: autocomplete.params,
          success: function(data) {
            if (data !== null) {
              for(var i=0; i<data.length; i++){
                // if not object value must be a string
                if (typeof(data[i]) == "number"){
                  data[i] = data[i].toString();
                }
              }
            }
            TZR.autoCompleteCache[acfield.attr('id')][term] = data;
            response(data);
          }
        });
    },
    select : function(event, ui) {
      var autocomplete = jQuery(this).data('autocomplete');
      if (autocomplete.callback && typeof autocomplete.callback == 'function') {
        autocomplete.callback(autocomplete.params.id, ui.item.value, ui.item.label);
      } else {
        if (hiddenfield) {
          hiddenfield.value = ui.item.value;
        }
        acfield.val(ui.item.label);
      }
      jQuery(hiddenfield).change();
      return false; // job done
    },
    open: function() {
      jQuery('.ui-autocomplete').css({width:'300px', zIndex:99999});
    },
    focus: function( event, ui ) {
      event.preventDefault();
    }
  }).on('focus', function(e) {
    if(_minLength==0)
      jQuery(this).autocomplete( "search", "%" );
   });
}

TZR.autoCompleteTagCache = {};
TZR.addAutoCompleteTag=function(id, options) {
    options = jQuery.extend({count:2, list:false, textskey:'#'},options);
    var hiddenfield = document.getElementById(id),
        acfield = jQuery("#_INPUT"+id),
	alive = false;
    // init/empty cache
    TZR.autoCompleteTagCache[acfield.attr('id')] = {};
    acfield.keydown(function(event) {
        if(event.keyCode!=13 && event.keyCode!=9 && hiddenfield) hiddenfield.value="";
        if(event.keyCode==13) {
            event.preventDefault();
            return false;
        }
    }).autocomplete({
        autoFocus: true,
        delay: 500,
        minLength: options.count,
	create: function() {
	    if(options.list) {
		var self = this;
		jQuery(self).data('ui-autocomplete').close = function(e) {
		    if(alive) {
			return false;
		    }
		    this.cancelSearch = true;
		    this._close( e );
		}

		alive = true;
		jQuery(jQuery(self).data('ui-autocomplete').bindings[1]).on('mouseenter', function(e) {
		    alive = true;
		});
		jQuery(jQuery(self).data('ui-autocomplete').bindings[1]).on('mouseleave', function(e) {
		    alive = false;
		    setTimeout(
			function(){
			    if (!alive) {
				jQuery(self).data('ui-autocomplete').close();
			    }
			}, 500
		    );
		});
	    }
	},
        source : function(request, response) {
            // check cache
	    request.term = request.term.normalize('NFD').replace(/[\u0300-\u036f]/g, "");
            var term = request.term.toLowerCase().trim(),
                cache = TZR.autoCompleteTagCache[acfield.attr('id')];

            if (cache && cache[term]) {
                response(cache[term]);
                return;
            }
            var autocomplete = this.element.data('autocomplete');
            jQuery.ajax({
                url: autocomplete.url + '&q=' + request.term,
                dataType: "json",
                data: autocomplete.params,
                success: function(data) {
                    if (!TZR.autoCompleteTagCache[acfield.attr('id')]) {
                        TZR.autoCompleteTagCache[acfield.attr('id')] = {};
                    }
                    TZR.autoCompleteTagCache[acfield.attr('id')][term] = data;
                    response(data);
                },
            });
        },
        select : function(event, ui) {
            var autocomplete = jQuery(this).data('autocomplete');
            if (autocomplete.callback && typeof autocomplete.callback == 'function') {
                autocomplete.callback(autocomplete.params.id, ui.item.value, ui.item.label, true);
            } else {
                if (hiddenfield) {
                    hiddenfield.value = ui.item.value;
                }
                acfield.val(ui.item.label);
            }
            jQuery(hiddenfield).change();
            return false; // job done
        },
        open: function() {
            jQuery('.ui-autocomplete').css({width: '300px'});
        },
        focus: function( event, ui ) {
            event.preventDefault();
        }
    }).on('focus click', function(e) {
	jQuery(this).autocomplete( "search", "" );
    });
}

TZR.autoCompleteTagSearchCache = {};
TZR.autoCompleteTagSearchCacheIdx = [];
TZR.autoCompleteTagSearchCacheTerm = null;
TZR.autoCompleteTagSearchInit = null;
TZR.addAutoCompleteTagSearch=function(id, options) {
    options = jQuery.extend({count:2, list:false, textskey:'#'},options);
    var acfield = jQuery("#"+id),
        alive = false;
    TZR.autoCompleteTagSearchInit = options.init;
    // init/empty cache
    TZR.autoCompleteTagSearchCache[id] = {};
    acfield.on("keydown", function(event) {
        var keyCode = jQuery.ui.keyCode;
	switch(event.keyCode) {
        case keyCode.ENTER:
            if(!options.list){
                event.preventDefault();
                return false;
            }
            break;
	}
    }).autocomplete({
        autoFocus: true,
        delay: 500,
        minLength: options.count,
        create: function() {
            if (options.list) {
                var self = this;

                jQuery(self).data('ui-autocomplete').close = function(e) {
                    if(alive) {
                        return false;
                    }
                    this.cancelSearch = true;
                    this._close( e );
                }

                alive = true;
                jQuery(jQuery(self).data('ui-autocomplete').bindings[1]).on('mouseenter', function(e) {
                    alive = true;
                });
                jQuery(jQuery(self).data('ui-autocomplete').bindings[1]).on('mouseleave', function(e) {
                    alive = false;
                    setTimeout(
                        function(){
                            if (!alive) {
                                jQuery(self).data('ui-autocomplete').close();
                            }
                        }, 500
                    );
                });
            }
        },
        source : function(request, response) {
          //Permet de ne pas afficher la liste des tags après un recherche
          if (TZR.autoCompleteTagSearchInit === true) {
            TZR.autoCompleteTagSearchInit = false;
            response([]);
            return;
          }
          
	    request.term = request.term.normalize('NFD').replace(/[\u0300-\u036f]/g, "");
            var strings = request.term.split(' ');
            var term = strings[strings.length-1].toLowerCase(),
                cache = TZR.autoCompleteTagSearchCache[id];
            TZR.autoCompleteTagSearchCacheTerm = request.term;
            if (cache && cache[term]) {
                TZR.autoCompleteTagSearchCacheIdx[id] = -1;
                response(cache[term]);
                return;
            }
            var autocomplete = this.element.data('autocomplete');
            var url = autocomplete.url + '&q=' + term.replace(options.textskey,'');
            jQuery.ajax({
                url: url,
                dataType: "json",
                data: autocomplete.params,
                success: function(data) {
                    if (!TZR.autoCompleteTagSearchCache[id]) {
                        TZR.autoCompleteTagSearchCache[id] = {};
                    }
                    TZR.autoCompleteTagSearchCache[id][term] = data;
                    TZR.autoCompleteTagSearchCacheIdx[id] = -1;
                    response(data);
                },
            });
        },
        select : function(event, ui) {
            var autocomplete = jQuery(this).data('autocomplete');
            if (autocomplete.callback && typeof autocomplete.callback == 'function') {
                autocomplete.callback(autocomplete.params.id, ui.item.value, ui.item.label);
            } else {
                var strings = TZR.autoCompleteTagSearchCacheTerm.split(' ');
                strings[strings.length-1] = options.textskey+ui.item.label;
                acfield.val(strings.join(' '));
                //acfield.val(ui.item.label);
            }
            return false; // job done
        },
        open: function() {
            jQuery('.ui-autocomplete').css({width: '300px'});
        },
        focus: function( event, ui ) {
            event.preventDefault();
        }
    }).autocomplete("search");
}

TZR.removeAutoCompleteTagSearch=function(id) {
    var acfield = jQuery("#"+id);
    // init/empty cache
    TZR.autoCompleteTagSearchCache[id] = {};
    TZR.autoCompleteTagSearchCacheIdx = [id];
    TZR.autoCompleteTagSearchCacheTerm = null;
    acfield.autocomplete("destroy");
}

TZR.isAutoCompleteTagSearch=function(id) {
    return jQuery("#"+id).autocomplete("instance") != undefined;
}

function getCharBeforeCaret (element) {
    var input = element.get(0);
    var pos = 0;
    if (!input) return; // No (input) element found
    if ('selectionStart' in input) {
        // Standard-compliant browsers
        pos = input.selectionStart;
    } else if (document.selection) {
        // IE
        input.focus();
        var sel = document.selection.createRange();
        var selLen = document.selection.createRange().text.length;
        sel.moveStart('character', -input.value.length);
        pos = sel.text.length - selLen;
    }
    pos--;
    if (pos >= 0) {
        return element.val().substr(pos,1);
    } else {
        return "";
    }
}

function getCharBeforeCaretCKE(editor) {
    var range = editor.getSelection().getRanges()[0],
        startNode = range.startContainer;

    if (startNode.type == CKEDITOR.NODE_TEXT && range.startOffset)
        return startNode.getText()[range.startOffset - 1];
    else {
        range.collapse(true);
        range.setStartAt(editor.editable(), CKEDITOR.POSITION_AFTER_START);

        var walker = new CKEDITOR.dom.walker(range),node;

        while ((node = walker.previous())) {
            if (node.type == CKEDITOR.NODE_TEXT)
                return node.getText().slice(-1);
        }
    }
    return "";
}
TZR.tagAutoCompleteCallback = function(){
    if (typeof(add_input_tag) == "function"){
	return add_input_tag;
    } else {
	return null;
    }
}
TZR.autoCompleteTagInputCache = {};
TZR.addAutoCompleteTagInput=function(id, options) {
    options = jQuery.extend({users:false,texts:false,userskey:"@",textskey:"#"},options);
    if (!options.users && !options.text){
	return;
    }
    var hiddenfield = document.getElementById("TAG_text");
    acfield = jQuery("#"+id);
    // init/empty cache
    TZR.autoCompleteTagInputCache[acfield.attr('id')] = {};
    acfield.keydown(function(event) {
	// capture key if allowed
	if (event.key==options.textskey && !options.texts)
	    return;
	if (event.key==options.userskey && !options.users)
	    return;
        if  (event.key==options.userskey || event.key==options.textskey && (options.users || options.texts)) {
            var usercomplete = 0;
            var pos = jQuery("#"+id).caret('offset');
            var lastchar = getCharBeforeCaret(jQuery("#"+id));
            if (event.key==options.userskey) { // user completion
                if (lastchar.match(/\S/)) { // @ in a word : ignore
                    return;
                } else {
                    usercomplete = 1;
                }
            } else if (!hiddenfield) {
                return;
            }
            var text = document.getElementById("texttaginput");
            if (!text) {
                text = document.createElement("input");
                text.id = "texttaginput";
                text.className = "texttaginput";
                text.onblur=function(){jQuery("#texttaginput").hide();};
                document.body.appendChild(text);
            }
            jQuery("#texttaginput").keydown(function(event){
                if(event.keyCode==13) {
                    event.preventDefault();
                    jQuery("#texttaginput").hide();
                    return false;
                }
            }).autocomplete({
                autoFocus: true,
                delay: 500,
                minLength: 3,
                source : function(request, response) {
                    // check cache
                    var term = request.term.toLowerCase().trim();
                        cache = TZR.autoCompleteTagInputCache[acfield.attr('id')];
                    if (cache && cache[term]) {
                        response(cache[term]);
                        return;
                    }
                    var autocomplete = jQuery("#"+id).data('autocomplete');
                    var acurl = autocomplete.url;
                    if (usercomplete) {
                        acurl = autocomplete.url2;
                    }
                    jQuery.ajax({
                        url: acurl + '&q=' + escape(term),
                        dataType: "json",
                        data: autocomplete.params,
                        success: function(data) {
                            if (!TZR.autoCompleteTagInputCache[acfield.attr('id')]) {
                                TZR.autoCompleteTagInputCache[acfield.attr('id')] = {};
                            }
                            TZR.autoCompleteTagInputCache[acfield.attr('id')][term] = data;
                            response(data);
                        },
                    });
                },
                select : function(event, ui) {
                    var autocomplete = jQuery("#"+id).data('autocomplete');
                    var pid = jQuery("#texttaginput").attr("pid");
                    if (autocomplete.callback && typeof autocomplete.callback == 'function') {
                        if (usercomplete) {
                            autocomplete.callback(pid, ui.item.value, ui.item.label);
                        } else {
                            autocomplete.callback(pid, ui.item.value, ui.item.label, "TAG");
                        }
                    } else {
                        if (hiddenfield) {
                            hiddenfield.value = ui.item.value;
                        }
                        acfield.val(ui.item.label);
                    }
                    jQuery(hiddenfield).change();
                    return false; // job done
                },
                open: function() {
                    jQuery('.ui-autocomplete').css({width: '300px'});
                },
                focus: function( event, ui ) {
                    event.preventDefault();
                }
            });
            text.style.left = pos.left+"px";
            text.style.top = pos.top+"px";
            text.value = event.key;
            jQuery("#texttaginput").attr("pid",id);
            jQuery("#texttaginput").show();
            text.focus();
            text.setSelectionRange(1,1);
            return false;
        }
    });
}

TZR.autoCompleteTagCKECache = {};
TZR.addAutoCompleteTagCKE=function(id, options) {
    options = jQuery.extend({users:false,texts:false,userskey:"@",textskey:"#"},options);
    var hiddenfield=document.getElementById("TAG_text");
    acfield = jQuery("#"+id);
    // init/empty cache
    TZR.autoCompleteTagCKECache[acfield.attr('id')] = {};
    CKEDITOR.instances[id].on( 'key', function(evt) {
        var key = evt.data.domEvent['$'].key;
        // capture key if allowed
        if (key==options.textskey && !options.texts)
          return;
        if (key==options.userskey && !options.users)
          return;
        if  (key==options.userskey || key==options.textskey && (options.users || options.texts)) {
            var usercomplete = 0;
            var css_class = 'cketag';
            if (key==options.userskey) { // user completion
                var lastchar = getCharBeforeCaretCKE(CKEDITOR.instances[id]);
                if (lastchar.match(/\S/)) { // @ in a word : ignore
                    return;
                } else {
                    usercomplete = 1;
                    css_class = 'ckeusertag';
                }
            } else if (!hiddenfield) {
                return;
            }
            var ifr = jQuery('#'+id).parent().find('.cke_wysiwyg_frame')[0];
            var ckeid = "cke"+new Date().getTime();
            this.insertHtml('<span id="'+ckeid+'" class="'+css_class+'">'+key+'</span>&nbsp;');
            var pos = jQuery('body', ifr.contentDocument).caret('offset', {iframe: ifr});
            if (pos) {
                var left = jQuery(ifr).offset().left + pos.left;
                var top = jQuery(ifr).offset().top + pos.top;
                var text = document.getElementById("texttaginput");
                if (!text) {
                    text = document.createElement("input");
                    text.id = "texttaginput";
                    text.className = "texttaginput";
                    text.onblur=function(){jQuery("#texttaginput").hide();};
                    document.body.appendChild(text);
                }
                jQuery("#texttaginput").keydown(function(event){
                    if(event.keyCode==13) {
                        event.preventDefault();
                        jQuery("#texttaginput").hide();
                        return false;
                    }
                }).autocomplete({
                    autoFocus: true,
                    delay: 500,
                    minLength: 2,
                    source : function(request, response) {
                        // check cache
                        var term = request.term.toLowerCase().trim();
                        var cache = TZR.autoCompleteTagCKECache[acfield.attr('id')];

                        if (cache && cache[term]) {
                            response(cache[term]);
                            return;
                        }

                        var autocomplete = jQuery("#"+id).data('autocomplete');
                        var acurl = autocomplete.url;
                        if (usercomplete) {
                            acurl = autocomplete.url2;
                        }
                        jQuery.ajax({
                            url: acurl + '&q=' + escape(term),
                            dataType: "json",
                            data: autocomplete.params,
                            success: function(data) {
                                if (!TZR.autoCompleteTagCKECache[acfield.attr('id')]) {
                                    TZR.autoCompleteTagCKECache[acfield.attr('id')] = {};
                                }
                                TZR.autoCompleteTagCKECache[acfield.attr('id')][term] = data;
                                response(data);
                            },
                        });
                    },
                    select : function(event, ui) {
                        var autocomplete = jQuery("#"+id).data('autocomplete');
                        var pid = jQuery("#texttaginput").attr("pid");
                        if (autocomplete.callback && typeof autocomplete.callback == 'function') {
                            if (usercomplete) {
                                autocomplete.callback(pid, ui.item.value, ui.item.label);
                            } else {
                                autocomplete.callback(pid, ui.item.value, ui.item.label, "TAG");
                            }
                        } else {
                            if (hiddenfield) {
                                hiddenfield.value = ui.item.value;
                            }
                            acfield.val(ui.item.label);
                        }
                        jQuery(hiddenfield).change();
                        return false; // job done
                    },
                    open: function() {
                        jQuery('.ui-autocomplete').css({width: '300px'});
                    },
                    focus: function( event, ui ) {
                        event.preventDefault();
                    }
                });
                text.style.left = left+"px";
                text.style.top = top+"px";
                text.value = "";
                jQuery("#texttaginput").attr("pid",id);
                jQuery("#texttaginput").attr("ckeid",ckeid);
                jQuery("#texttaginput").show();
                text.focus();
            }
        }
    });
}


TZR.autoCompleteMultipleFields = function (id, oid, v) {
  var autocomplete = jQuery('#' + id).data('autocomplete'),
      relatedFields = autocomplete.params.relatedFields.split(','),
      _values = v.split(', ');
  jQuery('#' + id).val(_values[0]);
  for (key in relatedFields) {
    jQuery(':input[name="' + relatedFields[key] + '"]').val(_values[parseInt(key) + 1]);
  }
};
TZR.autoCompleteMultipleValue=function(id,oid,v){
  if(jQuery('#table'+id).find('input[value="'+oid+'"]').length>0) return;
  var tr=TZR.addTableLine("table"+id,[undefined,v],0,false);
  jQuery('input',tr.cells[0]).val(oid);
  jQuery(tr).show();
  jQuery('#_INPUT'+id).val('');
};
TZR.autoCompleteUsers = function(id, oid, label){
  if (oid == '*no_result*')
    return;
  TZR.UserSelector.autoCompleteSelection.call(TZR.UserSelector, id, oid, label);
};
TZR.isIdValid = function (id) {
  found=false;
  nb=TZR.validator.length;
  for(i=0;i<nb;i++) {
    if(id==TZR.validator[i][0]) {
	if(!TZR.isIdxValid(i)){
	    var valmess = jQuery("#"+id).data('pattern-error-message');
	    if (typeof valmess != "undefined")
		TZR.setCustomValidityMess(id, valmess);
	    else
		TZR.setCustomValidityMess(id, "");
	    return false;
	} else {
	    TZR.setCustomValidityMess(id, "");
	}
    }
  }
  if(typeof(TZR.customOnBlur)!='undefined')
    return TZR.customOnBlur(id);
  return true;
}
TZR.setCustomValidityMess = function(id, valmess){
    try{
	document.getElementById(id).setCustomValidity(valmess);
    } catch(except){
	// IE9
    }
}
TZR.copyDivToHidden = function (idsrc, iddst) {
  var objsrc = document.getElementById(idsrc);
  var objdst = document.getElementById(iddst);
  if(objsrc.innerHTML=="...") objdst.value="";
  else objdst.value = objsrc.innerHTML;
  return true;
}

TZR.addOnSubmit=function (src) {
  nb=TZR.onsubmit.length;
  TZR.onsubmit[nb]=src;
}
TZR.addValidator=function(src){
  var type = typeof src[0];
  if(type!=='object'){
    var o=document.getElementById(src[0]);
  } else {
    var o=src[0];
  }
  if(!o) return false;
  if(!src[5] && src[4]=='Form') src[5]=jQuery('div.error:visible:first',o);
  if(src[99]===undefined) src[99]=true;
  var v=jQuery(o).data('validators');
  if(!v) v=[src];
  else v.push(src);
  jQuery(o).data('validators',v);
  TZR.validator.push(src);
  return true;
}
TZR.changeValidatorsState=function(id,active){
  if(!active) active=false;
  var vs=jQuery('#'+id+',#url'+id).data('validators');
  if(vs){
    for(var i in vs){
      vs[i][99]=active;
    }
  }
}
/* existe aussi TZR.Field.editSec */
TZR.editSec=function (selfu, moid, oid, selectedfrom, _function) {
  if (typeof _function == 'undefined') {
    _function = 'secEditSimple';
  }
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
    oid="oid="+oid;
  }
  TZR.Dialog.openURL(selfu+'&function='+_function+'&template=Core/Module.edit-sec.html&moid='+moid+'&tplentry=br&'+oid);
  return false;
}

TZR.autocomplete_cb = function (suggestion) {
  if ( suggestion ) {
    var inp = TZR.autocomplete_field;
    var inp_enc = TZR.autocomplete_encoded_field;
    inp_enc.value=suggestion[0];
    // IE
    if ( document.selection ) {
      var sel = document.selection.createRange();
      sel.text = suggestion[1];
      sel.move( 'character', -suggestion[1].length );
      sel.findText( suggestion[1] );
      sel.select();
    } else {
      var preLength = inp.value.length;
      inp.value += suggestion[1];
      inp.selectionStart = preLength;
      inp.selectionEnd   = inp.value.length;
    }
  }
}

TZR.autocomplete_running=false;
TZR.autocomplete_precheck = function ( tab, field, myform, e ) {
  // Check for alpha numeric keys
  TZR.autocomplete_field = myform.elements[field+'_HID'];
  TZR.autocomplete_encoded_field = myform.elements[field];
  if ( ( e.keyCode >= 48 && e.keyCode <= 57 ) || ( e.keyCode >= 65 && e.keyCode <= 90 ) ) {
    x_xlinkdef_autocomplete( tab, field, TZR.autocomplete_field.value, TZR.autocomplete_cb);
  }
}

// Inverse la coche de toutes les checkbox d'un formulaire
TZR.toggleCheckBoxes = function(src,startn,endn) {
  if(!startn) startn='selectstart';
  var start=false;
  for(var i=0;i<src.elements.length;i++) {
    if(endn){
      if(src.elements[i].value==endn) break;
    }
    if(start) src.elements[i].checked = !src.elements[i].checked;
    if(src.elements[i].value==startn) start=true;
  }
}
// Coche ou decoche toutes les checkbox d'un formulaire
TZR.checkBoxes = function(src,value,startn,endn) {
  if(!startn) startn='selectstart';
  var start=false;
  for(var i=0;i<src.elements.length;i++) {
    if(endn){
      if(src.elements[i].value==endn) break;
    }
    if(start && (src.elements[i].type=='checkbox')) src.elements[i].checked=value;
    if(src.elements[i].value==startn) start=true;
  }
}
// Retourne vrai si au moins une checkbox est coché dans le formulaire
TZR.checkBoxesIsChecked = function(src,startn,endn,nameregex) {
  if(!startn) startn='selectstart';
  var start=false;
  for(var i=0;i<src.elements.length;i++) {
    if(endn){
      if(src.elements[i].value==endn) break;
    }
    if(src.elements[i].value==startn){
      start=true;
      continue;
    }
    if(nameregex && !nameregex.test(src.elements[i].name)) continue;
    if(start && src.elements[i].checked) return true;
  }
  return false;
}

TZR.referer = function(markers,lang) {
 var maintenant = new Date();
 var msg=maintenant.toLocaleString();
 var n1=new Image();
 n1.src=TZR._sharescripts+'marker.php?_marks='+markers+'&_lang='+lang+'&alea='+msg;
}
TZR.refererWithTotalOn = function(markers,lang,totalon) {
 var maintenant = new Date();
 var msg=maintenant.toLocaleString();
 var n1=new Image();
 n1.src=TZR._sharescripts+'marker.php?_marks='+markers+'&_lang='+lang+'&_total='+totalon+'&alea='+msg;
}

TZR.setDateEmpty = function(id) {
  document.getElementById(id).value='';
  return false;
}

TZR.selectDocument=function(selfu,moid,id,showfiles,title){
  if(typeof(showfiles)=='undefined') showfiles=1;
  TZR.Dialog.openURL(selfu+'&function=index2Light&nosess=1&template=Module/DocumentManagement.modaltree.html&moid='+moid+'&tplentry=br&showfiles='+showfiles+'&_raw=1&_silent=1&___action=selectDoc&___target='+id+'&title='+escape(title),
		     null,
		     {
		       initCallback:{
			 _function:"TZR.DocumentManagement.ModalTree.init",
			 _param:{
			   selectedcb:function(oid, title){
			     TZR.DocumentManagement.ModalTree.selectDocument.call(TZR.DocumentManagement.ModalTree, oid, title);
			   },
			   moid:moid,
			   target:id,
			   action:"selectDoc"
			 }
		       }
		     }
  );
};

TZR.setDocumentEmpty = function(id) {
  document.getElementById("id_"+id).value='';
  document.getElementById("id_INPUT"+id).value='';
  jQuery("#id_"+id).change();
  return false;
}

TZR.selectTopic = function(moid, selectedTopicOid, onUnloadCallback) {
  var params = {
    'function': 'home',
    'template': 'Module/InfoTree.popaction.html',
    'moid': moid,
    'tplentry': 'mit',
    'do': 'showtree',
    'action': 'selectTopic',
    'maxlevel': '5',
    '_selected': {}
  };
  if (typeof(selectedTopicOid) == 'string' && selectedTopicOid.length > 0)
    params._selected[selectedTopicOid] = '1';
  TZR.Dialog.openURL(TZR._self+jQuery.param(params), {}, {closeCallback:onUnloadCallback});
}

TZR.applySelected=function(func,form,message,template,tplentry,noselect) {
   if(TZR.checkBoxesIsChecked(form)){
     form.target='_self';
     form._function.value=func;
     form.message.value=message;
     if(template!==false){
       form.template.value=template;
       if (form.elements['_next']){
	 form._next.value="";
       }
     }
     if(tplentry!==false) form.tplentry.value=tplentry;
     if(!form.onsubmit || form.onsubmit()) form.submit();
   }else{
     alert(noselect);
     return;
   }
}

TZR.doubleAdd = function(src,dst,morder) {
  var i=0;
  var changed = false;
  if(morder) {
    if (jQuery(dst).find('optgroup').length > 0) { // grouped options
      for (i=0; i<src.options.length; i++) {
        if (src.options[i].selected) {
	  changed = true;
          order = parseInt(jQuery(src.options[i]).attr('order'));
          groupId = src.options[i].parentNode.id.replace(/(unselected_)?(.*)/, "$2");
          dstGroup = jQuery(dst).find('optgroup[id$="'+groupId+'"]');
          opts = jQuery(dstGroup).find('option');
          for (j=0; j<opts.length; j++) {
            if (parseInt(jQuery(opts[j]).attr('order')) > order)
              break;
          }
          if (opts[j])
            jQuery(opts[j]).before(src.options[i]);
          else
            jQuery(dstGroup).append(src.options[i]);
          i--;
        }
      }
    } else { // non grouped options
      for (i=0; i<src.options.length; i++) {
        if (src.options[i].selected) {
	  changed = true;
          order = parseInt(jQuery(src.options[i]).attr('order'));
          for (j=0; j<dst.options.length; j++) {
            if (parseInt(jQuery(dst.options[j]).attr('order')) > order)
              break;
          }
          if (dst.options[j])
            jQuery(dst.options[j]).before(src.options[i]);
          else
            jQuery(dst).append(src.options[i]);
          i--;
        }
      }
    }
  } else {
    for (i=src.options.length-1; i>=0; i--) {
      if (src.options[i].selected) {
        jQuery(dst).append(src.options[i]);
	changed = true;
      }
    }
  }
  jQuery(src).val('');
  jQuery(dst).val('');
  // trigger the change event for additionnal control (FO usage)
  // if change occured
  if (changed){
    jQuery(dst).trigger('change', ['doubleboxchange']);
    jQuery(src).trigger('change', ['doubleboxchange']);
  }
}

TZR.idxidx=10;
TZR.addTableLine = function(tableid,td,trtoclone,del) {
  if(td[1]=='--') return;
  if(typeof(trtoclone)=='undefined') trtoclone=0;
  if(typeof(del)=='undefined') del=true;
  var table = document.getElementById(tableid);
  var tbody = table.tBodies[0];
  var tr = tbody.rows[trtoclone].cloneNode(true);
  if(del){
    for(i=0;i<tr.cells.length; i++) {
      tr.cells[i].innerHTML="";
    }
  }
  table.tBodies[0].appendChild(tr);
  var td3;
  for(i=0;i<td.length; i++) {
    if(td[i]!=undefined){
      td3=td[i].replace(/xidxid/g,'xid'+TZR.idxidx);
      jQuery(tr.cells[i]).html(td3);
    }
  }
  TZR.idxidx++;
  return tr;
}

/* ajout d'une valeur a partir d'une combo */
TZR.addValueToShortText = function(text, multi, sep) {
   var text1 = document.getElementById(text);
   var combo1 = document.getElementById(text+'_H');
   if(combo1.selectedIndex==0) return;
   if(multi) {
     if(text1.value=='') sep='';
     text1.value+=sep+combo1.options[combo1.selectedIndex].text;
   } else {
     text1.value=combo1.options[combo1.selectedIndex].text;
   }
   jQuery(text1).trigger('change');
   return;
}

/* trouve le tag "parentTagName" parent de "element" */
TZR.getParent = function(element, parentTagName) {
  if ( ! element ) return null;
  else if ( element.nodeType == 1 && element.tagName.toLowerCase() == parentTagName.toLowerCase() ) return element;
  else return TZR.getParent(element.parentNode, parentTagName);
}

/* supprimer une ligne dans un tableau */
TZR.delLine = function(link) {
  var td = link.parentNode;
  var table = TZR.getParent(td, 'TABLE');
  var tbody = table.tBodies[0];
  tbody.removeChild(TZR.getParent(td, 'TR'));
}

// Recupere le contenu d'une url via ajax en synchrone
TZR.file_get_contents=function(url,nocache){
    var req = null;
    try { req = new ActiveXObject("Msxml2.XMLHTTP"); } catch (e) {
        try { req = new ActiveXObject("Microsoft.XMLHTTP"); } catch (e) {
            try { req = new XMLHttpRequest(); } catch(e) {}
        }
    }
    if (req == null) throw new Error('XMLHttpRequest not supported');
    if(typeof(nocache)!='undefined'){
      url += (url.match(/[?]+/) ? '&' : '?' ) + 'uniqid=' + TZR.uniqid();
    }
    req.open("GET", url, false);
    req.send(null);
    return req.responseText;
}
TZR.winEditor = null;
TZR.openhtmlfileeditor = function(options){
  var url = options.url+'&formid='+options.uniqid+'&editflag='+escape(options.editflag)+'&uploader='+escape(options.uploader)+'&downloader='+escape(options.downloader);
  try{
    if(TZR.winEditor.closed) throw "Closed";
    TZR.winEditor.location = url;
    TZR.winEditor.focus();
  } catch(e){
    TZR.winEditor = window.open(url, 'file editor', 'width=800px,height=700px;')
  }
}
// Génére un identifiant unique avec un prefix eventuel
TZR.uniqid=function(prefix){
  if(typeof(prefix)=='undefined') prefix="";
  var alea=Math.random()*1000000000;
  return prefix+alea.toString();
}
TZR.addDependency=function(ftype,fd,f,vd,v,op,s,nochange){
  if(typeof(TZR.dependency[fd])=='undefined'){
    TZR.dependency[fd]=new Object();
    TZR.dependency[fd]['_depfields']=new Object();
    TZR.dependency[fd]['_=']=new Object();
    TZR.dependency[fd]['_!=']=new Object();
  }
  if(vd=='') vd='_empty';
  if(op=='='){
    if(typeof(TZR.dependency[fd]['_='][vd])=='undefined')
      TZR.dependency[fd]['_='][vd]=new Object();
  }else{
    if(typeof(TZR.dependency[fd]['_!='][vd])=='undefined')
      TZR.dependency[fd]['_!='][vd]=new Object();
  }
  TZR.dependency[fd]['_depfields'][f]=1;
  if(op=='=')
    TZR.dependency[fd]['_='][vd][f]={field:f,ftype:ftype,value:v,style:s,op:op,nochange:nochange};
  else
    TZR.dependency[fd]['_!='][vd][f]={field:f,ftype:ftype,value:v,style:s,op:op,nochange:nochange};
}
TZR.activeDependency = function (f) {
  jQuery(":input", f).each(function (i, e) {
    jQuery(e).data('required', jQuery(e).prop('required'));
  });
  jQuery(f).find('select').bind('change',{form:f},TZR.checkDependency);
  jQuery(f).find(':checkbox,:radio').bind("click",{form:f},TZR.checkDependency);
  var els=jQuery(f).find('select');
  for(var i=0;i<els.length;i++){
    var fd = jQuery(els[i]).attr('name');
    if(fd) fd = fd.replace("[]","");
    if(typeof(TZR.dependency[fd])!='undefined')
      jQuery(els[i]).triggerHandler("change");
  }
  var oks=new Object();
  var els=jQuery(f).find(':checkbox:checked,:radio:checked');
  for(var i=0;i<els.length;i++){
    jQuery(els[i]).triggerHandler("click");
    oks[els[i].name]=1;
  }
  var els=jQuery(f).find(':checkbox,:radio');
  for(var i=0;i<els.length;i++){
    if(els[i].checked || oks[els[i].name]==1) continue;
    jQuery(els[i]).triggerHandler("click");
    oks[els[i].name]=1;
  }
  // voir un generique tzrload ... (voir jcp)
  // + correction dans xmodtable/edit
  if (typeof(TZR.linkedfields) != "undefined"){
    TZR.linkedfields.init(f);
}
}
// Dependance uniquement sur select simple, select multiple, radio et checkbox de booleen
TZR.checkDependency=function(e){
  var t=e.target;
  var n = t.name;
  try {
    var valList = [].concat(jQuery(t).val());
  } catch (e) {
    var valList = [].concat(t.value);
  }

  if(typeof(n)=='undefined' || n=="") return;
  if(t.type=="hidden") return;
  if(t.type=="radio" && !t.checked) valList=[];
  if(t.type=="checkbox"){
    n=n.replace(/(\[.+\])$/g,"");
    n=n.replace(/(_HID)$/g,"");
    if(t.checked) valList=["1"];
    else valList=["2"];
  }
  if(t.type=="select-multiple"){
    n=n.replace("[]","");
  }
  if(valList.length==0 || (valList.length==1 && !valList[0])) valList=['_empty'];
  if(typeof(TZR.dependency[n])=='undefined') return;

  for(var v in TZR.dependency[n]['_=']) {
    // Si la valeur dépendante (v) est dans la liste des val sélectionnées
    if(valList.indexOf(v)!=-1){
      for(var i in TZR.dependency[n]['_='][v]){
        var dep=TZR.dependency[n]['_='][v][i];
        TZR.setDependencyValue(e.data.form,dep.field,dep.ftype,dep.value,dep.nochange);
        if(!e.data.nostyle) TZR.setDependencyStyle(dep.field,dep.style);
      }
    }
  }

  for(var v2 in TZR.dependency[n]['_!=']){
    // Si la valeur dépendante (v2) n'est pas dans la liste des val sélectionnées
    if(valList.indexOf(v2)==-1){
      for(var i in TZR.dependency[n]['_!='][v2]){
        var dep=TZR.dependency[n]['_!='][v2][i];
        TZR.setDependencyValue(e.data.form,dep.field,dep.ftype,dep.value,dep.nochange);
        if(!e.data.nostyle) TZR.setDependencyStyle(dep.field,dep.style);
      }
    }
  }
}

TZR.setDependencyStyle=function(field,style){
  var toStyle=jQuery('[id="cont-'+field+'"]');
  if(typeof(toStyle)=='undefined' || !toStyle || !toStyle.length){
    toStyle=jQuery('[id="cv8d-displayobj-field-'+field+'"]');
  }
  if(jQuery(toStyle).length > 1) {
    // On est dans un contexte de ss-module avec champs homonymes
    if(jQuery('.cv8-contenu-center .tzr-tabcontent.active').attr('id').match(/^sc-[0-9]+-[a-z0-9]+(-add)?$/)) {
      var tabId=jQuery('.cv8-contenu-center .tzr-tabcontent').filter(function(){ return this.id.match(/sc-[0-9]+-[a-z0-9]+-add/); });
      toStyle=jQuery('#cont-'+field, tabId)[0];
      if(typeof(toStyle)=='undefined' || !toStyle){
	toStyle=jQuery('#cv8d-displayobj-field-'+field, tabId)[0];
      }
    }
  } else {
    toStyle=toStyle[0];
  }
  var input = jQuery(toStyle).find(":input");
  if(typeof(toStyle)!='undefined' && toStyle){
    if(style=='hidden'){
      toStyle.style.display='none';
      input.prop('data-disabled', true).prop('required', false);
    }else if(style=="invalid"){
      input.attr('disabled', true).prop('data-disabled', true).prop('required', false);
    }else{
      input.attr('disabled', false).prop('data-disabled', false).prop('required', input.data('required'));
      toStyle.style.display='';
    }
    TZR.checkFieldsGroupsVisibility(toStyle);
  }
}
TZR.setDependencyValue=function(form,field,ftype,value,nochange){
  if(!nochange || nochange=="0"){
    TZR.setValue(form,field,ftype,value);
    if(typeof(TZR.dependency[field])=='undefined') return;
    TZR.checkDependency({target:{name:field,value:value},data:{nostyle:false,form:form}});
  }
}
TZR.checkFieldsGroupsVisibility = function(field){
  var group = jQuery(field).parents('fieldset.fieldsetAccordion, .cv8d-group');
  var conts = jQuery("*[id^='cont-'], *[id^='cv8d-displayobj-field-']", jQuery(group));
  if (conts.length > 0) {
    var show = false;
    conts.each(function(ii, o){
      if (jQuery(o).css('display') != 'none'){
        show = true;
      }
     });
    if (show){
      jQuery(group).show();
    } else {
      jQuery(group).hide();
    }
  }
}
TZR.setValue=function(form,field,ftype,value){
  if(typeof(field)=='string' && !form){
    fid=document.getElementById(field);
    field=fid.name;
    form=fid.form;
  }else if(typeof(field)=='string' && typeof(form)=='string'){
    form=jQuery('form[name='+form+']').get(0);
  }

  if(ftype=="\Seolan\Field\Link\Link" || ftype=="\Seolan\Field\Thesaurus\Thesaurus") TZR.setLinkValue(form,field,value);
  else TZR.setTextValue(form,field,value);
}

// form (objet), field (string)
TZR.setLinkValue=function(form,field,value){
  var cont=document.getElementById('cont-'+field);
  if(typeof(cont)=='undefined' || !cont){
    cont=document.getElementById('cv8d-displayobj-field-'+field);
  }

  var ret=jQuery(cont).find(":radio[name='"+field+"']").val([value]);
  if(ret.length==0) var ret=jQuery(cont).find("select[name='"+field+"']").val(value);
  if(ret.length==0) var ret=jQuery(cont).find("select[name='"+field+"[]']").val(value);
  if(ret.length==0) var ret=jQuery(cont).find(":checkbox[name^='"+field+"[']").val([value]);
  if(!value && ret.length==0) var ret=jQuery(cont).find("tr:not('.model') a.xthesaurus-del").click();
}
TZR.setTextValue=function(form,field,value){
  if(form.elements[field]) form.elements[field].value=value;
}

// Affiche du contenu centré dans l'écran avec une div d'arriere plan pleine page
// content est soit du texte, soit l'id d'un objet deja existant
TZR.dispFullScreenContent=function(content,color,op){
  if(!color) color="#000000";
  if(!op) op=0.2;

  var div=document.getElementById('fsoverlay');
  if(!div){
    jQuery("body").append('<div id="fsoverlay"></div>');
    jQuery("#fsoverlay").css({position:"absolute", zIndex:99998, padding:0, margin:0, top:0, left:0, background:color,
	  	              opacity:op, width:"100%", height:jQuery(document).height()});
  }
  if(content.substr(0,1)!="#"){
    jQuery("#fscontent").remove();
    jQuery("body").append('<div id="fscontent"></div>');
    jQuery("#fscontent").css({position:"absolute", zIndex:99999, padding:0, margin:0, top:0, left:0});
    jQuery("#fscontent").html(content);
    jQuery("#fscontent").css({top:(jQuery(window).height()-jQuery("#fscontent").outerHeight())/2+jQuery(window).scrollTop(),
    				    left:(jQuery(window).width()-jQuery("#fscontent").outerWidth())/2, visibility:"visible"});
  }else{
    jQuery(content).css({visibility:"visible", display:"block"});
    jQuery(content).css({position:"absolute", zIndex:99999, padding:0, margin:0,
				    top:(jQuery(window).height()-jQuery(content).outerHeight())/2+jQuery(window).scrollTop(),
				    left:(jQuery(window).width()-jQuery(content).outerWidth())/2});
  }
}
TZR.hideFullScreenContent=function(content){
  jQuery("#fsoverlay").remove();
  if(content!=undefined && content.substr(0,1)=="#"){
    jQuery(content).css('display','none');
  }else{
    jQuery("#fscontent").remove();
  }
}

/* Fonctions pour faciliter la gestion des champs préremplis avec leur libellé */
// Initialise les différentes propiétés du champ (o=objet, l=libellé)
function inputInit(o,l,cpltblur){
  if(cpltblur==undefined) cpltblur=true;
  if(typeof o=="string") { o=document.getElementById(o); }
  if(o.type=="text" || o.type=="textarea" || o.type=="password"){
    o.cpltblur=cpltblur;
    o.ovalue=l;
    if(o.value=="") o.value=l;
    o.onfocus=delValue;
    o.onblur=retablishValue;
    o.onchange=function(){};
  }else if(o.type=="select-one"){
    o.options[0].text=l;
  }
}
// Efface le contenu d'un champ (o=objet (se renseigne automatiquement sur un onfocus))
function delValue(o){
  if(o==undefined || o.type=="focus") o=this;
  if(o.value==o.ovalue){
    o.value="";
  }else{
    var r=new RegExp("^"+o.ovalue+" : ","");
    o.value=o.value.replace(r,"");
  }
}
// Rétabli le libellé si necessaire lors de la perte du focus (o=objet (se renseigne automatiquement sur un onblur))
function retablishValue(o){
  if(o==undefined || o.type=="blur") o=this;
  if(o.value==""){
    o.value=o.ovalue;
  }else if(o.cpltblur){
    o.value=o.ovalue+" : "+o.value;
  }
}
// A appeller sur le submit pour vider les champs non saisis
function checkFields(f){
  l=f.elements.length;
  for(i=0;i<l;i++){
    if((f.elements[i].type=="text" || f.elements[i].type=="textarea")){
      if(f.elements[i].value==f.elements[i].ovalue){
        f.elements[i].value = "";
      }else{
        var r = new RegExp("^" + f.elements[i].ovalue + " : ", "");
        f.elements[i].value = f.elements[i].value.replace(r, "");
      }
    }
  }
}

/* Fonctions pour formater les champs date et heure/durée (besoin de jquery.ui.datepicker)*/
TZR.formatDate=function(obj,fmt){
  if(obj.value[0]=='=') return;
  if(fmt==undefined) fmt=jQuery(obj).datepicker('option','dateFormat');
  if(fmt==undefined || fmt.length == 0) return;
  var val=obj.value;
  if(val=='') return false;
//   val = val.replace('-','<>');
//   obj.value = val;
//   return;
  // traitement interval
  fmt_re = fmt.replace(new RegExp('dd|mm|y','g'), '\\d{1,2}').replace(new RegExp('\/','g'),'[\/.-]');
  range_pattern = new RegExp('('+fmt_re+')\\s*\<\>\\s*('+fmt_re+')');
  matches = val.match(range_pattern);
  if (matches != null && matches.length == 3)
    dates = new Array(matches[1], matches[2]);
  else
    dates = new Array(val);
  values = new Array();
  var fmttab=fmt.split(/[\/.-]/);
  for (i=0; i<dates.length; i++) {
    var tab=dates[i].split(/[\/.-]/);
    var date = jQuery(obj).datepicker('getDate');
    for (j=0; j<fmttab.length; j++){
      if((fmttab[j]=='dd' || fmttab[j]=='d') && tab[j]!=undefined && tab[j]!='') date.setDate(tab[j]);
      if((fmttab[j]=='mm' || fmttab[j]=='m') && tab[j]!=undefined && tab[j]!='') date.setMonth(tab[j]-1);
      if((fmttab[j]=='yy' || fmttab[j]=='y') && tab[j]!=undefined && tab[j]!=''){
        if(tab[j].length==1) date.setYear("200"+tab[j]);
        else if(tab[j].length==2) date.setYear("20"+tab[j]);
        else if(tab[j].length==3) date.setYear("2"+tab[j]);
        else date.setYear(tab[j]);
      }
    }
    values[i] = jQuery.datepicker.formatDate(fmt,date);
  }
  obj.value = values.join(' <> ');
  if(typeof TZR.Table != "undefined" && TZR.Table.saveSomeLangsCheckFields) {
    TZR.Table.saveSomeLangsCheckFields(obj);
  }
}
TZR.format2dates=function(obj,b,e,fmt){
  if(fmt==undefined) fmt=jQuery.datepicker._defaults.dateFormat;
  var form=obj.form;
  var b=form.elements[b];
  var e=form.elements[e];
  TZR.formatDate(obj);
  var begindate=jQuery.datepicker.parseDate(fmt,b.value);
  var enddate=jQuery.datepicker.parseDate(fmt,e.value);
  if(obj==b && begindate>enddate) e.value=b.value;
  else if(obj==e && begindate>enddate) b.value=e.value;
}
TZR.formatHour=function(obj,n){
  val=obj.value;
  tab=val.split(":");
  var rtab=new Array();

  if(/^[0-9]$/.test(tab[0])) {
    rtab[0]="0"+tab[0];
  }else if(/^[0-9]{2}$/.test(tab[0])) {
    rtab[0]=tab[0];
  }else{
    return;
  }
  if(n>1){
   if(!tab[1]){
     rtab[1]="00";
   }else if(/^[0-9]$/.test(tab[1])){
     rtab[1]="0"+tab[1];
   }else if(/^[0-9]{2}$/.test(tab[1])) {
     rtab[1]=tab[1];
   }else{
     return;
   }
  }
  if(n>2){
   if(!tab[2]){
     rtab[2]="00";
   }else if(/^[0-9]$/.test(tab[2])){
     rtab[2]="0"+tab[2];
   }else if(/^[0-9]{2}$/.test(tab[2])) {
     rtab[2]=tab[2];
   }else{
     return;
   }
  }
  obj.value=rtab.join(":");
}

TZR.format2hours=function(obj,n,b,e,bd,ed){
  var form=obj.form;
  b=form.elements[b];
  e=form.elements[e];
  if(bd!=undefined) bd=form.elements[bd];
  if(ed!=undefined) ed=form.elements[ed];
  TZR.formatHour(obj,n);
  var reg=/^[0-9]{2}(:[0-9]{2})?(:[0-9]{2})?$/;
  var tab=obj.value.split(":");
  if(obj.value==""){
    b.value="";
    e.value="";
  }else if(obj==b && b.value>e.value && (bd==undefined || ed==undefined || bd.value==ed.value) && reg.test(b.value)){
    var hour=parseFloat(tab[0]);
    var minute=parseFloat(tab[1]);
    if(hour==23) e.value="23:59";
    else e.value=(hour+1)+":"+minute;
    TZR.formatHour(e,n);
  }else if(obj==e && b.value>e.value && (bd==undefined || ed==undefined || bd.value==ed.value) && reg.test(e.value)){
    var hour=parseFloat(tab[0]);
    var minute=parseFloat(tab[1]);
    if(hour==0) b.value="00:00";
    else b.value=(hour-1)+":"+minute;
    TZR.formatHour(b,n);
  }
};

// fonctions des champs de geodesiccoordinates
TZR.geodesic = {
  geoSearch:null,
  dms2dd:function(inid, outid){
    var dms = document.getElementById(inid).value;
    var exp = new RegExp(/[ ]*([0-9]{1,2})° ([0-9]{1,2})' ([0-9]{1,2}\.[0-9]{0,3})'' ([NS]{1})[ ]+([0-9]{1,3})° ([0-9]{1,2})' ([0-9]{1,2}\.[0-9]{0,3})'' ([WOE]{1})/);
    if (exp.test(dms)){
      var dd = document.getElementById(outid);
      var dlng = RegExp.$5;
      var mlng = RegExp.$6;
      var slng = RegExp.$7;
      var lng = RegExp.$8;
      var dlat = RegExp.$1;
      var mlat = RegExp.$2;
      var slat = RegExp.$3;
      var lat = RegExp.$4;
      if (lat == 'S'){
	lat = '-';
      } else {
	lat = '';
      }
      if (lng == 'W' || lng == 'O'){
	lng = '-';
      } else {
	lng = '';
      }
      var ddlat = parseInt(dlat) + parseFloat(mlat/60) + parseFloat(slat/3600);
      var ddlng = parseInt(dlng) + parseFloat(mlng/60) + parseFloat(slng/3600);
      dd.value = lat+ddlat+';'+lng+ddlng;
    }else{
      //      var dd = document.getElementById('dd').value='####';
    }
  },
  setPointCoordinates:function(field, rawvalue, dmsvalue){
    document.getElementById(field.fid).value = rawvalue;
    document.getElementById('dms'+field.fid).value = dmsvalue;
    document.getElementById(field.fid+'_autogc').checked = false;
    this.geocodeauto(document.getElementById(field.fid+'_autogc'), field.fid);
  },
  geocodeauto:function(cb, fid){
    if (cb.checked){
      document.getElementById(fid+'_b1').style.display='block';
      document.getElementById(fid+'_b2').style.display='block';
    } else {
      document.getElementById(fid+'_b1').style.display='none';
      document.getElementById(fid+'_b2').style.display='none';
      document.getElementById(fid+'_unchanged').value = '0';
      document.getElementById(fid+'_accuracy1').innerHTML = '';
      document.getElementById(fid+'_upd').innerHTML = '';
      document.getElementById(fid+'_accuracy2').value = 'N/A';
    }

  },
    openGeoSearch:function( url, fname, fid, ftable, foptions){
    var v = document.getElementById(fid).value;
    if (v != ';'){
      var c = v.split(';');
      var newp = false;
      var lat = c[0];
      var lng = c[1];
    } else {
      var newp = true;
      var lat = 0;
      var lng = 0;
    }
    try{
	TZR.geodesic.geoSearch.setPoint({table:ftable, name:fname, id:fid, newpoint:newp, lat:lat, lng:lng, options:foptions});
	TZR.geodesic.geoSearch.focus();
    }catch(e){
	var foptionsu = '';
	for(o in foptions){
	    foptionsu = '&foptions['+o+']='+escape(foptions[o]);
	}
	TZR.geodesic.geoSearch = window.open(url+'&fid='+fid+'&ftable='+ftable+'&fname='+fname+'&flatlng='+escape(v)+foptionsu, 'GeoSearch',"resizable=no,width=700,height=530,left=100,top=100");
	TZR.geodesic.geoSearch.focus();
    }
  },
  clear:function(fid){
    document.getElementById(fid).value='';
    document.getElementById('dms'+fid).value='';
    document.getElementById(fid).value='';
    document.getElementById(fid+'_accuracy1').innerHTML = '';
    document.getElementById(fid+'_upd').innerHTML = '';
    document.getElementById(fid+'_accuracy2').value = 'N/A';
  },
    openGeoView:function(coordstring, url, fname, fid, ftable, foptions){
    var v = coordstring;
    if (v != ';'){
      var c = v.split(';');
      var newp = false;
      var lat = c[0];
      var lng = c[1];
    } else {
      var newp = true;
      var lat = 0;
      var lng = 0;
    }
    try{
	TZR.geoSearch.setPoint({table:ftable, name:fname, id:fid, newpoint:newp, lat:lat, lng:lng, foptions:foptions});
      TZR.geoSearch.focus();
    }catch(e){
	var foptionsu = '';
	for(o in foptions){
	    foptionsu = '&foptions['+o+']='+escape(foptions[o]);
	}
	TZR.geoSearch = window.open(url+'&fid='+fid+'&ftable='+ftable+'&fname='+fname+'&flatlng='+escape(v)+foptionsu, 'GeoView',"resizable=no,width=700,height=530,left=100,top=100");
	TZR.geoSearch.focus();
    }
  }
};

// Applique une popup a des objets (obj est un objet jQuery)
TZR.applyHottip=function(obj,param){
  param=jQuery.extend(true,{fill:'#F9F9F9',strokeStyle:'#4b4b4b',spikeLength:10,spikeGirth:10,padding:8,cornerRadius:0,
			    cssStyles:{fontFamily:'"lucida grande",tahoma,verdana,arial,sans-serif',fontSize:'11px'},
			    closeWhenOthersOpen:true,closeButton:0,ajaxPath:["$(this).attr('bt-xpath')"],offsetParent:'body',overlap:-2},param);
  obj.tooltip(param);
  return obj;
}

// Fonction d'échapement des caractères spéciaux lors d'un RegExp.test(xxx)
if(typeof RegExp.escape !== "function"){
	RegExp.escape = function(s) {
      return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
	};
}

function scrollToElement(o,offset){
  var dTop=jQuery(o).offset().top;
  var sTop=jQuery(window).scrollTop();
  var wHeight=jQuery(window).height();
  if(!offset) offset=20;
  if(sTop>dTop) jQuery(window).scrollTop(dTop-offset);
  else if(sTop+wHeight<dTop) jQuery(window).scrollTop(dTop-wHeight+jQuery(o).height()+offset);
}

function showObj(objid){
  var o = document.getElementById(objid);
  if(o){
    o.style.visibility = "visible";
    o.style.position = "relative";
  }
}
function hideObj(objid){
  var o = document.getElementById(objid);
  if(o){
    o.style.visibility = "hidden";
    o.style.position = "absolute";
  }
}
function showHide(objid, height){
  var o = document.getElementById(objid);
  if(o){
    if(o.style.visibility == "hidden"){
      o.style.visibility = "visible";
      o.style.position = "relative";
      o.style.height=null;
    }else{
      if (height)
	o.style.height=height;
      o.style.visibility = "hidden";
      o.style.position = "absolute";
    }
  }
}
function showHideSize(objid, height){
  showHide(objid);
  var o = document.getElementById(objid);
  if(o){
    if(o.style.visibility == "hidden"){
      o.style.height = '';
    }else{
      o.style.height = height;
    }
  }
}

// Function appelé à la fin du chargment de la page
function onLoadComplete(){
  // Correction des PNG sous IE
	  if(window.attachEvent && window.correctPNG) correctPNG();
}

window.onload=onLoadComplete;
