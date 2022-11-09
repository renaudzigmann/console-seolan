<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template match="Dossier">
		<xsl:variable name="title">
			<xsl:value-of select="/Publication/dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="Titre"/>
		</xsl:variable>
		<xsl:variable name="class">
			<xsl:text>spPublicationNoeud spPublicationDFT</xsl:text>
			<xsl:if test="position() = 1">
				<xsl:text> spPublicationDFTFirst</xsl:text>
			</xsl:if>
			<xsl:if test="position() = count(../Dossier)">
				<xsl:text> spPublicationDFTLast</xsl:text>
			</xsl:if>
		</xsl:variable>
		<li class="{$class}">
			<h3 class="spPublicationDossier">
	   			<xsl:call-template name="getPublicationLink">
	   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
	   				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
	   				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
				</xsl:call-template>
			</h3>
		</li>
	</xsl:template>
	
	<xsl:template match="Dossier" mode="Sous-Theme">
		<xsl:variable name="title">
			<xsl:value-of select="/Publication/dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="../Titre"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="text()"/>
		</xsl:variable>
		<xsl:variable name="class">
			<xsl:text>spPublicationNoeud spPublicationDFT</xsl:text>
			<xsl:if test="position() = 1">
				<xsl:text> spPublicationDFTFirst</xsl:text>
			</xsl:if>
			<xsl:if test="position() = count(../Dossier)">
				<xsl:text> spPublicationDFTLast</xsl:text>
			</xsl:if>
		</xsl:variable>
		<li class="{$class}">
			<h3 class="spPublicationDossier">
	   			<xsl:call-template name="getPublicationLink">
	   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
	   				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
	   				<xsl:with-param name="text"><xsl:value-of select="text()"/></xsl:with-param>
				</xsl:call-template>
			</h3>
		</li>
	</xsl:template>

	<xsl:template match="Dossier" mode="Ressource">
		<xsl:variable name="title">
			<xsl:value-of select="/ServiceComplementaire/dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="Titre"/>
		</xsl:variable>
		<xsl:variable name="class">
			<xsl:text>spPublicationNoeud spPublicationDFT</xsl:text>
			<xsl:if test="position() = 1">
				<xsl:text> spPublicationDFTFirst</xsl:text>
			</xsl:if>
			<xsl:if test="position() = count(../Dossier)">
				<xsl:text> spPublicationDFTLast</xsl:text>
			</xsl:if>
		</xsl:variable>
		<li class="{$class}">
			<h3 class="spPublicationDossier">
	   			<xsl:call-template name="getPublicationLink">
	   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
	   				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
	   				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
				</xsl:call-template>
			</h3>
		</li>
	</xsl:template>

</xsl:stylesheet>
