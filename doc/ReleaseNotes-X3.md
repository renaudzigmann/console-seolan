## 2022-09-05

### Authentification sécurité RGPD

* feat : amélioration de la génération de doc et du paramétrage (Richard Reynaud, Jean-Christophe Plat)
* feat : écran de configuration RGDP améliorés (Jean-Christophe Plat)
* feat : activation par défaut de l'obglet sécurité des groupes au lieu des utilisateurs (Richard Reynaud)
* feat : pas d'exécution du scheduler, fastdaemon et upgrades si /var/tzr/noscheduler existe (Renaud Zigmann)

### Divers

* fix : affichage des commenatires dans les options de templates (Rémi Lazarini)
* feat/fix : vendor Upgrade
* fix: Deepl ne fonctionnait pas correctement quand il y avait plusieurs InfoTree (Quentin Charrier)

### Applications

* feat : wizard de création du corail comme une application (Richard Reynaud)
* feat : ajout de la checklist au module application. (Rémi Lazarini)

### Application mobile

* feat : Configurateur d'application mobile générique (Vincent Castille),

### Divers modules

* fix : suppression d'un module : menage dans les sections (Jean-Christophe Plat)
* feat : module CRM, filtre mails vide et/ou anonymisés (Jean-Christophe Plat)
* feat : Module MailLogs, renvoi d'un mail (Jean-Christophe Plat)
* fix : DataSource, browse remplacement count(*) par SELECT FOUND_ROWS() (Jean-Christophe Plat)
* feat : Module CRM,  mise à jour d'une ligne (Jean-Christophe Plat)
* feat : SITRA, MAJ de l'import schema + ajout de tables standard avec les modules de consolidation (Rémi Lazarini)
* fix : Source de données, propagateOnOtherLangs ne pas écraser UPD, perte info "Traduction à jour"
* fix : module Cache, restreindre le ménage à un hôte (Jean-Christophe Plat),
* feat : module MailingList, procédure de demande de confirmation d'inscription (Rémi Lazarini)
* feat : module Médiathèque, traiter le cas de la securité sur les objets en front (Rémi Lazarini),
* feat : module Médiathèque, mise en oeuvre de la corbeille (Richard Reynaud),
* feat : module TarteAuCitron, url prsonnalisée sur "Voir le site Officiel) (Cécile Robin)
* fix : module Agenda, correction de la synchro et très nombreuses autres corrections (Richard Reynaud)
* feat : module CartV2, fonction pour récupérer le montant d'une commande sans session utilisateur (Cécile Robin)

### Ensemble de fiches

* fix : recherche rapide dans la sélection de lien en popup ne fonctionnait plus (Richard Reynaud)
* fix : correction édition colonne (Richard Reynaud),
* fix : archives, problème d'accès aux archives (Richard Reynaud),
* feat : prise en compte de l'option fieldsname dans les imports spécifiques (Rémi Lazarini)
* feat : affichage des fiches en plusieurs colonnes (Renaud Zigmann, Bruno Barroyer)
* fix : acceptation des mails dans les champs url en json (Rémi Lazarini)

### Gestion de rubriques

* feat : recherche SolR frontoffice (Richard Reynaud),

### Gestion des champs

* fix : Champ Date, recherche sur date future et date passée (Richard Reynaud)
* fix : Champ Pays, erreur groupemen (Jean-Christophe Plat)
* fix : Champ Fichier, affichage de fichier Autocad (Richard Renaud),
* feat : Champ Mot de passe, ajout d'une option d'affichage de force. Fonctionnement des templates génériques identique pour le backoffice et le frontoffice (Rémi Lazarini)
* fix : Champ Lien normalisés, contrôle de l'existence des composants (Richard Reynaud)


### Monétique

* fix : changemen notif boutique en cas de retour banque partiel (Brian Charles),
* fix : correction retour boutique (Cécile Robin)
* feat : ajout Axepta : nouvelle sol de paiement BNP (Cécile Robin),
* feat : vérification du montant avec celui de la commande si possible (Jean-Christophe Plat)

## 2022-03-11

Le fiat majeur de la version X3 est le redéveloppement et
l'utilisation généralisée des applications. 

### Applications

* feat refonte complète des applications (Antoine Roiron)
  stockage des configurations en base et sur disque
  adaptation du corail
  ajout du type de champ "Lien vers une application"
  gestion de la charte pour les corails

### Gestion de rubriques

* fix, prise en compte des zones dans l'export PDF (Benjamin Maxant)

### Ensemble de fiches

* feat, la recherche rapide (quickquery) passe en ajax : amélioration
  du code, amélioration des performances (Richard Reynaud)
* feat, optimisation des requêtes dans les sous-modules (Antoine Roiron)

### Agenda, Formulaires, divers

* fix, agenda, diverses corrections (Quentin Charrier)
* fix, Formulaires, corrections (Quentin Charrier)
* fix, Sitra, corrections (Benjamin Maxant)
* feat, Vendor update (Renaud Zigmann)
* fix, sources de données : perte d'OIDs dans la recherche (JC Plat)
* fix/feat, traduction automatique via deepl dans les ensembles de
  fiches et les gestionnaires de rubriques (Quentin Charrier)
* fix, FrontOfficeStats graph flash en chartjs (JC Plat)
* fix, geoip quand chargé depuis php (Antoine Roiron)

### Monétique

* fix/feat Sharegroop, mise à jour (Brian Charles)
* fix, systempay, correction (JC Plat)

### Mobiles et notifications

* feat, gestion de l'envoi des smartphones et des notifications push
  (Vincent Castille)
* feat, gestion de l'authentification automatique depuis mobile
  (Vincent Castille)
* feat, ajout d'une notion d'expiration des données stockées dans
  l'application mobile (Vincent Castille)  

### Champs

* fix, champ password, correction du code généré (Rémi Lazarini)
* feat, champ password, visualisation possible du mot de passe (Rémi
  Lazarini)
* feat, champ Thesaurus, ajout de la possibilité de paramétrer un
  ordre (Antoine Roiron)
* feat, champ date, suppression du date picker en 3 select,
  utilisation du date picker html5 à la place (Antoine Roiron)
* fix, Ensemble de chaines, correction des soid et oid (Antoine
  Roiron)
* feat, Champ URL, passage de texte court à texte long (Antoine
  Roiron)
* feat, Ajout d'un nouveau champ Video (Antoine Roiron) permettant de
  gérer les sous-titrages
* fix, Champ Icone, corrections de code (Rémi Lazarini)

### Authentification sécurité

* feat, fonction d'authentification automatique (Antoine Roiron)
* fix/sec, correction utilisation antivirus (Antoine Roiron)



