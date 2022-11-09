<?php

/**
 * Modification de l'url pour le caldav
 *
 * /tzr/scripts/caldav.php/44/basile/ag002:54331/hgjhjhjh.ics
 * ==>
 * /tzr/scripts/caldav.php?function=synchro&moid=0001&user=basile&calendar=ag002:54331&event=hgjhjhjh.ics
 */
$_SERVER['REQUEST_URI'] = str_replace("?XDEBUG_SESSION_START=netbeans-xdebug", "", $_SERVER['REQUEST_URI']);
$rq=preg_replace('/.*caldav.php\//','',$_SERVER['REQUEST_URI']);

@list($moid,$trace,$login,$calendar,$event,$uidi) = explode("/", rawurldecode($rq));

$_REQUEST['moid'] = $moid;
$_REQUEST['function'] = 'synchro';
if(!empty($calendar)) {
    $_REQUEST['oid'] = $calendar;
}
if(!empty($uidi)){
    $_REQUEST['uidi'] = $uidi;
} else if(!empty($event)){
    $_REQUEST['uidi'] = $event;
}
$_REQUEST['caldav_request'] = true;

require_once 'auth.php';