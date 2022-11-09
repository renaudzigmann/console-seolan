<?php
/**
 * @param float
 * @return integer
 */
function smarty_modifier_price_format($price) {
    return \Seolan\Core\Lang::price_format($price);
}
