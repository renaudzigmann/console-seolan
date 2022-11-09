<?php
namespace Seolan\Module\Monetique\Axepta\Library;

/*
* Adaptation de la librairie Paypage pour les autres services (reverse, refund, inquire,...)
*/

class Server {
  public $debug = true;
  public $pspFields;
  
  /** Axepta request hmac fields **/
  //Request The HMAC value is obtained by ciphering the string PayID*TransID*MerchantID*Amount*Currency with the HMAC key of your shop.
  protected $QHMACFields = [
    'PayID' => 'PayID', 'TransID', 'MerchantID', 'Amount','Currency'
  ];
  /** Axepta response hmac fields **/
  protected $RHMACFields = [
    'PayID' => 'PayID', 'TransID', 'MerchantID', 'Status','Code'
  ];
  /** Axepta Request blowfish crypt fields **/
  protected $BfishFields_Mandatories = [
		'MerchantID',
    //'MsgVer',
    'TransID',
    'MAC',
  ];
  protected $BfishFields = [
		'MerchantID',
    'MsgVer',
    'TransID',
    'RefNr',
    'Amount',
    'Currency',
    'URLNotify',
    'URLSuccess',
    'URLFailure',
    'MAC',
    'OrderDesc',
    
    'Textfeld1',
    'Textfeld2',
    
    'Response','UserData','Capture','Plain',
    'PayID',
		'Amount3D',		
		'ReqID',
		'expirationTime','AccVerify','RTF','ChDesc',
		'MID','XID','Status','Description','Code',
    'AmountAuth','AmountCap','AmountCred','LastStatus',
		
		'PCNr','CCNr','CCCVC','CCBrand','CCExpiry','TermURL','UserAgent',
		'HTTPAccept','AboID','ACSXID','MaskedPan','CAVV','ECI','DDD','Type','Custom'
  ];
  //shared properties
  protected $hmacKey;
  protected $cryptKey;
  protected $parameters;
  protected $requiredFields;
  
  public function __construct($ar) {
    $this->hmacKey = $ar['hmacKey'];
    $this->cryptKey = $ar['cryptKey'];
  }
  
    /** @return string */
  public function getShaSign($request)
  {
    /*$this->parameters = $request;
    $this->requiredFields = $this->QHMACFields;
    unset($this->requiredFields['PayID']);
    $this->validate();*/
    return $this->ctHMAC($request['PayID'], $request['TransID'], $request['MerchantID'], $request['Amount'], $request['Currency'], $this->hmacKey);
    //return $this->shaCompose($this->QHMACFields);
  }
  
    //Short Equivalent to getShaSign
  protected function ctHMAC($PayID = "", $TransID = "", $MerchantID, $Amount, $Currency, $HmacPassword)
  {
    return hash_hmac("sha256", "{$PayID}*{$TransID}*{$MerchantID}*{$Amount}*{$Currency}", $HmacPassword);
  }
  
	/** HMAC compute and store in MAC field**/
	public function shaCompose(array $parameters)
  {
    // compose SHA string
    $shaString = '';
    $i = 0;
    foreach($parameters as $key) {
  		if(array_key_exists($key, $this->parameters) && !empty($this->parameters[$key])) {
  			$value = $this->parameters[$key];
  			$shaString .= $value;
  		}
      //$shaString .= (array_search($key, $parameters) != (count($parameters)-1)) ? '*' : '';
      $shaString .= ($i != (count($parameters)-1)) ? '*' : '';
      $i++;
    }
    if ($this->debug) {
      \Seolan\Core\Logs::critical(__METHOD__.' _____ sha string ='.$shaString.PHP_EOL);
      \Seolan\Core\Logs::critical(__METHOD__.' _____ hmacKey ='.$this->hmacKey.PHP_EOL);
    }
 		$this->parameters['MAC'] = hash_hmac('sha256', $shaString, $this->hmacKey);
    return $this->parameters['MAC'];
  }

	
	
