<?php
namespace Seolan\Module\Monetique\Atos\Library;

class OfficeJson {

  protected $singleDimArray = array();
  
  function compute_payment_init_seal($sealAlgorithm, $data, $secretKey) {
     $dataStr = $this->flatten($data); 
     return $this->compute_seal_from_string($sealAlgorithm, $dataStr, $secretKey, true);
  }
  
  function compute_payment_response_seal($sealAlgorithm, $data, $secretKey) {
     return $this->compute_seal_from_string($sealAlgorithm, $data, $secretKey, false);
  }
  
  function compute_seal_from_string($sealAlgorithm, $data, $secretKey, $hmac256IsDefault) {
     if (strcmp($sealAlgorithm, "HMAC-SHA-256") == 0){
        $hmac256 = true;
     }elseif(empty($sealAlgorithm)){
        $hmac256 = $hmac256IsDefault;
     }else{
        $hmac256 = false;
     }
     return $this->compute_seal($hmac256, $data, $secretKey);
  }
  
  function compute_seal($hmac256, $data, $secretKey) {
     $serverEncoding = mb_internal_encoding();
     
     if(strcmp($serverEncoding, "UTF-8") == 0){
        $dataUtf8 = $data;
        $secretKeyUtf8 = $secretKey;
     }else{
        $dataUtf8 = iconv($serverEncoding, "UTF-8", $data);
        $secretKeyUtf8 = iconv($serverEncoding, "UTF-8", $secretKey);
     }
     if($hmac256){
        $seal = hash_hmac('sha256', $dataUtf8, $secretKeyUtf8);
     }else{
        $seal = hash('sha256',  $dataUtf8.$secretKeyUtf8);
     }
     return $seal;
  }
  
  //Alphabetical order of field names in the table
  function recursive_table_sort($table) {
     ksort($table);
     foreach($table as $key => $value)
     {
        if(is_array($value)){
           $value = $this->recursive_table_sort($value);
           $table[$key] = $value;
        }
     }
     return $table;
  }
  
  //This function flattens the sorted payment data table into singleDimArray 
  function valueResearch($value, $key) {
     $this->singleDimArray[] = $value;
     return $this->singleDimArray;
  }
  
  function flatten($multiDimArray) {
    $sortedMultiDimArray = $this->recursive_table_sort($multiDimArray);
    //array_walk_recursive($sortedMultiDimArray, [&$this, 'valueResearch']);
    array_walk_recursive($sortedMultiDimArray, function($value, $key) {
      $this->singleDimArray[] = $value;
      return $this->singleDimArray;
    });
    $string = implode("", $this->singleDimArray);
    $this->singleDimArray = array();
    return $string;
  }

}
