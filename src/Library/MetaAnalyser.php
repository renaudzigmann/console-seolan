<?php
/// Analyse et ecrit les meta d'une image JPEG
namespace Seolan\Library;

class MetaAnalyser{
  public $file;
  public $header=null;

  public $xmp_dom;
  public $xmp_xpath;
  // Liste des NS connus
  public $xmp_nss=array('photoshop'=>'http://ns.adobe.com/photoshop/1.0/','crs'=>'http://ns.adobe.com/camera-raw-settings/1.0/',
			'exif'=>'http://ns.adobe.com/exif/1.0/','tiff'=>'http://ns.adobe.com/tiff/1.0/',
			'dc'=>'http://purl.org/dc/elements/1.1/','rdf'=>'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
			'xmlns'=>'http://www.w3.org/2000/xmlns/','GettyImagesGIFT'=>'http://xmp.gettyimages.com/gift/1.0/', 'attr'=>'attr');
  // Type à appliquer par defaut sur les balises
  public $xmp_default_types=array('dc:title'=>'Alt','dc:description'=>'Alt','dc:creator'=>'Seq','dc:rights'=>'Alt','dc:subject'=>'Bag',
				  'photoshop:SupplementalCategories'=>'Bag','GettyImagesGIFT:Personality'=>'Seq','attr:Personality'=>'Seq');
  public $xmp_descr_path='/x:xmpmeta/rdf:RDF';
  public $xmp_descr_node='rdf:Description';

  public $iptc=null;
  public $iptc_ps_irb=null;
  public $iptc_by_code=null;
  public $iptc_names=array(
			   'encode'=>'1:90',
			   'object_name'=> '2:05',
			   'edit_status'=> '2:07',
			   'priority'=>'2:10',
			   'category'=>'2:15',
			   'supplementary_category'=>'2:20',
			   'fixture_identifier'=>'2:22',
			   'keywords'=>'2:25',
			   'release_date'=>'2:30',
			   'release_time'=>'2:35',
			   'special_instructions'=>'2:40',
			   'reference_service'=>'2:45',
			   'reference_date'=>'2:47',
			   'reference_number'=>'2:50',
			   'created_date'=>'2:55',
			   'originating_program'=>'2:64',
			   'program_version'=>'2:70',
			   'object_cycle'=>'2:75',
			   'byline'=>'2:80',
			   'byline_title'=>'2:85',
			   'city'=>'2:90',
			   'province_state'=>'2:95',
			   'country_code'=>'2:100',
			   'country'=>'2:101',
			   'original_transmission_reference'=>'2:103',
			   'headline'=>'2:105',
			   'credit'=>'2:110',
			   'source'=>'2:115',
			   'copyright_string'=>'2:116',
			   'caption'=>'2:120',
			   'local_caption'=>'2:121',
			   );

