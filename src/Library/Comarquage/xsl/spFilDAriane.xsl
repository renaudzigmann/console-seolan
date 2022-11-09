<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
  	<xsl:variable name="sepFilDAriane">
		<xsl:text> &#187; </xsl:text>  	
  	</xsl:variable>

  	<!-- Fild'ariane -->
   	<xsl:template match="FilDAriane">
   		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
   	
	   		<div class="spFilDAriane">
	   			
	   			<div class="entiteImageFloatRight">
					<xsl:call-template name="imageOfATheme">
						<xsl:with-param name="id">
							<xsl:choose>
								<xsl:when test="//Publication/FilDAriane/Niveau/@ID">
									<xsl:value-of select="//Publication/FilDAriane/Niveau/@ID"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="//Publication/@ID"/>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:with-param>
					</xsl:call-template>
	  			</div>
	  			
                                   <xsl:call-template name="getFilDArianeTheme"/>
                                   <xsl:for-each select="./Niveau">
                                     <xsl:if test="position() > 1">
					<xsl:variable name="title">
						<xsl:text>Guide des droits et des démarches des </xsl:text>
						<xsl:value-of select="$CATEGORIE_NOM"/>
						<xsl:text> : </xsl:text>
						<xsl:value-of select="text()"/>
					</xsl:variable>
                                        <xsl:if test="position() > 1">
                                        <xsl:value-of select="$sepFilDAriane"/>
                                      </xsl:if>
	    			<xsl:call-template name="getPublicationLink">
	    				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
	    				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
	    				<xsl:with-param name="text"><xsl:value-of select="text()"/></xsl:with-param>
					</xsl:call-template>
                                      </xsl:if>
		   		</xsl:for-each>
				   <!--<xsl:value-of select="$sepFilDAriane"/>-->
				   <!--<xsl:value-of select="//Publication/dc:title"/>-->
		   	</div>
		   	
		</xsl:if>
 	</xsl:template>
	
	<!-- Theme du fil d'ariane -->
	<xsl:template name="getFilDArianeTheme">
		<xsl:variable name="title">
			<xsl:text>Guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<span class="spFilDArianeIci">
                        <!--<xsl:text>Fil d'Ariane du guide :</xsl:text>-->
 		</span>
		<xsl:text> </xsl:text>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>Theme</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Guide des </xsl:text><xsl:value-of select="$CATEGORIE_NOM"/></xsl:with-param>
		</xsl:call-template>
	</xsl:template>
	
	<!-- Ressources du fil d'ariane -->
	<xsl:template name="getFilDArianeRessources">
		<xsl:variable name="title">
			<xsl:text>Toutes les services du guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>Ressources</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Services</xsl:text></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Ressources en ligne du fil d'ariane -->
	<xsl:template name="getFilDArianeRessourcesEnLigne">
		<xsl:variable name="title">
			<xsl:text>Tous les services en ligne du guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>RessourcesEnLigne</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Services en ligne</xsl:text></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Dossiers du fil d'ariane -->
	<xsl:template name="getFilDArianeDossiers">
		<xsl:variable name="title">
			<xsl:text>Tous les dossiers de A à Z du guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>Dossiers</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Dossiers</xsl:text></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Glossaire du fil d'ariane -->
	<xsl:template name="getFilDArianeGlossaire">
		<xsl:variable name="title">
			<xsl:text>Le glossaire du guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>Glossaire</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Glossaire</xsl:text></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Centre de contacts du fil d'ariane -->
	<xsl:template name="getFilDArianeCentresDeContact">
		<xsl:variable name="title">
			<xsl:text>Les centres de contact du guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>CentresDeContact</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Centres de contact</xsl:text></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Pivots du fil d'ariane -->
	<xsl:template name="getFilDArianePivots">
		<xsl:variable name="title">
			<xsl:text>Annuaire du guide des droits et des démarches des </xsl:text>
			<xsl:value-of select="$CATEGORIE_NOM"/>
		</xsl:variable>
		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:text>Annuaire</xsl:text></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:text>Annuaire</xsl:text></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Création d'un fil d'ariane sans champs existant dans la publication -->	
	<xsl:template name="createFilDAriane">
   		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
	   		<div class="spFilDAriane">
	  			<div class="entiteImageFloatRight">
					<xsl:call-template name="imageOfATheme">
						<xsl:with-param name="id">
							<xsl:value-of select="//Publication/@ID"/>
						</xsl:with-param>
					</xsl:call-template>
	  			</div>
	   			<xsl:call-template name="getFilDArianeTheme"/>
	   			<xsl:value-of select="$sepFilDAriane"/>
	   			<xsl:value-of select="//Publication/dc:title"/>
		   	</div>
		</xsl:if>
 	</xsl:template>

	<!-- Création d'un fil d'ariane pour une ressource -->
	<xsl:template name="createFilDArianeRessource">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
	 		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:if test="$AFF_RESSOURCES = 'true'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessources"/>
		   		</xsl:if>
				<xsl:if test="//ServiceComplementaire/dc:type = 'Définition de glossaire'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeGlossaire"/>
		   		</xsl:if>
				<xsl:if test="//ServiceComplementaire/dc:type = 'Centre de contact'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeCentresDeContact"/>
		   		</xsl:if>	   		
				<xsl:if test="//ServiceComplementaire/dc:type = 'Formulaire'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessourcesEnLigne"/>
		   		</xsl:if>	   		
				<xsl:if test="//ServiceComplementaire/dc:type = 'Téléservice'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessourcesEnLigne"/>
		   		</xsl:if>	   		
				<xsl:if test="//ServiceComplementaire/dc:type = 'Module de calcul'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessourcesEnLigne"/>
		   		</xsl:if>	   		
				<xsl:if test="//ServiceComplementaire/dc:type = 'Lettre type'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessourcesEnLigne"/>
		   		</xsl:if>	   		
	 			<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="//ServiceComplementaire/dc:title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour les dossiers -->
	<xsl:template name="createFilDArianeDossiers">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:text>Tous les dossiers de A à Z du guide des droits et des démarches des </xsl:text>
				<xsl:value-of select="$CATEGORIE_NOM"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:value-of select="$sepFilDAriane"/>
                                <xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour les ressources -->
	<xsl:template name="createFilDArianeRessources">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:text>Tous les services de A à Z du guide des droits et des démarches des </xsl:text>
				<xsl:value-of select="$CATEGORIE_NOM"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour les ressources en ligne -->
	<xsl:template name="createFilDArianeRessourcesEnLigne">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:text>Tous les services en ligne de A à Z du guide des droits et des démarches des </xsl:text>
				<xsl:value-of select="$CATEGORIE_NOM"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:if test="$AFF_RESSOURCES = 'true'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessources"/>
		   		</xsl:if>
				<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour le glossaire -->
	<xsl:template name="createFilDArianeGlossaire">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:text>Toutes les définitions du glossaire de A à Z du guide des droits et des démarches des </xsl:text>
				<xsl:value-of select="$CATEGORIE_NOM"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:if test="$AFF_RESSOURCES = 'true'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessources"/>
		   		</xsl:if>
				<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour les centres de contact -->
	<xsl:template name="createFilDArianeCentresDeContact">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:text>Tous les centres de contact de A à Z du guide des droits et des démarches des </xsl:text>
				<xsl:value-of select="$CATEGORIE_NOM"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:if test="$AFF_RESSOURCES = 'true'">
					<xsl:value-of select="$sepFilDAriane"/>
		   			<xsl:call-template name="getFilDArianeRessources"/>
		   		</xsl:if>
				<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour les pivots -->
	<xsl:template name="createFilDArianePivots">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:text>Annuaire de A à Z du guide des droits et des démarches des </xsl:text>
				<xsl:value-of select="$CATEGORIE_NOM"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- Création d'un fil d'ariane pour un pivot -->
	<xsl:template name="createFilDArianePivot">
  		<xsl:if test="$AFF_FIL_ARIANE = 'true'">
			<xsl:variable name="title">
				<xsl:value-of select="PTA-PL-Titre"/>
			</xsl:variable>
	   		<div class="spFilDAriane">
	   			<xsl:call-template name="getFilDArianeTheme"/>
				<xsl:value-of select="$sepFilDAriane"/>
	   			<xsl:call-template name="getFilDArianePivots"/>
				<xsl:value-of select="$sepFilDAriane"/>
	 			<xsl:value-of select="$title"/>
			</div>
		</xsl:if>
	</xsl:template>

</xsl:stylesheet>
