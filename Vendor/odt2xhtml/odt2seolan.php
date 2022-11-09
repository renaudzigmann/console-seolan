<?php

/***
 *
 * Fichier : odt2seolan.class.php
 * Auteur : 	XSALTO - Fr?ric Rouvier
 * Date :	Mai 2009
 *
 * La classe odt2seolan permet :
 * - de transformer un fichier texte OpenDocument en un fichier (x)HTML,
 * - de r?p?r le style CSS du fichier,
 * - de r?p?r zone par zone, balise par balise le code HTML
 * -> pour int?er au CMS S?an des pages de contenu structur? et
 *    hi?rchis? dans la conception de site Corail.
 * 
 *
 ***/

class xhtml2seolan {
  const NS_ODT_FO      = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
  const NS_ODT_OFFICE  = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
  const NS_ODT_STYLE   = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
  const NS_ODT_TEXT    = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
  const NS_XLINK   = 'http://www.w3.org/1999/xlink';
  
  private $chemin = '';
  private $dir = '';
  private $file = '';
  private $html = '';
  private $body = '';
  private $css = '';
  private $handle = '';
  private $tags_pages=array();
  private $tags_maj=array();
  private $pattern=array();
  private $remplacement=array();
  private $rubrique=array();
  private $rub_pere=array();
  private $rub_rang=array();
  private $contenu=array();
  private $decalage=0;
  private $alias='';
  private $maj = '';
  private $maj_type = '';
  private $maj_langue = '';
  private $sectionsparam = array();
  private $motherrub = '';
  private $mod = '';

  /*** Fonctions publiques ***/
		
  public function __construct(&$mod, $dir, $file, $sectionsparam, $motherrub) {
    $this->dir = $dir;
    $this->file = $file;
    $this->sectionsparam = $sectionsparam;
    $this->motherrub = $motherrub;
    $this->mod = &$mod;

  }// __construct()

  public function import() {
    $this->load_html();
    $this->extractStyles();
    switch($this->check_new_or_maj())
      {
      case 'new':
        $this->parse_odt();
        $this->save_in_bd();
        break;
      case 'maj':
        echo 'MAJ';
        break;
      case 'lan':
        echo 'Langue';
        break;
      }
  }

  private function check_new_or_maj ()
  {
    if (preg_match("/<[ ]*maj[ ]*type='[a-z-]*'[ ]*langue='[A-Z]{2}'[ ]*>/", $this->body, $match))
      {
        $this->maj = $match[0];
        preg_match_all("/'[a-zA-Z-]*'/", $this->maj, $match2);
        $this->maj_type = $match2[0][0];
        $this->maj_langue = $match2[0][1];
        $back = ($this->maj_type == "'modification-contenu'") ? 'maj' : 'lan';
      }
    else
      $back = 'new';
    return $back;
  }// check_new_or_maj()

  /**
    * Extraction des styles.
    * pour l'instant les attributs bold italic et underline
    */
  private function extractStyles( ) {
      $styles = $this->xpath->query( '//style:style' );

      foreach ( $styles as $style) {
        $name = $style->attributes->getNamedItem('name')->value;
        $textProperties = $this->xpath->query( 'style:text-properties', $style )->item( 0 );
        if ($textProperties) {
          if ($textProperties->attributes->getNamedItem('text-underline-style')->value)
            $this->styles[$name]['underline'] = 1;
          if ($textProperties->attributes->getNamedItem('font-weight')->value == 'bold')
            $this->styles[$name]['bold'] = 1;
          if ($textProperties->attributes->getNamedItem('font-style')->value == 'italic')
            $this->styles[$name]['italic'] = 1;
        }
      }
  }
  
  /**
    * Applique un style sur un text
    * @param string $text
    * @param string class : nom de style odt
    * @return string
    */
  private function applyStyle($text, $class=null) {
/*    if ($this->styles[$class]['underline'])
      $text = "<em style=\"text-decoration: underline;\">$text</em>";*/
    if ($this->styles[$class]['underline'])
      $text = "<u>$text</u>";
    if ($this->styles[$class]['bold'])
      $text = "<strong>$text</strong>";
    if ($this->styles[$class]['italic'])
      $text = "<em>$text</em>";
    return $text;
  }
  
