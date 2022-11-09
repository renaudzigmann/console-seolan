<?php
/**
 * Page-level DocBlock
 * @subpackage add-ons/vimeo
 */
/**
 * API V3
 */
/**
 * Index keys for video thumbnail sizes (apiv3)
 */
define("VIMEO_THUMB_XS", 0);
define("VIMEO_THUMB_S", 1);
define("VIMEO_THUMB_M", 2);
define("VIMEO_THUMB_L", 3);
define("VIMEO_THUMB_XL", 4);

/**
 * getVimeoLastVideo (apiv3)
 * Get Last Video + Large Thumbnail of account defined by token
 * @param string token: token created in vimeo account
 * @return array (thumb => image url, title => video title, desc => video description, maj => last update, link => video link)
 */
function getVimeoLastVideo($token) {
    $header = array("Authorization: bearer $token");
    $url = "https://api.vimeo.com/me/videos?sort=date&direction=desc&page=1&per_page=1";
    $data = vimeo_get_data($url,$header);
    $result = json_decode($data);
    if (empty($result->total) || ($result->total == 0))
        return NULL;
        
    $ret = array(
        'thumb' => $result->data[0]->pictures->sizes[VIMEO_THUMB_L]->link,
        'title' => $result->data[0]->name,
        'desc' => $result->data[0]->description,
        'maj' => $result->data[0]->modified_time,
        'link' => $result->data[0]->link
    );
    return $ret;
}

/**
 * getVimeoVideos (apiv3)
 * Get videos from account defined by token
 * @param string token: token created in vimeo account
 * @param string sort: sort order of returned videos
 * @param string direction: asc or desc order of sorted results
 * @param string per_page: number of returned videos per page
 * @param string page: split results in page if is more than 1
 * @return array()
 */
function getVimeoVideos($token, $sort = 'date', $direction = 'desc', $per_page = 3, $page = 1) {
    $header = array("Authorization: bearer $token");
    $url = "https://api.vimeo.com/me/videos?sort=$sort&direction=$direction&page=$page&per_page=$per_page";
    $data = vimeo_get_data($url,$header);
    $result = json_decode($data);
    if (empty($result->total) || ($result->total == 0))
        return NULL;
    return $result->data;
}

/**
 * API V2
 */
/**
 * getVimeoThumbnail (apiv2)
 * @param string id: video id
 * @return array (thumbL => image url, title => video title, desc => video description)
 */
function getVimeoThumbnail($id){
    $data = vimeo_get_data("http://vimeo.com/api/v2/video/$id.json");
    $result = json_decode($data);
    $ret = array(
        'thumbL' => stripcslashes($result[0]->thumbnail_large),
        'title' => stripcslashes($result[0]->title),
        'desc' => stripcslashes($result[0]->description),
    );
    return $ret;
}

/**
 * Curl helper
 */
function vimeo_get_data($url,$header=NULL) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!empty($header))
        curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
