<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Vcollection
 *
 * @author blegal
 */

require_once 'VCalendar.php';
require_once 'CalDAVDataBase.php';
class Vcollection {
    
    public $uri;
    
    public $vCalendars;
    
    public $ressourceType = [
        'collection',
        'principals'
        ];
    
    private $xmod;
    
    private $user;
    
    public $owner;
    
    public $resourcetype = [
        "collection",
        "principal"
    ];
    
    public $email;
    
    public $display_name;
    
    public $privilege_set = [
        "write",
        "write-acl",
        "write-properties",
        "write-content",
        "bind",
        "unbind",
        "unlock",
        "read",
        "read-acl",
        "read-current-user-privilege-set"
    ];
    
    public $supportedReportSet = [
        'd:expand-property',
        'd:principal-property-search',
        'd:principal-search-property-set'       
    ];
    
    public $supported_prop = [
        'current-user-principal',
        'principal-URL',
        'resourcetype',
        'supported-report-set',
        'displayname',
        'calendar-home-set',
        'calendar-user-address-set',
        'current-user-privilege-set',
        'owner',
        'principal-collection-set'
    ];
    
    public $principal;
    
    
    public function __construct($depth,$request, $xmod) {
        
        $this->user = $request->user;
        $this->setUri($request->moid);
        $this->xmod = $xmod;
        $this->set_principal($request->moid);
        $this->setOwner();
        $this->set_email();
        $this->set_display_name();
	\Seolan\Core\Logs::debug(get_class($this).'caldav::__construct end');
    }
    
    public function getCalendars()
    {
      $calendars_koid = CalDAVDataBase::get_user_calendars_koid($this->user, $this->xmod);
        if($calendars_koid){
            foreach ($calendars_koid as $calendar_koid ) {
            $calendar_koid = str_replace($this->xmod->tagenda.':', '', $calendar_koid);    
            $this->vCalendars[] = new VCalendar($this,$calendar_koid,$this->xmod);
            
            }
        }
    }
    
    public function getCalendar($calendar_koid) {
        
      $checked_calendar_koid = CalDAVDataBase::check_calendar_koid($calendar_koid , $this->xmod);
        if($checked_calendar_koid) {
            $calendar_koid = str_replace($this->xmod->tagenda.':', '', $checked_calendar_koid);    
            return new VCalendar($this,$calendar_koid,$this->xmod);
        }
        require_once '/exceptions/InexistantCalendarException.php';
        throw new InexistantCalendarException();
    }


    private function setUri($moid) {
        
        $this->uri = "/scripts/caldav.php/"
                . $moid . "/"
                . "calendars/"
                . $this->user . "/";
    }
    
    private function set_principal($moid) {
        
        $this->principal = $this->owner = "/scripts/caldav.php/"
                . $moid . "/"
                . "principals/";
        
    }
    
    private function setOwner() {
        
        $this->owner = $this->principal
                . $this->user . "/";
    }
    
    
    private function set_email() {
        
      $this->email = CalDAVDataBase::get_user_email($this->user);
    }
    
    private function set_display_name() {
        
        $this->display_name = CalDAVDataBase::get_user_display_name($this->user);
    }
    
    
}
