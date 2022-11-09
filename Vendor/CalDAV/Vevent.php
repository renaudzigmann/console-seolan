<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Vevent
 *
 * @author blegal
 */
require_once 'CalDAVDataBase.php';
class Vevent {
    
    public $uri;
    
    private $koid;
    
    public $data;
    
    public $etag;
    
    public $uidi;
    

    public $xmod;

    public function __construct(VCalendar $calendar,$koid,$report_Request,$xmod) {
	$this->xmod = $xmod;
        $this->koid = CalDAVDataBase::get_event_koid_from_uid($koid,$calendar->koid , $this->xmod);
        $this->setUidi($koid);
        $this->uri = $calendar->uri . ($this->uidi ? $this->uidi : $this->koid) . ".ics";
        $this->setEtag($calendar->koid);
        if($report_Request) {
            $this->setData($xmod,$calendar);
        }
    }
    
    private function setUidi($koid) {
        
        if($this->koid != $koid) {
            $this->uidi = $koid;
        }
        else {
	  $this->uidi = CalDAVDataBase::get_event_uid($this->koid, $this->xmod);
        }
        
    }
    
    private function setData($xmod,$calendar) {

        $koid = $this->checkIfKoidIsUidi();
        $param['intcall'] = 1;
        $param['expoid'] = $this->xmod->tevt.':'. $koid;
        $param['calendar_koid'] = $this->xmod->tagenda.':'.$calendar->koid;
        $this->data = $xmod->saveExport($param);
    }
    
    // check if the event's requested in the uri is the koid or the uidi 
    // ? identique à get_event_koid_from_uid ? à koidd près
    private function checkIfKoidIsUidi() {
        
        $query = 'SELECT KOID FROM '.$this->xmod->tevt.' WHERE UIDI = ?';
        $koid = getDB()->fetchCol($query, array($this->koid));
        if($koid) {
            $koid = str_replace($this->xmod->tevt.':', '', $koid[0]);
            return $koid;
        }
        return $this->koid;
    }
    
    // return the update time as the unique etag 
    
    private function setEtag($calendar_koid) {
        
            
      $this->etag = CalDAVDataBase::get_event_etag($this->koid,$calendar_koid,  $this->xmod);
            
    }
}
