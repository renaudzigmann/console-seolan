<?php
  /**
   * les annuaires d'utilisateurs
   * local directory est obligatoire surchargeable dans le tzr/directories-configurations.php, en particulier pour les filtres isQualified et exclusiveUser
   * par defaut : 
   * tout login est "exclusif" à l'annuaire local 
   */
return [
	// k = id dans la conf qui suit, v = ordre de préséance
	// ordre = false pour désactiver un des annuaires par défaut
	'directoriesId'=>['local'=>2],
	'local'=>['classname'=>'\Seolan\Core\Directory\LocalDirectory',
		  'label'=>'Annuaire local',
		  'config'=>['loginFilter'=>'/^\S*$/',
			     'exclusiveFilter'=>'/^(.*)$/'
			     ]
		  ]
	];
