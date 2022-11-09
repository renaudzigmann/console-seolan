<?
/*

This code is released under the Simplified BSD License:

Copyright 2004 Razvan Florian. All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice, this list of
      conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright notice, this list
      of conditions and the following disclaimer in the documentation and/or other materials
      provided with the distribution.

THIS SOFTWARE IS PROVIDED BY Razvan Florian ''AS IS'' AND ANY EXPRESS OR IMPLIED
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL Razvan Florian OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those of the
authors and should not be interpreted as representing official policies, either expressed
or implied, of Razvan Florian.

http://www.coneural.org/florian/papers/04_byteserving.php

*/

function set_range($range, $filesize, &$first, &$last){
  /*
  Sets the first and last bytes of a range, given a range expressed as a string
  and the size of the file.

  If the end of the range is not specified, or the end of the range is greater
  than the length of the file, $last is set as the end of the file.

  If the begining of the range is not specified, the meaning of the value after
  the dash is "get the last n bytes of the file".

  If $first is greater than $last, the range is not satisfiable, and we should
  return a response with a status of 416 (Requested range not satisfiable).

  Examples:
  $range='0-499', $filesize=1000 => $first=0, $last=499 .
  $range='500-', $filesize=1000 => $first=500, $last=999 .
  $range='500-1200', $filesize=1000 => $first=500, $last=999 .
  $range='-200', $filesize=1000 => $first=800, $last=999 .

  */
  list($first,$last) = explode('-',$range);
  if ($first=='') {
    //suffix byte range: gets last n bytes
    $suffix = $last;
    $last = $filesize-1;
    $first = $filesize-$suffix;
    if ($first < 0)
      $first = 0;
  } else {
    if ($last=='' || $last > $filesize-1)
      $last = $filesize-1;
  }
  if($first>$last){
    //unsatisfiable range
    header("Status: 416 Requested range not satisfiable");
    header("Content-Range: */$filesize");
    exit;
  }
}

function buffered_read($file, $bytes, $buffer_size=1024){
  /*
  Outputs up to $bytes from the file $file to standard output, $buffer_size bytes at a time.
  */
  $bytes_left = $bytes;
  while ($bytes_left>0 && !feof($file)) {
    if($bytes_left>$buffer_size)
      $bytes_to_read=$buffer_size;
    else
      $bytes_to_read=$bytes_left;
    $bytes_left-=$bytes_to_read;
    $contents=fread($file, $bytes_to_read);
    echo $contents;
    flush();
  }
}

function byteserve($filename, $mime) {
  /*
  Byteserves the file $filename.

  When there is a request for a single range, the content is transmitted
  with a Content-Range header, and a Content-Length header showing the number
  of bytes actually transferred.

  When there is a request for multiple ranges, these are transmitted as a
  multipart message. The multipart media type used for this purpose is
  "multipart/byteranges".
  */

  $filesize = filesize($filename);

  $ranges=NULL;
  if ($_SERVER['REQUEST_METHOD']=='GET' && isset($_SERVER['HTTP_RANGE']) && $range=stristr(trim($_SERVER['HTTP_RANGE']),'bytes=')) {
    $range = substr($range,6);
    $boundary = uniqid();//set a random boundary
    $ranges = explode(',',$range);
  }

  if ($ranges && count($ranges)) {
    $file = fopen($filename, "rb");
    header("HTTP/1.1 206 Partial content");
    if(count($ranges)>1) {
      /*
      More than one range is requested.
      */

      //compute content length
      $content_length=0;
      foreach ($ranges as $range) {
        set_range($range, $filesize, $first, $last);
        $content_length+=strlen("\r\n--$boundary\r\n");
        $content_length+=strlen("Content-type: $mime\r\n");
        $content_length+=strlen("Content-range: bytes $first-$last/$filesize\r\n\r\n");
        $content_length+=$last-$first+1;
      }
      $content_length+=strlen("\r\n--$boundary--\r\n");

      //output headers
      header("Content-Length: $content_length");
      header("Content-Type: multipart/x-byteranges; boundary=$boundary");

      //output the content
      foreach ($ranges as $range) {
        set_range($range, $filesize, $first, $last);
        echo "\r\n--$boundary\r\n";
        echo "Content-type: $mime\r\n";
        echo "Content-range: bytes $first-$last/$filesize\r\n\r\n";
        fseek($file,$first);
        buffered_read ($file, $last-$first+1);
      }
      echo "\r\n--$boundary--\r\n";
    } else {
      /*
      A single range is requested.
      */
      $range=$ranges[0];
      set_range($range, $filesize, $first, $last);
      header("Content-Length: ".($last-$first+1) );
      header("Content-Range: bytes $first-$last/$filesize");
      header("Content-Type: $mime");
      fseek($file,$first);
      buffered_read($file, $last-$first+1);
    }
    fclose($file);
  } else{
    //no byteserving
    header("Content-Length: $filesize");
    header("Content-Type: $mime");
    readfile($filename);
  }
}

?>