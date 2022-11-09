function CssEditor(params) {
    'use strict';
    //Private variable
    this.params = params;
    this.debug = false;
    this.state = '';
    this.mode = '';
    this.extractMode = '';
    this.dom = '';
    this.noticeableBlock = '';
    this.editor = '';
    this.editorContainer = '';
    this.editorNotification = '';
    this.cssFile = '';
    this.exporturl = '';
    this.exportname = '';
    this.self = this;
    this.jQueryuiUse = false;
    this.editorNotificationTemplate = '<div id="css-editor-help">Edition des styles en cours (Faire un double click sur les élément pour modifier le style) <span class="css-editor-alerte"></span></div>';
    this.template = '<div id="style-editor" class="cke_top" draggable="true">\
                  <nav>\
                    <div class="cke_info">&nbsp;</div>\
                    <span class="cke_toolbar_break"></span>\
                    <div class="cke_toolgroup ">\
                    <a class="cke_button cke_button_off">\
                    <span id="showdiv" class="cke_button_icon cke_button__showdiv_icon" onclick="CssEditor.showDiv()" title="Voir la structure du document">&nbsp;</span>\
                    </a>\
                    </div>\
                    <div class="cke_toolgroup ">\
                    <a class="cke_button cke_button_off">\
                    <input type="color" class="cke_button__bgcolor_icon colorinput" onchange="CssEditor.paintElement(this)" data-stylename="background-color" value="#ff0000" title="Couleur de fond">\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <input type="color" class="cke_button__textcolor_icon colorinput" onchange="CssEditor.paintElement(this)" data-stylename="color" value="#ff0000" title="Couleur de text">\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <input type="color" class="cke_button__textcolor_icon colorinput" onchange="CssEditor.paintElement(this)" data-stylename="color" value="#ff0000" title="Couleur de text">\
                    </a>\
                    </div>\
                    <div class="cke_toolgroup ">\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__bold_icon" onclick="CssEditor.paintElement(this)" data-stylename="font-weight" data-value="bold" title="Texte en gras">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__underline_icon" onclick="CssEditor.paintElement(this)" data-stylename="text-decoration" data-value="underline" title="Texte souligné">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__strike_icon" onclick="CssEditor.paintElement(this)" data-stylename="text-decoration" data-value="line-through" title="Texte barré">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__italic_icon" onclick="CssEditor.paintElement(this)" data-stylename="font-style" data-value="italic" title="Texte en italic">&nbsp;</span>\
                    </a>\
                    </div>\
                    <div class="cke_toolgroup ">\
                    <a class="cke_button cke_button_off">\
                    <span id="remove-style" class="cke_button_icon cke_button__removeformat_icon" onclick="CssEditor.removeStyle(this)" title="Supprimer le style appliqu&eacute;">&nbsp;</span>\
                    </a>\
                    </div>\
                    <div class="cke_toolgroup extractgroup">\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__element_icon" onclick="CssEditor.addElementExtractMode(this)" data-value="tag" title="Appliquer a tout les elements du meme type">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__relative_icon" onclick="CssEditor.addElementExtractMode(this)" data-value="relative" title="Appliquer a l\'&eacute;lement en cours">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__block_icon" onclick="CssEditor.addElementExtractMode(this)" data-value="block" title="Appliquer aux elements semblables">&nbsp;</span>\
                    </a>\
                    </div>\
                    <span class="cke_toolbar_break"></span>\
                    <div class="cke_toolgroup">\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__alignLeft_icon" onclick="CssEditor.paintElement(this)" data-stylename="text-align" data-value="left" title="Aligne gauche">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__alignCenter_icon" onclick="CssEditor.paintElement(this)" data-stylename="text-align" data-value="center" title="Aligne centre">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__alignRight_icon" onclick="CssEditor.paintElement(this)" data-stylename="text-align" data-value="right" title="Aligne droite">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__alignJustify_icon" onclick="CssEditor.paintElement(this)" data-stylename="text-align" data-value="justify" title="Justifie">&nbsp;</span>\
                    </a>\
                    <a class="cke_button cke_button_off">\
                    <span class="cke_button_icon cke_button__paddingLeft_icon" onclick="CssEditor.paintElement(this)" data-stylename="padding-left" data-value="15px" title="Algine droite">&nbsp;</span>\
                    </a>\
                    </div>\
                    <div class="cke_toolgroup">\
                    <a class="cke_button cke_button_off">\
                    <span id="exportCss" class="cke_button_icon cke_button__extractcss_icon" onclick="CssEditor.extract()" data-value="italic" title="Exporter les modifications">&nbsp;</span>\
                    </a>\
                    <span class="cke_toolbar_separator" role="separator"></span>\
                    <a class="cke_button cke_button_off">\
                    <span id="close" class="cke_button_icon cke_button__close_icon" onclick="CssEditor.closeEditor()" title="Fermer l\'&eacute;diteur" >&nbsp;</span>\
                    </a>\
                    </div>\
                    <input id="editedElement" type="hidden" value="">\
                  </nav>\
                  </div>';
    this.retry = 0;
    var myEditor = this;
    var that = this;
    that.DELAY = 700;
    that.clicks = 0;
    that.timer = null;
    jQuery(document).ready(function () {
        CssEditor.init(myEditor.params);

        jQuery("body").on('click','a', function (e) {
	    var $this = jQuery(this);
	    if (!$this.closest("#style-editor").length && !$this.is('[id*=css-editor]')) {
		e.preventDefault();    
		that.clicks++;  //count clicks
		if(that.clicks === 1) {
		    that.timer = setTimeout(function() {
			
			CssEditor.log($this);
			if(confirm('Edition des styles en cours . voulez vous suivre le lien ?'))
			    location.href = $this.attr('href');
			
			that.clicks = 0;             //after action performed, reset counter
		    }, that.DELAY);
		    
		} else {
		    clearTimeout(that.timer);    //prevent single-click action
		    that.clicks = 0;             //after action performed, reset counter
		}
	    }	    
	})
	
	
    });
}