  //-----------------------------------------------------
  public function getBfishCrypt($request)
  {
    $this->parameters = $request;
    $this->requiredFields = $this->BfishFields_Mandatories;
    $this->validate();
    return $this->BFishCompose($this->BfishFields);
  }

	public function BfishCompose(array $parameters)
  {
    // compose Blowfish hex string
    $blowfishString = '';
		
		foreach($parameters as $key) {
			if(array_key_exists($key, $this->parameters) && !empty($this->parameters[$key])) {
				$value = $this->parameters[$key];
				$blowfishString .= $key.'='.$value.'&';
			}
		}
		$blowfishString = rtrim($blowfishString,'&');
		$this->parameters['Debug'] = $blowfishString;
		$this->parameters['Len'] = strlen($blowfishString);
		$this->parameters['DATA'] = bin2hex($this->encrypt($blowfishString,$this->cryptKey));

		return [$this->parameters['DATA'], $this->parameters['Len'], $this->parameters['Debug']];
  }
	
 
  //---------------------------------------------------------------------------------------------------
	private function encrypt($data, $key)
  {
    $l = strlen($key);
    if ($l < 16)
      $key = str_repeat($key, ceil(16/$l));

    if ($m = strlen($data)%8)
      $data .= str_repeat("\x00",  8 - $m);
    if (function_exists('mcrypt_encrypt'))
      $val = mcrypt_encrypt(MCRYPT_BLOWFISH, $key, $data, MCRYPT_MODE_ECB);
    else
      $val = openssl_encrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

    return $val;
  }
  
  private function decrypt($data, $key)
  {
    $l = strlen($key);
    if ($l < 16)
        $key = str_repeat($key, ceil(16/$l));
    
    if (function_exists('mcrypt_encrypt'))
        $val = mcrypt_decrypt(MCRYPT_BLOWFISH, $key, $data, MCRYPT_MODE_ECB);
    else
        $val = openssl_decrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
    return rtrim($val, "\0");
  }
	
  //-------------------------------------------------------------------------------------------------------------
  public function __call($method, $args)
  {
    if(substr($method, 0, 3) == 'set') {
      // $field = lcfirst(substr($method, 3));
      $field = substr($method, 3);
       if(in_array($field, $this->pspFields)) {
          $this->parameters[$field] = $args[0];
          return;
      }
    }

    if(substr($method, 0, 3) == 'get') {
      //           $field = lcfirst(substr($method, 3));
      $field = substr($method, 3);
      if(array_key_exists($field, $this->parameters)) {
        return $this->parameters[$field];
      }
    }
    throw new BadMethodCallException("Unknown method $method");
  }

  public function toArray()
  {
    return $this->parameters;
  }

  public function toParameterString()
  {
    $parameterString = "";
    foreach($this->parameters as $key => $value) {
      $parameterString .= $key . '=' . $value;
      $parameterString .= (array_search($key, array_keys($this->parameters)) != (count($this->parameters)-1)) ? '|' : '';
    }
    return $parameterString;
  }

  /** @return PaymentRequest */
  public static function createFromArray(ShaComposer $shaComposer, array $parameters)
  {
    $instance = new static($shaComposer);
    foreach($parameters as $key => $value)
    {
      $instance->{"set$key"}($value);
    }
    return $instance;
  }

  public function validate()
  {
    foreach($this->requiredFields as $field) {
      if(!isset($this->parameters[$field])) {
        throw new \RuntimeException($field . " must be set even if empty");
      }
    }
  }

  protected function validateUri($uri)
  {
    if(!filter_var($uri, FILTER_VALIDATE_URL)) {
      throw new InvalidArgumentException("Uri is not valid");
    }
    if(strlen($uri) > 200) {
      throw new InvalidArgumentException("Uri is too long");
    }
  }

	//-------------------------------------------------------------------------------------------------------------
    // Traitement des reponses d'Axepta
    // -----------------------------------
	
	/** @var string */
  const SHASIGN_FIELD = "MAC";

  /** @var string */
  const DATA_FIELD = "Data";

