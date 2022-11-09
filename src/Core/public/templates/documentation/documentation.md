<%* doc console (json, modèle de données) *%>
<%* path / endpoints JSON API si il y en a *%>
<%* modules *%>
<%*<%$doc_level%><%$doc_title|escape_md%> : intégration pandoc*%>

Date de génération : <%$smarty.now|date_format:"%d/%m/%y"%>

<%if $smarty.request.json%>
<%include file="Core.documentation/json.md"%>

<%/if%>

<%$doc_level%> Modules
<%foreach from=$doc_mods item="mod"%>
<%$doc_level%># Module : <%$mod.name|escape_md%>

<%if !empty($mod.rgpd)%>
<%$doc_level%>## RGPD
<%foreach from=$mod.rgpd item="rgpdline"%>

<%$rgpdline%>

<%/foreach%>
<%/if%>

<%if !empty($mod.comment|trim)%>
<%$doc_level%>## Commentaires

<%$mod.comment|escape_md%>
<%/if%>

<%if $mod.documentation_more%>
<%$doc_level%>## Autres informations

<%$mod.documentation_more%>
<%/if%>


<%$doc_level%>## <%$mod.documentation_title|escape_md%>
<%if $mod.documentation_chapeau%><%$mod.documentation_chapeau|escape_md%><%/if%>

<%$mod.documentation%><%* ! c'est du md *%>

<%/foreach%>


<%* footnotes *%>