  private function load_html() {
    $zip = new ZipArchive;
    if ($zip->open($this->dir.'in/'.$this->file) === TRUE) {
      $zip->extractTo($this->dir.'/in/content/');
      $zip->close();
    } else {
      echo 'Erreur lecture fichier';
      die;
    }
    $content = file_get_contents($this->dir.'/in/content/content.xml');
    $this->doc = new DOMDocument();
    $this->doc->loadXML($content);
    $this->xpath = new DOMXpath($this->doc);
    $this->xpath->registerNamespace( 'office', self::NS_ODT_FO );
    $this->xpath->registerNamespace( 'office', self::NS_ODT_OFFICE );
    $this->xpath->registerNamespace( 'office', self::NS_ODT_STYLE );
    $this->xpath->registerNamespace( 'office', self::NS_ODT_TEXT );
    $this->xpath->registerNamespace( 'xlink', self::NS_XLINK );
    $this->body = $this->xpath->query('//office:body//office:text')->item( 0 );
  }

  // parse a textNode
  // @return string
  private function parse_node($node) {
    $content='';
    for ( $i = 0; $i < $node->childNodes->length; ++$i ) {
      $item = $node->childNodes->item( $i );
      switch ( $item->nodeName ) {
        case '#text' :
            $content .= $item->textContent;
            break;
        case 'text:a' :
            $href = $item->attributes->getNamedItem('href')->value;
            $title = $item->attributes->getNamedItem('name')->value;
            $target = $item->attributes->getNamedItem('target-frame-name')->value;
            $content .= '<a href="'.$href.'"'.($title?" title=\"$title\"":'').($target?" target=\"$target\"":'').'>'.$item->textContent.'</a>';
            break;
        case 'text:line-break' :
	  $content.='<br/>';
	  break;
        case 'text:list' :
            $class = $item->attributes->getNamedItem('style-name')->value;
            $content .= '<ul>';
            for ( $j = 0; $j < $item->childNodes->length; ++$j ) {
              $listItem = $item->childNodes->item( $j );
              $class = $listItem->attributes->getNamedItem('style-name')->value;
              $content .= '<li>'.$this->applyStyle($this->parse_node($listItem), $class).'</li>';
            }
            $content .= '</ul>';
            break;
        default :
            $class = $item->attributes->getNamedItem('style-name')->value;
            $content .= $this->applyStyle($this->parse_node($item), $class);
      }
    }
    return $content;
  }
  /**
   * Parse the body
   */
  private function parse_odt() {
    $labels=array();
    foreach ($this->mod->_categories->desc as $fieldName => &$fieldObject)
      $labels[$fieldObject->get_label()] = $fieldName;

    $linkup = $this->motherrub;
    $level_up = 0;
    
    for ( $i = 0; $i < $this->body->childNodes->length; ++$i ) {
        $item = $this->body->childNodes->item( $i );
        switch ($item->nodeName) {
          case "text:h" :
	    $level = $item->attributes->getNamedItem('outline-level')->value;
	    if ( $level <= 4 ) { // nouvelle rubrique
	      unset($current_rubrique);
	      $current_rubrique = array('level' => $level,
					'title' => $item->textContent);
	      $this->rubriques[] = &$current_rubrique;
	    } elseif ( $level == 5 ) { // nouvelle section
	      unset($current_section);
	      $current_section = array();
	      $current_rubrique['sections'][] = &$current_section;
	      $current_section = array('titsec' => $item->textContent); 
	    } elseif ( $level == 6 ) { //sous titre
	      if(!empty($current_section['subtit']) || !empty($current_section['chapeau']) || !empty($current_section['txt1'])) {
		unset($current_section);
		$current_section = array();
		$current_rubrique['sections'][] = &$current_section;
	      }
	      $current_section['subtit'] = $item->textContent;
	    } elseif ( $level == 7 ) { //chapeau
	      if(!empty($current_section['chapeau']) || !empty($current_section['txt1'])) {
		unset($current_section);
		$current_section = array();
		$current_rubrique['sections'][] = &$current_section;
	      }
	      $current_section['chapeau'] = $item->textContent;
	    }
	    break;
          case "text:p" :
	    $content = $item->textContent;
	    if(empty($content)) break;
	    if(empty($current_rubrique['sections'])) {
	      unset($current_section);
	      $current_section = array();
	      $current_rubrique['sections'][] = &$current_section;
	    }
	    $class = $item->attributes->getNamedItem('style-name')->value;
	    if (preg_match('/\$(.*)=(.*)\$/', $content, $matches)) {  // $Alias = monalias$ ou $Titre affiché = ...
	      $current_rubrique[$labels[$matches[1]]] = $matches[2];
	      if($labels[$matches[1]]=='alias') {
		$current_rubrique['alias']=rewriteToAscii($current_rubrique['alias'],true);
	      }
	    } else {
	      $current_section['txt1'] .= '<p>'.$this->applyStyle($this->parse_node($item), $class).'</p>';
	    }
	    break;
          case "text:list" :
	    if(empty($current_rubrique['sections'])) {
	      unset($current_section);
	      $current_section = array();
	      $current_rubrique['sections'][] = &$current_section;
	    }
	    $class = $item->attributes->getNamedItem('style-name')->value;
	    $current_section['txt1'] .= '<ul>';
	    for ( $j = 0; $j < $item->childNodes->length; ++$j ) {
	      $listItem = $item->childNodes->item( $j );
	      $class = $listItem->attributes->getNamedItem('style-name')->value;
	      $current_section['txt1'] .= '<li>'.$this->applyStyle($this->parse_node($listItem), $class).'</li>';
	    }
	    $current_section['txt1'] .= '</ul>';
	    break;
        }
    }
  }
  
  
  private function recup_tag_maj ($str)
  {
    //ajout
    if(preg_match_all ("/<ajout>.*<\/ajout>/", $str, $match))
      {
	$this->tags_maj[0]='ajout';
	$this->tags_maj[1] = $match[0][0];
      }

    if(preg_match_all ("/<suppression>.*<\/suppression>/", $str, $match))
      {
	$this->tags_maj[0]='suppression';
	$this->tags_maj[1] = $match[0][0];
      }

    if(preg_match_all ("/<modification>.*<\/modification>/", $str, $match))
      {
	$this->tags_maj[0]='modification';
	$this->tags_maj[1] = $match[0][0];
      }
			
			
  }// recup_tag_maj ()
		
