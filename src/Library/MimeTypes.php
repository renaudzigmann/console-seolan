<?php
namespace Seolan\Library;
// +----------------------------------------------------------------------+
// | MIME Types Class                                                     |
// +----------------------------------------------------------------------+

/**
* MIME Types class
*
* This class allows you to:
*   - Retrieve the appropriate MIME type of a file (based on it's extension, or by utilising
*     the file command to guess it based on the file contents).
*   - Retrieve extension(s) associated with a MIME type.
*   - Load MIME types and extensions from a mime.types file.
*
* Example:
    $mimeClasse = \Seolan\Library\MimeTypes::getInstance();
    echo "\n extension : ".$mimeClasse->get_extension($upload_type);
    echo "\n encoding : ".$mimeClasse->get_encoding($upload_filename);
    $result = @exec("/usr/bin/file -i -b $upload_filename");
    echo "\n<br>From file cmd :".$result;
    echo  "\n<br>From mime class :".$mimeClasse->getValidMime($upload_type,$upload_filename,$upload_name);
    $ime =& new Mime_Types('/usr/local/apache/conf/mime.types');
    echo $mime->get_type('pdf');    // application/pdf
    echo $mime->get_extension('text/vnd.wap.wmlscript');      // wmls
*/

class MimeTypes{
  // Types mime connus (s'ajoutent aux mime contenu dans le fichier chargé)
  public $mime_types = ['pjpeg' => 'image/pjpeg'
			// au 2018-06 non reconnus (car pas d'extension fournie) par defaut et ne doivent pas être vus comme des image (image/vnd.dwg) donc ajout. Les fichiers chargés sans extensions ne seront pas reconnus
			,'dwg'=>'application/dwg'
			,'ifc'=>'application/x-step'
			,'icon'=>'image/x-icon'
                        ,'bin-x' => 'application/x-octet-stream'
			,'sldprt' => 'application/sldworks'
			,'sldasm' => 'application/sldworks'
			,'sldprt' => 'application/sldworks',
			]; 
  // Types mime invalides
  public $invalid_mime_types = array(
    'bin' => 'application/octet-stream'
  )  ;
  // Commande exécutée pour récupérer le type mime d'un fichier
  private $file_cmd = TZR_FILE_CMD;
  // Fichier contenant la liste des types mime
  public $mimefile = TZR_MIME_FILE;
  
  /**
   * File options, used with the file command
   * Example:
   *   ['i'] => null // this option asks file to produce a MIME type if it can
   *   ['b'] => null // this option tells file to be brief
   * will result in 'file -i -b test_file'
   * @var array
   */
  private $file_options = array('b'=>null, 'i'=>null);

  //this is a singleton
  static $_MyInstance;  
  private function __construct(){
    //load our mime.type
    $this->load_file($this->mimefile);
    if (isset($GLOBALS['TZR_INVALID_MIME_TYPES']))
      $this->invalid_mime_types = $GLOBALS['TZR_INVALID_MIME_TYPES'];
  }
  static function getInstance() {
    if (empty(self::$_MyInstance)) {
      self::$_MyInstance = new MimeTypes;  
    }
    return self::$_MyInstance;
  }
  /**
   * AddMime
   * optional parameter can be either a string containing the path to the
   * mime.types files, or an associative array holding the extension
   * as key and the MIME type as value.
   * Example:
   *   $mime =& self::addMime('/usr/local/apache/conf/mime.types');
   *  or
   *   $mime =& self::addMime(array(
   *        'application/pdf'        => 'pdf', 
   *        'application/postscript' => array('ai','eps')
   *        ));
   * @param mixed $mime_types
  */
  public  function addMime($mime_types=null)
  {
    if (is_string($mime_types)) {
      $this->load_file($mime_types);
    } elseif (is_array($mime_types)) {
      $this->set($mime_types);
    }
  }
  
