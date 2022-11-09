<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	  	
	<xsl:template match="Texte">
		<xsl:apply-templates/>
	</xsl:template>

	<xsl:template match="Texte" mode="Definition">
		<xsl:apply-templates mode="Definition"/>
	</xsl:template>

	<xsl:template match="Texte" mode="OuSAdresser">
		<xsl:apply-templates mode="OuSAdresser"/>
	</xsl:template>

	<xsl:template match="Exposant">
		<sup class="spExposant"><xsl:apply-templates/></sup>
	</xsl:template>
	
	<xsl:template match="Indice">
		<sub class="spIndice"><xsl:apply-templates/></sub>
	</xsl:template>

	<xsl:template match="MiseEnEvidence">
		<xsl:if test="text()">
			<strong class="spMiseEnEvidence"><xsl:apply-templates/></strong>
		</xsl:if>
	</xsl:template>

	<xsl:template match="Expression">
		<xsl:if test="text()">
			<em class="spExpression"><xsl:apply-templates/></em>
		</xsl:if>
	</xsl:template>

	<xsl:template match="Citation">
		<xsl:if test="text()">
			<em class="spCitation"><xsl:apply-templates/></em>
		</xsl:if>
	</xsl:template>

	<xsl:template match="Variable">
		<xsl:if test="text()">
			<em class="spVariable"><xsl:apply-templates/></em>
		</xsl:if>
	</xsl:template>

	<xsl:template match="Valeur">
		<xsl:if test="text()">
			<em class="spValeur"><xsl:apply-templates/></em>
		</xsl:if>
	</xsl:template>

	<xsl:template match="ASavoir">
		<div class="spASavoir">
                        <xsl:if test="$AFF_IMAGES = 'true'">
			<img width="20" height="20" class="entiteImageFloatLeft">
				<xsl:attribute name="src">
					<xsl:value-of select="$IMAGES"/>
						<xsl:text>savoir.jpg</xsl:text>
					</xsl:attribute>
				<xsl:attribute name="alt">
					<xsl:text>A savoir</xsl:text>
				</xsl:attribute>
			</img>
                        </xsl:if>
			<xsl:apply-templates/>
		</div>
	</xsl:template>
	
	<xsl:template match="TermeEtranger">
		<span xml:lang="{@langue}" lang="{@langue}" class="spTermeEtranger" title="Terme étranger « {@langue} »">
			<xsl:apply-templates/>
		</span>
	</xsl:template>

	<xsl:template match="ANoter">
		<div class="spANoter well">
                        <xsl:if test="$AFF_IMAGES = 'true'">
			<img width="20" height="20" class="entiteImageFloatLeft">
				<xsl:attribute name="src">
					<xsl:value-of select="$IMAGES"/>
						<xsl:text>note.jpg</xsl:text>
					</xsl:attribute>
				<xsl:attribute name="alt">
					<xsl:text>A noter</xsl:text>
				</xsl:attribute>
			</img>
                        </xsl:if>
			<xsl:apply-templates/>
		</div>
	</xsl:template>

	<xsl:template match="Attention">
		<div class="spAttention alert alert-danger">
                        <xsl:if test="$AFF_IMAGES = 'true'">
			<img width="20" height="20" class="entiteImageFloatLeft">
				<xsl:attribute name="src">
					<xsl:value-of select="$IMAGES"/>
						<xsl:text>attention.jpg</xsl:text>
					</xsl:attribute>
				<xsl:attribute name="alt">
					<xsl:text>Attention</xsl:text>
				</xsl:attribute>
			</img>
                        </xsl:if>
			<xsl:apply-templates/>
		</div>
	</xsl:template>

	<xsl:template match="Description">
		<p class="spDescription"><xsl:apply-templates/></p>
	</xsl:template>
		
	<xsl:template match="Montant">
		<span class="spMontant">
			<xsl:value-of select="text()"/>
			<xsl:text> </xsl:text>
			<abbr title="Euros">€</abbr>
		</span>
	</xsl:template>
	
	<xsl:template match="Source"/>

</xsl:stylesheet>