CssEditor.prototype.init = function () {
    this.parseParams(this.params);
    this.log("Initialisation de CssEditor...");

    this.checkDependencies();
    this.postInit();
    this.sendEvent('Dom interaction ready');
    this.initDomInteraction();
};
CssEditor.prototype.log = function (value) {
    if(this.debug)
	console.log(value);
}
CssEditor.prototype.parseParams = function (params) {
    if (typeof params != "object" && params != undefined) {
        this.log("No object pass trought the parameters, assuming params is editorContainer name");
        this.editorContainer = params || '#style-editor';
    } else if (typeof params == "object" && params != undefined) {
	this.debug = params.debug || false;
        this.state = params.state || "editable";
        this.mode = params.mode || 'jQuery';
        this.dom = params.dom || 'body';
        this.noticeableBlock = params.noticeableBlock || ['#'];
        this.extractMode = params.extractMode || 'relative';
        this.editorContainer = params.editorContainer || '#style-editor';
        this.cssFile = params.cssFile || "editor.css";
        this.exporturl = params.exporturl || "";
        this.exportname = params.exportname || "cssextractred";
    } else {
        throw new Error('CssEditor need parameters: try {dom:"body",editorContainer:"#style-editor"}');
    }
};

CssEditor.prototype.getEditorDomObject = function (container) {
    var domContainer = jQuery(container);
    if (domContainer.length == 0) {
        throw new Error('Editor container not found!\ncheck the html dom\nto be sure that it\'s included');
    }
    return domContainer;
};

CssEditor.prototype.sendEvent = function (name) {
    var event;
    //if not IE
    if (document.createEvent) {
        event = document.createEvent("HTMLEvents");
        event.initEvent("CssEditor", true, true);
    } else { // on IE
        event = document.createEventObject();
        event.eventType = "CssEditor";
    }
    event.eventName = name;
    if (document.createEvent) {
        document.dispatchEvent(event);
    } else {
        document.fireEvent("on" + event.eventType, event);
    }
};


CssEditor.prototype.getEditor = function () {
    return this.editor;
};