  /**
   * Scan - goes through all MIME types passing the extension and type to the callback function.
   * The types will be sent in alphabetical order.
   * If a type has multiple extensions, each extension will be passed seperately (not as an array).
   *
   * The callback function can be a method from another object (eg. array(&$my_obj, 'my_method')).
   * The callback function should accept 3 arguments:
   *  1- A reference to the Mime_Types object (&$mime)
   *  2- An array holding extension and type, array keys: 
   *     [0]=>ext, [1]=>type
   *  3- An optional parameter which can be used for whatever your function wants :),
   *     even though you might not have a use for this parameter, you need to define
   *     your function to accept it.  (Note: you can have this parameter be passed by reference)
   * The callback function should return a boolean, a value of 'true' will tell scan() you want
   * it to continue with the rest of the types, 'false' will tell scan() to stop calling
   * your callback function.
   *
   * @param mixed $callback function name, or array holding an object and the method to call.
   * @param mixed $param passed as the 3rd argument to $callback
   */
  public  function scan($callback, &$param)
  {
    if (is_array($callback)) $method =& $callback[1];
    $mime_types = $this->mime_types;
    asort($mime_types);
    foreach ($mime_types as $ext => $type) {
      $ext_type = array($ext, $type);
      if (isset($method)) {
	$res = $callback[0]->$method($this, $ext_type, $param);
      } else {
	$res = $callback($this, $ext_type, $param);
      }
      if (!$res) return;
    }
  }

  //return true if the mime type given is an image type
  //
  public function isImage($type){
    if($this->isValidMime($type) && strstr($type,'image') ){
      return true;
    }else return false;
  } 
  //return true if the given type is a video
  public function isVideo($type){
    if($this->isValidMime($type) && strstr($type,'video')){
      return true;
    }else return false;
  }  
  //return true if the given type is an audio type
  public function isAudio($type){
    if($this->isValidMime($type) && strstr($type,'audio')){
      return true;
    }else return false;
  }

  //Return true if the given type respect the accepted string
  public function isAccepted($type,$accept){
    $accept = trim($accept);
    $accepted = explode(',',$accept);
    if(!empty($accept) && $accept != '*' && $accept != '.*' &&  is_array($accepted)) {
      $cnt=count($accepted);
      $i=0;
      while($i<$cnt) {
	if(preg_match('|'.$accepted[$i].'|',$type)) return true;
	else $i++;
      }
      return false;
    }
    return true;
  }

  /**
   * Get file type - returns MIME type by trying to guess it using the file command. 
   * Optional second parameter should be a boolean.  If true (default), get_file_type() will
   * try to guess the MIME type based on the file extension if the file command fails to find
   * a match.
   * Example:
   *   echo $mime->get_file_type('/path/to/my_file', false);
   *  or
   *   echo $mime->get_file_type('/path/to/my_file.gif');
   * @param string $file
   * @param bool $use_ext default: true
   * @return string false if unable to find suitable match
   */
  public function get_file_type($file, $use_ext=true)
  {
    $file = trim($file);
    if ($file == '') return false;
    $type = false;
    $result = false;
    if ($this->file_cmd && is_readable($file) && is_executable($this->file_cmd)) {
      $cmd = $this->file_cmd;
      foreach ($this->file_options as $option_key => $option_val) {
	$cmd .= ' -'.$option_key;
	if (isset($option_val)) $cmd .= ' '.escapeshellarg($option_val);
      }
      $cmd .= ' '.escapeshellarg($file);
      $result = @exec($cmd);
      if ($result) {
	$result = strtolower($result);
	$pattern = '[a-z0-9.+_-]';
	if (preg_match('!(('.$pattern.'+)/'.$pattern.'+)!', $result, $match)) {
	  if (in_array($match[2], array('application','audio','image','message',
					'multipart','text','video','chemical','model')) ||
	      (substr($match[2], 0, 2) == 'x-')) {
	    $type = $match[1];
	  }
	}
      }
    }
    // try and get type from extension
    if ((!$type || $type=='application/octet-stream') && $use_ext && strpos($file, '.')) $type = $this->get_type($file);
   
    // this should be some sort of attempt to match keywords in the file command output
    // to a MIME type, I'm not actually sure if this is a good idea, but for now, it tries
    // to find an 'ascii' string.
    if (!$type && $result && preg_match('/\bascii\b/', $result)) $type = 'text/plain';
    return $type;
  }
  public  function get_encoding($file)
  {
      $cmd = $this->file_cmd;
      $cmd .= ' -i -b '.$file;
      $result = @shell_exec($cmd);
      if ($result) {
	$result = strtolower($result);
	$pattern = '[a-z0-9.+_-]';
	if (preg_match('!(('.$pattern.'+)/'.$pattern.'+); charset=('.$pattern.'+)?!', $result, $match)) {
	  return $match[3];
	}
      }
      return false;
  }
  /**
   * Get type - returns MIME type based on the file extension.
   * Example:
   *   echo $mime->get_type('txt');
   *  or
   *   echo $mime->get_type('test_file.txt');
   * both examples above will return the same result.
   * @param string $ext
   * @return string false if extension not found
   */
  function get_type($ext)
  {
    $ext = strtolower($ext);
    // get position of last dot
    $dot_pos = strrpos($ext, '.');
    if ($dot_pos !== false) $ext = substr($ext, $dot_pos+1);
    if (($ext != '') && isset($this->mime_types[$ext])) return $this->mime_types[$ext];
    return false;
  }
  
