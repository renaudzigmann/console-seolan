<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 

        <!-- Camille : Application des onglets Bootstrap aux BlocCas / Exemple: F16871 -->
        <xsl:template match="BlocCas[@affichage = 'onglet']">
          <xsl:variable name="id" select="generate-id()"/>
          <xsl:choose>
            <xsl:when test="$BOOTSTRAP = 'true'">
              <ul class="nav nav-tabs" role="tablist">
                <xsl:for-each select="Cas/Titre">
                  <li role="presentation">
                    <xsl:if test="position() = 1"><xsl:attribute name="class"><xsl:text>active</xsl:text></xsl:attribute></xsl:if>
                    <a href="#cas{$id}{position()}" role="tab" data-toggle="tab"><xsl:value-of select="Paragraphe/text()"/></a>
                  </li>
                </xsl:for-each>
              </ul>
              <div class="tab-content">
                <xsl:for-each select="Cas">
                  <div id="cas{$id}{position()}" role="tabpanel" class="tab-pane">
                    <xsl:if test="position() = 1"><xsl:attribute name="class"><xsl:text>tab-pane active</xsl:text></xsl:attribute></xsl:if>
                    <xsl:apply-templates/>
                  </div>
                </xsl:for-each>
              </div>
            </xsl:when>
            <xsl:otherwise>
              <xsl:apply-templates/>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:template>

        <!-- Camille : Application des onglets Bootstrap aux ListeSituations / Exemple: F1342 -->
        <xsl:template match="ListeSituations[@affichage = 'onglet']">
          <xsl:variable name="id" select="generate-id()"/>
          <xsl:choose>
            <xsl:when test="$BOOTSTRAP = 'true'">
              <ul class="nav nav-tabs" role="tablist">
                <xsl:for-each select="Situation/Titre">
                  <li role="presentation">
                    <xsl:if test="position() = 1"><xsl:attribute name="class"><xsl:text>active</xsl:text></xsl:attribute></xsl:if>
                    <a href="#cas{$id}{position()}" role="tab" data-toggle="tab"><xsl:value-of select="text()"/></a>
                  </li>
                </xsl:for-each>
              </ul>
              <div class="tab-content">
                <xsl:for-each select="Situation">
                  <div id="cas{$id}{position()}" role="tabpanel" class="tab-pane">
                    <xsl:if test="position() = 1"><xsl:attribute name="class"><xsl:text>tab-pane active</xsl:text></xsl:attribute></xsl:if>
                    <xsl:apply-templates/>
                  </div>
                </xsl:for-each>
              </div>
            </xsl:when>
            <xsl:otherwise>
              <xsl:apply-templates/>
            </xsl:otherwise>
          </xsl:choose>
	</xsl:template>

</xsl:stylesheet>
