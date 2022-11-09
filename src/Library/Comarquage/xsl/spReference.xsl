<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template name="affReference">
		<xsl:if test="count(Reference) > 0">
			<div class="spPublicationReference" id="sp-reference">
				<h2>
					<xsl:call-template name="imageOfAPartie">
						<xsl:with-param name="nom">references</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Références</xsl:text>
                                      </h2>
                                      <ul>
                                        <xsl:apply-templates select="Reference"/>
                                      </ul>
			</div>
		</xsl:if>
	</xsl:template>
	
	<xsl:template match="Reference">
		<xsl:variable name="title">
			<xsl:value-of select="../dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:text>Référence</xsl:text>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="Titre"/>
		</xsl:variable>
			<li class="spPublicationReference">
				<span>
			 		<xsl:call-template name="getSiteLink">
			 			<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
			 			<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
			 			<xsl:with-param name="text">
			 				<xsl:value-of select="Titre"/>
							<xsl:if test="@commentaireLien">
								<xsl:text> - </xsl:text>
								<xsl:value-of select="@commentaireLien"/>
							</xsl:if>
			 			</xsl:with-param>
					</xsl:call-template>
				</span>
				<xsl:if test="Complement">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="Complement"/>
				</xsl:if>
			</li>
	</xsl:template>
	
	<xsl:template match="Reference" mode="ServiceComplementaire">
		<xsl:variable name="titre">
			<xsl:value-of select="@commentaireLien"/>
			<xsl:if test="@format">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@format"/>
			</xsl:if>
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
				<xsl:call-template name="getSiteLink">
					<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
					<xsl:with-param name="title"><xsl:value-of select="$titre"/></xsl:with-param>
					<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
					<xsl:with-param name="lang"><xsl:value-of select="@langue"/></xsl:with-param>
				</xsl:call-template>
				<xsl:if test="@format">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="@format"/>
				</xsl:if>
				<xsl:if test="@poids">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="@poids"/>
				</xsl:if>
				<xsl:if test="Complement">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="Complement"/>
				</xsl:if>
				<xsl:if test="Source">
					<xsl:text> - </xsl:text>
					<span class="italic"><xsl:value-of select="Source"/></span>
				</xsl:if>
			</li>
	</xsl:template>

</xsl:stylesheet>
