<?php
namespace Seolan\Core;
/// interface de gestion de la securité
interface ISecDeprecated {
  static function secList();
  /// securite des fonctions accessibles par le web
  static function secGroups($function,$group=NULL);
}

