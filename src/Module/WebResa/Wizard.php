<?php
namespace Seolan\Module\WebResa;
/**
 * File Wizard
 * Wizard du module \Seolan\Module\WebResa\WebResa
 *
 * \author Julien Guillaume <julien.guillaume@xsalto.com>
 * \version 1 
 * Décembre 2012 pour seolan 8.1
 * 
 */

class Wizard extends \Seolan\Core\Module\Wizard {
  const version =  '1.0';  

             /*                      sqlname,   libellé lang base      ,  trans, auto_tr,   own,publish	*/
  static $schematables = array(
			       array('btab'		,'bname[%lang%]'		,'translatable'	,'auto_translate' ,'own' ,'publish' ),
			       array('TREKS'		,'Webresa - Séjour'		,0		,0		  ,0	 ,1	    ),
			       array('AGENCES'		,'Webresa - Agences'		,0		,0		  ,0	 ,1	    ),
			       array('ATTRIBUTS'	,'Webresa - Attributs'		,0		,0		  ,0	 ,0	    ),
			       array('DEPARTS'		,'Webresa - Départs'		,0		,0		  ,0	 ,1	    ),
			       array('TARIFS'		,'Webresa - Tarifs'		,0		,0		  ,0	 ,0	    ),
			       array('OPTIONS'		,'Webresa - Options'		,0		,0		  ,0	 ,0	    ),
			       array('MEDIAS'		,'Webresa - Médias'		,0		,0		  ,0	 ,1	    ),
			       array('PROGRAMMES'	,'Webresa - Programmes'		,0		,0		  ,0	 ,0	    ),
			       array('FICHEPRATIQUES'	,'Webresa - Fiche Pratiques'	,0		,0		  ,0	 ,0	    ),
			       array('CHAPITRESLIBRES'	,'Webresa - Chapitre libres' 	,0		,0		  ,0	 ,0	    ),
			       array('PAYS'	        ,'Webresa - Pays'               ,0		,0		  ,0	 ,1	    )
			       );