CssEditor.prototype.postInit = function () {
    //Whatever we have to do after init
    jQuery('body').append(this.template);
    this.editorNotification = jQuery('body').append(this.editorNotificationTemplate);
    this.cssApplied = jQuery('<style id="css-editor-header" type="text/css"></style>');
    jQuery('head').append(this.cssApplied);
    this.editor = this.getEditorDomObject(this.editorContainer);
    this.sendEvent('postInit end');
};
CssEditor.prototype.alert = function (msg) {
    jQuery('.css-editor-alerte',this.editorNotification).html(msg);
}
CssEditor.prototype.checkDependencies = function () {
    this.log("Check dependencies");
    if (typeof jQuery == 'undefined' && this.mode == "jQuery") {
        throw new Error('CssEditor need jQuery library to work');
    }
    if (typeof jQuery.ui != 'undefined') {
        this.jQueryuiUse = true;
    } else {
        this.log('For a better user experience, load jQuery.ui.draggable module');
    }
    var cssReg = new RegExp('\.*'+this.cssFile+'.*');
    for(var i = 0; i < document.styleSheets.length; i++){
        if(cssReg.test(document.styleSheets[i].href)){
            found = true;
            break;
        }
    }
    if(!found){
        throw new Error('CssEditor can\'t find the css file :'+this.css);
    }
};

//Set Events listeners and actions associates to these events
CssEditor.prototype.initDomInteraction = function () {
    this.log('init dom interaction...');
    var self = this;//Closure for jQuery
    //Add drag over dom if jQuery.ui is loaded
    if (this.jQueryuiUse) {
        this.editor = this.getEditor();
        this.editor.draggable();
    }
    //Add color application on current dom element with paintElement function
    /*TODO Je sais plus pourquoi je l'ai ajouté ici et dans la propriété onchange de l'interface ca semble poser probleme sur chrome. voir si encore besoin
     /*jQuery( '.colorinput' ).on( 'input', function () {
     CssEditor.prototype.paintElement( this );
     });*/
    //Add dblclick event on all the dom. Check dblclicked element position and add the editorBox close to it
    jQuery('body *').dblclick(function (evt) {
        evt.stopPropagation();
        evt.preventDefault();
	var $this = jQuery(this);
	if($this.closest('[id*=css-editor-]').length)
	    return false;
        var offset = self.realOffset(jQuery(this));
        var editor = self.getEditor();
        var boxTopOffset = ( editor.height() + parseInt(editor.css("border-top-width")));
        var xpos = offset["left"];
        var ypos = offset["top"] - boxTopOffset-20;
        if ($this[0].tagName == "IMG") {
            //todo gestion d'images
            self.log('click sur image');
        }
        if ($this.attr('id') != editor.attr('id')) {
            editor.css({"top": ypos, "left": xpos});
            self.showEditor(jQuery(this));
            var path = self.getPath(jQuery(this));
            editor.find("#editedElement").val(path);
        }
    });
};

CssEditor.prototype.getPath = function (node, blockArray) {
    var path, node = jQuery(node);
    var blockToBreak = blockArray || "";
    while (node.length) {
        var realNode = node[0], name = realNode.localName;
        if (!name) break;
        name = name.toLowerCase();
        var parent = node.parent();
        var siblings = parent.children(name);
        if (siblings.length > 1) {
            name += ':eq(' + siblings.index(realNode) + ')';
        }
        path = name + ( path ? '>' + path : '' );
        node = parent;
        var parentId = parent.attr('id');
        var parentClass = parent.attr('class');
        if (blockToBreak.indexOf("#") !== -1 && parentId != undefined) {
            path = '#' + parentId + ( path ? '>' + path : '' );
            break;
        }
//        var breakOnId = this.searchInArray(parentId, blockToBreak);
//        var breakOnClass = this.searchInArray(parentClass, blockToBreak);
	var stopHere = this.searchInArraySelector(blockToBreak,parent);
//        if (breakOnId != -1 || breakOnClass != -1) {
	if (stopHere != -1 ) {
	
//            var id = (breakOnId != -1) ? '#' + blockToBreak[breakOnId] : "";
//            var _class = (breakOnClass != -1) ? '.' + blockToBreak[breakOnClass] : "";

            var _class = (stopHere) ? '.' + stopHere : "";
            path = _class + ( path ? '>' + path : '' );
            break;
        }
    }
    return path;
};