  /// generaion des pages et des sections dans la base de donnes
  private function save_in_bd ()
  {
    $context=array();
    foreach($this->rubriques as $i=>&$rubrique) {
      $rubrique['newoid']=\Seolan\Core\Kernel::getNewKoid($this->mod->table);
      $context[$rubrique['level']]=$rubrique['newoid'];
      $rubrique['linkup']=$context[$rubrique['level']-1];
      $rubrique['corder']=$i;
      if(empty($rubrique['linkup'])) $rubrique['linkup']=$this->motherrub;
      $this->mod->procInput($rubrique);
    }
    foreach($this->rubriques as &$rubrique) {
      $secorder=1;
      foreach($rubrique['sections'] as &$section) {
	if(!empty($section)) {
	  $section['PUBLISH']=1;
	  $section['oidit']=$rubrique['newoid'];
	  $section['oidtpl']=$this->sectionsparam['txt']['tploid'];
	  $section['_options']=array('local'=>true);
	  $section['tplentry']=TZR_RETURN_DATA;
	  $section['position']=$secorder;
	  $this->mod->insertsection($section);
	  $secorder++;
	}
      }
    }
  }// save_rubrique_in_bd()
		
		
  private function prepare_insertion_image ($id, $img)
  {
    $str_md5 = md5($id);
    $dir_img_1 = substr($str_md5, 0, 2);
    $dir_img_2 = substr($str_md5, 16, 2);
	
    //creation des dir
    mkdir(FILENAME_IMG_TO.'/'.$dir_img_1);
    mkdir(FILENAME_IMG_TO.'/'.$dir_img_1.'/'.$dir_img_2);

    //récupère l'extension de l'image
    $extension=strrchr($img,'.');
    $extension=substr($extension,1);
    if($extension == 'jpg')
      $extension = 'jpeg';

    $filename=strrchr($img,'/');
    $filename=substr($filename,1);

    //déplace et renome l'image sur le serveur
    if (file_exists(FILENAME_IMG_FROM.'/'.$filename))
      {
	copy(FILENAME_IMG_FROM.'/'.$filename, FILENAME_IMG_TO.'/'.$dir_img_1.'/'.$dir_img_2.'/'.$id);
      } 
			
    //fabrication du champ img1
    $champ_img1  = $id.';';
    $champ_img1 .= 'image/'.$extension.';';
    $champ_img1 .= $filename.';';
			
    return $champ_img1;
			
  }// prepare_insertion_image ()
		
		
  private function decale_balise_h()
  {
    $i=0;
    foreach ($this->contenu as $content_rub)
      {
	$j=0;
	if(substr($content_rub[0], 1, 1) == 'h') 
	  {
	    $lvl_first_h = substr($content_rub[0], 2, 1);
					
	    if (substr($content_rub[0], 2, 1) != 1)
	      {
		foreach ($content_rub as $cont)
		  {
		    if (substr($cont, 1, 1) == 'h')
		      {
			$cont[2] = $cont[2] + 1 - $lvl_first_h;
			$cont[strlen($cont)-2] = $cont[strlen($cont)-2] + 1 - $lvl_first_h;
			$this->contenu[$i][$j] = $cont;
		      }
		    $j++;
		  }// foreach contenu
	      }//if titre != 1
	  }//if titre
	$i++;
      }// foreach rubrique
		

  }// decale_balise_h()
		
		
}// class xhtml2seolan
?>
