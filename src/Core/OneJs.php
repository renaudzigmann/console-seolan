<?php
namespace Seolan\Core;

class OneJs{

  protected $input;
  protected $index = 0;
  protected $a = '';
  protected $b = '';
  protected $c;
  protected $options;
  protected static $defaultOptions = array( 'flaggedComments' => true );
  protected $locks = array();

  public static function prepareHeader( $contentLength, $filename ){
    session_cache_limiter('public');
    
    header( 'Content-Type: application/javascript' );

    $file_date = filectime($filename);
    $mtime = gmdate('D, d M Y H:i:s', $file_date). ' GMT';
    header('Last-Modified: ' . $mtime);
    header( 'Expires: ' . gmdate('D, d M Y H:i:s', time()+24*60*60) . ' GMT');
    header( 'Accept-Ranges: bytes' );
    header( 'Content-Length: '.$contentLength );
  }
  
  public static function stackJs( $files, $version, $cachedir, $scriptdir, $options = array() ){
    $stackedJs='';
    $existingFiles = self::checkIfFilesExists($scriptdir,$files);
    $hash = md5(http_build_query($existingFiles) . $version . @$options['minify']);
    $filename = 'file'.$hash.'.js';
    $dirname =  $cachedir.'js/onejs/';

    if( $options['forceall'] && $options['forceall'] == true ){
      self::deleteFromCacheFolder( $dirname, 0 );
    }
    if( $options['cache'] && $options['cache'] == true ){
      $cachedContent = self::importFromCachedFile( $dirname,$filename );
      if($cachedContent!=false){
        $stackedJs = $cachedContent; 
      }
    }
    
    if( strlen($stackedJs) == 0 ){
      $tempjs = self::parseFile(array_keys($existingFiles), $scriptdir);
      if( $options['minify'] && $options['minify'] == true ){
        $tempjs = self::minify($tempjs);
      }
      $stackedJs = $tempjs;
      
      self::save( $dirname, $filename, $stackedJs );
    }
    
    $contentLength = strlen( $stackedJs );
    
    self::prepareHeader( $contentLength, $dirname.$filename);

    return $stackedJs;
  }

  private static function checkIfFilesExists( $dirname, $files ) {
    $existingFiles = array();
    foreach( $files as $file ) {
      if ($mtime = @filemtime($dirname . $file)) {
        $existingFiles[$file] = $mtime;
      }
    }
    return $existingFiles;
  }
  
  private static function importFromCachedFile( $dirname,$filename ){
    
    if( file_exists( $dirname.$filename ) ){
      $fromcache = true;
      $file = file_get_contents( $dirname.$filename );
      return $file;
    }
    return false;
  }

  private static function deleteFromCacheFolder( $dirname, $day = 7 ){
    \Seolan\Library\Dir::clean($dirname, 60 * 60 * 24 * $day);
  }
  
  private static function parseFile( $files,$dir ){
    $parsedJs = '';
    $endpattern = '';
    
    foreach($files as $file) {
    
      if( !preg_match( '/(\.js)$/',$file ) ){
        continue;
      }
      
      $file=preg_replace( '/([^a-z0-9_\/\.-]+)/i','',$file );
      $file=str_replace('..','',$file);

      $parsedJs .= '/*'.$file."*/;\n";
      $js=file_get_contents( $dir.'/'.$file );
      $parsedJs .= $js.";\n";
    }
    return $parsedJs;
  }
  
  private static function save($dirname, $filename, $content  ) {
    if( !file_exists( $dirname.$filename ) ) {
      if( !is_dir($dirname) && !file_exists( $dirname ) ){
        mkdir( $dirname, 0755, true );
      }
      file_put_contents ( $dirname.$filename, $content );
      return true;
    }
  }
  
  
  private static function minify( $js, $options = []) {
    try {
      ob_start();
      
      $shrink = new \Seolan\Core\OneJs();
      $js = $shrink->lock($js);
      $shrink->minifyToOutput( $js, $options );
      
      $js = ltrim(ob_get_clean());
      $js = $shrink->unlock( $js );
      
      unset($shrink);
      
      return $js;
    }catch( \Exception $e ) {
    
      if (isset( $shrink )) {
        $shrink->clean();
        unset( $shrink );
      }
      
      ob_end_clean();
      throw $e;
    }
  }

