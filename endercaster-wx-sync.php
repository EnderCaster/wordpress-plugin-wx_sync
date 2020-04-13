<?php
/*
Plugin Name: 微信素材同步添加插件
Plugin URI: https://wordpress.endercaster.com/
Description: 特定标签的文章自动同步到素材库
Version: 0.0.1
Author: EnderCaster
Author URI: https://wordpress.endercaster.com/
*/
// If this file is called directly, abort.
if (!defined('WPINC')) {
    wp_die();
}
//---------------------------------------初始化---------------------------------------------
/**
 * 初始化数据库
 * @global $wpdb wp_db
 */
function init_database()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $tables = [];
    $results = $wpdb->get_results("show tables");
    foreach ($results as $table) {
        $tables[] = $table->Tables_in_wordpress;
    }
    if (array_search("{$prefix}endercaser_wx_exists", $tables) === false) {
        $init_sql = file_get_contents(dirname(__FILE__) . '/dump.sql');
        $init_sql = str_replace("{{prefix}}", $prefix, $init_sql);
        $sqls = explode(";\n", str_replace("\r\n", "\n", $init_sql));
        foreach ($sqls as $sql) {
            $wpdb->query($sql);
        }
    }
}
register_activation_hook(__FILE__, 'init_database');

/**
 * 初始化设置项
 */
function options_init()
{
    add_option('endercaster-wx-app-id', '');
    register_setting('endercaster-wx-settings', 'endercaster-wx-app-id', ['description' => '微信公众号APP ID']);
    add_option('endercaster-wx-app-secret', '');
    register_setting('endercaster-wx-settings', 'endercaster-wx-app-secret', ['description' => '微信公众号APP Secret']);
    add_option('endercaster-wx-access-key', '');
    register_setting('endercaster-wx-settings', 'endercaster-wx-access-key', ['description' => 'Access Key']);
    add_option('endercaster-wx-expire-at', time());
    register_setting('endercaster-wx-settings', 'endercaster-wx-expire-at', ['description' => 'Access Key过期时间']);
    add_option('endercaster-wx-preview-open-id', '');
    register_setting('endercaster-wx-settings', 'endercaster-wx-preview-open-id', ['description' => '预览用户的OPEN ID']);
}
add_action('admin_init', 'options_init');

/**
 * 添加目录项
 */
function add_menu_item()
{
    // 插件设置
    add_submenu_page('plugins.php', '微信设置', '微信设置', 'manage_options', 'endercaster-wx-settings', 'endercaster_wx_settings_page');
    // add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null )
    add_menu_page("微信公众号同步设置", "公众号同步设置", "manage_options", "endercaster-wx-sync-settings", "endercaster_wx_sync_settings_page", null, 99);
}
add_action("admin_menu", "add_menu_item");

//TODO
//---------------------------------------钩子---------------------------------------------
/**
 * 文章同步钩子
 * @var $post_id int
 * @var $post WP_Post
 */
function sync_post_to_wx($post_id, $post)
{
}
add_action("publish_post", 'sync_post_to_wx', 10, 2);
add_action("edit_post", 'sync_post_to_wx', 10, 2);



//---------------------------------------工具函数---------------------------------------------
/**
 * 写日志
 */
function log_resp_to_database($status_code, $resp_data, $request_time = '', $type = "news")
{
    global $wpdb;
    if (empty($request_time)) {
        $request_time = date("Y-M-d H:i:s");
    }
}
function obj_to_array($obj_array)
{
    return json_decode(json_encode($obj_array, JSON_UNESCAPED_UNICODE), true);
}
function build_sync_action($row)
{
    $action = [];

    return implode("&nbsp;", $action);
}
function build_table($data, $headers)
{
    $table_html = "<table class='wp-list-table widefat fixed striped'>";
    $table_html .= "<thead><tr>";
    foreach ($headers as $key => $label) {
        $table_html .= "<th class='$key'>{$label}</th>";
    }
    $table_html .= "</tr></thead>";
    foreach ($data as $row) {
        $table_html .= "<tr>";
        foreach (array_keys($headers) as $key) {
            $content = array_key_exists($key, $row) ? $row[$key] : "&nbsp";
            $table_html .= "<td class='$key'>{$content}</td>";
        }
        $table_html .= "</tr>";
    }
    $table_html .= "<tfoot><tr>";
    foreach ($headers as $key => $label) {
        $table_html .= "<th class='$key'>{$label}</th>";
    }
    $table_html .= "</tr></tfoot>";
    $table_html .= "</table>";
    return $table_html;
}
function build_link_with_function($label, $function_string)
{
    return "<a href='javascript:void(0);' onclick='{$function_string}'>{$label}</a>";
}


