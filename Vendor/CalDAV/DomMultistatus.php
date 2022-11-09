<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DomMultistatus
 *
 * @author blegal
 */
class DomMultistatus {
    
    public $dom;
    private $xmlns = [
        'DAV:' => 'd',
        'http://sabredav.org/ns' => 's',
        'urn:ietf:params:xml:ns:caldav' => 'cal',
        'http://calendarserver.org/ns/' => 'cs'        
    ];
    private $calendar;
    private $request;
    private $collection;
    
    public function __construct(Request $request, Vcollection $collection) {
        
        $this->request = $request;
        $multistatus = $this->setMultiStatusNode();
        $this->collection = $collection;
        $this->setResponsesNode($multistatus);   
    }
    
    public function setMultiStatusNode(){
        
        $this->dom = new \DOMDocument('1.0','utf-8');
        $multiStatus = $this->dom->createElement('d:multistatus');
        $this->dom->appendChild($multiStatus);

        // Adding in default namespaces
        foreach($this->xmlns as $namespace=>$prefix) {
            $multiStatus->setAttribute('xmlns:' . $prefix,$namespace);
        }
        
        return $multiStatus;
        
    }
    
    public function setResponsesNode($document) {
        $noevent=false;
        if($this->request->method === 'PROPFIND' && 
                ($this->request->uri === $this->collection->uri ||
                $this->request->uri === $this->collection->owner)) {
            
            $noevent = true;
            $document->appendChild($this->getResponseNode());
            //append the properties of the collection to the response node
            if ($this->request->depth === '1' && !($this->request->uri === $this->collection->owner)) {
                
                $this->collection->getCalendars();
                
                foreach ($this->collection->vCalendars as $calendar){
                    
                    $this->calendar = $calendar;
                    $this->request->uri = $calendar->uri;
                    $document->appendChild($this->getResponseNode());
                }                
            }
        }
        
        elseif($this->request->method === 'PROPFIND') {
            
            //append the properties of the calendar requested to the response node
            require_once 'exceptions/InexistantCalendarException.php';
            try {
                $this->calendar = $this->collection->getCalendar($this->request->calendar_koid);
            }
            catch (InexistantCalendarException $e) {
                header('HTTP/1.1 404 Inexistant Calendar');
                exit();
            }
            $this->request->uri = $this->calendar->uri;
            $document->appendChild($this->getResponseNode());      
        }
        
        
        if($this->request->depth === '1' && !$this->request->parsed_xml->hrefs && !$noevent) {
            $this->calendar === null ? $this->calendar = $this->collection->getCalendar($this->request->calendar_koid) : $this->calendar;
            $events = $this->calendar->getEvents(null,$this->request->parsed_xml->filters); 
        }
        
        
        if ($this->request->parsed_xml->hrefs) {    
                
                $this->calendar = $this->collection->getCalendar($this->request->calendar_koid);
                $events = $this->calendar->getEvents($this->request->parsed_xml->hrefs);
        }
        
        if (isset($events)) {
            
            foreach ($events as $event) {
                
                $this->event = $event;
                $this->request->uri = $event->uri;
                $document->appendChild($this->getResponseNode());
            }
        }
        
            
    }
    
    private function getResponseNode() {
        
        $xresponse = $this->dom->createElement('d:response');
        $xresponse->appendChild($this->getHrefNode());
        $prop404 = $this->getprop404();
        $xresponse->appendChild($this->getPropStatNode(array_diff($this->request->parsed_xml->props, $prop404), 'HTTP/1.1 200 OK'));
        
        if(!empty($prop404) && $this->request->method === 'REPORT') {
            
            $xresponse->appendChild($this->getPropStatNode($prop404, 'HTTP/1.1 404 Not Found'));
        }
        
        return $xresponse;
        
    }
    
    private function getPropStatNode($props,$status) {
        
        $xpropstat = $this->dom->createElement('d:propstat');
        $xprop = $this->dom->createElement('d:prop');
        $xpropstat->appendChild($xprop);
        
        foreach ($props as $propName){   
            
            $xpropName = $this->setPropName($propName);
            $xprop->appendChild($xpropName);          
        }
        $xpropstat->appendChild($this->dom->createElement('d:status', $status));
        
        return $xpropstat;
    }
    
   private function getprop404() {
       
       $props = $this->request->parsed_xml->props;
       $prop404 = array();
       
       if($this->request->uri === $this->calendar->uri) {
           
           foreach($props as $prop) {
               
               if (!in_array($prop, $this->calendar->supported_prop))  {
                   
                    $prop404[] = $prop;
               }     
           }    
       }
                  
       
       if($this->request->uri === $this->collection->owner || 
          $this->request->uri === $this->collection->uri) {
                      
           foreach ($props as $prop) {
               if (!in_array($prop,$this->collection->supported_prop)) {
                   
                   $prop404[] = $prop;
               }
           }
           if($this->request->uri === $this->collection->uri) {
               $prop404[] = 'displayname';
           }
       }
       
       if($this->request->method === 'REPORT') {
           $report_prop404 = array('updated-by','schedule-tag','created-by');
           foreach ($props as $prop) {
               if (in_array($prop,$report_prop404)) {
                   
                   $prop404[] = $prop;
               }
           }
       }
       
       return $prop404;
   } 
    
