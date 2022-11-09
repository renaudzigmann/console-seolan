<?php
namespace Seolan\Core\Module;
interface ConnectionInterface {
  function XMCinput($ar);	/* generation du formulaire d'entree */
  function XMCprocInput($ar);	/* insertion d'un doc dans le repository */
  function XMCedit($ar);	/* formulaire d'edition */
  function XMCprocEdit($ar);	/* validation edition */
  function XMCdisplay($ar);	/* affichage */
  function XMCdel($ar);		/* suppression */
  function XMCfullDelete($ar);	/* suppression complete */
  function XMCduplicate($oidsrc); /* duplication d'un document */
  function XMCeditDup($oidsrc); /* duplication d'un document */
  function XMCprocEditDup($ar); /* duplication d'un document */
  function XMCquery($ar);       /* preparartion d'un formulaire de recherche */
  function XMCprocQuery($ar);       /* traitement d'un formulaire de recherche */
  function XMCbrowseTrash($ar);       /* éléments de la corbeille  */
  function XMCemptyTrash($ar);       /* vidage de la corbeille  */
  function XMCmoveFromTrash($ar);       /* restauration depuis la corbeille  */
  function XMCallowComments(); /* autoriser les commentaires */
  function XMCcommentsMoid(); /* module 'portant' les commentaires */
  function XMCgetLastUpdate(string $oid); /* rend la date de dernière mise à jour */
}
	