  protected function minifyToOutput ( $js, $options ) {
    $this->initialize( $js, $options );
    $this->loop();
    $this->clean();
  }

  protected function initialize( $js, $options )
  {
    $this->options = array_merge( static::$defaultOptions, $options );
    $js = str_replace("\r\n", "\n", $js);
    $js = str_replace('/**/', '', $js);
    $this->input = str_replace("\r", "\n", $js);
    $this->input .= PHP_EOL;
    $this->a = "\n";
    $this->b = $this->getReal();
  }

  protected function loop()
  {
    while ($this->a !== false && !is_null($this->a) && $this->a !== '') {
      switch ($this->a) {
        // new lines
        case "\n":
          // if the next line is something that can't stand alone preserve the newline
          if (strpos('(-+{[@', $this->b) !== false) {
            echo $this->a;
            $this->saveString();
            break;
          }

          // if B is a space we skip the rest of the switch block and go down to the
          // string/regex check below, resetting $this->b with getReal
          if($this->b === ' ')
            break;

        // otherwise we treat the newline like a space

        case ' ':
          if(static::isAlphaNumeric($this->b))
            echo $this->a;

          $this->saveString();
          break;

        default:
          switch ($this->b) {
            case "\n":
              if (strpos('}])+-"\'', $this->a) !== false) {
                echo $this->a;
                $this->saveString();
                break;
              } else {
                if (static::isAlphaNumeric($this->a)) {
                  echo $this->a;
                  $this->saveString();
                }
              }
              break;

            case ' ':
              if(!static::isAlphaNumeric($this->a))
                break;

            default:
              // check for some regex that breaks stuff
              if ($this->a === '/' && ($this->b === '\'' || $this->b === '"')) {
                $this->saveRegex();
                break;
              }

              echo $this->a;
              $this->saveString();
              break;
          }
      }

      // do reg check of doom
      $this->b = $this->getReal();

      if(($this->b == '/' && strpos('(,=:[!&|?', $this->a) !== false))
        $this->saveRegex();
    }
  }
  
  protected function getChar()
  {
    // Check to see if we had anything in the look ahead buffer and use that.
    if (isset($this->c)) {
      $char = $this->c;
      unset($this->c);

    // Otherwise we start pulling from the input.
    } else {
      $char = substr($this->input, $this->index, 1);

      // If the next character doesn't exist return false.
      if (isset($char) && $char === false) {
        return false;
      }

      // Otherwise increment the pointer and use this char.
      $this->index++;
    }

    // Normalize all whitespace except for the newline character into a
    // standard space.
    if($char !== "\n" && ord($char) < 32)

      return ' ';

    return $char;
  }

  protected function getReal()
  {
    $startIndex = $this->index;
    $char = $this->getChar();

    // Check to see if we're potentially in a comment
    if ($char !== '/') {
      return $char;
    }

    $this->c = $this->getChar();

    if ($this->c === '/') {
      return $this->processOneLineComments($startIndex);

    } elseif ($this->c === '*') {
      return $this->processMultiLineComments($startIndex);
    }

    return $char;
  }

  protected function processOneLineComments($startIndex)
  {
    $thirdCommentString = substr($this->input, $this->index, 1);

    // kill rest of line
    $this->getNext("\n");

    if ($thirdCommentString == '@') {
      $endPoint = $this->index - $startIndex;
      unset($this->c);
      $char = "\n" . substr($this->input, $startIndex, $endPoint);
    } else {
      // first one is contents of $this->c
      $this->getChar();
      $char = $this->getChar();
    }

    return $char;
  }

