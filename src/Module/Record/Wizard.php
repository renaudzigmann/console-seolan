<?php
namespace Seolan\Module\Record;

/****c* tzr-5/\Seolan\Module\Table\Wizard/\Seolan\Module\Record\Wizard
 * NAME
 *   \Seolan\Module\Record\Wizard -- Assistant de création d'un module Fiche
 * DESCRIPTION
 *  Cette classe est le wizard (assistant) de création d'un module Fiche, c'est à dire d'un module permettant l'affichage et/ou l'édition d'un enregistrement
 * SYNOPSIS
 ****/

class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    return parent::__construct($ar);
  }
  function istep1() {
    return parent::istep1();
  }

  function iend($ar=NULL) {
    return parent::iend();
  }
}

?>
