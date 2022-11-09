<?php
namespace Seolan\Module\MailLogs;
interface ConnectionInterface{
  /// Traite un email comme bounce
  public function applyBounce($email);
  /// Reactive un mail
  public function unBounce($email);
  /// Statut du mail dans le module
  public function emailStatus($email);
}
?>