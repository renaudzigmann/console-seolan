<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of VCalendar
 *
 * @author blegal
 */
class VCalendar {
    public $uri;
    
    public $ctag;
    
    public $koid;
    
    public $displayName;
    
    private $xmod;
    
     public $ressourceType = [
        'd:collection',
        'cal:calendar'    
        ];
    
    public $supportedReportSet = [
        'd:expand-property',
        'd:principal-property-search',
        'd:principal-search-property-set',
        'cal:calendar-multiget',
        'cal:calendar-query'           
    ];
    
    public $supportedComponant = [
        'VEVENT',
        'VTODO'
    ];
    
    public $supported_prop = [
        'resourcetype',
        'owner',
        'supported-report-set',
        'supported-calendar-component-set',
        'getctag',
        'displayname',
        'calendar-data',
        'current-user-principal',
        'calendar-color',
        'sync-token',
        'current-user-privilege-set',
        'calendar-order',
        'schedule-calendar-transp'
                
    ];
    
    public $color;
    
    public $synctoken;

    
    public function __construct($collection,$koid, \Seolan\Module\Calendar\Calendar $xmod) {
        
        $this->uri = $collection->uri . $koid . '/';
        $this->koid = $koid;
        $this->xmod = $xmod;
        $this->setSyncToken();
        $this->setDisplayName();
    }
    
    private function setDisplayName() {
           
      $this->displayName = CalDAVDataBase::get_calendar_display_name($this->koid, $this->xmod);
    }
    
    private function setSyncToken(){
        
      $sync_token = CalDAVDataBase::get_calendar_sync_token($this->koid, $this->xmod);
        $this->ctag = 'http://seolan/ns/sync/'.$sync_token;
        $this->synctoken = null;
    }
    
    public function getEvents($hrefs,$time_filters=null) {
        
        require_once 'Vevent.php';
	
        if(!$hrefs) {
            
	  $events_koid = CalDAVDataBase::get_calendar_events($this->koid, $this->xmod, $time_filters);
        } else {
            foreach ($hrefs as $href) {
                $foo1=explode('/',$href);
                $href = end($foo1);                
                $events_koid[] = str_replace('.ics', '', $href);
            }
        }
        if ($events_koid) {
	  
            foreach ($events_koid as $event_koid) {
                
	      $event_koid = str_replace($this->xmod->tevt.':', '', $event_koid);
	      $this->events[] = new Vevent($this, $event_koid,true, $this->xmod);
                
            }
            return $this->events;
        }
    }
    
    public function get_color() {
        
        $default_color = '#369de1';
        $color = CalDAVDataBase::get_calendar_color($this->koid, $this->xmod);
        if($color) {
            return $color;
        }
        else {           
            $this->set_color($default_color);
        }
        return $default_color;
    }
    
    public function set_color($color) {
        
      CalDAVDataBase::set_calendar_color($this->koid, $color, $this->xmod);
    }
}
