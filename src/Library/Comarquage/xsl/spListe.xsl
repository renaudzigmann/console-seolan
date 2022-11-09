<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template match="Liste">
		<xsl:choose>
			<xsl:when test="@type = 'numero'">
				<ol class="spListe"><xsl:apply-templates/></ol>
			</xsl:when>
			<xsl:otherwise>
				<ul class="spListe"><xsl:apply-templates/></ul>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="Item">
		<xsl:choose>
			<xsl:when test="name(../..) = 'Chapitre'">
				<li class="spItemChapitre"><xsl:apply-templates/></li>
			</xsl:when>
			<xsl:otherwise>
				<li class="spItemListe"><xsl:apply-templates/></li>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

</xsl:stylesheet>
