<?php

\Seolan\Core\Labels::$LABELS['Seolan_Core_SessionMessages']
= array(
	'lost_password_msg'=>'Hello,<br>This email contains a link to a page for fill in a new password for the account [ %s ] on the website '.$GLOBALS['HOME_ROOT_URL'].'.<br><br>To launch the procedure and change your password, follow <a href="%s">this link</a>.<br><br>This link will be deleted in '.($GLOBALS['TZR']['passwordtokendduration']??24).' hours and is valid once only.<br><br>If this request doesn\'t originate from you, please ignore this mail.<br><br>Cordially yours.',
	'lost_password_sub'=>'New Password',
	'expire_password_msg'=>'Hello,<br>Your password has expired. This email contains a link to a page for fill in in your new password for the account [ %s ] on the website '.$GLOBALS['HOME_ROOT_URL'].'<br>To launch the procedure and change your password, follow <a href="%s">this link</a>.<br/>This link will be deleted in '.($GLOBALS['TZR']['passwordtokendduration']??24).' hours and is valid once only.<br>Then, you\'ll be able to change your password once connected in the backoffice.<br>Cordially yours.',
	'expire_password_sub'=>'New Password',
        'login_msg'=>"Hello,\n\nYour account on ".TZR_SERVER_NAME.TZR_SHARE_ADMIN_PHP." has been activated.\nYour login name is [ %s ] and your password is [ %s ].\nFor the sake of security, please delete this email message after reading it.\nCordially yours",
        'login_sub'=>'Account',
	'noback'=>'Could not get access to this page',
	'fillin_password_please'=>'Please, fill in your new password.',
	'fillin_password_error'=>'Password and confirm fields must have the same value and contain from 6 to 20 characters.',
	'new_password_registration_text'=>'Hello %s,<br>your new password for your account %s at '.$GLOBALS['HOME_ROOT_URL'].' is registered. It will expire after %s days on %s.<br>Cordially yours.',
	'new_password_registration_subject'=>'Password registration',
	'rgpd_password_format_text'=>'A password must be at least 12 characters long, including at least 1 upper case letter, 1 lower case letter, 1 number and 1 special character.',
	'console_password_error_complement'=>'The %s old passwords are stored and cannot be reused.',
	'zz');
?>
