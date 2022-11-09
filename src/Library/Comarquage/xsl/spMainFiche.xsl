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

  	<!-- Publication -->
   	<xsl:template match="/Publication">

		<div class="spPublicationMain">

			<xsl:call-template name="getBarreThemes"/>
			<xsl:apply-templates select="FilDAriane"/>
			<xsl:call-template name="getTitre"/>
	
			<xsl:call-template name="affAvertissement"/>
	
			<xsl:if test="$AFF_SOMMAIRE = 'true'">
				<div class="spPublicationMenuDroite">
					<xsl:call-template name="createSommaire"/>
					<xsl:call-template name="affDossiers"/>
				</div>
			</xsl:if>
	
			<xsl:call-template name="getDate"/>
			<xsl:apply-templates select="Introduction"/>
			<xsl:apply-templates select="Texte"/>
			<xsl:apply-templates select="ListeSituations"/>
	
			<xsl:call-template name="affCommentFaireSi"/>
			<xsl:call-template name="affInformationComplementaire"/>		
			<xsl:call-template name="affLienExterneCommente"/>		
			<xsl:call-template name="affPartenaire"/>		
			<xsl:call-template name="affPourEnSavoirPlus"/>		
			<xsl:call-template name="affQuestionReponse"/>		
			<xsl:call-template name="affReference"/>		
			<xsl:call-template name="affServiceEnLigne"/>		
			<xsl:call-template name="affSiteInternetPublic"/>
			<xsl:call-template name="affVoirAussi"/>
			<xsl:call-template name="affActualite"/>		
			<xsl:call-template name="affOuSAdresser"/>		
	
	  		<xsl:call-template name="ancreTop"/>

		</div>
		
	 </xsl:template>
		
</xsl:stylesheet>
