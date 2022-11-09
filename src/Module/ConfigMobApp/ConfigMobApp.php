<?php

namespace Seolan\Module\ConfigMobApp;

use Seolan\Core\Param;
use Seolan\Module\Table\Table;
use Seolan\Core\Labels;
use Seolan\Core\Module\Action;
use Seolan\Core\Shell;

class ConfigMobApp extends Table {
  private const PASSPHRASE = 'Humorless-Absolute-Rumble-Unbend-Depth-Punk-Sharpener-Vexingly';
  
  public function secGroups($function, $group = null) {
    $g = [
      'generateQRCode' => ['ro','rw','rwv','admin'],
    ];
  
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    
    return parent::secGroups($function, $group);
  }
  
  protected function _actionlist(&$my, $alfunction = true) {
    parent::_actionlist($my);
    $oid = $_REQUEST['oid'] ?? '';
    $function = Shell::_function();
    
    if(in_array($function, ['display','edit']) && $this->secure($oid,'generateQRCode')) {
      $o1=new Action(
        $this,
        'generateQRCode',
        Labels::getSysLabel('Seolan_Module_ConfigMobApp_ConfigMobApp','generate_qrcode'),
        '&moid='.$this->_moid.'&function=generateQRCode&tplentry=br&template=Module/ConfigMobApp.qrcode.html&oid='.$oid,
        'actions');
      
      $o1->menuable = true;
      $my['updates'] = $o1;
    }
  }
  
  /**
   * Fonction de génération du QRCode de paramétrage de l'application mobile.
   *
   * @param $ar
   *
   * @return void
   */
  public function generateQRCode ($ar = null) {
    $p = new Param($ar);
    $oid = $p->get('oid');
    
    $data = $this->xset->rDisplay($oid);
    $dataToEncrypt = [
      'url' => $data['ourl']->text,
      'uri' => $data['ouri']->text,
      'primary_color' => $data['oprimary_color']->text,
      'secondary_color' => $data['osecondary_color']->text,
    ];
    
    $cipherData = $this->encryptData(json_encode($dataToEncrypt));
    
    Shell::toScreen2($p->get('tplentry'), 'cipherData', $cipherData);
  }
  
  private function encryptData ($data) {
    $key = hash('md5', self::PASSPHRASE);
    
    $iv = openssl_random_pseudo_bytes(16);
    $ivBase64 = base64_encode($iv);
    $dataB64 = base64_encode($data);
    
    $hash = hash_hmac('md5', $dataB64 . $ivBase64, $key);
    
    return $hash . $ivBase64 . $dataB64;
  }
}