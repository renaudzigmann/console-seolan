<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template match="PivotLocal">
		<!--<xsl:apply-templates/>-->
	</xsl:template>

	<xsl:template match="PTA-PL-Code"/>
	<xsl:template match="PTA-PL-Coproducteur"/>
	<xsl:template match="PTA-PL-ZoneCompetenceGeographique"/>
	<xsl:template match="PTA-PL-Complement"/>
	<xsl:template match="PTA-PL-Titre">
		<h3><xsl:value-of select="text()"/></h3>
	</xsl:template>

	<xsl:template match="PTA-PL-Adresse">
		<xsl:param name="cssWidth"/>
		<div class="spPublicationPivotLocalOSA">
			<strong>Adresse :</strong><br/>
			<div class="entitePivotLocalAdresse">
				<xsl:if test="PTA-PL-Complement">
					<strong><xsl:value-of select="PTA-PL-Complement"/></strong><br/>
				</xsl:if>
				<xsl:value-of select="../PTA-PL-Titre"/><br/>
				<xsl:for-each select="PTA-PL-AdresseLigne">
					<xsl:apply-templates/><br/>
				</xsl:for-each>
			</div>
		</div>
	</xsl:template>

	<xsl:template match="PTA-PL-AdresseLigne">
		<xsl:apply-templates/>
	</xsl:template>

	<xsl:template match="PTA-PL-Communication">
		<div class="spPublicationPivotLocalOSA">
			<xsl:for-each select="child::*">
				<xsl:apply-templates select="."/><br/>
			</xsl:for-each>
		</div>
		<xsl:if test="PTA-PL-Defibrillateur">
			<strong>« Bâtiment équipé d'un défibrillateur »</strong><br/><br/>
		</xsl:if>
	</xsl:template>

	<xsl:template match="PTA-PL-Elu">
		<strong>
			<span class="underline">Elu en charge</span> : <xsl:value-of select="text()"/>
		</strong>
	</xsl:template>

	<xsl:template match="PTA-PL-Service">
		<strong><xsl:value-of select="text()"/></strong>
	</xsl:template>
	
	<xsl:template match="PTA-PL-Responsable">
		<strong>
			<span class="underline">Responsable</span> : <xsl:value-of select="text()"/>
			<xsl:if test="../PTA-PL-Complement">
				<xsl:text> - </xsl:text>
				<xsl:value-of select="../PTA-PL-Complement"/>
			</xsl:if>
		</strong>
	</xsl:template>

	<xsl:template match="PTA-PL-Tel">
		<xsl:if test="$AFF_PICTOS = 'true'">
			<xsl:choose>
				<xsl:when test="(substring(text(),1,2) = '06') or (substring(text(),1,5) = '+33 6')">
					<img src="{$PICTOS}mobile.jpg" width="11" height="20" alt="Téléphone mobile - portable"/>
				</xsl:when>
				<xsl:when test="(substring(text(),1,2) = '07') or (substring(text(),1,5) = '+33 7')">
					<img src="{$PICTOS}mobile.jpg" width="11" height="20" alt="Téléphone mobile - portable"/>
				</xsl:when>
				<xsl:otherwise>
					<img src="{$PICTOS}telephone.jpg" width="14" height="20" alt="Téléphone"/>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:text>&#32;</xsl:text>
		</xsl:if>
		<xsl:if test="@titre">
			<xsl:text>(</xsl:text><xsl:value-of select="@titre"/><xsl:text>)&#32;</xsl:text>
 		</xsl:if>
		<xsl:value-of select="text()"/>
	</xsl:template>

	<xsl:template match="PTA-PL-Fax">
		<xsl:if test="$AFF_PICTOS = 'true'">
			<img src="{$PICTOS}fax.jpg" width="20" height="20" alt="Télécopie - fax"/>
			<xsl:text>&#32;</xsl:text>
		</xsl:if>
		<xsl:value-of select="text()"/>
	</xsl:template>

	<xsl:template match="PTA-PL-SiteInternet">
		<xsl:if test="$AFF_PICTOS = 'true'">
			<img src="{$PICTOS}www.jpg" width="32" height="20" alt="Site internet www"/>
			<xsl:text>&#32;</xsl:text>
		</xsl:if>
		<xsl:choose>
			<xsl:when test="@titre">
		 		<xsl:call-template name="getSiteLink">
		 			<xsl:with-param name="href"><xsl:value-of select="text()"/></xsl:with-param>
		 			<xsl:with-param name="title"><xsl:value-of select="@titre"/></xsl:with-param>
		 			<xsl:with-param name="text"><xsl:value-of select="@titre"/></xsl:with-param>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
		 		<xsl:call-template name="getSiteLink">
		 			<xsl:with-param name="href"><xsl:value-of select="text()"/></xsl:with-param>
		 			<xsl:with-param name="title"><xsl:text>Site internet de « </xsl:text><xsl:value-of select="//PivotLocal/PTA-PL-Titre"/><xsl:text> »</xsl:text></xsl:with-param>
		 			<xsl:with-param name="text"><xsl:value-of select="text()"/></xsl:with-param>
				</xsl:call-template>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="PTA-PL-Courriel">
		<xsl:if test="$AFF_PICTOS = 'true'">
			<img src="{$PICTOS}courriel.jpg" width="24" height="20" alt="Courriel"/>
			<xsl:text>&#32;</xsl:text>
		</xsl:if>
		<xsl:variable name="text">
			<xsl:value-of select="normalize-space(text())"/>
		</xsl:variable>
		<a href="mailto:{$text}">
			<xsl:attribute name="title"><xsl:text>Courriel de « </xsl:text><xsl:value-of select="//PivotLocal/PTA-PL-Titre"/><xsl:text> »</xsl:text></xsl:attribute>
			<xsl:value-of select="$text"/>
		</a>
	</xsl:template>

	<xsl:template match="PTA-PL-ContactEnLigne">
		<xsl:if test="$AFF_PICTOS = 'true'">
			<img src="{$PICTOS}www.jpg" width="32" height="20" alt="Site internet - www"/>
			<xsl:text>&#32;</xsl:text>
		</xsl:if>
 		<xsl:call-template name="getSiteLink">
 			<xsl:with-param name="href"><xsl:value-of select="text()"/></xsl:with-param>
 			<xsl:with-param name="title"><xsl:value-of select="text()"/></xsl:with-param>
 			<xsl:with-param name="text">
 				<xsl:choose>
 					<xsl:when test="@titre">
 						<xsl:text>Contact - </xsl:text><xsl:value-of select="@titre"/>
 					</xsl:when>
 					<xsl:otherwise>
						<xsl:text>Contact - </xsl:text><xsl:value-of select="../../PTA-PL-Titre"/>
					</xsl:otherwise>
  				</xsl:choose>
 			</xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<xsl:template match="PTA-PL-Horaires">
		<xsl:param name="cssWidth"/>
		<div class="spPublicationPivotLocalOSA">
			<strong>
				<xsl:text>Horaires d'ouverture</xsl:text> 
				<xsl:if test="PTA-PL-Complement">
					<xsl:text> (</xsl:text>
					<xsl:value-of select="PTA-PL-Complement"/>
					<xsl:text>)</xsl:text>
				</xsl:if>
				<xsl:text> :</xsl:text>
			</strong><br/>
			<div class="entitePivotLocalHoraires">
				<xsl:for-each select="PTA-PL-Horaire">
					<xsl:apply-templates/><br/>
				</xsl:for-each>
			</div>
		</div>
	</xsl:template>

	<xsl:template match="PTA-PL-Horaire">
		<xsl:apply-templates/>
	</xsl:template>

	<xsl:template match="PTA-PL-Gps">
		<xsl:variable name="code">
			<xsl:text>google_maps_</xsl:text>
			<xsl:value-of select="../PTA-PL-Code"/>
			<xsl:if test="@code">
				<xsl:value-of select="@code"/>
			</xsl:if>
		</xsl:variable>
		<xsl:variable name="titre">
			<xsl:value-of select="../PTA-PL-Titre"/>
			<xsl:if test="@code">
				<xsl:text> - </xsl:text><xsl:value-of select="@code"/>
			</xsl:if>
		</xsl:variable>
		<div class="spGoogleMaps" id="{$code}">
			<span id="{$code}_nom"><xsl:value-of select="$titre"/></span><br/>
			<span id="{$code}_latitude"><xsl:value-of select="@latitude"/></span><br/>
			<span id="{$code}_longitude"><xsl:value-of select="@longitude"/></span><br/>
		</div>
	</xsl:template>

	<xsl:template match="PTA-PL-Gps" mode="AffPivotLocal">

		<xsl:variable name="latDegrees">
			<xsl:value-of select="floor(@latitude)"/>
		</xsl:variable>
		<xsl:variable name="latTemp">
			<xsl:value-of select="60 * (@latitude - $latDegrees)"/>
		</xsl:variable>
		<xsl:variable name="latMinutes">
			<xsl:value-of select="floor($latTemp)"/>
		</xsl:variable>
		<xsl:variable name="latTemp2">
			<xsl:value-of select="60 * ($latTemp - $latMinutes)"/>
		</xsl:variable>
		<xsl:variable name="latSecondes">
			<xsl:value-of select="floor($latTemp2)"/>
		</xsl:variable>
		<xsl:variable name="latCents">
			<xsl:value-of select="round(100 * ($latTemp2 - $latSecondes))"/>
		</xsl:variable>

		<xsl:variable name="lonDegrees">
			<xsl:value-of select="floor(@longitude)"/>
		</xsl:variable>
		<xsl:variable name="lonTemp">
			<xsl:value-of select="60 * (@longitude - $lonDegrees)"/>
		</xsl:variable>
		<xsl:variable name="lonMinutes">
			<xsl:value-of select="floor($lonTemp)"/>
		</xsl:variable>
		<xsl:variable name="lonTemp2">
			<xsl:value-of select="60 * ($lonTemp - $lonMinutes)"/>
		</xsl:variable>
		<xsl:variable name="lonSecondes">
			<xsl:value-of select="floor($lonTemp2)"/>
		</xsl:variable>
		<xsl:variable name="lonCents">
			<xsl:value-of select="round(100 * ($lonTemp2 - $lonSecondes))"/>
		</xsl:variable>

		<div class="spPublicationPivotLocalOSA">
			<span class="bold">
				<xsl:text>Coordonnées GPS</xsl:text>
				<xsl:if test="@code">
					<xsl:text> (</xsl:text><xsl:value-of select="@code"/><xsl:text>)</xsl:text>
				</xsl:if>
				<xsl:text> :</xsl:text>
			</span><br/> 
			<div class="entitePivotLocalAffGps">
				<xsl:text>DD (lat x lon) : </xsl:text>
				<xsl:value-of select="@latitude"/>
				<xsl:text> x </xsl:text>
				<xsl:value-of select="@longitude"/>
				<br/>
				<xsl:text>DMS (lat x lon) : </xsl:text>
				<xsl:text>N</xsl:text>
				<xsl:value-of select="$latDegrees"/>
				<xsl:text>°</xsl:text>
				<xsl:value-of select="$latMinutes"/>
				<xsl:text>'</xsl:text>
				<xsl:value-of select="$latSecondes"/>
				<xsl:text>.</xsl:text>
				<xsl:value-of select="$latCents"/>
				<xsl:text>'' x E</xsl:text>
				<xsl:value-of select="$lonDegrees"/>
				<xsl:text>°</xsl:text>
				<xsl:value-of select="$lonMinutes"/>
				<xsl:text>'</xsl:text>
				<xsl:value-of select="$lonSecondes"/>
				<xsl:text>.</xsl:text>
				<xsl:value-of select="$lonCents"/>
				<xsl:text>''</xsl:text>
				<br/>
			</div>
		</div>
		
	</xsl:template>

</xsl:stylesheet>
