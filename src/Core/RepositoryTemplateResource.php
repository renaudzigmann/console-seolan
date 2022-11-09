<?php

namespace Seolan\Core;

/** 
 * Résolution des gabarits des repo. externes pour le prefix donné
 */
class RepositoryTemplateResource extends \Seolan\Core\CustomTemplateResource{
  protected $_prefix = null;
  protected $_repository = null;
  public function setRepository($prefix, $repository){
    $this->_prefix = $prefix;
    $this->_repository = $repository;
  }
  protected function __buildFilepath(\Smarty_Template_Source $source, \Smarty_Internal_Template $_template = null) {
    $source->name = $this->decodeFilename($source->name, $_template);
    return parent::buildFilePath($source, $_template);
  }
  function decodeFilename($resource_name, $_template){
    Logs::debug(__METHOD__ . " resolving '$resource_name'");
    if(substr_count($resource_name,'.')<2) return $resource_name;
    // Sécurité pour empêcher de remonter dans l'arbo
    $resource_name=preg_replace('/\.+/','.',$resource_name);
    // Décodage
    list($path, $file) = explode('.',$resource_name,2);
    $parts = explode('/', $path);
    $fn = implode('/', $parts).'/public/templates/'.$file;
    Logs::debug(__METHOD__ . " resolved $resource_name -> $fn");
    return $fn;
  }
}
