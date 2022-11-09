<?php
function isAdmin() {
  $adminUris = [
		'/csx/scripts-admin',
		'/scripts/admin.php',
		'/scripts-admin'
		];
  foreach($adminUris as $adminUri){
    if( strpos($_SERVER['REQUEST_URI'],$adminUri)===0 ){
      return true;
    }
  }
  return false;
}