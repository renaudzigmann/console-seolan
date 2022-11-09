<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template name="affVoirAussi">
		<xsl:if test="count(VoirAussi) > 0">
			<div class="spPublicationVA" id="sp-voir-aussi">
				<h2>
					<xsl:call-template name="imageOfAPartie">
						<xsl:with-param name="nom">voir-aussi</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Voir aussi...</xsl:text>
				</h2>
				<xsl:apply-templates select="VoirAussi"/>
			</div>
		</xsl:if>
	</xsl:template>

	<xsl:template match="VoirAussi">
		<ul class="spPublicationVA">
			<xsl:for-each select="Dossier">
				<xsl:variable name="title">
					<xsl:value-of select="Titre"/>
				</xsl:variable>
				<li class="spPublicationVA">
					<h3>
						<xsl:call-template name="getPublicationLink">
			   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
			   				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			   				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
						</xsl:call-template>
					</h3>
				</li>
			</xsl:for-each>
			<xsl:for-each select="Fiche">
				<xsl:variable name="title">
					<xsl:value-of select="Theme"/>
					<xsl:value-of select="$sepFilDAriane"/>
					<xsl:value-of select="Titre"/>
				</xsl:variable>
				<li class="spPublicationVA">
					<h3>
						<xsl:call-template name="getPublicationLink">
			   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
			   				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			   				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
						</xsl:call-template>
					</h3>
				</li>
			</xsl:for-each>
		</ul>
	</xsl:template>

</xsl:stylesheet>
