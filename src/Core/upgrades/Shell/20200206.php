<?php
/**
 * AMAX peut contenir maxlvl;koid soit plus de 40 caractères
 * Le dépassement provoque une erreur avec certaines versions de MDB 
 */
function Shell_20200206(){
  getDB()->execute('ALTER TABLE ACL4_CACHE MODIFY AMAX VARCHAR(64)');
}

