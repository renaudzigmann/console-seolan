<%$doc_level%> Généralités sur l'interface Json
<%$doc_level%># Spécification

L'interface JSON est basée sur la spécification http://jsonapi.org/ .
Chaque objet est identifié par un type d'objet et un identifiant.
Lors de l'appel à un objet, l'API répond par du json comportant l'identifiant , le type, les attributs et les relations de l'objet.
Les relations de l'objet sont une liste de références vers d'autres objets liés à celui-ci.

<%$doc_level%># Gestion des traductions

Les champs traduisibles seront renvoyés sous forme d'objet contenant une entrée par langue. Chaque entrée est associée à un code langue de la forme CODELANGUE_CODEPAYS. Exemple :
```
UPD: {
    EN_GB: "2018-08-21 17:42:10",
    FR_CF: "2018-08-21 17:42:10",
    EN_CG: "2018-08-21 17:42:10",
    DE_DE: "2018-08-21 17:42:10",
    EL_EL: "2018-08-21 17:42:10",
    FR_FR: "2018-08-21 17:42:10",
    IT_IT: "2018-08-21 17:42:10",
    PL_PL: "2018-08-21 17:42:10",
    PT_PT: "2018-08-21 17:42:10",
    ES_SP: "2018-08-21 17:42:10",
    EN_US: "2018-08-21 17:42:10"
}
```

<%$doc_level%># Authentification

Utilisation de la ressource login

```
    GET /login/?login=xxx&password=xxx HTTP/1.1
    Accept: application/vnd.api+json
```


La réponse est un objet jsonApi .
1. si la connexion fonctionne :

```
    {
      "data": true,
      "sessionid": "d8b610292b5acb87ea80e6bb0a66421a",
      "links": {
        "self":
        "<%$doc_json_uri%>/login/?login=xxx&password=xxx"
      }
    }
```

Le paramètre sessionid est alors à utiliser sur tous les appels de l'api, comme dans l'exemple ci-dessous :

```
    GET /product?sessionid=v94o7fvn5tc9g5hshb95m43vb2 HTTP/1.1
    Accept: application/vnd.api+json
```

2. Si la connexion échoue
un objet jsonApi error est retourné avec un code erreur HTTP 403 .

```
    {
        "errors": [
            {
            "id": "000000000338f8750000000064da4c2f",
            "links": {
                "about": ""
            },
            "status": 403,
            "code": 403,
            "title": "Seolan Json Error",
            "detail": "Login refused",
            "source": [],
            "meta": []
            }
        ]
    }
```


<%$doc_level%># Appel de ressources

Pour chaque ressource disponible il est possible de faire appel à une liste d'éléments ou à
un seul.

* Liste

```
    GET /product?sessionid=v94o7fvn5tc9g5hshb95m43vb2 HTTP/1.1
```

* Element seul

```
    GET /product/53x1ml04oit6?sessionid=v94o7fvn5tc9g5hshb95m43vb2 HTTP/1.1
```

* Description de la réponse

Un objet json retourné se compose des éléments suivants.

| Nom| Description| remarques|
|:---|:-----------|:---------|
|id  |identifiant de l'objet obligatoire|obligatoire|
|type|Type de l'objet permettant d'identifier la ressource correspondante obligatoire|obligatoire|
|attributes|Tableau des champs composant l’élément relations Tableau des liens vers d'autres ressources enrelation avec l'élément| |
|lst_upd|Dernière mise à jour de l'élément| |


* Description des attributs

|Type|Description|Exemple|
|:---|:----------|:------|
|texte|texte simple|     reference:"RLFMJ03"|
|texte traduisible|Pour les éléments traduisible le tableau est alors indexé par les langues |"DESCR_DESCRIPTIONCONSUMERS": {"en_AA" : "...","es_AR": "...",....}|
|Booléen|Valeur vrai ou faux|REFERENCEIMAGE:false|
|Fichier|Fichier déposé, tableau des éléments suivants : * mimetype : type mime du fichier * originalnalme : nom du fichier original * url : url de téléchargement de l'image |MEDIAFILE:mimetype:"image/jpeg",originalname:"RLFMJ03_389.jpg"url:"/csx/scripts/downloader2.php?filename=..."
|Fichier Externe|Fichier externe : youtube, vimeo ... Tableau des éléments suivants : externalType : type externe (youtube) externalUrl : url sur le site externe|MEDIAFILE:externalType:"youtube",externalUrl:"https://youtu.be/qANYMXe41uM"|

* Description des relations

Les relations sont des liens vers d'autres ressources elles contiennent un élément data
contenant soit directement un objet (relation monovaluée) soit un tableau d'objets (multivaluée).

Exemples:

```
GET product/53x1ml04oit6 HTTP/1.1
{
    "data": {
	"type": "product",
	"id": "53x1ml04oit6",
	"lst_upd": "2017­02­01 12:03:04",
	"attributes": {
		...
	}
	"relationships": {
	    "trademark": { // monovalué
		"data": {
		    "type": "trademark",
		    "id": "cgjhp4gni777l",
		    "attributes": {
			"NAME": "Ma société"
		    }
		}
	    }
	    "DESCR_OTHERTECHNOLOGIES": { //multivalué
		"data": [
		    {
			"type": "techno",
			"id": "gfs1g39pd5ezk",
			"attributes": {
			    "REFERENCE": "R1617_DWR"
			}
		    },
		    //		.....
		    {
			"type": "techno",
			"id": "jsdegwp1nn7qj",
			"attributes": {
			    "REFERENCE": "R1617_FULLY_SEAMS"
			}
		    },
		}
	    }
	}
    }
}
```

