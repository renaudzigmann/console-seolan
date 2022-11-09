# Gestionnaire d'applications

## Résumé

Les application sont éxecutés lorsque il y a correspondance avec le nom de domaine configuré.

## Documentations spécifiques

### Sous-site

Lors de la configuration d'un sous-site, les champs correspondent à:

* **NOM**: Nom de l'application sous-site (destiné au back-ofice)
* **DOMAINE**: Nom de domaine correspondant à l'application
* **DOMAINE EST UNE REGEX**: Permet la saisie d'une expression régulière dans le champ "DOMAINE"
* **GROUPE DE SÉCURITÉ**: Groupe de sécurité (utilisateurs) dont va hériter le groupe dédié au sous-site
* **GESTIONNAIRE DE RUBRIQUES**: Gestionnaire de rubrique modèle qui sera cloné afin de créer un gestionnaire de rubrique dédié à ce sous-site
* **TITRE DU SITE**: Nom du site, destiné au Front-Office
