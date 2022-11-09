<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

 	<xsl:import href="spVariables.xsl"/>
  	<xsl:import href="spCommon.xsl"/>
  	<xsl:import href="spTitre.xsl"/>
  	<xsl:import href="spFilDAriane.xsl"/>

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
  	
    <xsl:key name="pivots" match="Pivot" use="translate(substring(Titre,1,1),'ÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ','AAAAAAEEEEIIIIOOOOOOUUUU')"/>
	<xsl:variable name="letters" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"/>

   	<xsl:template match="/Pivots">
		<div class="spIndexMain">
	 		<xsl:call-template name="getBarreThemes"/>
			<xsl:call-template name="createFilDArianePivots"/>
			<div class="spCenter">
				<h1>
					<xsl:text>Annuaire de A à Z du guide des </xsl:text>
					<xsl:value-of select="$CATEGORIE_NOM"/>
				</h1>
			</div>
	    	<div class="spLetters">
		   		<ul>
		   			<xsl:call-template name="createIndex"/>
		   		</ul>
	    	</div>
			<xsl:apply-templates select="Pivot[generate-id(.)=generate-id(key('pivots',translate(substring(Titre,1,1),'ÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ','AAAAAAEEEEIIIIOOOOOOUUUU'))[1])]">
				<xsl:sort select="translate(Titre,'ÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ','AAAAAAEEEEIIIIOOOOOOUUUU')"/>
			</xsl:apply-templates>
		</div>
  	</xsl:template>
	
    <xsl:template match="Pivot">
    	<xsl:variable name="title" select="translate(Titre,'ÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ','AAAAAAEEEEIIIIOOOOOOUUUU')"/>
    	<xsl:variable name="letter" select="substring($title,1,1)"/>
    	<xsl:if test="contains($letters,$letter)">
	    	<div class="spIndex">
	    		<h2 id="sp-letter-{$letter}">
	    			<xsl:value-of select="$letter"/>
	    		</h2>
	    		<ul class="spPublicationNoeud">
			        <xsl:for-each select="key('pivots',$letter)">
						<xsl:sort select="$title"/>
						<xsl:variable name="class">
							<xsl:text>spPublicationNoeud spPublicationDFT</xsl:text>
							<xsl:if test="position() = 1">
								<xsl:text> spPublicationDFTFirst</xsl:text>
							</xsl:if>
						</xsl:variable>
						<xsl:variable name="href">
							<xsl:text>Annuaire-</xsl:text>
							<xsl:value-of select="@ID"/>
							<xsl:text>-pivot-</xsl:text>
							<xsl:call-template name="textWithoutAccent">
								<xsl:with-param name="string" select="Titre"/>
							</xsl:call-template>						
						</xsl:variable>
						<li class="{$class}">
							<h3 class="spPublicationRessource">
					   			<xsl:call-template name="getPublicationLink">
					   				<xsl:with-param name="href"><xsl:value-of select="$href"/></xsl:with-param>
					   				<xsl:with-param name="title"><xsl:value-of select="Titre"/></xsl:with-param>
					   				<xsl:with-param name="text"><xsl:value-of select="Titre"/></xsl:with-param>
								</xsl:call-template>
							</h3>
						</li>
			        </xsl:for-each>
			    </ul>
		 		<xsl:call-template name="ancreTop"/>
		   	</div>
		</xsl:if>
    </xsl:template>
    
    <xsl:template name="createIndex">
    	<xsl:param name="index" select="1"/>
    	<xsl:variable name="letter" select="substring($letters,$index,1)"/>
       	<li>
        	<xsl:choose>
	        	<xsl:when test="key('pivots',$letter)">
	        		<a href="#sp-letter-{$letter}" title="Contacts commençant par la lettre {$letter}">
			        	<xsl:value-of select="$letter"/>
	        		</a>
	        	</xsl:when>
	        	<xsl:otherwise>
		        	<xsl:value-of select="$letter"/>
	        	</xsl:otherwise>
        	</xsl:choose>
        </li>
        <xsl:if test="not($index = string-length($letters))">
            <xsl:call-template name="createIndex">
                <xsl:with-param name="index" select="$index + 1"/>
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

</xsl:stylesheet>
