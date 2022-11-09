<?php
namespace Seolan\Module\Form;
interface ConnectionInterface {
  function XFormGetDataSource();    /* Recupération de la source de données */
  function XFormInput($ar);         /* Insertion */
  function XFormEdit($ar);          /* Edition */
  function XFormProcInput($ar);     /* Validation de l'insertion */
  function XFormProcEdit($ar);	     /* Validation de l'édition */
  function XFormBrowse($ar);        /* Parcours */
}
?>
