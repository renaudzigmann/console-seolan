<?php
namespace Seolan\Core;

/**
 * Class XSessionBackup
 * Objet permettant de stocker la globale $_SESSION en vue de la restaurer a posteriori.
 *
 */
class SessionBackup {

  /**
   * Instance singleton de XSessionBackup
   * @var XSessionBackup
   */
  private static $instance;

  /**
   * contenu de $_SESSION lorsque stocké. Sinon vaut Null
   * @var array
   */
  protected $session;

  /**
   * @return XSessionBackup
   */
  public static function getInstance() {
    if (!static::$instance) {
      static::$instance = new self();
    }
    return static::$instance;
  }

  /**
   * Copie le contenu de $_SESSION
   * @throws SessionAlreadyStored
   */
  public function store() {
    if ($this->session) {
      throw new \Seolan\Core\Exception\SessionAlreadyStored("Session already stored");
    }
    $this->session = $_SESSION;
  }

  /**
   * Retourne vrai si il y à une session à restaurer.
   * @return bool
   */
  public function hasBackup() {
    return (bool) $this->session;
  }

  /**
   * Remplace $_SESSION par la copie actuellement stocké
   * @throws NoSessionToRestore
   */
  public function restore() {
    if (!$this->session) {
      throw new \Seolan\Core\Exception\NoSessionToRestore();
    }
    $_SESSION = $this->session;
    $this->session = Null;
  }

}