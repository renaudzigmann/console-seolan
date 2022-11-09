<?xml version="1.0" encoding="UTF-8"?> 
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

 	<xsl:import href="spVariables.xsl"/>
  	<xsl:import href="spCommon.xsl"/>
  	<xsl:import href="spActualite.xsl"/>
  	<xsl:import href="spFilDAriane.xsl"/>
  	<xsl:import href="spLien.xsl"/>
  	<xsl:import href="spCommentFaireSi.xsl"/>
  	<xsl:import href="spServiceEnLigne.xsl"/>

	<xsl:output method="html" encoding="UTF-8" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template match="/Noeud">
   	  	<!-- <xsl:apply-templates /> -->
   		<xsl:apply-templates select="Descendance"/>
   		
   		<div id="cm-sidebar">
   			<xsl:call-template name="affActualite"/>
   			<xsl:call-template name="affCommentFaireSi"/>
   			
			<xsl:text> </xsl:text>
   		</div>
	</xsl:template>
	
	<xsl:template match="TitreLong"/>
	<xsl:template match="Nom"/>
	<xsl:template match="CouvertureGéographique"/>
  	
  	<xsl:template match="Descendance">
  		
  		<div id="cm-content">
  		<ul id="co-home-menu">
  		<xsl:for-each select="Fils">
  			<li>
  				<!-- <xsl:call-template name="imageOfATheme"/> -->
  				<xsl:call-template name="imageOfATheme">
					<xsl:with-param name="id" select="@lien"/>
					<xsl:with-param name="class" select="'co-home-img'"/>
				</xsl:call-template>
  				<h2>
  				<xsl:call-template name="getPublicationLink">
    				<xsl:with-param name="href"><xsl:value-of select="@lien"/></xsl:with-param>
    				<xsl:with-param name="title"><xsl:value-of select="./TitreContextuel"/></xsl:with-param>
    				<xsl:with-param name="text"><xsl:value-of select="./TitreContextuel"/></xsl:with-param>
				</xsl:call-template>
  				</h2>
  				
				<!-- Recuperation de l'URL du fichier d'information sur l'organisme -->
				<xsl:variable name="FILE_LINK">
			    	<xsl:value-of select="$XML_COURANT"/>
			    	<xsl:value-of select="@lien"/>
			    	<xsl:text>.xml</xsl:text>
				</xsl:variable>
				
				<ul class="co-home-sousmenu">
					<xsl:apply-templates select="document($FILE_LINK)/Publication/SousTheme" mode="sousmenu"/>
					<xsl:apply-templates select="document($FILE_LINK)/Publication/Dossier" mode="sousmenu"/>
				</ul>
  			</li>
  		</xsl:for-each>
  		</ul>
  		
  		</div>
  	</xsl:template>

  	<xsl:template match="SousTheme" mode="sousmenu">
  		<li>
  		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="./Titre"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:value-of select="./Titre"/></xsl:with-param>
		</xsl:call-template>
		<xsl:text>,</xsl:text><xsl:text>&#xA0;</xsl:text>
  		</li>
  	</xsl:template>

  	<xsl:template match="Dossier" mode="sousmenu">
  		<li>
  		<xsl:call-template name="getPublicationLink">
			<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
			<xsl:with-param name="title"><xsl:value-of select="./Titre"/></xsl:with-param>
			<xsl:with-param name="text"><xsl:value-of select="./Titre"/></xsl:with-param>
		</xsl:call-template>
		<xsl:text>,</xsl:text><xsl:text>&#xA0;</xsl:text>
  		</li>
  	</xsl:template>
	
</xsl:stylesheet>