  function __construct($file=null){
    \Seolan\Core\System::loadVendor('pjmt/JPEG.php');
    \Seolan\Core\System::loadVendor('pjmt/XMP.php');
    \Seolan\Core\System::loadVendor('pjmt/IPTC.php');
    \Seolan\Core\System::loadVendor('pjmt/Photoshop_IRB.php');

    $this->file=$file;
    $this->header=get_jpeg_header_data($file);
    if (file_exists($file.'_ld')) {
      $this->file_ld = $file.'_ld';
      $this->header_ld = get_jpeg_header_data($this->file_ld);
    }

    // Initialise XMP
    $default="<?xpacket begin='\xef\xbb\xbf' id='W5M0MpCehiHzreSzNTczkc9d'?>\n".
      '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="XMP Core 4.1.1">'.
      '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>'.
      '</x:xmpmeta>'.
      str_repeat("                                                                                                   \n", 30).
      '<?xpacket end="w"?>';
    $xmp=get_XMP_text($this->header);
    if(empty($xmp)) $xmp=$default;
    try{
      $sxe=new \SimpleXMLElement($xmp);
    }catch(\Exception $e){
      $xmp=$default;
      $sxe=new \SimpleXMLElement($xmp);
    }
    $this->xmp_dom=new \DOMDocument();
    $this->xmp_dom->preserveWhiteSpace=false;
    $this->xmp_dom->validateOnParse = true;
    $this->xmp_dom->loadXML(trim($xmp));
    $this->xmp_dom->normalizeDocument();
    $this->xmp_xpath=new \DOMXpath($this->xmp_dom);
    $this->xmp_nss=array_merge($this->xmp_nss,$sxe->getNamespaces(true));
    foreach($this->xmp_nss as $p=>$u){
      $this->xmp_xpath->registerNamespace($p,$u);
    }

    // Initialise IPTC
    $this->iptc_ps_irb=get_Photoshop_IRB($this->header);
    $this->iptc=get_Photoshop_IPTC($this->iptc_ps_irb);
    foreach($this->iptc as $i=>&$tmp){
      $this->iptc_by_code[$tmp['IPTC_Type']]=$i;
      $tmp['RecData']=str_replace("\0",'',$tmp['RecData']);
    }
    $charset=$this->getIPTCProperty('encode');
    if($charset!="\x1B\x25\x47"){
      foreach($this->iptc as $i=>&$tmp){
	convert_charset($tmp['RecData'],'ISO-8859-15','UTF-8');
      }
    }
  }

  /// Recupere une propriété XMP
  function getXMPProperty($name,$opt=array()){
    $bal=$this->xmp_xpath->query('//'.$name);
    if(empty($bal)) return $this->getXMPPropertryByDescriptionAttribute($name);
    $bal=$bal->item(0);
    if(empty($bal)) return $this->getXMPPropertryByDescriptionAttribute($name);
    if(!$bal->hasChildNodes()) return '';
    $fc=$bal->firstChild;
    if($fc->nodeType==XML_TEXT_NODE) return (object)array('type'=>'value','raw'=>$bal->textContent,'text'=>$bal->textContent,'node'=>&$bal);
    switch($fc->nodeName){
    case 'rdf:Bag':
      $ret=array();
      foreach($fc->childNodes as $node){
	$ret[]=$node->textContent;
      }
      return (object)array('type'=>'bag','raw'=>$ret,'text'=>implode(',',$ret),'node'=>&$bal);
      break;
    case 'rdf:Seq':
      $ret=array();
      $text=array();
      foreach($fc->childNodes as $node){
	if($node->firstChild->nodeType==XML_TEXT_NODE){
	  $ret[]=$node->textContent;
	  $text[]=$node->textContent;
	}else{
	  $foo=array();
	  $o=(object)array();
	  foreach($node->childNodes as $node2){
	    $name=$node2->nodeName;
	    $o->$name=$node2->textContent;
	    $foo[]=$name.':'.$node2->textContent;
	  }
	  $ret[]=$o;
	  $text[]=implode(',',$foo);
	}
      }
      $text=implode("\r\n",$text);
      return (object)array('type'=>'seq','raw'=>$ret,'text'=>$text,'node'=>&$bal);
      break;
    case 'rdf:Alt':
      $ret=array();
      $text=array();
      foreach($fc->childNodes as $node){
	$lg=strtolower($node->getAttribute('xml:lang'));
	$ret[$lg]=$node->textContent;
	$text[$lg]=$lg.':'.$node->textContent;
      }
      // On demande le retour d'une langue en particulier
      if(!empty($opt['Alt']['lang'])){
	if(!is_array($opt['Alt']['lang'])) $opt['Alt']['lang']=array($opt['Alt']['lang']);
	foreach($opt['Alt']['lang'] as $l){
	  $l=strtolower($l);
	  if(empty($text[$l])) continue;
	  $text=str_replace($l.':','',$text[$l]);
	  break;
	}
	if(is_array($text)) $text='';
      }else{
	$text=implode("\r\n",$text);
      }
      return (object)array('type'=>'alt','raw'=>$ret,'text'=>$text,'node'=>&$bal);
      break;
    default:
      return (object)array('type'=>'','raw'=>'','text'=>'','node'=>NULL);
      break;
    }
  }

