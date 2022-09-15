<?php
/**
 * Classe de gestion de base des SMS
 * @todo : se rapprocher de \Seolan\Library\Mail (logs ...)
 * @note : les caractères accentués ne sont pas supportés
 */
namespace Seolan\Library;
class SMS {
  private $soapClient = NULL;
  protected $parms = NULL;
  public $smstable = 'SMS';
  public $dssms = NULL;
  protected $auth = NULL;
  public function __construct($params=NULL){
    \Seolan\Core\Logs::debug(get_class($this).'::__construct '.var_export($params, true));
    $this->parms = $params;
    try{
      $this->soapClient = new \SoapClient($this->parms['wsdl'], array('exceptions'=>true, 'connection_timeout'=>(isset($this->params['connectionTimeout']))?$this->params['connectionTimeout']:30));
    } catch(\Exception $e){
      \Seolan\Core\Logs::critical(get_class($this), '::__construct error while connecting : '.$e->getMessage());
    }
    $this->dssms = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->smstable);
  }
  /**
   * lecture des comptes SMS configurés (pour un module ou en général)
   */
  public static function getSmsAccounts($moid=NULL, $name=NULL){
    $smsAccounts = NULL;
    if (\Seolan\Core\System::tableExists('_ACCOUNTS')){
      if ($moid != NULL){
	$rs = getDB()->select('select * from _ACCOUNTS where atype="SMSACCOUNT" and modid=?',array($moid));
      } else {
	$rs = getDB()->select('select * from _ACCOUNTS where atype="SMSACCOUNT" and name=?',array($name));
      }
      if ($rs->rowCount() > 0){
	$smsAccount = array();
      }
      while($rs && ($ors = $rs->fetch())){
	$smsAccounts[$ors['KOID']] = $ors;
	$smsAccounts[$ors['KOID']]['wsdl'] = $ors['url'];
	$smsAccounts[$ors['KOID']]['cplt'] = json_decode($ors['cplt']);
	$smsAccounts[$ors['KOID']]['clientid'] = $smsAccounts[$ors['KOID']]['cplt']->clientid;
      }
    }
    return $smsAccounts;
  }
  /**
   * recuperation des SMS de la carte en table
   * @note  : on depile tante que il a quelque chose
   */
  function readSMSs(){
    \Seolan\Core\Logs::notice(get_class($this), '::readSMSs ...');
    $nb = 0;
    $res = $this->getSMS();
    while($res && $res->status == 1){
      \Seolan\Core\Logs::debug('readSMSs '.var_export($res,1));
      if (strlen($res->sender) >= 7) { // skip short number, ads ...
        $this->dssms->procInput(array(
            'INOROUT' => 'INCOMMING',
            'STATUS' => $res->status,
            'STATUSTXT' => $res->status_message,
            'SMS' => $res->message,
            'SENDER' => $res->sender,
            'RECIPIENT' => $res->recipient,
            'SMSID' => NULL,
            '_options' => array('local' => 1)
        ));
        $nb++;
      }
      $res = $this->getSMS();
    }
    \Seolan\Core\Logs::notice(get_class($this), '::readSMSs ...'.$nb);
  }
  /**
   * récuperation d'un sms
   */
  function getSMS(){
    try{
      $this->connect();
      $res = $this->soapClient->get_sms();
    } catch(\Exception $e){
      \Seolan\Core\Logs::critical(get_class($this), '::getSMS '.$e->getMessage());
      return false;
    }
    return $res;
  }
  /**
   * envoi d'un sms
   * @param String : $num le destinataire
   * @param String : $message
   * @param String : $smsOid optionnel, oid du SMS
   * @param Array : données supplémentaire pour insertion dans ligne de la table SMS
   * @return String id du sms (rend par l'api)
   */
  function sendSMS($num, $mess, $smsOid=NULL, $data=NULL){
    if ($data == NULL){
      $data = array();
    }
    try{
      $this->connect();
      $mess = removeaccents($mess);
      $send = $this->soapClient->send_sms((String)$num, $mess);
    } catch(\Exception $e){
      \Seolan\Core\Logs::critical(get_class($this), '::sendSMS '.$e->getMessage());
      $send = (Object)array('status'=>'X', 'status_message'=>'Could not connect to device or send message -  '.$e->getMessage(), 'id'=>NULL);
    }
    if (!is_object($send)){
      $send = (Object)array('status'=>'X', 'status_message'=>'Unexpected error', 'id'=>NULL);
    }
    $r = $this->dssms->procInput(array_merge($data, array(
        'newoid' => $smsOid,
        'INOROUT' => 'OUTGOING',
        'STATUS' => $send->status,
        'STATUSTXT' => $send->status_message,
        'SMS' => $mess,
        'RECIPIENT' => $num,
        'SMSID' => $send->id,
        '_options' => array('local' => 1)
        )));
    return array('smsid'=>$send->id, 'oid'=>$r['oid'], 'status'=>$this->getSMSStatus($r['oid']));
  }
  /**
   * statut d'un message
   */
  function getSMSStatus($oid){
    $d = $this->dssms->rdisplay($oid);
    if (is_array($d)){
      return array('code'=>$d['oSTATUS']->raw, 'txt'=>$d['oSTATUSTXT']->raw);
    } else {
      return array('code'=>'x', 'txt'=>'unknown message');
    }
  }
  /**
   * connection à l'interface
   */
  function connect(){
    if ($this->auth != NULL)
      return;
    try{
      $auth = $this->soapClient->login($this->parms['login'], $this->parms['passwd'], $this->parms['clientid']);
      \Seolan\Core\Logs::notice(get_class($this), '::connect '.$auth->status_message);
      if ($auth->status != 0){
        throw new \Exception('invalid status : '.$auth->status.' '.$auth->status_message);
      }
      $this->auth = $auth;
    } catch(\Exception $e){
      throw new \Exception('Error during login ('.$this->parms['login'].'): '.$e->getMessage());
    }
  }
}
