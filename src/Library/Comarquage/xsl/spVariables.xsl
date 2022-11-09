<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
  	<!-- Code de la catégorie
  		* part : particuliers
  		* asso : associations
  		* pro : entreprises et professionnels
  	 -->
	<xsl:param name="CATEGORIE"/>

  	<!-- Nom associé à 1 des 3 catégories : particuliers, associations et professionnels -->
	<xsl:variable name="CATEGORIE_NOM">
		<xsl:choose>
			<xsl:when test="$CATEGORIE = 'part'">
				<xsl:text>particuliers</xsl:text>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'asso'">
				<xsl:text>associations</xsl:text>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'pro'">
				<xsl:text>entreprises et professionnels</xsl:text>
			</xsl:when>
		</xsl:choose>
	</xsl:variable>

  	<!-- Hyperlien principal du guide des particuliers -->
	<xsl:param name="HYPERLIEN_PART"/>

  	<!-- Hyperlien principal du guide des associations -->
	<xsl:param name="HYPERLIEN_ASSO"/>

  	<!-- Hyperlien principal du guide des entreprises et professionnels -->
	<xsl:param name="HYPERLIEN_PRO"/>

 	<!-- Hyperlien du répertoire contenant les pictogrammes
 		* courriel, téléphone, fax...
 	 -->
	<xsl:param name="PICTOS"/>

	<!-- Liste des pivots renseignés localement -->
	<xsl:param name="PIVOTS_ACTIFS"/>

	<!-- Hyperlien du répertoire contenant les données XML -->
	<xsl:param name="DONNEES"/>

	<!-- Hyperlien principal de la catégorie en cours -->
	<xsl:variable name="HYPERLIEN_COURANT">
		<xsl:choose>
			<xsl:when test="$CATEGORIE = 'part'">
				<xsl:value-of select="$HYPERLIEN_PART"/>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'asso'">
				<xsl:value-of select="$HYPERLIEN_ASSO"/>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'pro'">
				<xsl:value-of select="$HYPERLIEN_PRO"/>
			</xsl:when>
		</xsl:choose>
	</xsl:variable>
 	
	<!-- URL du répertoire contenant le fichier XML à afficher -->
	<xsl:variable name="XML_COURANT">
		<xsl:value-of select="$DONNEES"/>
		<xsl:choose>
			<xsl:when test="$CATEGORIE = 'part'">
				<xsl:text>/part/</xsl:text>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'asso'">
				<xsl:text>/asso/</xsl:text>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'pro'">
				<xsl:text>/pro/</xsl:text>
			</xsl:when>
		</xsl:choose>
	</xsl:variable>

  	<!-- Texte des comment faire... -->
	<xsl:variable name="TEXTE_CFS">
		<xsl:choose>
			<xsl:when test="$CATEGORIE = 'pro'">
				<xsl:text>Comment faire pour...</xsl:text>
			</xsl:when>
			<xsl:when test="$CATEGORIE = 'asso'">
				<xsl:text>Comment faire si...</xsl:text>
			</xsl:when>
			<xsl:otherwise>
				<xsl:text>Comment faire si...</xsl:text>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Identifiant du noeud affichant tous les comment faire... -->
	<xsl:variable name="ACCUEIL_CFS">
		<xsl:choose>
			<xsl:when test="$CATEGORIE = 'pro'">N23971</xsl:when>
			<xsl:when test="$CATEGORIE = 'asso'">N31000</xsl:when>
			<xsl:otherwise>commentFaireSi</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Nom du fichier XML gérant les pages d'accueil des 3 guides
		* arborescence : flux V2.3
		* Themes : flux V2.2
	-->
	<xsl:variable name="THEME_ARBORESCENCE">
		<xsl:text>000-PTA-Themes</xsl:text>
	</xsl:variable>

	<!-- Indique si un fichier gère l'ensemble des ressources R*
		* true : oui
		* false : non
	-->
	<xsl:variable name="AFF_RESSOURCES">
		<xsl:value-of select="true()"/>
	</xsl:variable>

	<!-- Active ou désactive l'affichage du fil d'ariane
		* true : activation
		* false : désactivation
	-->
	<xsl:variable name="AFF_FIL_ARIANE">
		<xsl:value-of select="true()"/>
	</xsl:variable>

	<!-- Active ou désactive l'affichage du menu du sommaire et des dossiers
		* true : activation
		* false : désactivation
	-->
	<xsl:variable name="AFF_SOMMAIRE">
		<xsl:value-of select="true()"/>
	</xsl:variable>

	<!-- Active ou désactive l'affichage de la barre des thèmes principaux
		* true : activation
		* false : désactivation
	-->
	<xsl:variable name="AFF_BARRE_THEME">
		<xsl:value-of select="false()"/>
	</xsl:variable>

	<!-- Active ou désactive l'affichage des images des thèmes et des catégories
		* fonctions : imageOfTheme et imageOfPartie
		* true : activation
		* false : désactivation
	-->
	<xsl:variable name="AFF_IMAGES">
		<xsl:value-of select="false()"/>
	</xsl:variable>

	<!-- Active ou désactive l'affichage des pictogrammes présents dans les pivots locaux
		* true : activation
		* false : désactivation
	-->
	<xsl:variable name="AFF_PICTOS">
		<xsl:value-of select="true()"/>
	</xsl:variable>

	<!-- Répertoire contenant les images -->
	<xsl:variable name="IMAGES">
		<xsl:value-of select="$DONNEES"/><xsl:text>images/</xsl:text>
	</xsl:variable>

	<!-- Gestion des pivots de type PivotLocal
		* pivot : pour afficher des « fichiers pivots locaux » au format XML
		* web : pour afficher les liens génériques produits par service-public.fr
	-->
	<xsl:variable name="MODE_PIVOT">
		<xsl:text>pivot</xsl:text>
	</xsl:variable>

	<!-- Répertoire contenant les pivots -->
	<xsl:variable name="PIVOTS">
		<xsl:value-of select="$DONNEES"/><xsl:text>pivots/</xsl:text>
	</xsl:variable>
	
</xsl:stylesheet>
