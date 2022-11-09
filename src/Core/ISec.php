<?php
/// interface de gestion de la securité

namespace Seolan\Core;

interface ISec {
  public function secList();
  /// securite des fonctions accessibles par le web
  public function secGroups($function,$group=NULL);
}
?>
