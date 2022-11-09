<?php
namespace Seolan\Core\Directory;
/**
 * répertoire local des utlisateurs 
 * interface avec le module et la table user de la console
 * prise en compte du root/imap en user local
 */
class LocalDirectory extends \Seolan\Core\Directory\Directory{
  protected function authenticationAdapterFactory($login, $password) : \Zend\Authentication\Adapter\AdapterInterface{
    return new \Seolan\Core\Directory\Authentication\Adapter($this, $login, $password);
  }
  /*
   * traiter root user en local uniquement (imap)
   */
  public function isQualified(string $alias):bool{
    if ($alias == TZR_ROOTAUTH_ALIAS)
      return true;
    return parent::isQualified($alias);
  }
  public function exclusiveUser(string $alias):bool{
    if ($alias == TZR_ROOTAUTH_ALIAS)
      return true;
    return parent::exclusiveUser($alias);
  }
  protected function getItems():\Iterator{
    return new \EmptyIterator(); // pas de synchronisation
  }
  protected function formatUserData($item){
    return null; // pas de synchro 
  }
  /**
   * authentification via le module utilisateur
   * traitement du root imap à part
   */
  public function authenticate($login, $password):?array{
    // Si la connexion root doit ce faire via une boite mail
    if(TZR_ROOTAUTH_USEIMAP && $login==TZR_ROOTAUTH_ALIAS){
      return $this->rootImapAuthenticate($password);
    }

    $mod = $this->getModUser();
    list($ok, $messagescodes, $user) = $mod->authenticate($login, $password);
    // seulement les utilisateurs 'locaux'
    if ($ok && !empty($user->_cur['directoryname']) && $user->_cur['directoryname'] != $this->id){
      $ok = false;
      $user = null;
      $messagescodes[] = 'invalid_user_directory';
    }
    return [$ok, $messagescodes, $user];
  }
  /**
   * imap authentication dedicated to root user
   */
  protected function rootImapAuthenticate($password){
    foreach(explode(',', TZR_ROOTAUTH_IMAPUSER) as $imapUser){
      $rootok = false;
      try{
	$mail=new \Zend\Mail\Storage\Imap(array('host'=>TZR_ROOTAUTH_IMAP,'user'=>$imapUser,'password'=>$password));
	$rootok=true;
	$rs=getDB()->select('SELECT * FROM USERS WHERE alias=?', array(TZR_ROOTAUTH_ALIAS));
	\Seolan\Core\Logs::debug(__METHOD__." root check on imap server user $imapUser");
	break;
      }catch(\Exception $e){}
    }
    if ($rootok && $rs->rowCount()==1){
      $ors = $rs->fetch();
      $user = new \Seolan\Core\User($ors['KOID']);
      return [true, null, $user];
    } else {
      return [false, ['invalid_credential'], null];
    }
  }
  /**
   * check whether password has expired
   */
  public function postAuthenticationCheck(\Seolan\Core\User $user){
    if ($user->uid() == TZR_USERID_ROOT)
      return [true, null];
    $modUser = $this->getModUser();
    $dateexpire = $modUser->getPasswordExpirationDate($user->uid());
    if ($dateexpire){
      $datenow = date('Y-m-d');
      if (is_array($dateexpire))
	$dateexpire = $dateexpire[0];
      if ($dateexpire < $datenow){
	$options = ['mail'=>true];
        \Seolan\Core\Logs::notice(__METHOD__,"password expiration {$user->_cur['KOID']}");
	// initialisation traitement de renouvellement + message adhoc
        if ($this->getConfiguration()->get('config')->get('prepareNewPassword')) {
          $options = $this->getConfiguration()->get('config')->get('prepareNewPassword')->toArray();
        }
	$modUser->prepareNewPassword(null, $user->uid(), 'expired', $options);
	return [false, 'passwordexpire_sent'];
      }
    }
    return [true, null];
  }
  /**
   * user management
   */
  public function getUserManager() :\Seolan\Core\Directory\UserDirectoryInterface{
    return $this->getModUser();
  }

}