    private function setPropName($propName) {
        // return a DOMnode corresponding to the propname and the object we are working on
        switch ($propName){
            case 'resourcetype':
                return $this->setRessourceType();
            
            case 'owner':
                return $this->setOwner();
                
            case 'current-user-principal':
                return $this->setCurrentPrincipal();
                
            case 'supported-report-set':
                return $this->setSupportedReport();
                
            case 'supported-calendar-component-set':
                return $this->setSupportedCalendar();
            
            case 'getctag':
                return $this->setCtag();
            
            case 'calendar-color':
                return $this->setCalendarColor();
            
            case 'calendar-order':
                return $this->setCalendarOrder();
                
            case 'displayname':
                return $this->setDisplayName();
            
            case 'getetag':
                return $this->setEtag();
                
            case 'getcontenttype':
                return $this->setContentType();
                
            case 'calendar-data':
                return $this->getEventData();
                
            case 'principal-URL':
                return $this->set_principal_url();
                
            case 'calendar-home-set':
                return $this->set_calendar_home_set();
                
            case 'calendar-user-address-set':
                return $this->set_calendar_user_address_set();
                
            case 'sync-token':
                return $this->set_sync_token();
                
            case 'current-user-privilege-set':
                return $this->set_user_privilege_set();
                
            case 'schedule-calendar-transp':
                return $this->set_calendar_transp();
                
            case 'principal-collection-set':
                return $this->set_principal_collection_set();
            
            default :
                $node = $this->dom->createElement($propName);
                return $node;
                                    
        }
    }
    

    
   private function getHrefNode() {
       
       $xhref = $this->dom->createElement('d:href',$this->request->uri);
       return $xhref;
       
   }
    

    private function setContentType() {
        
        if (isset($this->event) ? ($this->request->uri === $this->event->uri) : false) {
            
            $xdisplayName = $this->dom->createElement('d:getcontenttype','text/calendar; charset=utf-8; component=vevent');
        }
        else {
            
            $xdisplayName = $this->dom->createElement('d:getcontenttype');
        }
        return $xdisplayName;
    }
    
    private function setDisplayName() {
        
        $display_name = null;
        if($this->request->uri === $this->calendar->uri) {
            
            $display_name = $this->calendar->displayName;
        }
        elseif ($this->request->uri === $this->collection->owner) {
            
            $display_name = $this->collection->display_name;      
        }
        return $this->dom->createElement('d:displayname',$display_name);
    }
    
    private function setCalendarColor() {
        
        // if we request a calendar it may have a color
        if($this->request->uri === $this->calendar->uri) {
            
            $xcalendarColor = $this->dom->createElement('x4:calendar-color');
            $xcalendarColor->setAttribute('xmlns:x4','http://apple.com/ns/ical/');
            $xcolor = $this->dom->createTextNode($this->calendar->get_color());
            $xcalendarColor->appendChild($xcolor);
        }
        // a collection or an event can't have a color
        else {
            
            $xcalendarColor = $this->dom->createElement('x4:calendar-color');
            $xcalendarColor->setAttribute('xmlns:x4','http://apple.com/ns/ical/'); 
        }
        return $xcalendarColor;
    }
    
    private function setCalendarOrder() {
       
        $xcalendarOrder = $this->dom->createElement('x4:calendar-order',0);
        $xcalendarOrder->setAttribute('xmlns:x4','http://apple.com/ns/ical/'); 
        return $xcalendarOrder;
    }
    
    
    private function setEtag() {
        
        if (isset($this->event) ? ($this->request->uri === $this->event->uri) : false) {
            
        $etag = $this->event->etag;
        $xEtag =$this->dom->createElement('d:getetag',$etag);
        }
        else {
            
            $xEtag =$this->dom->createElement('d:getetag');
        }
        return $xEtag;
    }
    
    private function setCtag() {
        
        if($this->request->uri === $this->calendar->uri) {
            
            $xctag = $this->dom->createElement('cs:getctag',$this->calendar->ctag);
        }
        else {
            
            $xctag = $this->dom->createElement('cs:getctag');
        }
        return $xctag;
    }
    