  /// Recupere une propriété via les attributs des balises description
  function getXMPPropertryByDescriptionAttribute($name){
    $descrs=$this->xmp_xpath->query('//'.$this->xmp_descr_node);
    foreach($descrs as $i=>$node){
      if($node->hasAttribute($name)){
	$v=$node->getAttribute($name);
	return (object)array('type'=>'attr','raw'=>$v,'text'=>$v,'node'=>$node);
      }
    }
    return NULL;
  }

  /// Ajoute/modifie une propriété XMP
  function setXMPProperty($name,$value,$type=''){
    list($prefix,$balise)=explode(':',$name);
    $p=$this->getXMPProperty($name);
    if(empty($type)){
      if(!empty($this->xmp_default_types[$name])) $type=$this->xmp_default_types[$name];
      else $type=$this->getXMPValueType($value);
    }
    if(empty($type)) return false;
    $f='createXMP'.$type.'Node';
    if(empty($p)){
      $descr=$this->getXMPDescriptionFromPrefix($prefix);
      if(empty($descr)) $descr=$this->addXMPDescription($prefix,$prefix);
      return $this->$f($descr,$name,$value);
    }elseif(strtolower($type)==strtolower($p->type)){
      $descr=$p->node->parentNode;
      $descr->removeChild($p->node);
      return $this->$f($descr,$name,$value);
    }elseif(strtolower($type)=='bag' && strtolower($p->type)=='seq'){
      $descr=$p->node->parentNode;
      $descr->removeChild($p->node);
      return $this->createXMPSeqNode($descr,$name,$value);
    }elseif(strtolower($type)=='value' && strtolower($p->type)=='attr'){
      $p->node->setAttribute($name,$value);
    }else{
      return false;
    }
  }

  /// Recupere le type xmp d'une valeur
  function getXMPValueType(&$value){
    if(is_string($value)) return 'Value';
    elseif(is_array($value)){
      if(array_key_exists(0,$value)) return 'Bag';
      else{
	$first=reset($value);
	if(is_array($first)) return 'Seq';
	else return 'Alt';
      }
    }
    return false;
  }

  /// Ajoute une balise description definissant un ns donné
  function addXMPDescription($prefix,$nurl){
    if(empty($this->xmp_nss[$prefix])){
      $this->xmp_xpath->registerNamespace($prefix,$nurl);
      $this->xmp_nss[$prefix]=$nurl;
    }
    $nurl=$this->xmp_nss[$prefix];
    $rdf=$this->xmp_xpath->query($this->xmp_descr_path)->item(0);
    $descr=$this->xmp_dom->createElementNS($this->xmp_nss['rdf'],$this->xmp_descr_node);
    $descr->setAttributeNS($this->xmp_nss['rdf'],'rdf:about','');
    $descr->setAttributeNS($this->xmp_nss['xmlns'],'xmlns:'.$prefix,$nurl);
    if ($rdf)
      $rdf->appendChild($descr);
    return $descr;
  }

  /// Retourne la balise description qui defini un ns donné
  function getXMPDescriptionFromPrefix($prefix){
    $descrs=$this->xmp_xpath->query($this->xmp_descr_path.'/'.$this->xmp_descr_node);
    foreach($descrs as $i=>$descr){
      if($descr->lookupNamespaceURI($prefix)) return $descr;
    }
    return NULL;
  }

  /// Créé une propriété de type texte
  function createXMPValueNode($parent,$name,$value){
    $node=$this->xmp_dom->createElement($name);
    $node->nodeValue=$value;
    $parent->appendChild($node);
    return $node;
  }

  /// Créé une propriété de type bag
  function createXMPBagNode($parent,$name,$value){
    $node=$this->xmp_dom->createElement($name);
    $bag=$this->xmp_dom->createElement('rdf:Bag');
    if(!is_array($value)) $value=array($value);
    foreach($value as $v){
      $b=$this->xmp_dom->createElement('rdf:li');
      $b->nodeValue=$v;
      $bag->appendChild($b);
    }
    $node->appendChild($bag);
    $parent->appendChild($node);
    return $node;
  }

