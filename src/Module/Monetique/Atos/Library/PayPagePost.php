<?php
namespace Seolan\Module\Monetique\Atos\Library;

class PayPagePost {
  //This file is used to calculate the seal using the HMAC-SHA256 AND SHA256 algorithms
  function compute_seal_from_string($sealAlgorithm, $data, $secretKey) {
     if(strcmp($sealAlgorithm, "HMAC-SHA-256") == 0) {
        $hmac256 = true;
     }elseif(empty($sealAlgorithm)){
        $hmac256 = false;
     }else{
        $hmac256 = false;
     }
     return $this->compute_seal($hmac256, $data, $secretKey);
  }
  
  function compute_seal($hmac256, $data, $secretKey) {
     $serverEncoding = mb_internal_encoding();
     
     if(strcmp($serverEncoding, "UTF-8") == 0) {
        $dataUtf8 = $data;
        $secretKeyUtf8 = $secretKey;
     }else{
        $dataUtf8 = iconv($serverEncoding, "UTF-8", $data);
        $secretKeyUtf8 = iconv($serverEncoding, "UTF-8", $secretKey);
     }
     if($hmac256){
        $seal = hash_hmac('sha256', $data, $secretKey);
     }else{
        $seal = hash('sha256',  $data.$secretKey);
     }
     return $seal;
  }
  
  // utility method called by flatten_undefined or by itself
  // returns a single dimensional array representing this array
  function flatten_array($array, $keyStack) {
     $simpleValues = array();$result = array();
     
     foreach($array as $key => $value){
        if(is_int($key)){
           // Values without keys are added to results after ones having keys
           if(is_array($value)){
              $noKeyStack = array();
              $simpleValues = array_merge($simpleValues, $this->flatten_array($value, $noKeyStack));
           }else{
              $simpleValues[] = $value;
           }
        }else{
           $keyStack[] = $key;
           $result = array_merge($result, $this->flatten_undefined($value, $keyStack));
           array_pop($keyStack);
        }
     }
     
     if(!empty($simpleValues)){
        if(empty($keyStack)){
           $result = array_merge($result, $simpleValues);
        }else{
           $result[] = implode(".", $keyStack) . '=' . implode(",", $simpleValues);
        }
     }
     return $result;
  }
  
  // utility method called by flatten_to_sips_payload and flatten_array
  // returns a single dimensional array that can be imploded as a string with the
  // required separator
  function flatten_undefined($object, $keyStack) {
     $result = array();
     if(is_array($object)){
        $result = array_merge($result, $this->flatten_array($object, $keyStack));
     }else if(!empty($keyStack)){
        $result[] = implode('.', $keyStack) . '=' . $object;
     }else{
        $result[] = $object;
     }
     return $result;
  }
  
  // public API for user code
  // returns a string that can be submitted to sips API to initiate a payment on
  // Paypage Post interface
  function flatten_to_sips_payload($input) {
     $keyStack = array();
     return implode("|", $this->flatten_undefined($input, $keyStack));
  }
}
