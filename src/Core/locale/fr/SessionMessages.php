<?php

\Seolan\Core\Labels::$LABELS['Seolan_Core_SessionMessages']
= array(
	'lost_password_msg'=>'Bonjour,<br><br>Suite à votre demande, cet email contient un lien vous permettant de générer un nouveau mot de passe pour votre compte [ %s ] sur le site '.$GLOBALS['HOME_ROOT_URL'].'.<br><br>Pour changer votre mot de passe, <a href="%s">cliquez ici</a>.<br><br>Ce lien est valide '.($GLOBALS['TZR']['passwordtokendduration']??24).' heures et ne peut être utilisé qu\'une seule fois.<br><br>S\'il s\'avérait que vous n\'êtes pas à l\'origine de cette demande, veuillez ignorer cet email.<br><br>Cordialement.<br>',
	'lost_password_sub'=>'Changement de mot de passe',
	'expire_password_msg'=>'Bonjour,<br><br>La durée de validité de votre mot de passe arrive à terme. Cet email contient un lien vous permettant de renseigner un nouveau mot de passe pour votre compte [ %s ] sur le site '.$GLOBALS['HOME_ROOT_URL'].' <br>Pour effectuer cette opération, <a href="%s">cliquez ici</a>.<br>Ce lien est valide '.($GLOBALS['TZR']['passwordtokendduration']??24).' heures et ne peut être utilisé qu\'une seule fois.<br>Vous pourrez ensuite changer votre mot de passe une fois connect&eacute; sur le site.<br><br>Cordialement.',
	'expire_password_sub'=>'Changement de mot de passe',
	'login_msg'=>"Bonjour,\n\nVotre compte sur ".$GLOBALS['HOME_ROOT_URL']." est actif.\nVotre nom de connexion est [ %s ] et votre mot de passe est [ %s ].\nPar mesure de sécurité nous vous conseillons de supprimer ce message après en avoir pris connaissance.\nCordialement",
	'login_sub'=>'Compte',
	'noback'=>'Impossible d\'accéder à la page demandée',
	'fillin_password_please'=>'Merci de renseigner votre nouveau mot de passe.',
	'fillin_password_error_equals'=>'Mot de passe et confirmation du mot de passe doivent être identiques.',
	'fillin_password_error'=>'Un mot de passe doit comporter de 8 à 20 caractères. Les %s anciens mots de passe sont mémorisés et ne sont pas réutilisables.',
	'new_password_registration_text'=>'Bonjour %s,<br><br>votre nouveau mot de passe pour l\'identifiant [ %s ] sur le site [ '.$GLOBALS['HOME_ROOT_URL'].' ] a bien été enregistré. Il est valide %s jours, jusqu\'au %s.<br><br>Cordialement.',
	'new_password_registration_subject'=>'Enregistrement de votre nouveau mot de passe',
	'rgpd_password_format_text'=>'Un mot de passe doit avoir 12 caractères au minimum dont au moins 1 lettre majuscule, 1 lettre minuscule, 1 chiffre et un caractère spécial.',
	'console_password_error_complement'=>'Les %s anciens mots de passe sont mémorisés et ne sont pas réutilisables.',
	'zz');

