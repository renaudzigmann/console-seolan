if(window.location.href==window.location.origin+"/home"){
    class SeolanRocketchat{
	initRocketChat(){
	    // Selectionne le noeud dont les mutations seront observées
	    var targetNode = document.getElementsByTagName("body")[0];
	    // Options de l'observateur (quelles sont les mutations à observer)
	    var config = { childList: true, subtree: true };	    
	    // Fonction callback à éxécuter quand une mutation est observée
	    var callback = function(mutationsList) {
		var els = jQuery("a");
		var el = [];
		for(let e of els){
		    if(e.classList.contains("rcx-box--full")){
			el.push(e);
		    }
		}
		if(el.length){
		    for(let i of el){
			i.onclick = function(event){
			    var message = {seolanAction:'addChat',url:this.href};
			    window.parent.postMessage(message, '*');
			};
		    }
		}
	    };
	    // Créé une instance de l'observateur lié à la fonction de callback
	    var observer = new MutationObserver(callback);
	    // Commence à observer le noeud cible pour les mutations précédemment configurées
	    observer.observe(targetNode, config);
	}
    }
    var fileref=document.createElement("link");
    fileref.setAttribute("rel", "stylesheet");
    fileref.setAttribute("type", "text/css");
    fileref.setAttribute("href", "https://takeoff.xsalto.com/csx/src/Core/Rocketchat/public/css/sidebar.css");
    var head = document.getElementsByTagName("head")[0];
    head.insertBefore(fileref,head.firstChild);
    
    init = new SeolanRocketchat;
    init.initRocketChat();
}