  /**
   * Set - set extension and MIME type
   * Example:
   *   $mime->set('text/plain', 'txt');
   *  or
   *   $mime->set('text/html', array('html','htm'));
   *  or
   *   $mime->set('text/html', 'html htm');
   *  or
   *   $mime->set(array(
   *        'application/pdf'        => 'oda', 
   *        'application/postscript' => array('ai','eps')
   *        ));
   * @param mixed $type either array containing type and extensions, or the type as string
   * @param mixed $exts either array holding extensions, or string holding extensions
   * seperated by space.
   * @return void
  */
  function set($type, $exts=null)
  {
    if (!isset($exts)) {
      if (is_array($type)) {
	foreach ($type as $mime_type => $exts) {
	  $this->set($mime_type, $exts);
	}
      }
      return;
    }
    if (!is_string($type)) return;
    // get rid of any parameters which might be included with the MIME type
    // e.g. text/plain; charset=iso-8859-1
    $type = strtr(strtolower(trim($type)), ",;\t\r\n", '     ');
    if ($sp_pos = strpos($type, ' ')) $type = substr($type, 0, $sp_pos);
    // not bothering with an extensive check of the MIME type, just checking for slash
    if (!strpos($type, '/')) return;
    // loop through extensions
    if (!is_array($exts)) $exts = explode(' ', $exts);
    foreach ($exts as $ext) {
      $ext = trim(str_replace('.', '', $ext));
      if ($ext == '') continue;
      $this->mime_types[strtolower($ext)] = $type;
    }
  }
  
  /**
   * Has extension - returns true if extension $ext exists, false otherwise
   * Example:
   *   if ($mime->has_extension('pdf')) echo 'Got it!';
   * @param string $ext
   * @return bool
  */
  public function has_extension($ext)
  {
    return (isset($this->mime_types[strtolower($ext)]));
  }
  
  /**
   * Has type - returns true if type $type exists, false otherwise
   * Example:
   *   if ($mime->has_type('image/gif')) echo 'Got it!';
   * @param string $type
   * @return bool
  */
  public function has_type($type)
  {
    return (in_array(strtolower($type), $this->mime_types));
  }
  
  //return tru if mime is acceptable
  public function isValidMime($mime){
    return ($mime<>'') && $this->has_type($mime) && !in_array( strtolower($mime),$this->invalid_mime_types );
  }
  //return a valide mime
  //
  public function getValidMime($mime,$fn,$originalName='')
  {
    if(!$this->isValidMime($mime)){
      $type = $this->get_file_type($fn);
      if(!$this->isValidMime($type)){
	return $this->get_type($originalName);
      }else return $type;
    }else return $mime;
  }
  
