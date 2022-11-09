<%* description d'un datasource en markdown pour la génération de la documentation *%>
<%function name="tableFieldDoc"%><%strip%>
<%assign var="close" value=""%>
<%assign var="props" value=[]%>
<%if $field->queryable%>
<%$props[]="°"%>
<%$hasSearchField=true%>
<%/if%>
<%if $field->published%>
<%assign var="open" value="**"%><%assign var="close" value=$close|cat:"**"%>
<%$hasPubField=true%>
<%/if%>
<%if $field->RGPD_personalData%>
<%$props[]="Θ"%>
<%$hasRGPDField=true%>
<%/if%>

|<%$open%><%$field->field|trim|escape_md%><%$props|implode:","|escape_md%><%$close%> | <%$field->label|escape_md%> | <%$field->type|escape_md%> | <%$field->description|replace:"\n":" "|trim|escape_md%> | <%$field->constraints|implode:', '|escape_md%> |<%/strip%><%/function%>
<%function name="tableFieldAnnex"%>
<%if $field->annex%>
<%$doc_level%>## Détail champ <%$field->label|escape_md%>

<%$field->annex|implode:', '|escape_md%>
<%/if%>
<%/function%>

<%assign var="fieldNotes" value=[]%>
<%assign var="hasPubField" value=false%>
<%assign var="hasRGPDField" value=false%>
<%assign var="hasSearchField" value=false%>

| Nom| Label| Type| Description| Contraintes/remarques|
|:---|:-----|:----|:-----------|:---------------------|
<%foreach from=$doc_desc item="fd"%>
<%tableFieldDoc field=$fd%>
<%if $fd->queryable%><%$hasSearchField=true%><%/if%>
<%if $fd->published%><%$hasPubField=true%><%/if%>
<%if $fd->RGPD_personalData%><%$hasRGPDField=true%><%/if%>
<%/foreach%>

<%foreach from=$doc_sources.main.data.desc item="fd"%>
<%tableFieldAnnex field=$fd%>
<%/foreach%>

<%if $hasSearchFied==true%><%$fieldNotes[] = "° : champ de recherche"%><%/if%>
<%if $hasPubField==true%><%$fieldNotes[] = "en gras : champ publié"%><%/if%>
<%if $hasRGPDField==true%><%$fieldNotes[]="Θ données personnelles (RGPD)"%><%/if%>

<%if !empty($fieldNotes)%>_<%","|implode:$fieldNotes%>_<%/if%>