  static $schemafields = array(
array("table","forder","field","label[%lang%]","ftype","fcount","compulsory","published","browsable","translatable","queryable","multivalued","target","options[readonly]","options[checkbox]","options[display_format]","options[filter]","options[decimal]","options[listbox]"),
/*
      table,order,sql name,                libellé,          ftype,fcount,obl., publ., brows., trans., query. ,  multi.,   target,  ro, chbox, disp_format
*/
array('TREKS',0,'PUBLISH',     'Publié',"\Seolan\Field\Boolean\Boolean",    20,   0,     0,      0,      1,      1,        0,                    NULL,   0,     1,          '','',0,0),
array('TREKS',1,'trek_id',     'Webrésa trek id',"\Seolan\Field\ShortText\ShortText",    20,   1,     0,      1,      0,      1,        0,                    NULL,   1,     0,          '','',0,0),
array('TREKS',2,'webresa_id',        'Webrésa uid',"\Seolan\Field\ShortText\ShortText",    20,   1,     0,      0,      0,      1,        0,                    NULL,   1,     0,          '','',0,0),
array('TREKS',3,'code',             'Webrésa code',"\Seolan\Field\ShortText\ShortText",    20,   1,     0,      1,      0,      1,        0,                    NULL,   1,     0,          '','',0,0),
array('TREKS',4,'webresa_num',    'Webrésa numéro',"\Seolan\Field\ShortText\ShortText",    20,   1,     0,      0,      0,      1,        0,                    NULL,   1,     0,          '','',0,0),
array('TREKS',5,'href'     ,           'Url du circuit',"\Seolan\Field\Url\Url"      ,   255,   0,     0,      0,      0,      1,        0,                    NULL,   0,     0,          '','',0,0),
array('TREKS',6,'pays'     ,           'Pays'          ,"\Seolan\Field\Link\Link",   150,   0,     0,      1,      0,      1,        0, 'tzrprefix_PAYS',   1,     0,          '','',0,0),
array('TREKS',7,'agences_oid',         'Agence'        ,"\Seolan\Field\Link\Link"     ,   150,   0,     0,      1,      0,      1,        0,'tzrprefix_AGENCES'     ,   1,     0,          '','',0,0),
array('TREKS',8,'region'     ,         'Region'        ,"\Seolan\Field\ShortText\ShortText",   150,   0,     0,      0,      0,      1,        0,               NULL,   1,     0,          '','',0,0),
array('TREKS',9,'mode'       ,         'Mode'          ,"\Seolan\Field\StringSet\StringSet",   150,   0,     0,      1,      0,      1,        0,               NULL,   1,     0,          '','',0,0),
array('TREKS',10,'type_oid'   ,         'Type'          ,"\Seolan\Field\Link\Link"     ,   150,   0,     0,      0,      0,      1,        0,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'typer\'',0,0),
array('TREKS',11,'libelle'   ,         'Titre'          ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     1,      1,      1,      1,        0,                NULL , 0,     0,          '','',0,0),
array('TREKS',18,'libelle1'   ,         'Sous Titre'          ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     0,      0,      0,      0,        0,                NULL , 0,     0,          '','',0,0),

/*
      table,order,sql name,                libellé,          ftype,fcount,obl., publ., brows., trans., query. ,  multi.,   target,  ro, chbox, disp_format
*/
array('TREKS',12,'duree'   ,  'Nombre de jours'   ,"\Seolan\Field\Real\Real",   10,   0,     0,      0,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('TREKS',13,'duree_nuit'   ,  'Nombre de nuits'   ,"\Seolan\Field\Real\Real",   10,   0,     0,      0,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('TREKS',14,'marche',  'Nombre de jour de marche',"\Seolan\Field\Real\Real",   10,   0,     0,      0,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('TREKS',15,'niveau_oid','Niveau requis des participants',"\Seolan\Field\Link\Link",   150,   0,     0,      0,      0,      1,        0,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'niveau\'',0,0),
array('TREKS',16,'niveau_tech_oid','Niveau technique requis par l\'activité','\Seolan\Field\Link\Link',   150,   0,     0,      0,      0,      1,        0,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'niveau_tech\'',0,0),
array('TREKS',17,'hebergements','Hebergements proposés','\Seolan\Field\Link\Link',   150,   0,     0,      0,      0,      1,        1,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'hebergement\'',0,0),
array('TREKS',18,'auteur'   ,         'Auteur'          ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     0,      0,      0,      0,        0,                NULL , 0,     0,          '','',0,0),

/*
      table,order,sql name,                libellé,          ftype,fcount,obl., publ., brows., trans., query. ,  multi.,   target,  ro, chbox, disp_format
*/
array('TREKS',19,'themes','Thèmes du circuit','\Seolan\Field\Link\Link',   150,   0,     0,      0,      0,      1,        1,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'theme\'',0,0),
array('TREKS',20,'activite','Activité','\Seolan\Field\Link\Link',   150,   0,     0,      0,      0,      1,        0,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'activite\'',0,0),
array('TREKS',21,'portage','Portage','\Seolan\Field\Link\Link',   150,   0,     0,      0,      0,      0,        0,'tzrprefix_ATTRIBUTS', 1,     0,          '','att_type = \'portage\'',0,0),
array('TREKS',22,'nb_jour_portage',  'Nombre de jour de portage',"\Seolan\Field\Real\Real",   10,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('TREKS',23,'circuit_exception',  'Circuit d\'exception',"\Seolan\Field\Boolean\Boolean",   0,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('TREKS',24,'ville_depart', 'Départ',"\Seolan\Field\ShortText\ShortText",   255,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('TREKS',25,'geoloc_depart', 'Localisation du départ', "\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates",   0,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('TREKS',26,'ville_arrivee', 'Arrivée',"\Seolan\Field\ShortText\ShortText",   255,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('TREKS',27,'geoloc_arrivee', 'Localisation de l\'arrivée', "\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates",   0,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('TREKS',28,'prix_minimum',  'Prix minimum de l\'offre',"\Seolan\Field\Real\Real",   10,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',2,0),
array('TREKS',29,'prix_maximum',  'Prix maximum de l\'offre',"\Seolan\Field\Real\Real",    10,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',2,0),
array('TREKS',30,'tel',  'N° de téléphone',"\Seolan\Field\ShortText\ShortText",     20,   0,     0,      0,      0,      1,        0,        NULL,       0,     0,     '','',0,0),
array('TREKS',31,'commentaire',  'Commentaire',"\Seolan\Field\Text\Text",     80,   0,     0,      0,      1,      0,        0,        NULL,       0,     0,     '','',0,0),
array('TREKS',32,'resume',  'Description',"\Seolan\Field\RichText\RichText",     80,   0,     0,      0,      1,      0,        0,        NULL,       0,     0,     '','',0,0),
array('TREKS',32,'complement',  'Complément programme',"\Seolan\Field\RichText\RichText",     80,   0,     0,      0,      1,      0,        0,        NULL,       0,     0,     '','',0,0),

array('TREKS',33,'fiche_technique',  'Url de la fiche technique',"\Seolan\Field\ShortText\ShortText",    255,   0,     0,      0,      1,      0,        0,        NULL,       1,     0,     '','',0,0),
array('TREKS',34,'nomaj',  'Ne pas mettre à jour depuis Webresa',"\Seolan\Field\Boolean\Boolean",    0,   0,     0,      1,      1,      0,        0,        NULL,       0,     1,     '','',0,0),
array('TREKS',35,'datePublication',  'Date de publication de la fiche technique',"\Seolan\Field\Date\Date",    0,   0,     0,      0,      0,      0,        0,        NULL,       0,     1,     '','',0,0),
array('TREKS',36,'dateLastModification',  'Date de dernière modification de la fiche technique',"\Seolan\Field\Date\Date",    0,   0,     0,      0,      0,      0,        0,        NULL,       0,     1,     '','',0,0),
array('TREKS',37,'urlIframeDate',  'Url de l\'iframe de reservation',"\Seolan\Field\Url\Url",    0,   0,     0,      0,      0,      0,        0,        NULL,       0,     1,     '','',0,0),
array('TREKS',37,'url2IframeDate',  'Url de l\'iframe de reservation 2',"\Seolan\Field\Url\Url",    0,   0,     0,      0,      0,      0,        0,        NULL,       0,     1,     '','',0,0),
/*
      table,order,sql name,                libellé,          ftype,fcount,obl., publ., brows., trans., query. ,  multi.,   target,  ro, chbox, disp_format,filtre,decimal
*/
array('AGENCES',1,'libelle' ,      'Nom'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('AGENCES',2,'code' ,      'Code'          ,"\Seolan\Field\ShortText\ShortText"  ,    20,   0,     0,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('AGENCES',3,'date_crea' ,'Date de création du flux',"\Seolan\Field\Date\Date"  ,    0,   0,     0,      1,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('ATTRIBUTS',1,'libelle' ,      'Nom'      ,"\Seolan\Field\ShortText\ShortText"  ,    255,   0,     1,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('ATTRIBUTS',2,'att_type' ,      'type'       ,"\Seolan\Field\StringSet\StringSet"  ,    150,   0,     0,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('ATTRIBUTS',3,'agence' ,      'Agence'       ,"\Seolan\Field\Link\Link"  ,    0,   0,     0,      1,      0,      1,        0,'tzrprefix_AGENCES' , 1,     0,          '','',0,0),
array('ATTRIBUTS',4,'libelleweb' ,      'Libellé site internet'      ,"\Seolan\Field\ShortText\ShortText"  ,    255,   0,     1,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('MEDIAS',1,'typem' ,   'type de média'       ,"\Seolan\Field\StringSet\StringSet" ,    0,   0,     1,      1,      0,      1,        0,NULL , 0,     0,          '','',0,0),
array('MEDIAS',2,'ordre' ,   'Ordre'       ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('MEDIAS',3,'media' ,   'Média'       ,"\Seolan\Field\File\File",    0,   0,     0,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('MEDIAS',4,'source' ,   'Url d\'origine'       ,"\Seolan\Field\ShortText\ShortText",    255,   0,     0,      0,      0,      0,        0,NULL , 1,     0,          '','',0,0),
array('MEDIAS',5,'treks_id' , 'Circuit concerné' ,"\Seolan\Field\Link\Link",    0,   1,     0,      0,      0,      0,        0,'tzrprefix_TREKS', 0,     0,          '','',0,0),

array('DEPARTS',1,'date_depart' ,   'Date de départ' ,"\Seolan\Field\Date\Date",    255,   1,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('DEPARTS',2,'date_fin' ,   'Date de fin' ,"\Seolan\Field\Date\Date",    255,   1,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('DEPARTS',3,'prix' ,   'Prix à partir de ' ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',2,0),
array('DEPARTS',4,'ancien_prix' ,   'Ancien prix ' ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',2,0),
array('DEPARTS',5,'etat' ,   'Etat'           ,"\Seolan\Field\StringSet\StringSet",    0,   0,     0,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('DEPARTS',6,'disponibilite' , 'Nombre de places disponibles' ,"\Seolan\Field\Real\Real",    10,   0,     0,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
/*
      table,order,sql name,                libellé,          ftype,fcount,obl., publ., brows., trans., query. ,  multi.,   target,  ro, chbox, disp_format,filtre,decimal
*/
array('DEPARTS',7,'capacite' , 'Nombre maximum de places' ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('DEPARTS',8,'resa' , 'Nombre de places réservées' ,"\Seolan\Field\Real\Real",    10,   0,     0,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('DEPARTS',9,'place_option' , 'Nombre de places en option' ,"\Seolan\Field\Real\Real",   10,   0,     0,      1,      0,      0,        0,NULL , 0,     0,          '','',0,0),
array('DEPARTS',10,'treks_id' , 'Circuit concerné' ,"\Seolan\Field\Link\Link",    0,   1,     0,      0,      0,      0,        0,'tzrprefix_TREKS', 0,     0,          '','',0,0),
array('DEPARTS',11,'nomaje',  'Ne pas mettre à jour l\'état depuis Webresa',"\Seolan\Field\Boolean\Boolean",    0,   0,     0,      1,      1,      0,        0,        NULL,       0,     1,     '','',0,0),
array('DEPARTS',12,'nomajt',  'Ne pas mettre à jour tarifs et options depuis Webresa',"\Seolan\Field\Boolean\Boolean",    0,   0,     0,      1,      1,      0,        0,        NULL,       0,     1,     '','',0,0),

array('TARIFS',1,'libelle' ,      'Nom'      ,"\Seolan\Field\ShortText\ShortText"  ,    255,   0,     1,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('TARIFS',2,'montant' ,   'Prix ' ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',2,0),
array('TARIFS',3,'ancien_montant' ,   'Ancien prix ' ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',2,0),
array('TARIFS',4,'depart_id' , 'Date concernée' ,"\Seolan\Field\Link\Link",    0,   1,     0,      1,      0,      0,        0,'tzrprefix_DEPARTS', 0,     0,          '','',0,0),
array('TARIFS',0,'PUBLISH',     'Publié',"\Seolan\Field\Boolean\Boolean",    20,   0,     0,      0,      1,      1,        0,                    NULL,   0,     1,          '','',0,0),

array('OPTIONS',4,'depart_id' , 'Date concernée' ,"\Seolan\Field\Link\Link",    0,   1,     0,      1,      0,      0,        0,'tzrprefix_DEPARTS', 0,     0,          '','',0,0),
array('OPTIONS',1,'libelle' ,      'Nom'      ,"\Seolan\Field\ShortText\ShortText"  ,    255,   0,     1,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('OPTIONS',2,'montant' ,   'Prix ' ,"\Seolan\Field\Real\Real",    10,   0,     1,      1,      0,      0,        0,NULL , 0,     0,          '','',2,0),
array('OPTIONS',3,'obligatoire',  'Obligatoire',"\Seolan\Field\Boolean\Boolean",   0,   0,     0,      0,      0,      1,        0,        NULL , 0,     0,     '','',0,0),
array('OPTIONS',4,'mode','Mode'          ,"\Seolan\Field\StringSet\StringSet",   150,   0,     0,      1,      0,      1,        0,               NULL,   1,     0,          '','',0,0),
array('OPTIONS',0,'PUBLISH',     'Publié',"\Seolan\Field\Boolean\Boolean",    20,   0,     0,      0,      1,      1,        0,                    NULL,   0,     1,          '','',0,0),

array('PROGRAMMES',1,'nom_jour' ,      'Libelle'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('PROGRAMMES',2,'ordre' ,      'Ordre'        ,"\Seolan\Field\Real\Real"  ,   10,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('PROGRAMMES',3,'webresa_id' ,      'Id Webresa'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     0,     0,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('PROGRAMMES',4,'description' ,      'Description'        ,"\Seolan\Field\RichText\RichText"  ,   255,   0,     0,     1,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('PROGRAMMES',5,'treks_id' , 'Circuit concerné' ,"\Seolan\Field\Link\Link",    0,   1,     0,      0,      0,      0,        0,'tzrprefix_TREKS', 0,     0,          '','',0,0),

array('FICHEPRATIQUES',1,'libelle' ,      'Libelle'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('FICHEPRATIQUES',2,'ordre' ,      'Ordre'        ,"\Seolan\Field\Real\Real"  ,   10,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('FICHEPRATIQUES',3,'webresa_id' ,      'Id Webresa'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     0,     0,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('FICHEPRATIQUES',4,'description' ,      'Description'        ,"\Seolan\Field\RichText\RichText"  ,   255,   0,     0,     1,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('FICHEPRATIQUES',5,'treks_id' , 'Circuit concerné' ,"\Seolan\Field\Link\Link",    0,   1,     0,      0,      0,      0,        0,'tzrprefix_TREKS', 0,     0,          '','',0,0),

array('CHAPITRESLIBRES',1,'libelle' ,      'Libelle'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('CHAPITRESLIBRES',2,'ordre' ,      'Ordre'        ,"\Seolan\Field\Real\Real"  ,   10,   0,     1,      1,      0,      1,        0,                NULL , 0,     0,          '','',0,0),
array('CHAPITRESLIBRES',3,'webresa_id' ,      'Id Webresa'        ,"\Seolan\Field\ShortText\ShortText"  ,   255,   0,     0,     0,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('CHAPITRESLIBRES',4,'description' ,      'Description'        ,"\Seolan\Field\RichText\RichText"  ,   255,   0,     0,     1,      0,      0,        0,                NULL , 1,     0,          '','',0,0),
array('CHAPITRESLIBRES',5,'treks_id' , 'Circuit concerné' ,"\Seolan\Field\Link\Link",    0,   1,     0,      0,      0,      0,        0,'tzrprefix_TREKS', 0,     0,          '','',0,0),
array('PAYS',1,'libelle' ,      'Nom'      ,"\Seolan\Field\ShortText\ShortText"  ,    255,   0,     1,      1,      0,      1,        0,                NULL , 1,     0,          '','',0,0),
array('PAYS',3,'agences' ,      'Agences'       ,"\Seolan\Field\Link\Link"  ,    0,   0,     0,      1,      0,      1,        1,'tzrprefix_AGENCES' , 1,     0,          '','',0,0),
/*
      table,order,sql name,                libellé,          ftype,fcount,obl., publ., brows., trans., query. ,  multi.,   target,  ro, chbox, disp_format,filtre,decimal
*/


);

  static $schemastringset = array(
				  array('table','field','soid','label'),
				  array('TREKS','mode','LIBERTE','Liberté'),
				  array('TREKS','mode','ACCOMPAGNE','Accompagné'),
				  array('ATTRIBUTS','att_type','typer','type de randonnée'),
				  array('ATTRIBUTS','att_type','niveau','Niveau requis des participant'),
				  array('ATTRIBUTS','att_type','niveau_tech','Niveau requis pour l\'activité'),
				  array('ATTRIBUTS','att_type','hebergement','Hebergement'),
				  array('ATTRIBUTS','att_type','theme','Thèmes'),
				  array('ATTRIBUTS','att_type','activite','Activité'),
				  array('ATTRIBUTS','att_type','portage','Portage'),
				  array('MEDIAS','typem','image','Image'),
				  array('MEDIAS','typem','pdf','Pdf'),
				  array('DEPARTS','etat','disponible','Disponible'),
				  array('DEPARTS','etat','confirme','Confirmé'),
				  array('DEPARTS','etat','annule','Annulé'),
				  array('DEPARTS','etat','complet','Complet'),
				  array('OPTIONS','mode','1','Forfaitaire'),
				  array('OPTIONS','mode','2','Par personne'),
				  array('OPTIONS','mode','3','% du prix du séjour')
				  );
  
  static $schemafieldsOptions = array(
                                      array("table","field" ,"options[usealt]","options[display_format]"),
                                      array('TREKS','href' ,     1),
                                      array('TREKS','urlIframeDate' ,     1),
                                      array('TREKS','url2IframeDate' ,     1),
                                      array('TREKS','prix_minimum' ,     0,''),
                                      array('TREKS','prix_maximum' ,     0,''),
                                );
  static function getSchemaTables(){
    $schema = self::$schematables;
    $schema[0][1] = str_replace('%lang%',TZR_DEFAULT_LANG,$schema[0][1]);
  
    return $schema;
  }
  static function getSchemaFields(){
    $schema = self::$schemafields;
    $schema[0][3] = str_replace('%lang%',TZR_DEFAULT_LANG,$schema[0][3]);
  
    return $schema;
  }
  function __construct($ar=NULL) {
    $this->schematables[0][1] = str_replace('%lang%',TZR_DEFAULT_LANG,$this->schematables[0][1]);
    parent::__construct($ar);
  }

  function istep1() {
    global $TZR_LANGUAGES;
    parent::istep1();
    \Seolan\Core\Labels::loadLabels('Seolan_Module_WebResa_WebResa');
    $labels = \Seolan\Core\Labels::$LABELS['\Seolan\Module\WebResa\WebResa'];

    //compte les modules du même type
    $tot = 1;
    foreach(\Seolan\Core\Module\Module::$_mcache as $moid => &$ors) {
      if($ors['TOID']==XMODWEBRESA_TOID) $tot++;
    }
    //Prefixe des tables
    $this->_module->prefix = 'WR'.$tot.'_';
    //commentaire
    foreach($TZR_LANGUAGES as $keylang => $adminlang){
      $this->_module->comment[$keylang] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_WebResa_WebResa',"modulename");
    }
    $this->_options->setOpt($label['tableprefix'], 'prefix', 'text');
  }
  function istep2($ar = NULL) {
    \Seolan\Core\Labels::loadLabels('Seolan_Module_WebResa_WebResa');
    $labels = \Seolan\Core\Labels::$LABELS['\Seolan\Module\WebResa\WebResa'];

    if(strlen($this->_module->prefix)>5) {
      $this->_step = 1;
      setSessionVar('message','Le Préfix ne doit pas excéder 5 caractères.');
      $this->irun($ar);
      return;
    }

    $this->_module->fluxurl = 'http://www.webresa.fr/rss/getWebResaFlow.aspx?key=CODEFLUX&id=AGENCE&dest=SITE';
    $this->_options->setOpt($labels['fluxurl'], 'fluxurl', 'text',array('size'=>150),'');
    $this->_options->setOpt($labels['agenceid'], 'agenceid', 'text');
    $this->_options->setOpt($labels['codeflux'], 'codeflux', 'list',array('labels'=>array($labels['codefluxall'],$labels['codefluxsite'],$labels['codefluxsiteextended']),'values'=>array('all_etendu','all_etendu_export','all_dates_etendu_export')),'all_etendu');
    $this->_options->setOpt($labels['codesite'], 'codesite', 'text',NULL,'');
    $this->_options->setOpt($labels['useiframe'], 'onlinebooking', 'boolean',0,'');
    $this->_options->setOpt($labels['importoptionstarifs'], 'importoptionstarifs', 'boolean',0,'');

    $this->_module->ipsortante = gethostbyname($_SERVER['HTTP_HOST']);
    $this->_options->setOpt($labels['iptodeclare'],'ipsortante','text','',NULL);

  }

  function iend($ar=NULL) {
    $message = self::updateSchema($this->_module->prefix);
    $this->_module->table = $this->_module->prefix.'TREKS';
    $this->_module->submodmax = 6;

    $moid = parent::iend();

    \Seolan\Core\Module\Module::clearCache();
    $mod = \Seolan\Core\Module\Module::objectFactory($moid);
    $message .= self::updateProperties($mod);
    //add Templates 
    $message .= self::addTemplates($module);

    \Seolan\Core\DbIni::setStatic($moid.'version',self::version);
    $message .= 'Schéma version '.self::version.' installé<br/>';
    \Seolan\Core\Shell::toScreen2('','message',$message);
    return $moid;
  }
  /**
   * Création ou Mise à jour des tables / champs
   * 
   */
  static function updateSchema($prefix){
    $mod_ds = new \Seolan\Module\DataSource\DataSource(array('tplentry'=>TZR_RETURN_DATA));
    $message = $mod_ds->importSources(array('data' => self::getSchemaTables(), 'prefixSQL' => $prefix, 'endofline' => "\n"));
    
    $tmpfile = TZR_TMP_DIR.'fields'.date('Ymd').'.csv';
    $fp = fopen($tmpfile, 'w');
    foreach (self::getSchemaFields() as $fields) {
      fputcsv($fp, $fields,';','"');
    }
    fclose($fp);
    $message .= $mod_ds->importFields(array('file' => $tmpfile, 'prefixSQL' => $prefix, 'endofline' => "\n"));
    unlink($tmpfile);
    

    //options pécifiques
    $tmpfile = TZR_TMP_DIR.'fields'.date('Ymd').'2.csv';
    $fp = fopen($tmpfile, 'w');
    foreach (self::$schemafieldsOptions as $fields) {
      fputcsv($fp, $fields,';','"');
    }
    fclose($fp);
    $message .= $mod_ds->importFields(array('file' => $tmpfile, 'prefixSQL' => $prefix, 'endofline' => "\n"));
    unlink($tmpfile);
    

    /// définition des option de génération d'oid
    $xt = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$prefix.'AGENCES');
    $xt->procEditProperties(array('options'=>array('bname'=>$xt->getLabel(),'oidstruct1'=>'code','translate'=>$xt->getTranslatable(),
                                  'auto_translate'=>$xt->getAutoTranslate())
                                  
                                  ));
    $xt = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$prefix.'TREKS');
    $xt->procEditProperties(array('options'=>array('bname'=>$xt->getLabel(),'oidstruct1'=>'trek_id','translate'=>$xt->getTranslatable(),
                                  'auto_translate'=>$xt->getAutoTranslate())
                                  
                                  ));


    $message .= "- Ensemble de chaines :<br/>";
    //initialisation des ensenble de chaine
    foreach(self::$schemastringset as $key => $stringsetdef){
      if($key>0){
	$xt = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$prefix.$stringsetdef[0]);
	if($xt)
	$f = $xt->getField($stringsetdef[1]);
	if($f){
	  $res = $f->newString($stringsetdef[3],$stringsetdef[2]);
	  if($res[0]) $message .= $stringsetdef[1].'->'.$stringsetdef[3].' Added <br>';
	}
      }
    }
    return $message;
  }
  static function updateProperties($module){
    $prefix = $module->prefix;
    $modulename = $module->getLabel();
    ///creation des autres modules 
    $message .= "- Création des modules :<br/>";
    
    ///tables attributs
    $modAttributs = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'ATTRIBUTS',true,false,false,true);
    if(count($modAttributs)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Attributs";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'ATTRIBUTS';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'libelle';
      $mod2->_module->trackchanges = 0;
      $moidAttribut = $mod2->iend();
      $message .= "Module Attributs créé: moid $moidAttribut<br>";
    }
    ///Agences
    $modAgences = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'AGENCES',true,false,false,true);
    if(count($modAgences)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Agences";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'AGENCES';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'libelle';
      $mod2->_module->trackchanges = 0;
      $moidAgence = $mod2->iend();
      $message .= "Module Agences créé: moid $moidAgence<br>";
    }
    ///Pays
    $modPays = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'PAYS',true,false,false,true);
    if(count($modPays)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Pays";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'PAYS';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'libelle';
      $mod2->_module->trackchanges = 0;
      $moidPays = $mod2->iend();
      $message .= "Module Pays créé: moid $moidPays<br>";
    }else{
      $modPays = array_keys($modPays);
      $moidPays =  $modPays[0];
    }

    //Départ
    $modDepart = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'DEPARTS',true,false,false,true);
    if(count($modDepart)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Départ";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'DEPARTS';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'date_depart ASC';
      $mod2->_module->trackchanges = 1;
      $moidDeparts = $mod2->iend();
      $message .= "Module Depart créé: moid $moidDeparts<br>";
    }else{
      $modDepart = array_keys($modDepart);
      $moidDeparts = $modDepart[0];
    }
    //Tarifs 
    $modTarifs = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'TARIFS',true,false,false,true);
    if(count($modTarifs)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod3 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod3->_module->modulename = $modulename."- Tarifs";
      $mod3->_module->group = $module->group;
      $mod3->_module->table = $prefix.'TARIFS';
      $mod3->_module->home = 1;
      $mod3->_module->order = 'libelle ASC';
      $mod3->_module->trackchanges = 1;
      $moidTarifs = $mod3->iend();
      $message .= "Module Tarifs créé: moid $moidTarifs<br>";
    }else{
      $modTarifs = array_keys($modTarifs);
      $moidTarifs = $modTarifs[0];
      $message .= "Module Tarifs existant: moid $moidTarifs<br>";
    }
    //Options 
    $modOptions = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'OPTIONS',true,false,false,true);
    if(count($modOptions)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod3 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod3->_module->modulename = $modulename."- Options";
      $mod3->_module->group = $module->group;
      $mod3->_module->table = $prefix.'OPTIONS';
      $mod3->_module->home = 1;
      $mod3->_module->order = 'libelle ASC';
      $mod3->_module->trackchanges = 1;
      $moidOptions = $mod3->iend();
      $message .= "Module Options créé: moid $moidOptions<br>";
    }else{
      $modOptions = array_keys($modOptions);
      $moidOptions = $modOptions[0];
      $message .= "Module Options existant: moid $moidTarifs<br>";
    }
    //option et tarif en sous module des departs
    $moduleDepart = \Seolan\Core\Module\Module::objectFactory($moidDeparts);
    $moduleDepart->submodmax = 3;
    $arD['options']['submodmax'] = 6;
    $arD['options']['ssmodtitle1'] = 'Tarifs';
    $arD['options']['ssmodfield1'] = 'depart_id';
    $arD['options']['ssmod1'] = $moidTarifs;
    $arD['options']['ssmodactivate_additem1'] = 1;
    $arD['options']['ssmoddependent1'] = 1;
    $arD['options']['ssmodtitle2'] = 'Options';
    $arD['options']['ssmodfield2'] = 'depart_id';
    $arD['options']['ssmod2'] = $moidOptions;
    $arD['options']['ssmodactivate_additem2'] = 1;
    $arD['options']['ssmoddependent2'] = 1;
    $moduleDepart->procEditProperties($arD);

    
    //Programme
    $modProg = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'PROGRAMMES',true,false,false,true);
    if(count($modProg)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Programmes";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'PROGRAMMES';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'ordre ASC';
      $mod2->_module->trackchanges = 0;
      $moidProg = $mod2->iend();
      $message .= "Module Programme créé: moid $moidProg <br>";
    }else{
      $modProg = array_keys($modProg);
      $moidProg = $modProg[0];
    }
    //Fiche pratique
    $modFiche = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'FICHEPRATIQUES',true,false,false,true);
    if(count($modFiche)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Fiches pratiques";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'FICHEPRATIQUES';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'ordre ASC';
      $mod2->_module->trackchanges = 0;
      $moidFiche = $mod2->iend();
      $message .= "Module Fiche pratique créé: moid $moidFiche <br>";
    }else{
      $modFiche = array_keys($modFiche);
      $moidFiche = $modFiche[0];
    }

    //Chapitre libre
    $modChapitre = \Seolan\Core\Module\Module::modulesUsingTable($prefix.'CHAPITRESLIBRES',true,false,false,true);
    if(count($modFiche)==0){
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Chapitre Libre";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'CHAPITRESLIBRES';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'ordre ASC';
      $mod2->_module->trackchanges = 0;
      $moidChapitre = $mod2->iend();
      $message .= "Module Chapitre libre créé: moid $moidChapitre <br>";
    }else{
      $modChapitre = array_keys($modChapitre);
      $moidChapitre = $modChapitre[0];
    }

    // un peu de marge + conserver ceux en place
    if ($module->submodmax <= 6)
      $module->submodmax = 8;

    $ar['options']['submodmax'] = $module->submodmax;
    if(empty($module->ssmod1)){ /// Sou module Média Image
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Médias Image";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'MEDIAS';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'typem, ordre ASC';
      $mod2->_module->filter = 'typem =  \'image\'';
      $mod2->_module->trackchanges = 0;
      $moidMedia = $mod2->iend();
      $message .= "Module Medias Image créé: moid $moidMedia<br>";

      $ar['options']['ssmodtitle1'] = 'Médias Image';
      $ar['options']['ssmodfield1'] = 'treks_id';
      $ar['options']['ssmod1'] = $moidMedia;
      $ar['options']['ssmodactivate_additem1'] = 1;
      $ar['options']['ssmoddependent1'] = 1;
    }

    if(empty($module->ssmod2)){ /// Sous module Média Pdf
      \Seolan\Core\Module\Module::clearCache();
      $mod2 = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
      $mod2->_module->modulename = $modulename."- Médias Pdf";
      $mod2->_module->group = $module->group;
      $mod2->_module->table = $prefix.'MEDIAS';
      $mod2->_module->home = 1;
      $mod2->_module->order = 'typem, ordre ASC';
      $mod2->_module->filter = 'typem =  \'pdf\'';
      $mod2->_module->trackchanges = 0;
      $moidMedia = $mod2->iend();
      $message .= "Module Medias Pdf créé: moid $moidMedia<br>";

      $ar['options']['ssmodtitle2'] = 'Médias Pdf';
      $ar['options']['ssmodfield2'] = 'treks_id';
      $ar['options']['ssmod2'] = $moidMedia;
      $ar['options']['ssmodactivate_additem2'] = 1;
      $ar['options']['ssmoddependent2'] = 2;
    }

    if(empty($module->ssmod3)){
      $ar['options']['ssmodtitle3'] = 'Départs';
      $ar['options']['ssmodfield3'] = 'treks_id';
      $ar['options']['ssmod3'] = $moidDeparts;
      $ar['options']['ssmodactivate_additem3'] = 0;
      $ar['options']['ssmoddependent3'] = 1;
    }
    if(empty($module->ssmod4)){
      $ar['options']['ssmodtitle4'] = 'Programme';
      $ar['options']['ssmodfield4'] = 'treks_id';
      $ar['options']['ssmod4'] = $moidProg;
      $ar['options']['ssmodactivate_additem4'] = 1;
      $ar['options']['ssmoddependent4'] = 1;
    }
    if(empty($module->ssmod5)){
      $ar['options']['ssmodtitle5'] = 'Fiche pratique';
      $ar['options']['ssmodfield5'] = 'treks_id';
      $ar['options']['ssmod5'] = $moidFiche;
      $ar['options']['ssmodactivate_additem5'] = 1;
      $ar['options']['ssmoddependent5'] = 1;
    }
    if(empty($module->ssmod6)){
      $ar['options']['ssmodtitle6'] = 'Chapitre libre';
      $ar['options']['ssmodfield6'] = 'treks_id';
      $ar['options']['ssmod6'] = $moidChapitre;
      $ar['options']['ssmodactivate_additem6'] = 1;
      $ar['options']['ssmoddependent6'] = 1;
    }

    // si il existaient des modules ajoutés manuellement on les reconduits
    for($j=7; $j<=$module->submodmax; $j++){
      $ar['options']['ssmodtitle'.$j] = $module->{'ssmodtitle'.$j};
      $ar['options']['ssmodfield'.$j] = $module->{'ssmodfield'.$j};
      $ar['options']['ssmod'.$j] = $module->{'ssmod'.$j};
      $ar['options']['ssmodactivate_additem'.$j] = $module->{'ssmodactivate_additem'.$j};
      $ar['options']['ssmoddependent'.$j] = $module->{'ssmoddependent'.$j};
    }

    if(empty($module->moidagence) && $moidAgence){
      $ar['options']['moidagences'] = $moidAgence;
    }
    if(empty($module->moidattributs) && $moidAttribut){
      $ar['options']['moidattributs'] = $moidAttribut;
    }
    if(empty($module->moidpays) && $moidPays){
      $ar['options']['moidpays'] = $moidPays;
    }
    $ar['options']['order'] = 'agences_oid,trek_id';
    $ar['options']['defaultispublished'] = 1;
    $ar['options']['trackchanges'] = 1;

    $module->procEditProperties($ar);
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    clearSessionVar(TZR_SESSION_PREFIX.'modmenu');
    setSessionVar('_reloadmenu',1);
    setSessionVar('_reloadmods',1);
    
    return $message;
  }
  static function addTemplates($module){
    $message = "Ajout des templates\n";
    $tpl = array('liste'=>array('title'=>'Liste des randonnées Webresa','name'=>'liste-treks-div.html','content'=>'<%include file="`$smarty.const.TZR_SHARE_DIR`Module/WebResa.liste-treks-div.html"%>','function'=>'\Seolan\Module\Table\Table::browse,\Seolan\Module\Table\Table::procQuery'),
                 'fiche'=>array('title'=>'Visualisation d\'une randonnée Webresa','name'=>'fiche.html','content'=>'<%include file="`$smarty.const.TZR_SHARE_DIR`Module/WebResa.fiche.html"%>','function'=>'\Seolan\Module\Table\Table::display'),
                 'resa'=>array('title'=>'Iframe de reservation webresa','name'=>'resa.html','content'=>'<%include file="`$smarty.const.TZR_SHARE_DIR`Module/WebResa.reservation.html"%>','function'=>'\Seolan\Module\WebResa\WebResa::reservation'),
                 'recherche'=>array('title'=>'Formulaire de recherche avec date','name'=>'link-disp-webresaqueryform.html','content'=>'<%include file="`$smarty.const.TZR_SHARE_DIR`Module/InfoTree.defaulttemplates/disp-webresaqueryform.html"%>','function'=>'\Seolan\Module\WebResa\WebResa::query')
                 );
    
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    foreach($tpl as $name=>$value){
      $file = TZR_TMP_DIR.$value['name'];
      file_put_contents($file,$value['content']); 
      
      $insert = $x->procInput(array('title'=>$value['title'],
                                    'modid'=>'',
                                    'gtype'=>'function',
                                    'tab'=>'',
                                    'functions'=>$value['function'],
                                    'disp'=>$file,
                                    'modidd'=>$module->_moid,
                                    'options'=>array('disp'=>array('del'=>false),
                                                     'edit'=>array('del'=>false)
                                                     ),
                                    '_updateifexists'=>1,
                                    'newoid'=>'TEMPLATES:'.$name.$module->_moid
                                    )
                              );
      $message .= $insert['message']."\n";
    }
    return $message; 
  }
}

?>
