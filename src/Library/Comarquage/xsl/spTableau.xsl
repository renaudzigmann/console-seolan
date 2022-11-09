<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
	<xsl:template match="Tableau">
		<table class="spTableau" cellspacing="0">
			<xsl:attribute name="summary">
				<xsl:value-of select="Resume"/>
			</xsl:attribute>
			<xsl:apply-templates select="Titre"/>
			<xsl:if test="count(Colonne) > 0">
				<colgroup>
					<xsl:apply-templates select="Colonne"/>
				</colgroup>
			</xsl:if>
			<xsl:if test="count(Rangée[@type='header']) > 0">
				<thead>
					<xsl:apply-templates select="Rangée[@type='header']"/>
				</thead>
			</xsl:if>
			<xsl:if test="count(Rangée[@type='normal']) > 0">
				<tbody>
					<xsl:apply-templates select="Rangée[@type='normal']"/>
				</tbody>
			</xsl:if>
		</table>
	</xsl:template>
	
	<xsl:template match="Resume">
	</xsl:template>

	<xsl:template match="Colonne">
		<col span="1">
			<xsl:attribute name="class">
				<xsl:text>spTableauCol</xsl:text><xsl:value-of select="@type"/>
			</xsl:attribute>
			<xsl:attribute name="width">
				<xsl:value-of select="@largeur"/><xsl:text>%</xsl:text>
			</xsl:attribute>
		</col>
	</xsl:template>

	<xsl:template match="Rangée">
		<tr class="spTableauRangee">
			<xsl:choose>
				<xsl:when test="@type = 'header'">
					<xsl:apply-templates mode="header"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates mode="normal"/>
				</xsl:otherwise>
			</xsl:choose>
		</tr>
	</xsl:template>

	<xsl:template match="Cellule" mode="normal">
		<xsl:variable name="celPos">
			<xsl:number/>
		</xsl:variable>
		<xsl:variable name="colNb">
			<xsl:value-of select="count(../../Colonne)"/>
		</xsl:variable>
		<xsl:variable name="celNb">
			<xsl:value-of select="count(../Cellule)"/>
		</xsl:variable>
		<xsl:variable name="fusNb">
			<xsl:value-of select="count(../Cellule/@fusionHorizontale)"/>
		</xsl:variable>
		<xsl:variable name="fusSum">
			<xsl:value-of select="sum(../Cellule/@fusionHorizontale)"/>
		</xsl:variable>
		<xsl:variable name="celNbReel">
			<xsl:value-of select="$celNb + $fusSum - $fusNb"/>
		</xsl:variable>
		<xsl:choose>
			<!--
				Pour l'accessilité, les entêtes <th> sont situées sur la 1ère ligne ou la 1ère colonne.
				La 1ère ligne est gérée grâce à l'attibut @type="header".
				Pour la 1ère colonne, il faut s'assurer que la 1ère cellule de la rangée est bien située à la 1ère colonne (cas de fusion).
			-->
			<xsl:when test="($celPos = 1) and ($celNbReel = $colNb) and (../../Colonne[position() = $celPos]/@type = 'header')">
				<th class="spTableauCelheader" scope="row">
					<xsl:if test="@fusionHorizontale">
						<xsl:attribute name="colspan">
							<xsl:value-of select="@fusionHorizontale"/>
						</xsl:attribute>
					</xsl:if>
					<xsl:if test="@fusionVerticale">
						<xsl:attribute name="rowspan">
							<xsl:value-of select="@fusionVerticale"/>
						</xsl:attribute>
					</xsl:if>
					<xsl:apply-templates/>
				</th>
			</xsl:when>
			<xsl:otherwise>
				<td class="spTableauCelnormal">
					<xsl:if test="@fusionHorizontale">
						<xsl:attribute name="colspan">
							<xsl:value-of select="@fusionHorizontale"/>
						</xsl:attribute>
					</xsl:if>
					<xsl:if test="@fusionVerticale">
						<xsl:attribute name="rowspan">
							<xsl:value-of select="@fusionVerticale"/>
						</xsl:attribute>
					</xsl:if>
					<xsl:apply-templates/>
				</td>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="Cellule" mode="header">
		<th class="spTableauCelheader" scope="col">
			<xsl:if test="@fusionHorizontale">
				<xsl:attribute name="colspan">
					<xsl:value-of select="@fusionHorizontale"/>
				</xsl:attribute>
			</xsl:if>
			<xsl:if test="@fusionVerticale">
				<xsl:attribute name="rowspan">
					<xsl:value-of select="@fusionVerticale"/>
				</xsl:attribute>
			</xsl:if>
			<xsl:apply-templates/>
		</th>
	</xsl:template>

</xsl:stylesheet>