CssEditor.prototype.paintElement = function (element) {
    var editedElement = this.editor.find("#editedElement").val();
    var styleValue = jQuery(element).val();
    var tagEditorValue;
    var elementEditorValue;
    var replaceTagEditorValueWith;
    var reg;
    if (styleValue.length == 0) {
        styleValue = jQuery(element).data('value');
    }
    var styleName = jQuery(element).data('stylename');
    if (jQuery(editedElement).css(styleName) == styleValue) {
        jQuery(editedElement).css(styleName, '');
        tagEditorValue = jQuery(editedElement).data('cssEditor');
        elementEditorValue = styleName + ':' + styleValue + ';';
        reg = new RegExp(elementEditorValue, "g");
        replaceTagEditorValueWith = tagEditorValue.replace(reg, '');
        jQuery(editedElement).data('cssEditor', replaceTagEditorValueWith);
    } else {
        jQuery(editedElement).css(styleName, styleValue);
        elementEditorValue = styleName + ':' + styleValue + ';';
        tagEditorValue = ( jQuery(editedElement).data('cssEditor') != undefined ) ? jQuery(editedElement).data('cssEditor') : "";
        if (tagEditorValue.indexOf(styleName) != -1) {
            reg = new RegExp(styleName + '([a-zA-Z]*);', "g");
            replaceTagEditorValueWith = tagEditorValue.replace(reg, styleValue);
        } else {
            replaceTagEditorValueWith = tagEditorValue + elementEditorValue;
        }
        jQuery(editedElement).data('cssEditor', replaceTagEditorValueWith);
    }
};

CssEditor.prototype.addElementExtractMode = function (element) {
    jQuery('.extractgroup > a').removeClass('cke_button_on');
    jQuery(element).parent().toggleClass('cke_button_on');
    this.log('click');
    var editedElement = this.editor.find("#editedElement").val();
    var extractMode = jQuery(element).data('value');
    jQuery(editedElement).data('extractmode', extractMode);
};

CssEditor.prototype.removeStyle = function (element) {
    var editedElement = this.editor.find("#editedElement").val();
    var tagEditorValue = jQuery(editedElement).data('cssEditor');
    var reg = new RegExp(tagEditorValue, "g");
    replaceTagEditorValueWith = tagEditorValue.replace(reg, '');
    jQuery(editedElement).attr('style', replaceTagEditorValueWith);
    jQuery(editedElement).attr('cssEditor', replaceTagEditorValueWith);
};

CssEditor.prototype.showEditor = function (edit) {
    if (this.editor.css('display') == 'none') {
        this.editor.toggle();
    } else {
        this.closeEditor();
        /*Pour prendre en compte les modifs et appliquer l'extractmode*/
        this.showEditor(edit);
    }
    var color = jQuery(edit).css('color');
    var bgcolor = jQuery(edit).css('background-color');
    color = this.rgb2hex(color);
    bgcolor = this.rgb2hex(bgcolor);
    this.editor.find('#color').value = color;
    this.editor.find('#background-color').value = bgcolor;
    jQuery('.xehighlight').removeClass('xehighlight');
    var extractmode = this.extractMode;
    if(jQuery(edit).data('extractmode') != undefined){
        extractmode = jQuery(edit).data('extractmode');
    }
    jQuery('.extractgroup > a').removeClass('cke_button_on');
    jQuery('.extractgroup > a').find("span[data-value='" + extractmode + "']").parent().addClass('cke_button_on');
    if (edit.has('.xehighlight').length == 0) {
        edit.addClass('xehighlight');
    }
};

CssEditor.prototype.closeEditor = function () {
    var editor = this.getEditor();
    var editedElement = this.editor.find("#editedElement").val();
    if (jQuery(editedElement).data('extractmode') == undefined) {
        jQuery(editedElement).data('extractmode', this.extractMode);
    }
    editor.css('display', 'none');
    jQuery('.xehighlight').removeClass('xehighlight');

};

CssEditor.prototype.showDiv = function () {
    jQuery('body *').each(function () {
        if (!jQuery(this).closest("#style-editor").length) {
            if (jQuery(this).hasClass('see-under-the-hood') || jQuery(this).hasClass('xetag')) {
                jQuery(this).remove('.xetag');
                jQuery(this).removeClass('see-under-the-hood');
            } else {
                var tag = jQuery(this)[0].tagName;
                var id = (jQuery(this).attr('id') != undefined) ? jQuery(this).attr('id') : '';
                jQuery(this).addClass('see-under-the-hood');
                jQuery(this).prepend('<div class="xetag">' + tag + ':' + id + '</div>');
            }
        }
    });
};