  protected function processMultiLineComments($startIndex)
  {
    $this->getChar(); // current C
    $thirdCommentString = $this->getChar();

    // kill everything up to the next */ if it's there
    if ($this->getNext('*/')) {

      $this->getChar(); // get *
      $this->getChar(); // get /
      $char = $this->getChar(); // get next real character

      // Now we reinsert conditional comments and YUI-style licensing comments
      if (($this->options['flaggedComments'] && $thirdCommentString === '!')
        || ($thirdCommentString === '@') ) {

        // If conditional comments or flagged comments are not the first thing in the script
        // we need to echo a and fill it with a space before moving on.
        if ($startIndex > 0) {
          echo $this->a;
          $this->a = " ";

          // If the comment started on a new line we let it stay on the new line
          if ($this->input[($startIndex - 1)] === "\n") {
            echo "\n";
          }
        }

        $endPoint = ($this->index - 1) - $startIndex;
        echo substr($this->input, $startIndex, $endPoint);

        return $char;
      }

    } else {
      $char = false;
    }

    if($char === false)
      throw new \RuntimeException('Unclosed multiline comment at position: ' . ($this->index - 2));

    // if we're here c is part of the comment and therefore tossed
    if(isset($this->c))
      unset($this->c);

    return $char;
  }

  protected function getNext($string)
  {
    // Find the next occurrence of "string" after the current position.
    $pos = strpos($this->input, $string, $this->index);

    // If it's not there return false.
    if($pos === false)

      return false;

    // Adjust position of index to jump ahead to the asked for string
    $this->index = $pos;

    // Return the first character of that string.
    return substr($this->input, $this->index, 1);
  }

  protected function saveString()
  {
    $startpos = $this->index;
    // saveString is always called after a gets cleared, so we push b into
    // that spot.
    $this->a = $this->b;
    // If this isn't a string we don't need to do anything.
    if ($this->a !== "'" && $this->a !== '"') {
      return;
    }
    // String type is the quote used, " or '
    $stringType = $this->a;
    // Echo out that starting quote
    echo $this->a;
    // Loop until the string is done
    while (true) {
      // Grab the very next character and load it into a
      $this->a = $this->getChar();
      switch ($this->a) {
        // If the string opener (single or double quote) is used
        // output it and break out of the while loop-
        // The string is finished!
        case $stringType:
          break 2;
        // New lines in strings without line delimiters are bad- actual
        // new lines will be represented by the string \n and not the actual
        // character, so those will be treated just fine using the switch
        // block below.
        case "\n":
          throw new \RuntimeException('Unclosed string at position: ' . $startpos );
          break;
        // Escaped characters get picked up here. If it's an escaped new line it's not really needed
        case '\\':
          // a is a slash. We want to keep it, and the next character,
          // unless it's a new line. New lines as actual strings will be
          // preserved, but escaped new lines should be reduced.
          $this->b = $this->getChar();
          // If b is a new line we discard a and b and restart the loop.
          if ($this->b === "\n") {
            break;
          }
          echo $this->a . $this->b;
          break;
        default:
          echo $this->a;
      }
    }
  }

  protected function saveRegex()
  {
    echo $this->a . $this->b;

    while (($this->a = $this->getChar()) !== false) {
      if($this->a === '/')
        break;
      if ($this->a === '\\') {
        echo $this->a;
        $this->a = $this->getChar();
      }
      if($this->a === "\n")
        throw new \RuntimeException('Unclosed regex pattern at position: ' . $this->index);
      echo $this->a;
    }
    $this->b = $this->getReal();
  }

  protected static function isAlphaNumeric($char)
  {
    return preg_match('/^[\w\$]$/', $char) === 1 || $char == '/';
  }
  
  protected function lock($js)
  {
    $lock = '"LOCK---' . crc32(time()) . '"';
    $matches = array();
    preg_match('/([+-])(\s+)([+-])/S', $js, $matches);
    if (empty($matches)) {
        return $js;
    }
    $this->locks[$lock] = $matches[2];
    $js = preg_replace('/([+-])\s+([+-])/S', "$1{$lock}$2", $js);
    return $js;
  }

  protected function unlock($js)
  {
    if (empty($this->locks)) {
      return $js;
    }
    foreach ($this->locks as $lock => $replacement) {
      $js = str_replace($lock, $replacement, $js);
    }
    return $js;
  }
  
  protected function clean () {
    unset($this->input);
    $this->index = 0;
    $this->a = $this->b = '';
    unset($this->c);
    unset($this->options);
  }
  
}

