<?php

namespace Seolan\Core;

/** 
 * Classe de gestion de resources smarty de type file, pour interpréter correctement la syntaxe spécifique 
 */
class CustomTemplateResource extends \Smarty_Internal_Resource_File {
  protected function buildFilepath(\Smarty_Template_Source $source, \Smarty_Internal_Template $_template = null) {
    $source->name = $this->decodeFilename($source->name, $_template);
    return parent::buildFilePath($source, $_template);
  }
  function decodeFilename($resource_name, $_template){
   Logs::debug(__METHOD__ . ' resolving '.$resource_name);
   // à traiter / home dir etc
   if (file_exists($resource_name)){
     return $resource_name;
   }
    if(substr_count($resource_name,'.')<2) return $resource_name;
    
    // Sécurité pour empecher de remonter dans l'arbo
    $resource_name=preg_replace('/\.+/','.',$resource_name);
    // Décodage
    $parts=explode('.',$resource_name,2);
    Logs::debug(__METHOD__ . ' resolved '.$resource_name.' -> '.$parts[0].'/public/templates/'.$parts[1]);
    // 'Local' n'est pas un répertoire mais la bibliotheque tzr locale
    if (strpos($parts[0], 'Local/')===0){
      $parts[0] = str_replace('Local/', '', $parts[0]);
    } else if($parts[0] === 'Local'){
      // cas particulier type 'Local.truc.html' -> 'tzr/public/template/truc.html'
      return 'public/templates/'.$parts[1];
    }
    return $parts[0].'/public/templates/'.$parts[1];
  }
  
}


?>
