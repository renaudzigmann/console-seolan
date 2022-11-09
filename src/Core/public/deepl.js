$('#select_all').click(function() {
    var checkboxes = $("form").find(':checkbox').each(function(index){
	if(!this.name.includes("_HID")){
	    this.checked = true;
	}
    });
});
function unselect(){
    var checkboxes = $("form").find(':checkbox').each(function(index){
	if(!this.name.includes("_HID")){
	    this.checked = false;
	}
    });
}
function getSrcText(name){
    var text = "";
    // Le RichText a un texte area caché qui permet de récupérer le title du iframe à partir du nom du champ
    target = jQuery('textarea[name="'+name+'"]');
    id = target.attr('id');
    richText = jQuery('iframe[title$='+id+']');
    if(richText.length){
	// Si on est un RichText on va chercher le body du RichText
	text = richText[0].contentDocument.body.innerHTML;
    } else {
	// Le champ cible n'est pas un iframe, on regarde si c'est un TextArea ou un Input
	if(!target.length){
	    target = jQuery('input[name="'+name+'"]')[1];
	    if(!target){
		target = jQuery('input[name="'+name+'_title"]')[0];
	    }
	    if(target){
		text = target.value;
	    }
	} else {
	    text = target.val();
	}
    }
    return text;
}

function tradSelection(lang){
    var name = [];
    var text = [];
    var i = 0;
    //On récupère les checkbox cochés
    var checkboxes = $('input:checked').each(function(index){
	if(!this.name.includes("_HID")){
	    value = getSrcText(this.name)
	    if(value){
		name[i] = this.name;
		text[i] = value;
		i++;
	    }
	}
    });
    check_iso(text,name,lang);
    unselect();
}

function CopyFromBaseLang(zone,oidsection,oidit,moid){
    var name = [];
    var i = 0;
    //On récupère les checkbox cochés
    var checkboxes = $('input:checked').each(function(index){
	name[i] = this.name;
	i++;
    });
    TZR.jQueryPost({
	'url':window.location.origin+'/scripts/deepl.php',
	'data':{mode:"getSrcText",name:name,zone:zone,oidsection:oidsection,oidit:oidit,moid:moid},
	'type':'POST',
	'cb':function(text){
	    text = JSON.parse(text);
	    for(let i=0;i<text.length;i++){
		displayTrad(text[i],name[i]);
	    }
	    unselect();
	}
    });
}

// Permet de récupérer la langue cible
function getTargetLang(){
    lang = document.getElementsByClassName('langselection');
    targetLang = lang[0].firstChild;
    targetLang = targetLang.options[targetLang.selectedIndex].value;
    return targetLang;
}

// Cette fonction s'occupe de mettre le texte traduit dans le bon input
// text représente le texte traduit et e le champ dans lequel il faut mettre le texte
function displayTrad(text,inputName){
    //On récupère le texte traduit puis on le met dans le input
    target = jQuery('textarea[name="'+inputName+'"]');
    id = target.attr('id');
    richText = jQuery('iframe[title$='+id+']');
    if(richText.length){
	// Si on est un RichText on va chercher le body du RichText
	body = richText[0].contentDocument.body;
	childNodes = body.childNodes;
	length = childNodes.length;
	// On enlève le html qu'il y a dans le body pour le remplacer par la traduction
	for (let i = 0; i < length; i++) {
	    body.removeChild(childNodes[0]);
	}
	//On traduit la réponse de DeepL en Node HTML pour pouvoir les mettrent dans le body
	html = new DOMParser().parseFromString(text, "text/html");
	childNodes = html.body.childNodes;
	length = childNodes.length;
	// On met les différents noeuds traduit dans le body
	for(let i = 0;i < length; i++){
	    body.appendChild(childNodes[0]);
	}
	 
    } else {
	// Le champ cible n'est pas un input, on regarde si c'est un RichText
	// Le RichText a un texte area caché qui permet de récupérer le title du iframe à partir du nom du champ
	if(!target.length){
	    target = jQuery('input[name="'+inputName+'"]');
	    if(target.length < 2){
		target = jQuery('input[name="'+inputName+'_title"]');
	    }
	}
	target.val(text);
    }
}

function DeeplAPI(text,inputName,lang){
    //On récupère la langue cible
    srcLang = lang['src'];
    //On récupère la langue cible
    targetLang = lang['target'];
    //On construit la requête
    TZR.jQueryPost({
	'url':window.location.origin+'/scripts/deepl.php',
	'data':{text:text,targetLang:targetLang,srcLang:srcLang},
	'type':'POST',
	'cb':function(translateText){
	    if(Array.isArray(inputName)){
		// Si e est un Array ça veut dire que l'on traduit plusieurs champs
		// translateText est donc un tableau sous forme de JSON qu'il faut parser pour pouvoir l'utiliser
		translateText = JSON.parse(translateText);
		// pour chaque pair champ/texte traduit, on met la traduction en place
		for(let i=0;i<translateText.length;i++){
		    displayTrad(translateText[i],inputName[i]);
		}
	    } else {
		// On a qu'un seul champ à traduire
		displayTrad(translateText,inputName);
	    }
	}
    });
}

// Cette fonction check si on peut appeler l'API (langue cible valable)
function check_iso(text,inputName,lang){
    TZR.jQueryPost({
	'url':window.location.origin+'/scripts/deepl.php',
	'data':{mode:"check_iso",srcLang:lang["src"],targetLang:lang["target"]},
	'type':'POST',
	'cb':function(response){
	    if(response){
		// Si la langue cible est valide, on appelle l'API
		DeeplAPI(text,inputName,lang);
	    } else {
		// Sinon on signale que la traduction automatique n'est pas disponible pour la langue cible choisie
		alert("La traduction automatique n'est pas disponible pour la langue cible choisie");
	    }
	}
    });
}
