<?php
use Seolan\Field\File\File;

function smarty_modifier_player_from_video_url($link, $dataType="embed")
{
  return File::player_from_video_url($link, $dataType);
}

