<?php
/// correction des ACOMMENTS de ACL4 et CACHE
function Shell_20200313(){
  try{
    getDB()->execute('alter table ACL4 alter ACOMMENT set default ""');
    getDB()->execute('alter table ACL4_CACHE alter ACOMMENT set default ""');
  }catch(\Exception $e){}
}
function Shell_comment_20200313(){
  return "valeur par défaut du champ ACOMMENT dans les tables ACL4 et ACL4_CACHE";
}
