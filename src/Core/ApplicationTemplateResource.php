<?php

namespace Seolan\Core;

/** 
 * Classe de gestion de resources smarty de type appplication, pour interpréter correctement la syntaxe spécifique 
 * et permettre une certaine surcharge via l'application
 */
class ApplicationTemplateResource extends \Seolan\Core\CustomTemplateResource{
  private $app = null;
  function __construct(\Seolan\Core\Application\Application $app){
    $this->app = $app;
  }
  // en mode application: on cherche systématiquement dans le path
  function decodeFilename($resource_name, $_template){
   Logs::debug(__METHOD__ . ' resolving '.$resource_name);
   if(substr_count($resource_name,'.')<2) {
     $ns = explode('\\',get_class($this->app));
     $c = array_pop($ns);
     array_shift($ns);
     $file = implode('/', $ns).'/public/templates/'.$resource_name;
     Logs::debug(__METHOD__ . ' resolved '.$resource_name.' -> '.$file);
     return $file;
   }
    
    // Sécurité pour empecher de remonter dans l'arbo
    $resource_name=preg_replace('/\.+/','.',$resource_name);
    // Décodage
    $parts=explode('.',$resource_name,2);

    Logs::debug(__METHOD__ . ' resolved '.$resource_name.' -> '.$parts[0].'/public/templates/'.$parts[1]);
    return $parts[0].'/public/templates/'.$parts[1];
  }
}


?>
