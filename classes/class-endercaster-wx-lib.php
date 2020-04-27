<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.0.1
 * @package    endercaster-wx-sync
 * @subpackage endercaster-wx-sync/classes
 * @author     EnderCaster
 */
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/class-endercaster-wx-log.php';
class WxLib
{

    function get_access_key()
    {

        $start_at = time();
        $access_token = get_option('endercaster-wx-access-key');
        $expire_at = get_option('endercaster-wx-expire-at');
        if ($expire_at > $start_at) {
            return $access_token;
        }
        $app_id = get_option('endercaster-wx-app-id');
        $app_secret = get_option('endercaster-wx-app-secret');
        $acc_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$app_id}&secret={$app_secret}";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $acc_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        $acc_data = curl_exec($curl);
        $acc_data = json_decode($acc_data, true);
        $access_token = $acc_data['access_token'];
        $expire_at = $start_at + $acc_data['expires_in'] - 20;
        update_option('endercaster-wx-access-key', $access_token);
        update_option('endercaster-wx-expire-at', $expire_at);
        return $access_token;
    }
    function log($curl, $response_data, $type, $post_data = [])
    {
        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $request_data = $this->get_request_data_from_url(curl_getinfo($curl, CURLOPT_URL));
        $request_data = array_merge($request_data, $post_data);
        Log::log_resp_to_database($status_code, $response_data, $request_data, '', $type);
    }
    function base_post($url, $post_data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        //curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post_data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        return $curl;
    }
    function json_post($url, $post_data)
    {
        $post_data = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        return $this->base_post($url, $post_data);
    }
    function base_get($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        $resp_data = curl_exec($curl);
        $resp_data = json_decode($resp_data, true);
        curl_close($curl);
        return $resp_data;
    }
    function get_request_data_from_url($url)
    {
        $data = [];
        $params = explode('?', $url);
        if (count($params) <= 1) {
            return $data;
        }
        foreach (explode('&', $params) as $param) {
            $pair = explode('=', $param);
            if ($pair[0] != "access_token") {
                $data[$pair[0]] = $pair[1];
            }
        }
        return $data;
    }
    function get_curl_resp_data($curl)
    {
        return json_decode(curl_exec($curl), true);
    }
    function get_permanent_media_list($type, $from = 0, $count = 20)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=" . $this->get_access_key();
        $post_data['type'] = $type;
        $post_data['offset'] = $from;
        $post_data['count'] = $count;
        $curl = $this->json_post($url, $post_data);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, 'get_media_list', $post_data);
        curl_close($curl);
        return $response_data;
    }
    function get_permanent_media_counts()
    {
        $url = "https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token=" . $this->get_access_key();
        $curl = $this->base_get($url);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, 'get_media_counts');
        curl_close($curl);
        return $response_data;
    }
    function add_permanent_material($file, $type = "image")
    {
        $url = "https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=" . $this->get_access_key() . "&type={$type}";
        $post_data['media'] = new CURLFile($file);
        $curl = $this->base_post($url, $post_data);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, "add_permanent_{$type}", $post_data);
        curl_close($curl);
        return array_key_exists('media_id', $response_data) ? $response_data['media_id'] : "";
    }
    function add_news($title, $content, $thumb, $source_url, $author = "EnderCaster", $show_cover_pic = 1, $digest = "")
    {
        $url = "https://api.weixin.qq.com/cgi-bin/material/add_news?access_token=" . $this->get_access_key();
        $single_article_data = [
            'title' => $title,
            'content' => $content,
            "thumb_media_id" => $thumb,
            "content_source_url" => $source_url,
            "author" => $author,
            'show_cover_pic' => $show_cover_pic
        ];
        if ($digest) {
            $single_article_data['digest'] = $digest;
        }
        $post_data['articles'][] = $single_article_data;
        $curl = $this->json_post($url, $post_data);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, 'add_news', $post_data);
        curl_close($curl);
        return array_key_exists('media_id', $response_data) ? $response_data['media_id'] : "";
    }
    function update_news($media_id, $title, $content, $thumb, $source_url, $author = "EnderCaster", $show_cover_pic = 1, $digest = "")
    {
        $url = "https://api.weixin.qq.com/cgi-bin/material/update_news?access_token=" . $this->get_access_key();
        $single_article_data = [
            'title' => $title,
            'content' => $content,
            "thumb_media_id" => $thumb,
            "content_source_url" => $source_url,
            "author" => $author,
            'show_cover_pic' => $show_cover_pic
        ];
        if ($digest) {
            $single_article_data['digest'] = $digest;
        }
        $post_data['articles'] = $single_article_data;
        $post_data['media_id'] = $media_id;
        $post_data['index'] = 0;
        $curl = $this->json_post($url, $post_data);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, 'update_news', $post_data);
        curl_close($curl);
        return $response_data['errcode'];
    }
    function push_to_preview($news_media_id, $to_user)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token=" . $this->get_access_key();
        $post_data = [
            'touser' => $to_user,
            'mpnews' => [
                'media_id' => $news_media_id
            ],
            'msgtype' => "mpnews"
        ];
        $curl = $this->json_post($url, $post_data);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, 'preview', $post_data);
        curl_close($curl);
        return $response_data['errcode'];
    }
    function delete_material($media_id)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/material/del_material?access_token=' . $this->get_access_key();
        $post_data = [
            'media_id' => $media_id
        ];
        $curl = $this->json_post($url, $post_data);
        $response_data = $this->get_curl_resp_data($curl);
        $this->log($curl, $response_data, 'delete_material', $post_data);
        curl_close($curl);
        return $response_data['errcode'];
    }
}
