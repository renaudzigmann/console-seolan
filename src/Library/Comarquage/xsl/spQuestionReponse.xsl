<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 

	<xsl:template name="affQuestionReponse">  	
		<xsl:if test="count(QuestionReponse) > 0">
			<div class="spPublicationQR" id="sp-question-reponse">
				<h2>
					<xsl:call-template name="imageOfAPartie">
						<xsl:with-param name="nom">questions-reponses</xsl:with-param>
					</xsl:call-template>
					<xsl:text>Questions - Réponses</xsl:text>
				</h2>
				<ul class="spPublicationNoeud">
					<xsl:apply-templates select="QuestionReponse"/>
				</ul>
			</div>
		</xsl:if>
	</xsl:template>

	<xsl:template match="QuestionReponse">
		<xsl:variable name="title">
			<xsl:value-of select="../dc:title"/>
			<xsl:value-of select="$sepFilDAriane"/>
			<xsl:value-of select="text()"/>
		</xsl:variable>
		<xsl:variable name="class">
			<xsl:text>spPublicationNoeud spPublicationDFT</xsl:text>
			<xsl:if test="position() = 1">
				<xsl:text> spPublicationDFTFirst</xsl:text>
			</xsl:if>
			<xsl:if test="position() = count(../QuestionReponse)">
				<xsl:text> spPublicationDFTLast</xsl:text>
			</xsl:if>
		</xsl:variable>
		<li class="{$class}">
			<h3>
    			<xsl:call-template name="getPublicationLink">
    				<xsl:with-param name="href"><xsl:value-of select="@ID"/></xsl:with-param>
    				<xsl:with-param name="title"><xsl:value-of select="$title"/></xsl:with-param>
    				<xsl:with-param name="text"><xsl:value-of select="text()"/></xsl:with-param>
				</xsl:call-template>
			</h3>
		</li>
	</xsl:template>
	
</xsl:stylesheet>
