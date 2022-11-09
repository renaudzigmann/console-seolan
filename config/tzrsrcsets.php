<?php
/*
   **Tag Picture**
   <picture>
        <!--[if IE 9]><video style="display: none;"><![endif]-->
        <source media="(min-width: 1200px)" srcset="images/large.jpg, images/large-highres.jpg 2x"></source>
        <source media="(min-width: 992px)" srcset="images/medium.jpg, images/medium-highres.jpg 2x"></source>
        <source media="(min-width: 768px)" srcset="images/small.jpg, images/small-highres.jpg 2x"></source>
        <source media="(min-width: 300px)" srcset="images/extra-small.jpg, images/extra-small-highres.jpg 2x"></source>
        <!--[if IE 9]></video><![endif]-->
        <img alt="Une bien belle image dans le alt" >
        <noscript>
          <img >
        </noscript>
    </picture>
   
   key(ex:nom du gabarit) -> order|mediaquery -> paramètres du resizer
                          -> noscript -> paramètres du resizer
 */

$iniPath = defined('CONFIG_INI') ? CONFIG_INI : $GLOBALS['LOCALLIBTHEZORRO'].'local.ini';
$xini=@parse_ini_file($iniPath);

$ggigeo = isset($xini['ggigeo'])?strstr($xini['ggigeo'],"x",true):'848';
$g2cgeo = isset($xini['g2cgeo'])?strstr($xini['g2cgeo'],"x",true):'410';
$g3igeo = isset($xini['g3igeo'])?strstr($xini['g3igeo'],"x",true):'270';
$g4igeo = isset($xini['g4igeo'])?strstr($xini['g4igeo'],"x",true):'200';

$srcsets = array();
$srcsets['img_full'] =
  array ('(min-width: 1200px)' => array('w' => '848', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 992px)' => array('w' => '688', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 768px)' => array('w' => '790', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 300px)' => array('w' => '470', 'onlybigger'=>1, '2x' => 1),
        'NOSCRIPT' => array('w' => $ggigeo, 'onlybigger'=>1)
);
$srcsets['img_demi'] =
  array ('(min-width: 1200px)' => array('w' => '410', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 992px)' => array('w' => '330', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 768px)' => array('w' => '330', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 300px)' => array('w' => '470', 'onlybigger'=>1, '2x' => 1),
        'NOSCRIPT' => array('w' => $g2cgeo, 'onlybigger'=>1)
);
$srcsets['img_tiers'] =
  array ('(min-width: 1200px)' => array('w' => '270', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 992px)' => array('w' => '217', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 768px)' => array('w' => '218', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 300px)' => array('w' => '470', 'onlybigger'=>1, '2x' => 1),
        'NOSCRIPT' => array('w' => $g3igeo, 'onlybigger'=>1)
);
$srcsets['img_quart'] =
  array ('(min-width: 1200px)' => array('w' => '200', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 992px)' => array('w' => '160', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 768px)' => array('w' => '160', 'onlybigger'=>1, '2x' => 1),
        '(min-width: 300px)' => array('w' => '210', 'onlybigger'=>1, '2x' => 1),
        'NOSCRIPT' => array('w' => $g4igeo, 'onlybigger'=>1)
);
return $srcsets;
