<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

 	<xsl:import href="spVariables.xsl"/>
  	<xsl:import href="spCommon.xsl"/>
  	<xsl:import href="spBootstrap.xsl"/>
  	<xsl:import href="spActualite.xsl"/>
  	<xsl:import href="spAvertissement.xsl"/>
 	<xsl:import href="spChapitre.xsl"/>
 	<xsl:import href="spCommentFaireSi.xsl"/>
 	<xsl:import href="spDossier.xsl"/>
  	<xsl:import href="spFilDAriane.xsl"/>
  	<xsl:import href="spInformationComplementaire.xsl"/>
  	<xsl:import href="spIntroduction.xsl"/>
 	<xsl:import href="spLien.xsl"/>
 	<xsl:import href="spListe.xsl"/>
   	<xsl:import href="spOuSAdresser.xsl"/>
 	<xsl:import href="spParagraphe.xsl"/>
 	<xsl:import href="spPartenaire.xsl"/>
  	<xsl:import href="spPourEnSavoirPlus.xsl"/>
  	<xsl:import href="spQuestionReponse.xsl"/>
  	<xsl:import href="spReference.xsl"/>
  	<xsl:import href="spServiceEnLigne.xsl"/>
  	<xsl:import href="spSiteInternetPublic.xsl"/>
 	<xsl:import href="spTableau.xsl"/>
  	<xsl:import href="spTexte.xsl"/>
  	<xsl:import href="spTitre.xsl"/>
  	<xsl:import href="spVoirAussi.xsl"/>
  	
	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 

	<xsl:template match="ServiceComplementaire">

		<div class="spPublicationMain">

			<xsl:call-template name="getBarreThemes"/>
			<xsl:call-template name="createFilDArianeRessource"/>
			<xsl:call-template name="getTitreOfRessource"/>

			<xsl:if test="$AFF_SOMMAIRE = 'true'">
				<div class="spPublicationMenuDroite">
					<xsl:call-template name="createSommaire"/>
					<xsl:call-template name="affDossiers"/>
				</div>
			</xsl:if>

			<xsl:apply-templates select="Description"/>
			<xsl:apply-templates select="Texte"/>
			<xsl:apply-templates select="LienWeb"/>
			<xsl:apply-templates select="NoticeLiee"/>
			<xsl:apply-templates select="LienMonServicePublic"/>
			<xsl:apply-templates select="Reference" mode="ServiceComplementaire"/>
			<xsl:call-template name="affPourEnSavoirPlus"/>		

			<div class="spPublicationNoeud">
				<xsl:if test="count(Dossier)+count(Fiche) > 0">
					<div class="spPublicationNoeud" id="sp-informations">
						<h2>
							<xsl:call-template name="imageOfAPartie">
								<xsl:with-param name="nom">complement</xsl:with-param>
							</xsl:call-template>
							<xsl:text>Articles connexes</xsl:text>
						</h2>
						<ul class="spPublicationNoeud">
							<xsl:apply-templates select="Dossier" mode="Ressource"/>
						</ul>
					</div>
				</xsl:if>
			</div>
		
		</div>
		
	</xsl:template>

	<xsl:template match="LienMonServicePublic" mode="ServiceComplementaire">
		<li class="spServiceComplementaireSN">
			<h2>
				<xsl:text>Vous pouvez aussi réaliser cette démarche sur mon.service-public.fr. </xsl:text>
				<a href="{@URL}" rel="noffolow" title="{@URL}">
					<xsl:apply-templates/>
					<xsl:if test="@commentaireLien != ''">
						<xsl:text> - </xsl:text>
						<xsl:value-of select="@commentaireLien"/>
					</xsl:if>
				</a>
			</h2>
		</li>
	</xsl:template>
			
	<xsl:template match="NoticeLiee" mode="ServiceComplementaire">
		<li class="spServiceComplementaireSN">
			<h2>
				<a href="{@URL}" rel="noffolow" title="{@URL}">
					<xsl:apply-templates/>
					<xsl:if test="@commentaireLien != ''">
						<xsl:text> - </xsl:text>
						<xsl:value-of select="@commentaireLien"/>
					</xsl:if>
				</a>
			</h2>
		</li>
	</xsl:template>
		
</xsl:stylesheet>
