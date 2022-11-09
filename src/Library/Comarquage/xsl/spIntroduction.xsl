<?xml version="1.0" encoding="ISO-8859-15"?>
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	exclude-result-prefixes="xsl dc">

	<xsl:output method="xml" encoding="ISO-8859-15" cdata-section-elements="script" indent="yes"/> 
 
	<xsl:template match="Introduction">
		<div class="spIntroduction" id="sp-introduction">
			<xsl:apply-templates/>
		</div>
	</xsl:template>		

</xsl:stylesheet>
