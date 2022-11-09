<?php
/*
 * Smarty plugin
 *
*/
function smarty_modifier_html_substr($string, $length, $addstring='...', $link='', $link_text='') { 
    // only execute if text is longer than desired length 
    if (strlen($string) > $length) { 
        if( !empty( $string ) && $length > 0 ) { 
            $isText = true; 
            $ret = ""; 
            $i = 0; 

            $lastSpacePosition = -1; 

            $tagsArray = array(); 
            $currentTag = ""; 

            $noTagLength = strlen(strip_tags($string)); 

            // Parser loop 
            $string_length = strlen($string); 
            for($j = 0 ; $j < $string_length ; $j++) { 

                $currentChar = substr( $string, $j, 1 ); 
                $ret .= $currentChar; 

                // Lesser than event 
                if( $currentChar == "<") $isText = false; 

                // Character handler 
                if( $isText ) { 

                    // Memorize last space position 
                    if( $currentChar == " " ) { 
                        $lastSpacePosition = $j; 
                    } 
                    else { 
                        $lastChar = $currentChar; 
                    } 

                    $i++; 
                } else { 
                    $currentTag .= $currentChar; 
                } 

                // Greater than event 
                if( $currentChar == ">" ) { 
                    $isText = true; 

                    // Opening tag handler 
                    if( ( strpos( $currentTag, "<" ) !== false) && 
                            ( strpos( $currentTag, "/>" ) === false) && 
                            ( strpos( $currentTag, "</") === false) ) { 

                        // Tag has attribute(s) 
                        if( strpos( $currentTag, " " ) !== false ) { 
                            $currentTag = substr( $currentTag, 1, strpos( $currentTag, " " ) - 1 ); 
                        } else { 
                            // Tag doesn't have attribute(s) 
                            $currentTag = substr( $currentTag, 1, -1 ); 
                        } 

                        array_push( $tagsArray, $currentTag ); 

                    } else if( strpos( $currentTag, "</" ) !== false ) { 
                        array_pop( $tagsArray ); 
                    } 

                    $currentTag = ""; 
                } 

                if( $i >= $length) { 
                    break; 
                } 
            } 

            // Cut HTML string at last space position 
            if( $length < $noTagLength ) { 
                if( $lastSpacePosition != -1 ) { 
                    $ret = substr( $string, 0, $lastSpacePosition ); 
                } else { 
                    $ret = substr( $string, $j ); 
                } 
            } 

            // Close broken XHTML elements 
            while( count( $tagsArray ) != 0 ) { 
                if ( count( $tagsArray ) > 1 ) { 
                    $aTag = array_pop( $tagsArray ); 
                    $ret .= "</" . $aTag . ">\n"; 
                } 
                // You may add more tags here to put the link and added text before the closing tag 
                elseif ($aTag = 'p' || 'div') { 
                    $aTag = array_pop( $tagsArray ); 
                    $ret .= $addstring . "<a href=\"". $link . "\" alt=\"". $link_text . "\">" . $link_text . "</a></" . $aTag . ">\n"; 
                } 
                else { 
                    $aTag = array_pop( $tagsArray ); 
                    $ret .= "</" . $aTag . ">" . $addstring . "<a href=\"". $link . "\" alt=\"". $link_text . "\">" . $link_text . "</a>\n"; 
                } 
            } 
        } else { 
            $ret = ""; 
        } 

        return $ret; 
    } 
    else { 
        return $string; 
    } 
} 
/* vim: set expandtab: */

?>
