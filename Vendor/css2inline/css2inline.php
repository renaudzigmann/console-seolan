<?php

/**
 * CSS to Inline Styles class
 *
 * This source file can be used to convert HTML with CSS into HTML with inline styles
 *
 * Known issues:
 * - no support for pseudo selectors
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-css-to-inline-styles-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * Changelog since 1.0.2
 * - .class are matched from now on.
 * - fixed issue with #id
 * - new beta-feature: added a way to output valid XHTML (thx to Matt Hornsby)
 *
 * Changelog since 1.0.1
 * - fixed some stuff on specifity
 *
 * Changelog since 1.0.0
 * - rewrote the buildXPathQuery-method
 * - fixed some stuff on specifity
 * - added a way to use inline style-blocks
 *
 * License
 * Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @authorTijs Verkoyen <php-css-to-inline-styles@verkoyen.eu>
 * @version1.0.3
 *
 * @copyrightCopyright (c) 2010, Tijs Verkoyen. All rights reserved.
 * @licenseBSD License
 */
class CSSToInlineStyles
{
  /**
   * The CSS to use
   *
   * @varstring
   */
  private $css;


  /**
   * The processed CSS rules
   *
   * @vararray
   */
  private $cssRules;


  /**
   * Should the generated HTML be cleaned
   *
   * @varbool
   */
  private $cleanup = false;


  /**
   * The HTML to process
   *
   * @varstring
   */
  private $html;


  /**
   * Use inline-styles block as CSS
   *
   * @varbool
   */
  private $useInlineStylesBlock = false;


  /**
   * Creates an instance, you could set the HTML and CSS here, or load it later.
   *
   * @returnvoid
   * @paramstring[optional] $htmlThe HTML to process
   * @paramstring[optional] $cssThe CSS to use
   */
  public function __construct($html = null, $css = null)
  {
    if($html !== null) $this->setHTML($html);
    if($css !== null) $this->setCSS($css);
  }


  /**
   * Convert a CSS-selector into an xPath-query
   *
   * @returnstring
   * @paramstring $selectorThe CSS-selector
   */
  private function buildXPathQuery($selector)
  {
    // redefine
    $selector = (string) $selector;

    // the CSS selector
    $cssSelector = array('/(\w)\s+(\w)/',// E FMatches any F element that is a descendant of an E element
			 '/(\w)\s*>\s*(\w)/',// E > FMatches any F element that is a child of an element E
			 '/(\w):first-child/',// E:first-childMatches element E when E is the first child of its parent
			 '/(\w)\s*\+\s*(\w)/',// E + FMatches any F element immediately preceded by an element
			 '/(\w)\[([\w\-]+)]/',// E[foo]Matches any E element with the "foo" attribute set (whatever the value)
			 '/(\w)\[([\w\-]+)\=\"(.*)\"]/',// E[foo="warning"]Matches any E element whose "foo" attribute value is exactly equal to "warning"
			 '/(\w+|\*)+\.([\w\-]+)+/',// div.warningHTML only. The same as DIV[class~="warning"]
			 '/\.([\w\-]+)/',// .warningHTML only. The same as *[class~="warning"]
			 '/(\w+)+\#([\w\-]+)/',// E#myidMatches any E element with id-attribute equal to "myid"
			 '/\#([\w\-]+)/'// #myidMatches any element with id-attribute equal to "myid"
			 );

    // the xPath-equivalent
    $xPathQuery = array('\1//\2',// E FMatches any F element that is a descendant of an E element
			'\1/\2',// E > FMatches any F element that is a child of an element E
			'*[1]/self::\1',// E:first-childMatches element E when E is the first child of its parent
			'\1/following-sibling::*[1]/self::\2',// E + FMatches any F element immediately preceded by an element
			'\1 [ @\2 ]',// E[foo]Matches any E element with the "foo" attribute set (whatever the value)
			'\1[ contains( concat( " ", @\2, " " ), concat( " ", "\3", " " ) ) ]',// E[foo="warning"]Matches any E element whose "foo" attribute value is exactly equal to "warning"
			'\1[ contains( concat( " ", @class, " " ), concat( " ", "\2", " " ) ) ]',// div.warningHTML only. The same as DIV[class~="warning"]
			'*[ contains( concat( " ", @class, " " ), concat( " ", "\1", " " ) ) ]',// .warningHTML only. The same as *[class~="warning"]
			'\1[ @id = "\2" ]',// E#myidMatches any E element with id-attribute equal to "myid"
			'*[ @id = "\1" ]'// #myidMatches any element with id-attribute equal to "myid"
			);

    // return
    return (string) '//'. preg_replace($cssSelector, $xPathQuery, $selector);
  }


