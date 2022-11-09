# Console Séolan

La Console Séolan est un CMS Open-Source développé par la société [XSALTO](http://www.xsalto.com/).

## Installation

### Prérequis

* serveur de bases de données Mariadb installé
* php version 7.4 installé

### Sur un serveur UNIX

Récupérer le code source depuis le repo git.

```
cd ~/..
git clone https://github.com/renaudzigmann/console-seolan.git csx
```

Puis exécutez le script d'installation :

```
php csx/contribs/console-create.php
```

Le script d'installation demande les informations de connexion à la base de données mariadb : nom, utilisateur, serveur, mot de passe

## Documentation

### Utilisateur

La documentation **utilisateur** est acccessible dans l'interface même de la Console Séolan (interface que l'on appelle plus communément le **back-office**) grâce à un petit icône (i) situé dans la barre d'action de chaque module.

Cette documentation est sur le site https://publicdocs.seolan.com

### Développeur et intégrateur

La documentation développeur est accessible sur https://publicdocs.seolan.com

## Licence

La Console Séolan est distribuée sous [licence GPL](https://fr.wikipedia.org/wiki/Licence_publique_g%C3%A9n%C3%A9rale_GNU).
