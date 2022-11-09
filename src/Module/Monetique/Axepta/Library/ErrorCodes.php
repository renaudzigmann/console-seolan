<?php
namespace Seolan\Module\Monetique\Axepta\Library;

//https://docs.axepta.bnpparibas/display/DOCBNP/A4+Response+codes

class ErrorCodes {

  const DIGIT_1 = [
    '0' => 'OK',
    '2' => 'ERROR',
    '4' => 'FATAL',
    '6' => 'TRANSIENT', //= tmp error
    '7' => 'EMV_3DS_INFO' //Intermediate states in the EMV 3-D Secure sequence
  ];

  const DIGIT_2_4 = [
    '001' => 'Platform cryptography (encrypting, decrypting)',
    '010' => 'Parameter is missing',
    '011' => 'Parameter error in format',
    '012' => 'Parameter value is missing',
    '013' => 'Parameter is too short',
    '014' => 'Parameter is too long',
    '015' => 'Parameter value is missing',
    '016' => 'Parameter value unknown or not allowed',
    '017' => 'Parameter is already present',
    '018' => 'Parameter is expired or not valid any more',
    '019' => 'Parameter is not allowed for current message version',
    '0xx' => 'Platform internal',
    'xxx' => 'Module Error : see module list',
  ];
  
  const CHARS_5_8 = [
    '0000' => 'Unspecified',
    '0001' => 'PayID error',
    '0002' => 'TransID error',
    '0003' => 'MerchantID error',
    '0004' => 'ReqID error',
    '0005' => 'Amount error',
    //...
    '0040' => 'No Response',
    //...
    '0044' => 'MAC error',
    '0045' => 'Merchant Busy',
    //...
    '0090' => 'Merchant 3DS plugin error',
    '0094' => 'Card expired',
    '0098' => 'Authentification Error',
    
    '0100' => 'Bank refused',
    '0102' => 'Invalid card number',
    '0103' => 'Issuer refused',
    '0104' => 'Declined by BlackList',
    '0110' => 'Expired',
    '0111' => 'Card Brand not supported',
    //...
  ];
}