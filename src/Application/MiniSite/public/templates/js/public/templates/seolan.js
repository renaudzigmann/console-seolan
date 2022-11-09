function edit13(){
    var div = jQuery(this) ;
    jQuery(document).ready(function(){
	jQuery('select[name*=fontFamily] option' ).each(function(){
	    $this=jQuery(this);
	    fontFamily = $this.text();
	    if(!jQuery('#minisitefonts').length )
		jQuery('head').append('<link id="minisitefonts" rel="stylesheet" type="text/css" media="screen,projection,print" href="/templates/css/allfonts.css" />');
	    $this.css('fontFamily',fontFamily).css('fontSize','20px');
	});
	var $text = jQuery('<div class="container" style="line-height: normal;"><h1></h1><p>Ipsam vero urbem Byzantiorum fuisse refertissimam disciplinarum liberalium inpendio paucis sine respiratione ulla extrusis ? <strong>Quae illi, exhausti sumptibus bellisque maximis,</strong> <em><strong>cum omnis Mithridaticos</strong></em> <em>impetus totumque Pontum armatum affervescentem</em>.</p><p class="big" style="font-size: 1.5em;margin: 0 0 10px 0;padding: 0;}">Ipsam vero urbem Byzantiorum fuisse refertissimam quis ignorat ? <strong>Quae illi, exhausti sumptibus bellisque maximis,</strong> <em><strong>cum omnis Mithridaticos</strong></em> <em>impetus totumque Pontum armatum affervescentem</em>.</p></div>');
	jQuery('select[name*=fontFamily]').parent().append($text);
	jQuery('select[name*=fontFamily]').change(function(){
	    $this=jQuery(this);
	    $next = $this.next();
	    $selected = jQuery('option:selected',$this);
	    jQuery('h1',$next).html($selected.text());
	    $next.css('fontFamily',$selected.text().toLowerCase());
	}).change();
    });
}