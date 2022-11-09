<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  		
	<xsl:template name="affServiceEnLigne">
		<xsl:if test="count(ServiceEnLigne) > 0">
			<div class="spPublicationSEL" id="sp-service-en-ligne">
				<h2>
					<xsl:call-template name="imageOfAPartie">
					<xsl:with-param name="nom">enligne</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Services et formulaires en ligne</xsl:text>
				</h2>
		<ul>
				<xsl:apply-templates select="ServiceEnLigne"/>
		</ul>
				<xsl:call-template name="affServiceEnLignePivotLocal"/>
			</div>
		</xsl:if>
	</xsl:template>
	
	<xsl:template match="ServiceEnLigne">
		<xsl:variable name="title">
			<xsl:value-of select="@type"/>
			<xsl:if test="@commentaireLien">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@commentaireLien"/>
			</xsl:if>
			<xsl:if test="@format">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@format"/>
			</xsl:if>
			<xsl:if test="@poids">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@poids"/>
                              </xsl:if>
		</xsl:variable>
			<li>
                          <h3>
					<xsl:call-template name="getPublicationLink">
						<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
						<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
                                                <xsl:with-param name="text"><xsl:value-of select="Titre/text()"/></xsl:with-param>
					</xsl:call-template>
				</h3>
				<xsl:if test="@type">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="@type"/>
				</xsl:if>
				<xsl:if test="@numerocerfa">
					<xsl:text> - Cerfa n°</xsl:text>
					<xsl:value-of select="@numerocerfa"/>
				</xsl:if>
				<xsl:if test="@autrenumero">
					<xsl:text> - N°</xsl:text>
					<xsl:value-of select="@autrenumero"/>
				</xsl:if>
			</li>
	</xsl:template>

	<xsl:template match="NoticeLiee">
		<xsl:variable name="titre">
			<xsl:value-of select="@commentaireLien"/>
			<xsl:if test="@poids">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@poids"/>
			</xsl:if>
			<xsl:text> - </xsl:text>
			<xsl:value-of select="@langue"/>
		</xsl:variable>
		<xsl:variable name="texte">
			<xsl:value-of select="text()"/>
		</xsl:variable>
			<li class="spLienWeb">
				<xsl:call-template name="getSiteLink">
					<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
					<xsl:with-param name="title"><xsl:value-of select="$titre"/></xsl:with-param>
					<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
					<xsl:with-param name="lang"><xsl:value-of select="@langue"/></xsl:with-param>
				</xsl:call-template>
				<xsl:if test="@type">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="@type"/>
				</xsl:if>
				<xsl:if test="@numerocerfa">
					<xsl:text> - Cerfa n°</xsl:text>
					<xsl:value-of select="@numerocerfa"/>
				</xsl:if>
				<xsl:if test="@autrenumero">
					<xsl:text> - N°</xsl:text>
					<xsl:value-of select="@autrenumero"/>
				</xsl:if>
			</li>
	</xsl:template>

	<xsl:template name="affServiceEnLignePivotLocal">
		<xsl:if test="//Publication/OuSAdresser">
			<xsl:for-each select="//Publication/OuSAdresser/PivotLocal">
				<xsl:variable name="pivot">
			    	<xsl:text>,</xsl:text>
			    	<xsl:value-of select="text()"/>
			    	<xsl:text>,</xsl:text>
				</xsl:variable>
				<xsl:variable name="file">
			    	<xsl:value-of select="$PIVOTS"/>
			    	<xsl:value-of select="text()"/>
			    	<xsl:text>.xml</xsl:text>
				</xsl:variable>
				<xsl:if test="contains($PIVOTS_ACTIFS,$pivot)">
					<xsl:apply-templates select="document($file)//PivotLocal/PTA-PL-Communication/PTA-PL-SiteInternet[@type='teleservice']"/>
				</xsl:if>
			</xsl:for-each>
		</xsl:if>
	</xsl:template>

</xsl:stylesheet>
