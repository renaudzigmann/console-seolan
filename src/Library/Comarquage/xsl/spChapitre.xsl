<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	  	
	<xsl:template match="Chapitre" mode="Definition">
		<xsl:apply-templates mode="Definition"/>
	</xsl:template>

	<xsl:template match="Chapitre" mode="OuSAdresser">
		<div class="spChapitre">
			<xsl:apply-templates mode="OuSAdresser"/>
		</div>
	</xsl:template>

	<xsl:template match="Chapitre">
		<div class="spChapitre">
			<xsl:if test="Titre">
				<xsl:attribute name="id">
					<xsl:call-template name="createChapitreId"/>
				</xsl:attribute>
				<xsl:attribute name="class">
					<xsl:text>spChapitre spChapitreAvecTitre</xsl:text>
				</xsl:attribute>
			</xsl:if>
			<xsl:apply-templates/>
		</div>
	</xsl:template>
	
	<xsl:template match="SousChapitre">
		<div class="spSousChapitre">
			<xsl:choose>
				<xsl:when test="@type != ''">
					<div class="spSousChapitreNote">
						<xsl:call-template name="affTexteType"/>
						<xsl:apply-templates/>
					</div>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates/>
				</xsl:otherwise>
			</xsl:choose>
		</div>
	</xsl:template>

</xsl:stylesheet>
