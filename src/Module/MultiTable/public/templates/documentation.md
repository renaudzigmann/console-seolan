<%* description d'un datasource en markdown pour la génération de la documentation *%>
<%function name="tableFieldDoc"%><%strip%>
<%assign var="close" value=""%>
<%assign var="props" value=[]%>
<%if $field->queryable%><%$props[]="°"%><%/if%>
<%if $field->published%><%assign var="open" value="**"%><%assign var="close" value=$close|cat:"**"%><%/if%>
|<%$open%><%$field->field|trim|escape_md%><%$props|implode:","|escape_md%><%$close%> | <%$field->label|escape_md%> | <%$field->type|escape_md%> | <%$field->description|escape_md%> | <%$field->constraints|implode:', '|escape_md%> |
<%/strip%><%/function%>

<%function name="tableFieldAnnex"%>
<%if $field->annex%>
<%$doc_level%>## Détail champ <%$field->label|escape_md%>

<%$field->annex|implode:', '|escape_md%>
<%/if%>
<%/function%>

| Nom| Label| Type| Description| Contraintes/remarques|
|:---|:-----|:----|:-----------|:---------------------|
<%foreach from=$doc_sources.main.data.desc item="fd"%><%tableFieldDoc field=$fd%>
<%/foreach%>

<%foreach from=$doc_sources.main.data.desc item="fd"%>
<%tableFieldAnnex field=$fd%>
<%/foreach%>

<%foreach from=$doc_sources.types item="ds"%>

<%$level%>## Variante <%$ds.chapeau%>

| Nom| Label| Type| Description| Contraintes/remarques|
|:---|:-----|:----|:-----------|:---------------------|
<%foreach from=$ds.data.desc item="fd"%><%if !in_array($fd->field, array('UPD','KOID'))%><%tableFieldDoc field=$fd%><%/if%><%/foreach%>

<%foreach from=$ds.data.desc item="fd"%>
<%tableFieldAnnex field=$fd%>
<%/foreach%>
<%/foreach%>

_° : champ de recherche_
_en gras : champ publié._
