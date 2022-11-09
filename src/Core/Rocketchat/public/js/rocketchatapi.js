var charged = false;
var user_id = "";
var token = "";
//Permet d'afficher/cacher le chat
displayChat = function(){
    if(charged){
	if(jQuery("#chat_bar").css('height') == '0px'){
	    jQuery("#chat_bar").css('height','100%');
	    jQuery("#chat_bar").css('min-width','300px');
	    jQuery("#chat_bar").css('width','300px');
	    jQuery("#chat_bar_tools").css('display','flex');
	    jQuery(".RCbadge").each(function(){
		this.innerHTML = "";
	    });
	} else {
	    jQuery("#chat_iframe").css('height','100%');
	    jQuery("#chat_bar").css('height','0px');
	    jQuery("#chat_bar").css('min-width','0px');
	    jQuery("#chat_bar").css('width','0px');
	    jQuery("#chat_bar_tools").hide();
	}
    }
}

//Déconnecte l'utilisateur
RClogout = function(){
    jQuery("#chat_box").hide();
    var iframe = document.getElementById("chat_iframe");
    iframe.contentWindow.postMessage({
	externalCommand: 'set-user-status',
	status: 'offline',
    }, '*');
    iframe.contentWindow.postMessage({
	externalCommand: 'logout',
    }, '*');
    return true;
}

autologout = function(){
    window.addEventListener('beforeunload', function(e) {
	RClogout();
    });
}

updateUnreadMsg = function(uid){
    TZR.jQueryPost({
	'url':window.location.origin+'/scripts/admin.php?function=checkUnreadMsg&toid=997',
	'type':'POST',
	'data':{'uid':uid},
	'cb':function(unread){
	    unread = JSON.parse(unread);
	    var badges = jQuery(".RCbadge").each(function() {
		badge = this;
		if(unread.unread){
		    if(badge.childNodes[0]){
			badge.childNodes[0].nodeValue = unread.nb
		    } else {
			var count = document.createTextNode(unread.nb);
			badge.appendChild(count);
		    }
		}
	    });
	}
    });
}

RClogin = function(uid=""){
    uid = uid.trim();
    user_id = uid;
    TZR.jQueryPost({
	'url':window.location.origin+'/scripts/admin.php?function=rocketchatLogin&toid=997',
	'type':'POST',
	'data':{'uid':uid},
	'target':jQuery("#chat_box"),
	'cb':function(response){
	    response = JSON.parse(response);
	    if(!response.error){
		var iframe = document.getElementById("chat_iframe");
		if(response.koid){
		    iframe.src= window.location.origin+'/csx/src/Core/Rocketchat/public/templates/bad_mail.html';
		} else {
		    var url = response.url;
		    token = response.token;
		    iframe.src = url;
		    updateUnreadMsg(uid);
		}
	    } else {
		console.log(response.msg);
	    }
	}
    });
}
// Permet de fermer un onglet de chat
// close est le bouton pour fermer un onglet du chat
close_chat = function(close){
    var iframe_box = close.parentNode.parentNode;
    iframe_box.innerHTML = "";
    iframe_box.remove();
}
reduce_chat = function(close){
    var iframe_box = close.parentNode.parentNode;
    var iframe = iframe_box.childNodes[1];
    iframe.style.maxHeight = '60px';
    iframe_box.style.maxHeight = '100px';
    close.onclick = function(){grow_chat(close)};
    var span = close.childNodes[0];
    span.classList.remove("csico-bottom");
    span.classList.add("csico-top");
}
grow_chat = function(close){
    var iframe_box = close.parentNode.parentNode;
    var iframe = iframe_box.childNodes[1];
    iframe.style.maxHeight = '100%';
    iframe_box.style.maxHeight = '100%';
    close.onclick = function(){reduce_chat(close)};
    var span = close.childNodes[0];
    span.classList.remove("csico-top");
    span.classList.add("csico-bottom");
}

reduce_bar = function(){
    jQuery("#chat_iframe").css('height','60px');
    jQuery("#chat_bar").css('height','100px');
    jQuery("#reduce_grow").attr("onclick","grow_bar()");
    jQuery("#reduce_grow_span").removeClass("csico-bottom");
    jQuery("#reduce_grow_span").addClass("csico-top");
}
    
grow_bar = function(){
    jQuery("#chat_iframe").css('height','100%');
    jQuery("#chat_bar").css('height','100%');
    jQuery("#reduce_grow").attr("onclick","reduce_bar()");
    jQuery("#reduce_grow_span").removeClass("csico-top");
    jQuery("#reduce_grow_span").addClass("csico-bottom");
}

close_bar = function(){
    grow_bar();
    displayChat();
}

