<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

 	<xsl:import href="spVariables.xsl"/>
  	<xsl:import href="spCommon.xsl"/>
  	<xsl:import href="spTitre.xsl"/>
  	<xsl:import href="spFilDAriane.xsl"/>
  	<xsl:import href="spPivotLocal.xsl"/>

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
   	<xsl:template match="/PivotLocal">
 		<xsl:call-template name="getBarreThemes"/>
 		<xsl:call-template name="createFilDArianePivot"/>
		<div class="spCenter"><h1><xsl:value-of select="PTA-PL-Titre"/></h1></div>
 		<xsl:apply-templates select="node()[not(self::PTA-PL-Titre)]"/>
  	</xsl:template>

</xsl:stylesheet>
