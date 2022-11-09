<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ParsedXml
 *
 * @author blegal
 */
class XmlParser {
    
        // Event's uri requested
    public $hrefs = array();
    
    // Properties requested
    public $props = array();
    
    // filters requested    
    public $filters = array();
    
    public $sets = array();

    
    public function __construct($xml) {
        
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $this->parse($dom);
    }
    
    
    private function parse(DOMDocument $DOMdoc,$tag=null) {  
        
        if(!$tag) {
            
            $tag = $DOMdoc->firstChild->localName;       
        }   
        if ($tag === "sync-collection") {
            
            throw new Exception('forbidden');
        }
	\Seolan\Core\Logs::debug(get_class($this).'caldav::parse '.$tag);
        $elements = $DOMdoc->getElementsByTagName($tag); 
        foreach($elements as $element) {
            
            $children = $element->childNodes;
            
            foreach ($children as $child) {

                $this->checkattributes($DOMdoc, $child);
            }
        }       
    }
    
    
    private function checkattributes($DOMdoc,$child) {
        
        if ($child instanceof DOMElement && ($child->localName == 'prop' || $child->localName == 'filter' || $child->localName === 'set')) {
            
            
            $this->parse($DOMdoc, $child->localName);
        }
         
        if ($child instanceof DOMElement && $child->localName === 'comp-filter') {
            
            $this->getFilterAttributes($child);
        }
        
        
        else if ($child instanceof DOMElement) {
            
            $this->setAttributes($child);
        }
    }
    
    
    private function setAttributes($child) {
        if ($child instanceof DOMElement && $child->localName === 'href') {
            
            $href = str_replace("?XDEBUG_SESSION_START=netbeans-xdebug", "", rawurldecode($child->nodeValue));
	    \Seolan\Core\Logs::debug(get_class($this).'caldav::setAttributes href : '.$href.' '.$child->nodeValue);
            $this->hrefs[] = $href;
        }
        elseif ($child instanceof DOMElement && $child->parentNode->parentNode->localName === 'set' 
                && $child->parentNode->localName === 'prop') {
            
            $this->sets[$child->localName] = $child->nodeValue;
            $this->props[] = $child->localName;
        }
        elseif ($child instanceof DOMElement && $child->parentNode->localName === 'prop') {
            
            $this->props[] = $child->localName;
        } else {
	  \Seolan\Core\Logs::debug(get_class($this).'caldav::setAttributes localName '.$child->localName);
	}

    }
    
    private function getFilterAttributes (DOMElement $comp_filter_node) {
        
        $this->filters['comp-filter'][] = $comp_filter_node->getAttribute('name'); 
        $children = $comp_filter_node->childNodes;
        foreach ($children as $child) {
            
            if($child->localName === 'comp-filter' || $child->localName === 'prop-filter') {
                
                $this->getFilterAttributes($child);
            }
            if($child->localName === 'time-range') {
                
                $this->getTimeRange($child);
            }
            if($child->localName === 'text-match') {
                $this->hrefs[] =  $this->uri . $child->nodeValue . ".ics";
            }
        }
    }
    
    private function getTimeRange(DOMElement $time_range_node) {
        
        $dateStart = DateTime::createFromFormat('Ymd????????', $time_range_node->getAttribute('start'));
        $dateStart = date_format($dateStart, 'Y-m-d');
        $dateEnd = DateTime::createFromFormat('Ymd????????', $time_range_node->getAttribute('end'));
        $dateEnd = date_format($dateEnd, 'Y-m-d');
        $this->filters['start'] = $dateStart;
        $this->filters['end'] = $dateEnd; 
    }
}
