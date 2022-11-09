<?php
# KCAPTCHA configuration file
$alphabet = "0123456789abcdefghijklmnopqrstuvwxyz"; # do not change without changing font files!
# symbols used to draw CAPTCHA
//$allowed_symbols = "0123456789"; #digits
$allowed_symbols = TZR_CAPTCHA_ALLOWED_SYMBOLS;
# folder with fonts
$fontsdir = 'fonts';	
# CAPTCHA string length
$length = TZR_CAPTCHA_LENGTH;
//$length = 6;
# symbol's vertical fluctuation amplitude divided by 2
$fluctuation_amplitude = TZR_CAPTCHA_FLUCTUATION_AMPLITUDE;
# increase safety by prevention of spaces between symbols
$no_spaces = TZR_CAPTCHA_NO_SPACES;
# JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
$jpeg_quality = 90;

##########################
# TZR config parametrable
# voir config/tzr.inc

# CAPTCHA image size (you do not need to change it, whis parameters is optimal)
$width = TZR_CAPTCHA_WIDTH;
$height = TZR_CAPTCHA_HEIGHT;

# show credits
if(TZR_CAPTCHA_CREDITS != ''){
$show_credits = true; # set to false to remove credits line. Credits adds 12 pixels to image height
$credits = TZR_CAPTCHA_CREDITS; # if empty, HTTP_HOST will be shown
}else $show_credits = false;

# CAPTCHA image colors (RGB, 0-255)
$foreground_color = explode(',',TZR_CAPTCHA_FORGROUND_COLOR);
$background_color = explode(',',TZR_CAPTCHA_BACKGROUND_COLOR);
//$foreground_color = array(mt_rand(0,100), mt_rand(0,100), mt_rand(0,100));
//$background_color = array(mt_rand(200,255), mt_rand(200,255), mt_rand(200,255));
?>