<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template match="SousDossier">
		<xsl:variable name="title">
			<xsl:value-of select="/Publication/dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="Titre"/>
		</xsl:variable>
		<div class="spPublicationNoeud">
			<xsl:attribute name="id">
				<xsl:call-template name="createSousDossierId"/>
			</xsl:attribute>
			<h2>
				<xsl:call-template name="imageOfATheme">
					<xsl:with-param name="id"><xsl:value-of select="/Publication/Theme/@ID"/></xsl:with-param>
					<xsl:with-param name="class" select="'entiteImageFloatLeft'"/>
				</xsl:call-template>
				<xsl:value-of select="Titre"/>
			</h2>
			<ul class="spPublicationNoeud">
				<xsl:apply-templates select="Fiche"/>
			</ul>
		</div>
	</xsl:template>

</xsl:stylesheet>
