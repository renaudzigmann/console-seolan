<?php

# ============================================================================
#                   TOOLBAR
# ============================================================================

$templates['wcchat.toolbar'] = '
<div id="wc_toolbar">
    <img src="{INCLUDE_DIR_THEME}images/pixel.gif" class="closed"  >

    {COMMANDS}
    <span id="wc_settings_icon" class="{ICON_STATE}">
    </span>
    {USER_NAME}{JOINED_STATUS} {BBCODE}
    <span id="wc_smiley_box" class="closed">
        {SMILIES}
    </span> 
    <img id="wc_post_loader" src="{INCLUDE_DIR_THEME}images/loader.gif" class="closed">
</div>';

$templates['wcchat.toolbar.joined_status'] = '';

$templates['wcchat.toolbar.bbcode'] = '
<span id="wc_smiley_icon{FIELD}">
</span>
<span id="wc_smiley_box{FIELD}" class="closed">
    {SMILIES}
</span>';

$templates['wcchat.toolbar.bbcode.attachment_uploads'] = ' <a href="#" onclick="wc_attach_test({ATTACHMENT_MAX_POST_N}); return false">
    <img src="{INCLUDE_DIR_THEME}images/upl.png" id="wc_attachment_upl_icon"  title="Upload Attachments">
</a> 
<span id="wc_attach_cont" class="closed">
    <input id="wc_attach" type="file" class="closed" onchange="wc_attach_upl(\'{CALLER}\', event)">
</span>';

$templates['wcchat.toolbar.commands'] = '
<div id="wc_commands">
    <a href="#" onclick="wc_toggle_time(\'{CALLER}\'); return false" title="Toggle TimeStamps">
    </a>
    <input type="hidden" id="wc_sline" >
    <input type="hidden" id="wc_mline" class="closed">
    {GSETTINGS}
    {EDIT}
</div>';

$templates['wcchat.toolbar.commands.gsettings'] = '';

$templates['wcchat.toolbar.commands.edit'] = '';

$templates['wcchat.toolbar.smiley.item'] = '';

$templates['wcchat.toolbar.smiley.item.parsed'] = '<img src="{INCLUDE_DIR_THEME}images/smilies/sm{key}.gif">';

$templates['wcchat.error_msg'] = '<div class="error_msg">{ERR}</div>';

$templates['wcchat.toolbar.onload'] = 'onload="wc_refresh_msg(\'{CALLER}\', \'ALL\', {REFRESH_DELAY}, {CHAT_DSP_BUFFER}, \'{INCLUDE_DIR_THEME}\', \'{PREFIX}\')"';

$templates['wcchat.toolbar.onload_once'] = 'onload="wc_refresh_msg_once(\'{CALLER}\', \'ALL\', {CHAT_DSP_BUFFER});"';

?>