  /**
   * Get extension - returns string containing a extension associated with $type
   * Example:
   *   $ext = $mime->get_extension('application/postscript');
   *   if ($ext) echo $ext;
   * @param string $type
   * @return string false if $type not found
   */
  public function get_extension($type,$all=false)
  {
    $type = strtolower($type);
    $ret = array();
    foreach ($this->mime_types as $ext => $m_type) {
      if ($m_type == $type){
        $ret[] = $ext;
        if(!$all) break;
      }
    }
    if($ret) return implode(',', $ret);
    return false;
  }
  
  /**
   * Get extensions - returns array containing extension(s)
   * Example:
   *   $exts = $mime->get_extensions('application/postscript');
   *   echo implode(', ', $exts);
   * @param string $type
   * @return array
  */
  public function get_extensions($type)
  {
    $type = strtolower($type);
    return (array_keys($this->mime_types, $type));
  }
  
  /**
   * Remove extension
   * Example:
   *   $mime->remove_extension('txt');
   *  or
   *   $mime->remove_extension('txt exe html');
   *  or
   *   $mime->remove_extension(array('txt', 'exe', 'html'));
   * @param mixed $exts string holding extension(s) seperated by space, or array
   * @return void
   */
  public function remove_extension($exts)
  {
    if (!is_array($exts)) $exts = explode(' ', $exts);
    foreach ($exts as $ext) {
      $ext = strtolower(trim($ext));
      if (isset($this->mime_types[$ext])) unset($this->mime_types[$ext]);
    }
  }
  
  /**
   * Remove type
   * Example:
   *   $mime->remove_type('text/plain');
   *  or
   *   $mime->remove_type('image/*');
   *   // removes all image types
   *  or
   *   $mime->remove_type();
   *   // clears all types
   * @param string $type if omitted, all types will be removed
   * @return void
   */
  public function remove_type($type=null)
  {
    if (!isset($type)) {
      $this->mime_types = array();
      return;
    }
    $slash_pos = strpos($type, '/');
    if (!$slash_pos) return;
    
    $type_info = array('last_match'=>false, 'wildcard'=>false, 'type'=>$type);
    if (substr($type, $slash_pos) == '/*') {
      $type_info['wildcard'] = true;
      $type_info['type'] = substr($type, 0, $slash_pos);
    }
    $this->scan(array(&$this, '_remove_type_callback'), $type_info);
  }

  /**
   * Load file - load file containing mime types.
   * Example:
   *   $result = $mime->load_file('/usr/local/apache/conf/mime.types');
   *   echo (($result) ? 'Success!' : 'Failed');
   * @param string $file
   * @return bool
   */
  public function load_file($file)
  {
    if (!file_exists($file) || !is_readable($file)) return false;
    $data = file($file);
    foreach ($data as $line) {
      $line = trim($line);
      if (($line == '') || ($line == '#')) continue;
      $line = preg_split('/\s+/', $line, 2);
      if (count($line) < 2) continue;
      $exts = $line[1];
      // if there's a comment on this line, remove it
      $hash_pos = strpos($exts, '#');
      if ($hash_pos !== false) $exts = substr($exts, 0, $hash_pos);
      $this->set($line[0], $exts);
    }
    return true;
  }
  
  //
  // private methods
  //
  
  /**
   * Remove type callback
   * @param object $mime
   * @param array $ext_type
   * @param array $type_info
   * @return bool
   * @access private
   */
  private function _remove_type_callback(&$mime, $ext_type, $type_info)
  {
    // temporarily we'll put match to false
    $matched = false;
    list($ext, $type) = $ext_type;
    if ($type_info['wildcard']) {
      if (substr($type, 0, strpos($type, '/')) == $type_info['type']) {
	$matched = true;
      }
    } elseif ($type == $type_info['type']) {
      $matched = true;
    }
    if ($matched) {
      $this->remove_extension($ext);
      $type_info['last_match'] = true;
    } elseif ($type_info['last_match']) {
      // we do not need to continue if the previous type matched, but this type didn't.
      // because all types are sorted in alphabetical order, we can be sure there will be
      // no further successful matches.
      return false;
    }
    return true;
  }        
}
?>
