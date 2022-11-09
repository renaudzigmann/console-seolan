<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template name="affPourEnSavoirPlus">
		<xsl:if test="count(PourEnSavoirPlus) > 0">
			<div class="spPublicationPESP" id="sp-pour-en-savoir-plus">
				<h2>
					<xsl:call-template name="imageOfAPartie">
						<xsl:with-param name="nom">savoir-plus</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Pour en savoir plus</xsl:text>
				</h2>
                                <ul>
                                  <xsl:apply-templates select="PourEnSavoirPlus"/>
                                </ul>
			</div>
		</xsl:if>	
	</xsl:template>

	<xsl:template match="PourEnSavoirPlus">
		<xsl:variable name="titre">
			<xsl:value-of select="../dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:choose>
				<xsl:when test="@commentaireLien">
					<xsl:value-of select="@commentaireLien"/>			
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="Titre"/>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:if test="@poids">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@poids"/>
			</xsl:if>
			<xsl:if test="@langue">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@langue"/>
			</xsl:if>
		</xsl:variable>
		<xsl:variable name="texte">
			<xsl:value-of select="Titre"/>
		</xsl:variable>
			<li class="spLienWeb">
				<xsl:choose>
					<xsl:when test="@URL != ''">
						<xsl:call-template name="getSiteLink">
							<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
							<xsl:with-param name="title"><xsl:value-of select="$titre"/></xsl:with-param>
							<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
						</xsl:call-template>            
					</xsl:when>
					<xsl:otherwise>                        					
						<xsl:call-template name="getPublicationLink">
							<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
							<xsl:with-param name="title"><xsl:value-of select="$titre"/></xsl:with-param>
							<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
						</xsl:call-template>                        
					</xsl:otherwise>
				</xsl:choose>
				<xsl:if test="@poids">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="@poids"/>
				</xsl:if>
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
				<xsl:if test="Source">
					<xsl:text> - </xsl:text>
					<span class="italic"><xsl:value-of select="Source"/></span>
				</xsl:if>
			</li>
	</xsl:template>

</xsl:stylesheet>
