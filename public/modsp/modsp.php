<?php
/**
 * Script PHP appelé pour générer un code JavaScript
 *
 * @author Camille Descombes
 * @package ServicePublic
 *
**/
// changement de taille d'une image
if(file_exists($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php');
  include_once($LIBTHEZORRO."bootstrap.php");
}
//appel nouveau service v3
include_once($LIBTHEZORRO."src/Library/Comarquage/Comarquage.php");
$c = new Seolan\library\Comarquage\Comarquage($_GET);

header('Content-Type: text/html; charset=ISO-8859-15');
die($c->transformToHtml());

?>
// Cache le message de chargement
document.getElementById("spLoader").style.display = 'none';