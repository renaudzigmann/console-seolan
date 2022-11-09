<?php
use Seolan\Field\File\File;

function smarty_modifier_html_from_video_url($link)
{
  return File::html_from_video_url($link);
}