* Description des ensembles de chaînes

Les champs de type ensemble de chaînes sont similaires aux champs lien vers objet.
La différence c'est que pour un id renvoyé dans la valeur d'un champ, seule une chaîne de caractères est associée. 
Pour récupérer la chaîne de caractères associée à un id, il faut aller chercher dans la clé <%\Seolan\Core\Json::getSetsAlias()%>
qui se trouve au même niveau que data dans la réponse JSON.
Exemple de réponse avec un champ de type Ensemble de chaîne : 

```
GET product/53x1ml04oit6 HTTP/1.1
{
    "data": {
	    "type": "product",
	    "id": "53x1ml04oit6",
	    "lst_upd": "2017­02­01 12:03:04",
	    "attributes": {
	        ...
	    },
	    "relationships": {
	        "mention_environement" : {
	            "data" : {
	                "type" : "<%\Seolan\Core\Json::getSetsAlias()%>",
	                "id" : "product_mention_environement_YES"
	            }
	        }
	    },
	    "<%\Seolan\Core\Json::getSetsAlias()%>" : [
	        {
	            "type" : "<%\Seolan\Core\Json::getSetsAlias()%>",
	            "id" : "product_mention_environement_YES",
	            "attributes" : {
	                "value" : {
	                    EN_GB: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        FR_CF: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        EN_CG: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        DE_DE: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        EL_EL: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        FR_FR: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        IT_IT: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        PL_PL: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        PT_PT: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        ES_SP: "Déposée également sur www.declaration-environnementale.gouv.fr",
                        EN_US: "Déposée également sur www.declaration-environnementale.gouv.fr"
	                }
	            }
	        },
	        {
	            "type" : "<%\Seolan\Core\Json::getSetsAlias()%>",
	            "id" : "product_mention_environement_NO",
	            "attributes" : {
	                "value" : {
	                    EN_GB: "Non",
	                    FR_CF: "Non",
	                    EN_CG: "Non",
	                    DE_DE: "Non",
	                    EL_EL: "Non",
	                    FR_FR: "Non",
	                    IT_IT: "Non",
	                    PL_PL: "Non",
	                    PT_PT: "Non",
	                    ES_SP: "Non",
	                    EN_US: "Non"
	                }
	            }
	        },
	        ...
	    ]
	}
}	    
```

<%$doc_level%># Filtres

Il est possible de filtrer une liste d’éléments en utilisant le paramètre filter[] d'une ressource
Pour la liste de produits saison 1617 :

```
GET /product/?filter[trademark]=cgjhp4gni777l&filter[season]=52abxx2p3oft
HTTP/1.1
```

* cgjhp4gni777l est l'identifiant de la marque 
* 52abxx2p3oft est l'identifiant de la saison

Il est possible de faire des filtres plus complexes en fonction du
type de champ et de gérer un opérateur. L'opérateur à appliquer se
définit avec le paramètre filter[CHAMP_op]. Les opérateurs disponibles
sont : =, >=, <=, > ou <. Ces opérateurs sont disponibles pour les
types de champ suivants : Chrono, Date et heure, Évaluation, Heure et
Numérique. Exemple de recherche : 

* filter[price_op]=>=&filter[price]=10.02 : Recherche toutes fiches dont le prix est supérieur ou égal à 10,02.
* filter[UPD_op]=<=&filter[UPD]=23/08/2018 : Recherche toutes les fiches dont la date de dernière modification est inférieure ou égale au 23/08/2018.

Il existe deux autres opérateurs : "is empty" et "is not empty" qui
sont disponible pour les types de champ suivant :  Texte, Chrono V2,
Logique,  Date et heure,  Date.
Exemple :
```
filter[title_op]=is empty
```

<%$doc_level%># Spécificité sur les champs de type Thésaurus et lien vers objet

Spécificités sur les opérateurs : AND, OR, NONE

* recherche toutes les fiches dont l'ID de la marque
vaut à la fois cgjhp4gni777l et  jsdegwp1nn7qj (valable pour les liens
vers objet multivalué et pour les thésaurus).
```
filter[trademark_op]=AND&filter[trademark][]=cgjhp4gni777l&filter[trademark][]=jsdegwp1nn7qj
```

* recherche toutes les fiches dont l'ID de la marque vaut soit
cgjhp4gni777l soit jsdegwp1nn7qj (valable pour les liens vers objet
multivalué et les thésaurus). 
```
filter[trademark_op]=OR&filter[trademark][]=cgjhp4gni777l&filter[trademark][]=jsdegwp1nn7qj
```

* recherche toutes les fiches dont l'ID de la marque ne vaut ni
gjhp4gni777l ni jsdegwp1nn7qj (valable pour les thésaurus uniquement)
```
filter[trademark_op]=NONE&filter[trademark][]=cgjhp4gni777l&filter[trademark][]=jsdegwp1nn7qj
```

<%$doc_level%># Pagination

La pagination est gérée avec l'attribut page en définissant une taille de page
( page[size] ) et un offset ( page[offset] ). La taille de page est
limité à 100.


```
GET /product/?page[offset]=0&page[size]=30 HTTP/1.1
GET /product/?page[offset]=30&page[size]=30 HTTP/1.1
```
