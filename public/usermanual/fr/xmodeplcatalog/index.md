Module EPL Catalog
===============================


1. Import du catalogue de DEV/PROD:

L'import du catalogue DEV/PROD se fait en fonction des informations liées aux environnements DEV/PROD définies dans le fichier local.inc.
Si on est sur l'environnement de PROD on pourra importé le catalogue de DEV et inversement.
Un back up des tables du catalogue est effcectué avant import il est donc possible de revenir en arrière si problème durant l'import.

-   Il est important que le fichier local.inc continne les informations liées aux 2 environnements DEV/PROD