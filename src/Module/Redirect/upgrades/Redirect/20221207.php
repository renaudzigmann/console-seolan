<?php
  /**
   * Ajout du champ ordre
   * Obligatoire et valeur par défaut = 0
   * Placé en dernière position
   */
function Redirect_20221207() {

  if (!\Seolan\Core\System::tableExists('REDIRECTION')){
    echo("\n Table REDIRECTION doesn't exist");
    return;
  }

  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('REDIRECTION');

  if ($ds->fieldExists('ordre')){
    echo("\n Field 'ordre' already exist in table REDIRECTION");
    return;
  }

  $forder = max(array_column($ds->desc, "forder"))+1;
  $ds->createField('ordre', 'Ordre' ,'\Seolan\Field\Order\Order', '0', $forder, '1', '0', '1', '0', '0', '0', '', ['default' => 0]);    
  echo("\n adding 'ordre' to REDIRECTION");
  
}
