<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Create a http CalDAV response and if needed an XML body
 *
 * @author blegal
 */

require_once 'CalDAVDataBase.php';
class Response {
      
    public $body;
    public $headers;
    public $xmod = NULL;

    public function __construct(Request $request,  \Seolan\Module\Calendar\Calendar $xmod) {
      
      $this->xmod = $xmod;
      \Seolan\Core\Logs::debug(get_class($this).'caldav::__construct '.$request->method);        
      //Insert new information into the database
      
      if ($request->method === 'PUT' or $request->method === 'UPDATE') {
	
	$_FILES['filetoimp']['tmp_name']=$_REQUEST['phpputdata'];
	$_FILES['filetoimp']['size']=filesize($_REQUEST['phpputdata']);
	$_FILES['filetoimp']['name']='Importics.ics';
	
	$xmod->importEvt($ar);
	
	unlink($_FILES['filetoimp']['tmp_name']);
	
      }
      
      //Update properties of a calendar
      
      if($request->method ==='PROPPATCH') {
	
	foreach ($request->parsed_xml->sets as $set_name => $set_value) {
	  
	  CalDAVDataBase::update_calendar_propertie($set_name, $set_value, $request->calendar_koid, $this->xmod);
	}
	$request->method = 'PROPFIND';
        
      }
      
      $this->setHeader($request->method,$request);
      
      if ($request->method === 'DELETE') {
	
	$event_koid = CalDAVDataBase::get_event_koid_from_uid($request->event_uid,$request->calendar_koid, $this->xmod);
	$ar = [
	  "koid" => $this->xmod->tevt.':'.$event_koid,
	  "noalert" => true
	];
	$xmod->delEvt($ar);
      }
      require_once 'Vcollection.php';
      
      $collection = new Vcollection(true,$request, $xmod);
      
      if ($request->method == 'PROPFIND' || $request->method == 'REPORT' && $request->uri !== $collection->principal)
        {    
	  try{
	    require_once('DomMultistatus.php');
	    $multistatus = new DomMultistatus($request,$collection);
	    $this->body = $multistatus->dom->saveXML();
	  } catch(Exception $e){
	    \Seolan\Core\Logs::critical(get_class($this), 'caldav:Response construct error '.$e->getMessage());
	  }
        }
      
      // Answer to a request made only by IOS 
      elseif ($request->uri === $collection->principal) {
	\Seolan\Core\Logs::debug(get_class($this).'caldav::__construct principal');
	$this->principal();
      }
      
    }
    
    private function principal() {
        
        $this->body = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<d:principal-search-property-set xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns" xmlns:cal="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/">
  <d:principal-search-property>
    <d:prop>
      <d:displayname/>
    </d:prop>
    <d:description xml:lang="en">Display name</d:description>
  </d:principal-search-property>
  <d:principal-search-property>
    <d:prop>
      <s:email-address/>
    </d:prop>
    <d:description xml:lang="en">Email address</d:description>
  </d:principal-search-property>
</d:principal-search-property-set>
EOD;
    }
    
    private function setHeader($method,Request $request)
    {
        switch ($method){
           case 'PROPFIND' :
               $this->headers = [
                   'HTTP/1.1 207 Multi-Status',
                   'Content-Type: application/xml; charset=utf-8',
                   'Vary: Brief,Prefer',
                   'DAV: 1, 3, access-control, calendar-access, calendar-principal-property-search, calendarserver-subscribed'
               ];
               
               break;
               
           case 'OPTIONS' :
               $this->headers = [
                   'HTTP/1.1 200 OK',
                   'Allow: OPTIONS, GET, HEAD, DELETE, PROPFIND, PUT, REPORT, PROPPATCH',
                   'DAV: 1, 3, access-control, calendar-access, calendar-principal-property-search, calendarserver-subscribed',
                   'MS-Author-Via: DAV',
                   'Accept-Ranges: bytes',
               ];
               break;
           
           case 'REPORT' :
               $this->headers = [
                   'HTTP/1.1 207 Multi-Status',
                   'Content-Type: application/xml; charset=utf-8',
                   'Vary: Brief, Prefer'     
               ];
                break;
           
           case 'UPDATE' :
	     $etag = CalDAVDataBase::get_event_etag($request->event_uid, $request->calendar_koid, $this->xmod);
               $this->headers = [
                   'HTTP/1.1 204 No Content',
                   'ETag: "'. $etag .'"'
               ];
               break;
           
           case 'DELETE' :
               $this->headers = [
                   'HTTP/1.1 204 No Content'
               ];
               break;
           
           case 'PUT' :
	     $etag = CalDAVDataBase::get_event_etag($request->event_uid, $request->calendar_koid, $this->xmod);
               $this->headers = [
                   'HTTP/1.1 201 Created',
                   'ETag: "'.$etag .'"'
               ];
               break;
           
           default :
               $this->headers = [
                   'HTTP/1.1 403 Forbidden'
               ];
               break;
        }
    }  
}