//Construit l'onglet de chat
//url est l'url de la conversation à ouvrir
addChat = function(url){
    jQuery("body")[0].style.cursor = "wait";
    url = url+"?layout=embedded";
    var iframeArray = [...document.getElementsByClassName("iframe_chat")];
    var newChat = true;
    iframeArray.forEach(element => {
	if(element.src == url){
	    newChat = false;
	}
    });
    
    if(newChat){
	//on construit le iframe
	var span = document.createElement("span");
	span.classList.add("zoom");
	span.classList.add("glyphicon");
	span.classList.add("csico-close");
	    
	var close_button = document.createElement("a");
	close_button.appendChild(span);
	close_button.setAttribute("onclick","close_chat(this)");
	close_button.classList.add("chat_bar_tool");
	close_button.classList.add("btn");
	close_button.classList.add("btn-primary");
		
	var iframe_box_tools = document.createElement("div");
	iframe_box_tools.appendChild(close_button);
	iframe_box_tools.classList.add("iframe_box_tools");
	
	var iframe = document.createElement('iframe');
	iframe.classList.add("iframe_chat");
	iframe.src = url;
	
	var iframe_box = document.createElement("div");
	iframe_box.classList.add("iframe_box");
	iframe_box.appendChild(iframe_box_tools);
	iframe_box.appendChild(iframe);

	var chat_display = document.getElementById("chat_display");
	chat_display.appendChild(iframe_box);
	window.addEventListener('message', function(e) {
	    if(e.data.eventName == "startup" && iframe.contentWindow){
		iframe.contentWindow.postMessage({
		    externalCommand: 'login-with-token',
		    token: token
		}, '*');
	    }
	    if(e.data.eventName == "Custom_Script_Logged_In"){
		iframe_box.style.display = "flex";
		jQuery("body")[0].style.cursor = "inherit";
	    }
	});
    } else {
	jQuery("body")[0].style.cursor = "inherit";
    }
}


checkConvExist = function(url){
    mode = url.split('/').slice(-2)[0];
    if(mode == 'direct'){
	rid = url.split('/').slice(-1)[0];
	TZR.jQueryPost({
	    'url':window.location.origin+'/scripts/admin.php?function=checkConvExist&toid=997',
	    'type':'POST',
	    'data': {'id':rid},
	    'target':jQuery("#chat_box"),
	    'cb':function(response){
		response = JSON.parse(response);
		if(response.url){
		    addChat(response.url);
		} else {
		    console.log(response);
		}
	    }
	});
    } else {
	addChat(url);
    }
}

//Permet d'ouvrir la fenetre de chat avec un utilisateur donné
// oid :
// uid :
createDM = function(oid,uid){
    TZR.jQueryPost({
	'url':window.location.origin+'/scripts/admin.php?function=RCcreateDM&toid=997',
	'type':'POST',
	'data': {'user':oid,'uid':uid},
	'target':jQuery("#chat_box"),
	'cb':function(response){
	    response = JSON.parse(response);
	    if(response.url){
		addChat(response.url);
	    } else {
		console.log(response)
	    }
	}
    });
}

//Permet de récupérer les clics qui vienne de l'iframe
getIframeEvent = function(){
    window.addEventListener('message', function(e) {
	var iframe = document.getElementById("chat_iframe");
	messageListener(e,iframe);
    });
}

var start = true;

messageListener = function(e,iframe){
    if(e.data.eventName == "startup" && start){
	start = false;
	iframe.contentWindow.postMessage({
	    externalCommand: 'login-with-token',
	    token: token
	}, '*');
	iframe.contentWindow.postMessage({
	    externalCommand: 'set-user-status',
	    status: 'online',
	}, '*');
    }
    
    if(e.data.eventName == "Custom_Script_Logged_In"){
	jQuery("#chat-top-bar").css('cursor','pointer');
	charged = true;
    }
    if(e.data.seolanAction === "sendUrl"){
	var message = {seolanAction:'getScriptUrl', url: window.location.origin+"/csx/src/Core/Rocketchat/public/js/rocketchat.js"};
	iframe.contentWindow.postMessage(message,"*");
	message = {seolanAction:'getCssUrl', url: window.location.origin+"/csx/src/Core/Rocketchat/public/css/customRocketchat.css"};
	iframe.contentWindow.postMessage(message,"*");
    }
    //l'iframe rocketchat envoie plein de message via post message mais on ne veut faire quelque chose qu'avec notre événement qui est le seul à ne pas avoir d'eventName
    if(e.data.seolanAction === "addChat"){
	checkConvExist(e.data.url);
    }
    
    if(e.data.seolanAction === "grow_bar"){
	grow_bar();
    }

    if(e.data.seolanAction == "grow_chat"){
	console.log(e.data.chat);
    }
    
    if(e.data.eventName === "notification"){
	if(jQuery("#chat_bar").css('height') == '0px'){
	    updateUnreadMsg(user_id);
	}
    }
}