CssEditor.prototype.rgb2hex = function (rgb) {
    if (rgb.length > 0) {
        if (rgb == "rgba(0, 0, 0, 0)") {
            return '#ffffff';//transparent pas gerÃ© par color picker
        }
        rgb = rgb.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i);
        var hex = '#ffffff';
        if (rgb && rgb.length === 4) {
            hex = "#" +
                ("0" + parseInt(rgb[1], 10).toString(16)).slice(-2) +
                ("0" + parseInt(rgb[2], 10).toString(16)).slice(-2) +
                ("0" + parseInt(rgb[3], 10).toString(16)).slice(-2);
        }
        return hex;
    }
    return '#ffffff';
};
CssEditor.prototype.searchInArraySelector = function (searchInSelector, object) {
    if (searchInSelector == undefined || searchInSelector == "") {
        return -1;
    }
    if (!(object instanceof jQuery)) {
        return -1;
    }
    var objectClasses =object.attr('class'); 
    var foundClass=-1;
    if(objectClasses  == undefined  || objectClasses == ""){
        return -1;
    }
	
    classes = objectClasses.split(' ');
    searchInSelector.forEach(function (searchValue) {
	for ( var i = 0, l = classes.length; i<l; ++i ) {
	    var regexp = new RegExp(searchValue);
	    if(classes[i].match(regexp)){
		foundClass = classes[i];
		return
	    }
	    
	}
    });
    return foundClass;
}
CssEditor.prototype.searchInArray = function (searchIn, searchFor) {
    if (searchFor == undefined || searchFor == "") {
        return -1;
    }
    if (searchIn == undefined || searchIn == "") {
        return -1;
    }
    var foundElementIndex = -1;
    if (typeof searchIn == "string") {
        searchIn = searchIn.split(" ");
    }
    searchFor.forEach(function (searchValue) {
        var index = searchIn.indexOf(searchValue);
        if (index != -1) {
            foundElementIndex = index;
            return
        }
    });
    return foundElementIndex;
};

CssEditor.prototype.realOffset = function (jobjet) {
    var coord = {"top": 0, "left": 0};
    var objet = jobjet;
    coord["top"] = objet.offset().top +
        parseInt(objet.css("border-top-width")) +
        parseInt(objet.css("margin-top")) +
        parseInt(objet.css("padding-top"));
    coord["left"] = objet.offset().left +
        parseInt(objet.css("border-left-width")) +
        parseInt(objet.css("margin-left")) +
        parseInt(objet.css("padding-left"));
    return coord;
};

CssEditor.prototype.extract = function () {
    var css = this.extractStyle();
    var regExp = new RegExp('(:eq)','g');
    css = css.replace(regExp,':nth-of-type');
    //css = css.split(':eq').join(':nth-child'); /*Hack en attendant Antoine */
    if(this.exporturl == undefined || this.exporturl == ''){
        throw new Error ('CssEditor does not know where to export extracted css, see doc or set exporturl parameter');
    }else{
        var exportname = this.exportname;
        var exporturl = this.exporturl;
        this.log('CSS to export :'.postValue);
	postValue = {};
        postValue[exportname] = css;
	this.alert('<a id="css-editor-alert-link" onclick="jQuery(\'#css-editor-alert-style\').toggle();jQuery(\'i\',this).toggleClass(\'fa-toggle-on\');" href="#" >Styles Enregistrés <i class="fa fa-toggle-off"></i></a> <div id="css-editor-alert-style">'+css+'</div>');
	var that = this;
        jQuery.ajax({
            method: "POST",
            url: exporturl,
            data: postValue,
        }).done(function( msg ) {
	    that.cssApplied.html(css);
        });
    }
    return css;
};

CssEditor.prototype.extractStyle = function () {
    var styleSheet = "";
    if(this.debug)
	debugger;
    var selector = this.dom;
    var mode = this.extractMode;
    var noticeableBlock = this.noticeableBlock;
    jQuery(selector + ' *').each(function () {
        if (!jQuery(this).closest("#style-editor").length) {
            var style = jQuery(this).data('cssEditor');
            var elementExtractMode = jQuery(this).data('extractmode') || mode;
            if (style != undefined) {
                if (elementExtractMode == 'relative') {
                    styleSheet += CssEditor.getPath(this) + "{\n" + style + "\n}\n";
                } else if (elementExtractMode == 'tag') {
                    styleSheet += jQuery(this).prop('tagName').toLowerCase() + "{\n" + style + "\n}\n";
                } else if (elementExtractMode == 'block') {
                    styleSheet += CssEditor.getPath(this, noticeableBlock) + "{\n" + style + "\n}\n";
                } else {
                    throw new Error('Extract mode must be either relative, block or tag');
                }
            }
        }
    });
    return styleSheet;
};
