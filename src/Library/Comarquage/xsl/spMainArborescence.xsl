<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

  	<xsl:import href="spVariables.xsl"/>
  	<xsl:import href="spCommon.xsl"/>
  	<xsl:import href="spFilDAriane.xsl"/>

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
   	<xsl:template match="/Arborescence">
		<div class="spArborescenceMain">
	   		<div class="center">
	   			<h1>Guide des droits et démarches des <xsl:value-of select="$CATEGORIE_NOM"/></h1>
	   		</div>
			<div class="spArborescence">
				<xsl:apply-templates select="Item">
					<xsl:sort select="Titre"/>
				</xsl:apply-templates>
	 		</div>
	 	</div>
  	</xsl:template>
	
	<xsl:template match="Item">
		<xsl:variable name="col" select="2"/>
		<xsl:variable name="classTheme">
			<xsl:text>spArborescenceItem</xsl:text><xsl:value-of select="$col"/><xsl:text>Col</xsl:text>
		</xsl:variable>
		<xsl:if test="((position() mod $col) = 1) and (position() > 1)">
			<br class="clearall"/>
		</xsl:if>
		<div class="{$classTheme}">
			<h2>
				<xsl:call-template name="imageOfATheme">
					<xsl:with-param name="id" select="@ID"/>
					<xsl:with-param name="class" select="'entiteImageFloatLeft'"/>
				</xsl:call-template>			
	   			<xsl:call-template name="getPublicationLink">
	   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
	   				<xsl:with-param name="title"><xsl:value-of select="Titre"/></xsl:with-param>
	   				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
				</xsl:call-template>
			</h2>
			<ul class="spPublicationNoeud">
				<xsl:apply-templates select="Item" mode="SousItem">
					<xsl:sort select="Titre"/>
				</xsl:apply-templates>
				<li class="spPublicationNoeud spPublicationDFT spPublicationDFTLast">...</li>
			</ul>
		</div>	
	</xsl:template>

	<xsl:template match="Item" mode="SousItem">
		<xsl:variable name="title">
			<xsl:value-of select="../Titre"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="Titre"/>
		</xsl:variable>
		<xsl:variable name="texte">
			<xsl:choose>
				<xsl:when test="substring-before(Titre,':')">
					<xsl:value-of select="substring-before(Titre,':')"/>
				</xsl:when>
				<xsl:when test="substring-before(Titre,'(')">
					<xsl:value-of select="substring-before(Titre,'(')"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="Titre"/>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>	
		<xsl:variable name="class">
			<xsl:text>spPublicationNoeud spPublicationDFT</xsl:text>
			<xsl:if test="position() = 1">
				<xsl:text> spPublicationDFTFirst</xsl:text>
			</xsl:if>
			<xsl:if test="position() = count(../Item)">
				<xsl:text> spPublicationDFTLast</xsl:text>
			</xsl:if>
		</xsl:variable>
		<li class="{$class}">
			<h3 class="spPublicationDossier">
	   			<xsl:call-template name="getPublicationLink">
	   				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
	   				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
	   				<xsl:with-param name="text"><xsl:value-of select="$texte"/></xsl:with-param>
				</xsl:call-template>
			</h3>
		</li>
	</xsl:template>	
</xsl:stylesheet>
