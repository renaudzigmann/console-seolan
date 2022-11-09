function correctPNG() { // correctly handle PNG transparency in Win IE 5.5 or higher.
  for(var i=0; i<document.images.length; i++) {
    var img = document.images[i]
      var imgName = img.src.toUpperCase()
      if (imgName.substring(imgName.length-3, imgName.length) == "PNG" ) {
	var imgID = (img.id) ? "id='" + img.id + "' " : ""
	var imgClass = (img.className) ? "class='" + img.className + "' " : ""
	var imgTitle = (img.title) ? "title='" + img.title + "' " : "title='" + img.alt + "' "
	var imgStyle = "display:inline-block;" + img.style.cssText  
	if (img.align == "left" ) imgStyle = "float:left;" + imgStyle
	if (img.align == "right" ) imgStyle = "float:right;" + imgStyle
	if (img.parentElement.href) imgStyle = "cursor:hand;" + imgStyle  
	var strNewHTML = "<span " + imgID + imgClass + imgTitle
 	 + " style=\"" + "width:" + img.width + "px; height:" + img.height + "px;" + imgStyle + ";"
	 + "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
	 + "(src=\'" + img.src + "\', sizingMethod='scale');\"></span>"  
	img.outerHTML = strNewHTML
	i = i-1
      }
  }
}
if(window.attachEvent){
  window.attachEvent("onload", correctPNG); 
}


// rollover sur image
function MM_preloadImages() { //v3.0
  var d=document; if(d.images){ if(!d.MM_p) d.MM_p=new Array();
  var i,j=d.MM_p.length,a=MM_preloadImages.arguments; for(i=0; i<a.length; i++)
    if (a[i].indexOf("#")!=0){ d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];}}
}

function MM_swapImgRestore() { //v3.0
  var i,x,a=document.MM_sr; for(i=0;a&&i<a.length&&(x=a[i])&&x.oSrc;i++) x.src=x.oSrc;
}

function MM_findObj(n, d) { //v4.01
  var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
    d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
  if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
  for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
  if(!x && d.getElementById) x=d.getElementById(n); return x;
}

function MM_swapImage() { //v3.0
  var i,j=0,x,a=MM_swapImage.arguments; document.MM_sr=new Array; for(i=0;i<(a.length-2);i+=3)
    if ((x=MM_findObj(a[i]))!=null){document.MM_sr[j++]=x; if(!x.oSrc) x.oSrc=x.src; x.src=a[i+2];}
}


function voir() {
	var i, obj, args = voir.arguments;
	if(document.getElementById) {
		for (i=0; i<args.length; i++) {
			obj = document.getElementById(args[i]);
                        obj.style.display = "block";
                        obj.style.visibility = "visible";
                }
        }
}

function cache() {
        var i, obj, args = cache.arguments;
        if(document.getElementById) {
                for (i=0; i<args.length; i++) {
                        obj = document.getElementById(args[i]);
                        obj.style.display = "none";
                        obj.style.visibility = "hidden";
                }
        }
}

function MM_openBrWindow(theURL,winName,features) { //v2.0
  neo = window.open(theURL,winName,features);
 if(neo.window.focus){neo.window.focus();}
}
