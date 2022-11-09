<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template name="affSiteInternetPublic">
		<xsl:if test="count(SiteInternetPublic) > 0">
			<div class="spPublicationSIP" id="sp-site-internet-public">
				<h2>
					<xsl:call-template name="imageOfAPartie">
						<xsl:with-param name="nom">sites-internet-publics</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Sites internet publics</xsl:text>
				</h2>
				<xsl:apply-templates select="SiteInternetPublic"/>
			</div>
		</xsl:if>
	</xsl:template>
	
	<xsl:template match="SiteInternetPublic">
		<xsl:variable name="title">
			<xsl:value-of select="../dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:text>Site internet public</xsl:text>
			<xsl:if test="@commentaireLien">
			<xsl:value-of select="$sepFilDAriane"/>
				<xsl:value-of select="@commentaireLien"/>
			</xsl:if>
		</xsl:variable>
		<ul class="spPublicationSIP">
			<li class="spPublicationSIP">
				<h3>
			 		<xsl:call-template name="getSiteLink">
			 			<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
			 			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			 			<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
					</xsl:call-template>
					<xsl:if test="@commentaireLien">
						<xsl:text> - </xsl:text>
						<xsl:value-of select="@commentaireLien"/>
					</xsl:if>
					<xsl:if test="@langue">
						<xsl:text>( </xsl:text>
						<xsl:value-of select="@langue"/>
						<xsl:text> )</xsl:text>
					</xsl:if>			
				</h3>
			</li>
		</ul>
	</xsl:template>
	
</xsl:stylesheet>
