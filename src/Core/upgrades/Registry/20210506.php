<?php
function Registry_comment_20210506(){
  return "Ajout de la table système _REGISTRY";
}
function Registry_20210506(){
  if(!\Seolan\Core\System::tableExists("_REGISTRY")) {
    \Seolan\Core\Registry::createStructure();
  }
}