  /**
   * Calculate the specifity for the CSS-selector
   *
   * @returnint
   * @paramstring $selector
   */
  private function calculateCSSSpecifity($selector)
  {
    // cleanup selector
    $selector = str_replace(array('>', '+'), array(' > ', ' + '), $selector);

    // init var
    $specifity = 0;

    // split the selector into chunks based on spaces
    $chunks = explode(' ', $selector);

    // loop chunks
    foreach($chunks as $chunk)
      {
	// an ID is important, so give it a high specifity
	if(strstr($chunk, '#') !== false) $specifity += 100;

	// classes are more important than a tag, but less important then an ID
	elseif(strstr($chunk, '.')) $specifity += 10;

	// anything else isn't that important
	else $specifity += 1;
      }

    // return
    return $specifity;
  }


  /**
   * Cleanup the generated HTML
   *
   * @returnstring
   * @paramstring $htmlThe HTML to cleanup
   */
  private function cleanupHTML($html)
  {
    // remove classes
    $html = preg_replace('/(\s)+class="(.*)"(\s)+/U', ' ', $html);

    // remove IDs
    $html = preg_replace('/(\s)+id="(.*)"(\s)+/U', ' ', $html);

    // return
    return $html;
  }


  /**
   * Converts the loaded HTML into an HTML-string with inline styles based on the loaded CSS
   *
   * @returnstring
   * @parambool $outputXHTMLShould we output valid XHTML?
   */
  public function convert($outputXHTML = false)
  {
    // redefine
    $outputXHTML = (bool) $outputXHTML;

    // validate
    if($this->html == null) throw new CSSToInlineStylesException('No HTML provided.');

    // should we use inline style-block
    if($this->useInlineStylesBlock)
    {
      // init var
      $matches = array();

      // match the style blocks
      preg_match_all('/<style(?!(?:[^>=]|=(["])(?:(?!\1).)*\1)*?\sdata-convert="no")[^>]*>(.*?)<\/style>/isU', $this->html, $matches);

      // any style-blocks found?
      if(!empty($matches[2]))
      {
        // add
        foreach($matches[2] as $i=>$match){
          $this->css .= trim($match) ."\n";
        }
        $this->html = mb_eregi_replace('<style(?!(?:[^>=]|=(["])(?:(?!\1).)*\1)*?\sdata-convert="no")[^>]*>(.*?)<\/style>','', $this->html);
      }
    }

    $body = $this->html;
    $encoding = mb_detect_encoding($body);
    $body = mb_convert_encoding($body, 'HTML-ENTITIES', $encoding);

    // create new DOMDocument
    $document = new DOMDocument();
    $document->encoding = $encoding;
    $document->strictErrorChecking = false;
    $document->formatOutput = true;
        
    // set error level
    libxml_use_internal_errors(true);

    // load HTML
    $document->loadHTML($body);
    $document->normalizeDocument();

    // create new XPath
    $xPath = new DOMXPath($document);

    // process css
    $this->processCSS();

    // any rules?
    if(!empty($this->cssRules))
      {
	// loop rules
	foreach($this->cssRules as $rule)
	  {
	    // init var
	    $query = $this->buildXPathQuery($rule['selector']);

	    // validate query
	    if($query === false) continue;

	    // search elements
	    $elements = $xPath->query($query);

	    // validate elements
	    if($elements === false) continue;

	    // loop found elements
	    foreach($elements as $element)
	      {
		// init var
		$properties = array();

		// get current styles
		$stylesAttribute = $element->attributes->getNamedItem('style');

		// any styles defined before?
		if($stylesAttribute !== null)
		  {
		    // get value for the styles attribute
		    $definedStyles = (string) $stylesAttribute->value;

		    // split into properties
		    $definedProperties = (array) explode(';', $definedStyles);

		    // loop properties
		    foreach($definedProperties as $property)
		      {
			// validate property
			if($property == '') continue;

			// split into chunks
			$chunks = (array) explode(':', trim($property), 2);

			// validate
			if(!isset($chunks[1])) continue;

			// loop chunks
			$properties[$chunks[0]] = trim($chunks[1]);
		      }
		  }

		// add new properties into the list
		foreach($rule['properties'] as $key => $value) $properties[$key] = $value;

		// build string
		$propertyChunks = array();

		// build chunks
		foreach($properties as $key => $value) $propertyChunks[] = $key .': '. $value .';';

		// build properties string
		$propertiesString = implode(' ', $propertyChunks);

		// set attribute
		if($propertiesString != '') $element->setAttribute('style', $propertiesString);
	      }
	  }
      }

    // should we output XHTML?
    if($outputXHTML)
      {
	// set formating
	$document->formatOutput = true;

	// get the HTML as XML
	$html = $document->saveXML(null, LIBXML_NOEMPTYTAG);

	// remove the XML-header
	$html = str_replace('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n", '', $html);
      }

    // just regular HTML 4.01 as it should be used in newsletters
    else
      {
	// get the HTML
	$html = $document->saveHTML();
      }

    // cleanup the HTML if we need to
    if($this->cleanup) $html = $this->cleanupHTML($html);

    // return
    return $html;
  }


  /**
   * Process the loaded CSS
   *
   * @returnvoid
   */
  private function processCSS()
  {
    // init vars
    $css = (string) $this->css;

    // remove newlines
    $css = str_replace(array("\r", "\n"), '', $css);

    // replace double quotes by single quotes
    $css = str_replace('"', '\'', $css);

    // remove comments
    $css = preg_replace('|/\*.*?\*/|', '', $css);

    // remove spaces
    $css = preg_replace('/\s\s+/', ' ', $css);

    // rules are splitted by }
    $rules = (array) explode('}', $css);

    // init var
    $i = 1;
    $index = 0;
    $mergeCssIndex = array();
    // loop rules
    foreach($rules as $rule)
      {
	// split into chunks
	$chunks = explode('{', $rule);

	// invalid rule?
	if(!isset($chunks[1])) continue;

	// set the selectors
	$selectors = trim($chunks[0]);

	// get cssProperties
	$cssProperties = trim($chunks[1]);

	// split multiple selectors
	$selectors = (array) explode(',', $selectors);

	// loop selectors
	foreach($selectors as $selector)
	  {
	    // cleanup
	    $selector = trim($selector);
	    
	    // build an array for each selector
	    $ruleSet = array();

	    // store selector
	    $ruleSet['selector'] = $selector;

	    // process the properties
	    $ruleSet['properties'] = $this->processCSSProperties($cssProperties);

	    // calculate specifity
	    $ruleSet['specifity'] = $this->calculateCSSSpecifity($selector);

	    // add into global rules
	    // JUG : allow overwriting rules based on declaration order
	    if(isset($mergeCssIndex[$selector])){
	      $existingProperties = $this->cssRules[$mergeCssIndex[$selector]]['properties'] ;
	      $this->cssRules[$mergeCssIndex[$selector]]['properties'] = array_merge($existingProperties,$ruleSet['properties']);
	    }else{
	      $this->cssRules[$index] = $ruleSet;
	      $mergeCssIndex[$selector] = $index;
	      $index++;
	    }
	    
	  }

	// increment
	$i++;
      }

    // sort based on specifity
    if(!empty($this->cssRules)) usort($this->cssRules, array('CSSToInlineStyles', 'sortOnSpecifity'));
  }


  /**
   * Process the CSS-properties
   *
   * @returnarray
   * @paramstring $propertyString
   */
  private function processCSSProperties($propertyString)
  {
    // split into chunks
    $properties = (array) explode(';', $propertyString);

    // init var
    $pairs = array();

    // loop properties
    foreach($properties as $property)
      {
	// split into chunks
	$chunks = (array) explode(':', $property, 2);

	// validate
	if(!isset($chunks[1])) continue;

	// add to pairs array
	$pairs[trim($chunks[0])] = trim($chunks[1]);
      }

    // sort the pairs
    ksort($pairs);

    // return
    return $pairs;
  }


  /**
   * Should the IDs and classes be removed?
   *
   * @returnvoid
   * @parambool[optional] $on
   */
  public function setCleanup($on = true)
  {
    $this->cleanup = (bool) $on;
  }


  /**
   * Set CSS to use
   *
   * @returnvoid
   * @paramstring $cssThe CSS to use
   */
  public function setCSS($css)
  {
    $this->css = (string) $css;
  }


  /**
   * Set HTML to process
   *
   * @returnvoid
   * @paramstring $html
   */
  public function setHTML($html)
  {
    $this->html = (string) $html;
  }


  /**
   * Set use of inline styles block
   * If this is enabled the class will use the style-block in the HTML.
   *
   * @parambool[optional] $on
   */
  public function setUseInlineStylesBlock($on = true)
  {
    $this->useInlineStylesBlock = (bool) $on;
  }


  /**
   * Sort an array on the specifity element
   *
   * @returnint
   * @paramarray $e1The first element
   * @paramarray $e2The second element
   */
  private static function sortOnSpecifity($e1, $e2)
  {

    // validate
    if(!isset($e1['specifity']) || !isset($e2['specifity'])) return 0;

    // lower
    if($e1['specifity'] < $e2['specifity']) return -1;

    // higher
    if($e1['specifity'] > $e2['specifity']) return 1;

    // fallback
    return 0;
  }
}


/**
 * CSSToInlineStyles Exception class
 *
 * @authorTijs Verkoyen <php-css-to-inline-styles@verkoyen.eu>
 */
class CSSToInlineStylesException extends Exception
{
}

?>