    private function setRessourceType(){
        
        $xressourceType = $this->dom->createElement('d:resourcetype');
        if($this->request->uri === $this->collection->uri){
            
            $xressourceType->appendChild($this->dom->createElement('d:collection'));
        }
        elseif ($this->request->uri === $this->calendar->uri) {
            
            foreach($this->calendar->ressourceType as $ressource){
                 
                $xressourceType->appendChild($this->dom->createElement($ressource));
            }
        }
        elseif ($this->request->uri === $this->collection->owner) {
            
            foreach($this->collection->resourcetype as $ressource){
               
                $xressourceType->appendChild($this->dom->createElement($ressource));
            }
        }
        return $xressourceType;
    }
    
    private function setOwner() {
        
        $xowner = $this->dom->createElement('d:owner');
        $xhref = $this->dom->createElement('d:href',$this->collection->owner);
        $xowner->appendChild($xhref);
        return $xowner;
    }
    
    private function setCurrentPrincipal() {
        
        $xcurrentUserPrincipal = $this->dom->createElement('d:current-user-principal');
        $xhref = $this->dom->createElement('d:href',$this->collection->owner);
        $xcurrentUserPrincipal->appendChild($xhref);
        return $xcurrentUserPrincipal;
    }
    
    private function setSupportedReport() {
        
        $xsupportedReportSet = $this->dom->createElement('d:supported-report-set');
        $supported_set = ($this->request->uri === $this->calendar->uri) ? 
                          $this->calendar->supportedReportSet : 
                          $this->collection->supportedReportSet; 
        foreach($supported_set as $report) {
            
            $xsupportedReport = $this->dom->createElement('d:supported-report');           
            $xreportFlag = $this->dom->createElement('d:report');
            $xreportMethod = $this->dom->createElement($report);
            $xreportFlag->appendChild($xreportMethod);
            $xsupportedReport->appendChild($xreportFlag);
            $xsupportedReportSet->appendChild($xsupportedReport);
        }
        return $xsupportedReportSet;
    }
    
    private function setSupportedCalendar() {
        
        $xsupportedComponent = $this->dom->createElement('cal:supported-calendar-component-set');
        foreach ($this->calendar->supportedComponant as $componant){
            
            $xcomponant = $this->dom->createElement('cal:comp');
            $xcomponant->setAttribute('name',$componant);
            $xsupportedComponent->appendChild($xcomponant);
        }
        return $xsupportedComponent;
    }
    
    private  function getEventData(){
        
        $xcalendarData = $this->dom->createElement('cal:calendar-data');
        $data = $this->dom->createCDATASection($this->event->data);
        $xcalendarData->appendChild($data);
        return $xcalendarData;
    }
    
    private function set_principal_url() {
        
        $xprincipal = $this->dom->createElement('d:principal-URL');
        $xhref = $this->dom->createElement('d:href',$this->collection->owner);
        $xprincipal->appendChild($xhref);
        return $xprincipal;
    }
    
    private function set_calendar_home_set() {
        
        $xcalendar_home = $this->dom->createElement('cal:calendar-home-set');
        $xhref = $this->dom->createElement('d:href',$this->collection->uri);
        $xcalendar_home->appendChild($xhref);
        return $xcalendar_home;
    }
    
    private function set_calendar_user_address_set() {
        
        $xcalendar_home = $this->dom->createElement('cal:calendar-user-adress-set');
        $xhref = $this->dom->createElement('d:href','mailto:'.$this->collection->email);
        $xcalendar_home->appendChild($xhref);
        $xhref = $this->dom->createElement('d:href',$this->collection->owner);
        $xcalendar_home->appendChild($xhref);
        return $xcalendar_home;
    }
    
    private function set_calendar_transp() {
        
        $xcalendar_transp = $this->dom->createElement('cal:schedule-calendar-transp');
        if($this->request->uri === $this->calendar->uri) {           
            $xcalendar_transp->appendChild($this->dom->createElement('cal:opaque'));
        }
        return $xcalendar_transp;
    }
    
    private function set_sync_token() {
        
        if($this->request->uri === $this->calendar->uri) {
            
            $xsync_token = $this->dom->createElement('d:sync-token',$this->calendar->synctoken);
        }
        else {
            
            $xsync_token = $this->dom->createElement('d:sync-token');
        }
        return $xsync_token;
    }
    
    private function set_user_privilege_set() {
        
        $xprivilege_set = $this->dom->createElement('d:current-user-privilege-set');
        foreach ($this->collection->privilege_set as $privilege ) {
            $xprivilege = $this->dom->createElement('d:privilege');
            $xprivilege->appendChild($this->dom->createElement('d:'.$privilege));
            $xprivilege_set->appendChild($xprivilege);
        }
        return $xprivilege_set;
    }
    
    private function set_principal_collection_set() {

        $xprincipal_collection = $this->dom->createElement('d:principal-collection-set');
        $xhref = $this->dom->createElement('d:href',$this->collection->principal);
        $xprincipal_collection->appendChild($xhref);
        return $xprincipal_collection;
    }
    
}
