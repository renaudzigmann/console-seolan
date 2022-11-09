<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template name="affLienExterneCommente">
		<xsl:if test="count(LienExterneCommente) > 0">
			<div class="spPublicationLienExterneCommente" id="sp-lien-externe">
				<xsl:call-template name="imageOfAPartie">
					<xsl:with-param name="nom">enligne</xsl:with-param>
				</xsl:call-template>
				<h2><xsl:text>Liens externes</xsl:text></h2>
				<xsl:apply-templates select="LienExterneCommente"/>
			</div>
		</xsl:if>
	</xsl:template>

	<xsl:template match="RessourceWeb">
		<a href="{@URL}" title="{@URL}" rel="noffolow" class="spRessourceWeb">
			<xsl:apply-templates/>
		</a>
	</xsl:template>

	<xsl:template match="LienExterne">
		<xsl:variable name="title">
			<xsl:choose>
				<xsl:when test="@commentaireLien">
					<xsl:value-of select="@commentaireLien"/>
				</xsl:when>
				<xsl:when test="Titre">
					<xsl:value-of select="Titre"/>
				</xsl:when>
				<xsl:when test="text()">
					<xsl:value-of select="text()"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="@URL"/>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:if test="@format">
				<xsl:text> - </xsl:text>
				<xsl:call-template name="upperCase">
					<xsl:with-param name="string"><xsl:value-of select="@format"/></xsl:with-param>
				</xsl:call-template>
			</xsl:if>
			<xsl:if test="@poids">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="@poids"/>
			</xsl:if>
		</xsl:variable>
  			<xsl:call-template name="getSiteLink">
  				<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
  				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
  				<xsl:with-param name="text"><xsl:value-of select="$title"/></xsl:with-param>
		</xsl:call-template>
	</xsl:template>
	
	<xsl:template match="LienInterne">
		<xsl:variable name="id" select="@LienPublication"/>
		<xsl:choose>
			<xsl:when test="@type = 'Sigle'">
				<xsl:variable name="titre">
					<xsl:value-of select="//*/Abreviation[@ID=$id]/Titre"/>
				</xsl:variable>
				<xsl:variable name="texte">
					<xsl:value-of select="normalize-space(//*/Abreviation[@ID=$id]/Texte)"/>
				</xsl:variable>
				<abbr title="{$texte}" class="spSigle"><xsl:value-of select="$titre"/></abbr>
			</xsl:when>
			<xsl:when test="@type = 'Acronyme'">
				<xsl:variable name="titre">
					<xsl:value-of select="//*/Abreviation[@ID=$id]/Titre"/>
				</xsl:variable>
				<xsl:variable name="texte">
					<xsl:value-of select="normalize-space(//*/Abreviation[@ID=$id]/Texte)"/>
				</xsl:variable>
				<acronym title="{$texte}" class="spAcronyme"><xsl:value-of select="$titre"/></acronym>
			</xsl:when>
			<xsl:when test="contains(@type,'Définition')">
				<xsl:variable name="titre">
					<xsl:value-of select="//Publication/Definition[@ID=$id]/Titre"/>
				</xsl:variable>
				<xsl:variable name="texte">
					<xsl:value-of select="normalize-space(//Publication/Definition[@ID=$id]/Texte)"/>
				</xsl:variable>
				<xsl:call-template name="getPublicationLink">
					<xsl:with-param name="href"><xsl:value-of select="@LienPublication"/></xsl:with-param>
					<xsl:with-param name="title"><xsl:value-of select="$titre"/></xsl:with-param>
					<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="getPublicationLink">
					<xsl:with-param name="href"><xsl:value-of select="@LienPublication"/></xsl:with-param>
					<xsl:with-param name="title"><xsl:value-of select="text()"/></xsl:with-param>
					<xsl:with-param name="text"><xsl:value-of select="text()"/></xsl:with-param>
				</xsl:call-template>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="Commentaire">
		<div class="spCommentaire"><xsl:apply-templates/></div>
	</xsl:template>

	<xsl:template match="LienWeb">
		<xsl:variable name="texte">
			<xsl:choose>
				<xsl:when test="@commentaireLien">
					<xsl:value-of select="@commentaireLien"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="@URL"/>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		<xsl:variable name="titre">
			<xsl:value-of select="$texte"/>
			<xsl:if test="@format">
				<xsl:text> - </xsl:text>
				<xsl:call-template name="upperCase">
					<xsl:with-param name="string"><xsl:value-of select="@format"/></xsl:with-param>
				</xsl:call-template>
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
			<li class="spLienWeb">
				<xsl:call-template name="getSiteLink">
					<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
					<xsl:with-param name="title"><xsl:value-of select="$titre"/></xsl:with-param>
					<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
					<xsl:with-param name="lang"><xsl:value-of select="@langue"/></xsl:with-param>
				</xsl:call-template>
				<xsl:if test="@format">
					<xsl:text> - </xsl:text>
					<xsl:call-template name="upperCase">
						<xsl:with-param name="string"><xsl:value-of select="@format"/></xsl:with-param>
					</xsl:call-template>
				</xsl:if>
				<xsl:if test="@poids">
					<xsl:text> - </xsl:text>
					<xsl:value-of select="@poids"/>
				</xsl:if>
				<xsl:if test="Source">
					<xsl:text> - </xsl:text>
					<span class="italic"><xsl:value-of select="Source"/></span>
				</xsl:if>
			</li>
	</xsl:template>

	<xsl:template match="LienMonServicePublic">
		<xsl:variable name="texte">
			<xsl:value-of select="@commentaireLien"/>
			<xsl:text> - mon.service-public.fr</xsl:text>
		</xsl:variable>
			<li class="spLienWeb">
				<xsl:call-template name="getSiteLink">
					<xsl:with-param name="href"><xsl:value-of select="@URL"/></xsl:with-param>
					<xsl:with-param name="title"><xsl:value-of select="$texte"/></xsl:with-param>
					<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
					<xsl:with-param name="lang"><xsl:value-of select="@langue"/></xsl:with-param>
				</xsl:call-template>
			</li>
	</xsl:template>

</xsl:stylesheet>