  /// Créé une propriété de type seq
  function createXMPSeqNode($parent,$name,$value){
    $node=$this->xmp_dom->createElement($name);
    $seq=$this->xmp_dom->createElement('rdf:Seq');
    if(!is_array($value)) $value=array($value);
    foreach($value as $n=>$v){
      $b=$this->xmp_dom->createElement('rdf:li');
      if(is_array($v)){
	$d=$this->xmp_dom->createElement($this->xmp_descr_node);
	$ob=$this->xmp_dom->createElement('rdf:value');
	$ob->nodeValue=$n;
	foreach($v as $qn=>$qv){
	  list($qp)=explode(':',$qn);
	  if(!$parent->hasAttribute('xmlns:'.$qp)){
	    $this->xmp_xpath->registerNamespace($qp,$qp);
	    $parent->setAttribute('xmlns:'.$qp,$qp);
	  }
	  $qb=$this->xmp_dom->createElement($qn);
	  $qb->nodeValue=$qv;
	  $ob->appendChild($qb);
	}
	$d->appendChild($ob);
	$b->appendChild($d);
      }else{
	$b->nodeValue=$v;
      }
      $seq->appendChild($b);
    }
    $node->appendChild($seq);
    $parent->appendChild($node);
    return $node;
  }

  /// Créé une propriété de type bag
  function createXMPAltNode($parent,$name,$value){
    $node=$this->xmp_dom->createElement($name);
    $alt=$this->xmp_dom->createElement('rdf:Alt');
    if(!is_array($value)) $value=array('x-default'=>$value);
    foreach($value as $l=>$v){
      $b=$this->xmp_dom->createElement('rdf:li');
      $b->setAttribute('xml:lang',$l);
      $b->nodeValue=$v;
      $alt->appendChild($b);
    }
    $node->appendChild($alt);
    $parent->appendChild($node);
    return $node;
  }

  /// Recupere une propriété IPTC
  function getIPTCProperty($code){
    if(!empty($this->iptc_names[$code])) $code=$this->iptc_names[$code];
    return (object)array('raw'=>$this->iptc[$this->iptc_by_code[$code]]['RecData']);
  }

  /// Recupere toutes les propriétés IPTC
  function getIPTCAll(){
    return $this->iptc;
  }

  /// Ajoute/modifie une propriété IPTC
  function setIPTCProperty($code,$value){
    if (is_array($value))
      $value = implode(',', $value);
    if(!empty($this->iptc_names[$code])) $code=$this->iptc_names[$code];

    if(!empty($this->iptc[$this->iptc_by_code[$code]]['RecData'])) $this->iptc[$this->iptc_by_code[$code]]['RecData']=$value;
    else $this->iptc[]=array('IPTC_Type'=>$code,'RecData'=>$value);

    return $value;
  }

  // Sauve tous les metas
  function save($file=null){
    if(!$file) $file=$this->file;

    $xml=$this->xmp_dom->saveXML($this->xmp_dom->documentElement);
    $this->header=put_XMP_text($this->header,$xml);
    if ($this->file_ld)
      $this->header_ld = put_XMP_text($this->header_ld,$xml);

    // Indique que les tags sont en utf8
    $this->setIPTCProperty('encode',"\x1B\x25\x47");
    usort($this->iptc,function($a,$b){
      if($a['IPTC_Type']==$b['IPTC_Type']) return 0;
      elseif($a['IPTC_Type']>$b['IPTC_Type']) return 1;
      else return -1;
    });
    $this->iptc_ps_irb=put_Photoshop_IPTC($this->iptc_ps_irb,$this->iptc);
    $this->header=put_Photoshop_IRB($this->header,$this->iptc_ps_irb);

    $ret = put_jpeg_header_data($this->file,$file,$this->header);

    if ($this->file_ld) {
      $this->header_ld = put_Photoshop_IRB($this->header_ld,$xml);
      put_jpeg_header_data($this->file_ld, $this->file_ld, $this->header_ld);
    }
    
    return $ret;
  }
}
?>