function sync_post_by_id($post_id)
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $exists_posts = obj_to_array($wpdb->get_results("select * from {$prefix}endercaser_wx_exists"));

    $result = [];
    if (array_search(intval($post_id), array_column($exists_posts, 'post_id')) === false) {
        $result = add_news_to_wx($post_id);
    } else {
        $result = update_news_to_wx($post_id);
    }
}
function sync_posts_table()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $sql = "select post.ID as post_id,post.post_title as title,sync.media_id as media_id from {$prefix}posts as post left join {$prefix}endercaser_wx_exists as sync on post.ID=sync.post_id and sync.type='news' where post.post_type='post' and post.post_status='publish'";
    $data = $wpdb->get_results($sql);
    $data = obj_to_array($data);
    $headers = [
        'post_id' => 'ID',
        'title' => '标题',
        'media_id' => '微信素材ID',
        'action' => '操作'

    ];
    foreach ($data as &$row) {
        $row['action'] = build_sync_action($row);
    }
    $table_html = build_table($data, $headers);
    echo $table_html;
}
// ---------------------------------------页面显示---------------------------------------------
/**
 * 插件设置页面
 */
function endercaster_wx_settings_page()
{
?>
    <!-- header -->
    <h1>微信设置</h1>

    <form method="post" action="options.php">
        <?php settings_fields('endercaster-wx-settings'); ?>
        <table class="form-table">
            <!-- *app_id -->
            <tr>
                <th>APP ID</th>
                <td><input type="text" id="endercaster-wx-app-id" name="endercaster-wx-app-id" value="<?php echo get_option('endercaster-wx-app-id'); ?>" /></td>
            </tr>
            <!-- *app_secret -->
            <tr>
                <th>APP Secret</th>
                <td><input type="text" id="endercaster-wx-app-secret" name="endercaster-wx-app-secret" value="<?php echo get_option('endercaster-wx-app-secret'); ?>" /></td>
            </tr>
            <!-- preview_user_openid -->
            <tr>
                <th>预览用户的Open ID</th>
                <td><input type="text" id="endercaster-wx-preview-open-id" name="endercaster-wx-preview-open-id" value="<?php echo get_option('endercaster-wx-preview-open-id'); ?>" /></td>
            </tr>
            <!-- affected_tag -->
            <tr>
                <th>生效标签</th>
                <td>
                    <select id="endercaster-wx-affected-tag" name="endercaster-wx-affected-tag">
                        <option value="0">全部</option>
                    </select>
                </td>
            </tr>
        </table>
        <!-- submit -->
        <?php submit_button(); ?>
    </form>

<?php
}
/**
 * 文章同步页
 */
function endercaster_wx_sync_settings_page()
{
?>
    <!-- header -->
    <h1>文章同步列表</h1>
    <div class="wrap">
        <!-- list -->
        <?php sync_posts_table(); ?>
    </div>
    <style>
        .post_id {
            width: 20px;
        }

        .title,
        .media_id {

            white-space: nowrap;
            overflow: hidden;
        }

        .action {
            width: 200px;
        }
    </style>
<?php
}
// ---------------------------------------REST部分---------------------------------------------
//TODO 暂时搁置
function sync_all($request)
{
    //循环获取全部news
    //获取全部符合条件的post
    //获取全部记录
    //对比 post update time 和 resp.item[i].update_time
    //
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'sync/all', array(
        'methods'  => 'POST',
        'callback' => 'sync_all'
    ));
});
//TODO
function sync_one($request)
{
    if (!is_user_logged_in()) {
        $resp = new WP_REST_Response([]);
        $resp->set_status(403);
        return $resp;
    }
    sync_post_by_id($request['post_id']);
    $data = [];
    $resp = new WP_REST_Response($data);
    $resp->set_status(200);
    return $resp;
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'sync/one', array(
        'methods'  => 'POST',
        'callback' => 'sync_one'
    ));
});
// ---------------------------------------微信部分---------------------------------------------
/**
 * 添加新图文消息到微信
 * @var $post array
 */
function add_news_to_wx($post)
{
}
/**
 * 更新微信图文消息
 * @var $post array
 */
function update_news_to_wx($post)
{
}
/**
 * 发送预览
 * @var $media_id string 微信素材ID
 */
function send_preview($media_id = '')
{
    //TODO
    $to_user = get_option('');
    if (empty($to_user)) {
        return [];
    }
}
