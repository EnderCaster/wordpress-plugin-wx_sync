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
class Log
{
    /**
     * 写日志
     */
    public static function log_resp_to_database($status_code, $response_data, $request_data = [], $request_time = '', $type = "news")
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        if (empty($request_time)) {
            $request_time = date("Y-M-d H:i:s");
        }
        $wpdb->insert("{$prefix}endercaser_wx_log", [
            "request_data" => json_encode($request_data, JSON_UNESCAPED_UNICODE),
            'status_code' => $status_code,
            'request_time' => $request_time,
            'type' => $type,
            'response_data' => json_encode($response_data, JSON_UNESCAPED_UNICODE)
        ]);
    }
}
