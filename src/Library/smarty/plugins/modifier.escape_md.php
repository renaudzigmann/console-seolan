<?php

/**
 * escape markdown characters :
 * \`*_{}[]()#+-.!
 * 
*/
function smarty_modifier_escape_md($string, $trim=true){
    return str_replace(['\\', '`','*','_','{','}','[',']','(',')','#','+','-','.','!','|'], 
    ['\\\\','\`','\*','\_','\{','\}','\[','\]','\(','\)','\#','\+','\-','\.','\!','\|'],
		     $trim?trim($string):$string);
}

?>