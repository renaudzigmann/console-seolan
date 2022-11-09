<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	  	
	<xsl:template match="Paragraphe" mode="Definition">
		<xsl:apply-templates/>
	</xsl:template>

	<xsl:template match="Paragraphe" mode="OuSAdresser">
		<p class="spParagraphe"><xsl:apply-templates/></p>
	</xsl:template>

	<xsl:template match="Paragraphe">
		<xsl:choose>
			<xsl:when test="name(..) = 'Titre'">
				<xsl:apply-templates/>
			</xsl:when>
			<xsl:otherwise>
				<p class="spParagraphe"><xsl:apply-templates/></p>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

</xsl:stylesheet>
