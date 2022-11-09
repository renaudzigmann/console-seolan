<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template name="affActualite">
		<xsl:if test="count(Actualite) > 0">
			<div class="spPublicationActualite" id="sp-actualite">
				<h2>
					<xsl:call-template name="imageOfAPartie">
						<xsl:with-param name="nom">actualites</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Actualités</xsl:text>
				</h2>
				<xsl:apply-templates select="Actualite"/>
			</div>
		</xsl:if>
	</xsl:template>

	<xsl:template match="Actualite">
		<xsl:choose>
			<xsl:when test="contains(@type,'Fil')">
				<xsl:if test="count(document(@URL)/rss/channel/item) > 0">
					<xsl:call-template name="filActualite">
						<xsl:with-param name="channel">
							<xsl:value-of select="document(@URL)/rss/channel"/>
						</xsl:with-param>
						<xsl:with-param name="nbItems">
							<xsl:choose>
								<xsl:when test="count(../Actualite) > 1">3</xsl:when>
								<xsl:otherwise>6</xsl:otherwise>
							</xsl:choose>
						</xsl:with-param>
					</xsl:call-template>
				</xsl:if>
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="articleActualite"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="articleActualite">	
		<xsl:variable name="title">
			<xsl:value-of select="../dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="Titre"/>
		</xsl:variable>
		<ul class="spPublicationActualite">
			<li class="spPublicationActualite">
				<h3>
	    			<xsl:call-template name="getSiteLink">
	    				<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
	    				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
	    				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
					</xsl:call-template>
				</h3>
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@type"/>
			</li>
		</ul>
	</xsl:template>

	<xsl:template name="filActualite">
		<xsl:param name="channel"/>
		<xsl:param name="nbItems"/>
		<ul class="spPublicationActualite">
			<xsl:for-each select="document(@URL)/rss/channel/item[position()&lt;$nbItems]">
				<li class="spPublicationActualite">
					<h3>
		    			<xsl:call-template name="getSiteLink">
		    				<xsl:with-param name="href"><xsl:value-of select="link"/></xsl:with-param>
		    				<xsl:with-param name="title"><xsl:value-of select="link"/></xsl:with-param>
		    				<xsl:with-param name="text"><xsl:value-of select="title"/></xsl:with-param>
						</xsl:call-template>
					</h3>
					<xsl:text> - </xsl:text>
					<xsl:call-template name="transformRssDate">
						<xsl:with-param name="date">
							<xsl:choose>
								<xsl:when test="dc:date"> 
									 <xsl:value-of select="dc:date"/>
								</xsl:when>
								<xsl:when test="pubDate"> 
									<xsl:value-of select="pubDate"/>
								</xsl:when>
							</xsl:choose>
						</xsl:with-param>
					</xsl:call-template>
					<br/>
					<xsl:value-of select="description" disable-output-escaping="yes"/>
				</li>	
			</xsl:for-each>
		</ul>
	</xsl:template>
		
</xsl:stylesheet>