  public function getResponse(array $httpRequest)
  {
      // use lowercase internally
      // $httpRequest = array_change_key_case($httpRequest, CASE_UPPER);

      // set sha sign        
      // $this->shaSign = $this->extractShaSign($httpRequest);

      // filter request for Sips parameters
      unset($this->parameters);
      $this->parameters = $this->filterRequestParameters($httpRequest);
      return $this->parameters;
  }

  /**
   * @var string
   */
  private $shaSign;

  private $dataString;

  /**
   * Filter http request parameters
   * @param array $requestParameters
   * => transform keys to lower case as recommended in Doc?
   */
  private function filterRequestParameters(array $httpRequest)
  {
    //filter request for Sips parameters
    $parameters = $this->parameters;
    if(!array_key_exists(self::DATA_FIELD, $httpRequest) || $httpRequest[self::DATA_FIELD] == '') {
      // throw new InvalidArgumentException('Data parameter not present in parameters.');
		  $parameters['Debug'] = implode('&',$httpRequest);
		  foreach($httpRequest as $key=>$value) {
			 $key = ($key=='mid')? 'MerchantID':$key;
			 $parameters[$key]=trim($value);
		  }
    } else {
  		$parameters[self::DATA_FIELD] = $httpRequest[self::DATA_FIELD];
  		$this->dataString = $this->decrypt(hex2bin($parameters[self::DATA_FIELD]),$this->cryptKey);
  		$parameters['Debug'] = $this->dataString;
  		$dataParams = explode('&', $this->dataString);
  		foreach($dataParams as $dataParamString) {
  			$dataKeyValue = explode('=',$dataParamString,2);
  			$key = ($dataKeyValue[0]=='mid')?'MerchantID':$dataKeyValue[0];
  			$parameters[$key] = trim($dataKeyValue[1]);
  		}
    }
    return $parameters;
  }

  public function getSeal()
  {
      return $this->shaSign;
  }

  private function extractShaSign(array $parameters)
  {
      if(!array_key_exists(self::SHASIGN_FIELD, $parameters) || $parameters[self::SHASIGN_FIELD] == '') {
          throw new InvalidArgumentException('SHASIGN parameter not present in parameters.');
      }
      return $parameters[self::SHASIGN_FIELD];
  }

  /**
   * Checks if the response is valid
   * @param ShaComposer $shaComposer
   * @return bool
   */
  public function isValid()
  {
      // return $this->shaCompose($this->RHMACFields) == $this->shaSign;
      return $this->shaCompose($this->RHMACFields) == $this->parameters['MAC'];
  }
  
  public function computeResponseHMAC($response)
  {
    return $this->responseHMAC($response['PayID'], $response['TransID'], $response['MerchantID'], $response['Status'], $response['Code'], $this->hmacKey);
  }
  protected function responseHMAC($PayID='', $TransID='', $MerchantID='', $Status='', $Code='', $HmacPassword)
  {
    return hash_hmac("sha256", "{$PayID}*{$TransID}*{$MerchantID}*{$Status}*{$Code}", $HmacPassword);
  }

  /**
   * Retrieves a response parameter
   * @param string $param
   * @throws \InvalidArgumentException
   */
  public function getParam($key)
  {
      if(method_exists($this, 'get'.$key)) {
          return $this->{'get'.$key}();
      }

      // always use uppercase
      // $key = strtoupper($key);
      // $parameters = array_change_key_case($this->parameters,CASE_UPPER);
      $parameters = $this->parameters;
      if(!array_key_exists($key, $parameters)) {
          throw new InvalidArgumentException('Parameter ' . $key . ' does not exist.');
      }

      return $parameters[$key];
  }

  /**
   * @return int Amount in cents
   */
  public function getAmount()
  {
      $value = trim($this->parameters['Amount']);
      return (int) ($value);
  }

  public function isSuccessful()
  {
      return in_array($this->getParam('Status'), array("OK", "AUTHORIZED"));
  }

  public function getDataString()
  {
      return $this->dataString;
  }
}