<?php
  /**
   * Affichage d'attribut du user connecté en accord avec l'environnement console
   * Veriider le type du template : ? \Seolan\Core\Template $smarty
   */

function smarty_function_user(array $params=null, Smarty_Internal_Template $template){
  if (\Seolan\Core\User::isNobody()){
    return '';
  }
  if (isset($params['fullname']) || isset($params['email']) || isset($params['logo'])){
    $user =\Seolan\Core\User::get_user();
    if (!isset($user)){
      return '';
    }
    if (isset($params['fullname'])){
      return $user->fullname();
    }
    if (isset($params['email'])){
      return $user->email();
    }
    if (isset($params['logo'])){
      $logo = $user->logo();
      if (isset($logo) && isset($logo->resizer)){
	return $logo->resizer;
      } else {
	return;
      }
    }
  } else {
    return '';
  }
  }