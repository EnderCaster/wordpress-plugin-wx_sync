<?php

/**
 * @wordpress-plugin
 * Plugin Name: 微信素材同步添加插件
 * Plugin URI: https://wordpress.endercaster.com/
 * Description: 特定标签的文章自动同步到素材库
 * Version: 0.0.1
 * Author: EnderCaster
 * Author URI: https://wordpress.endercaster.com/
 * @package endercaster-wx-sync
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
    $results = json_decode(json_encode($results), true);
    $tables = array_column($results, "Tables_in_" . DB_NAME);
    if (array_search("{$prefix}endercaster_wx_exists", $tables) === false) {
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
 * 清理隐私信息
 */
function clear_information()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    //TODO 解除注释
    // update_option('endercaster-wx-app-id', '');
    // update_option('endercaster-wx-app-secret', '');
    // update_option('endercaster-wx-access-key', '');
    // update_option('endercaster-wx-expire-at', time());
    // update_option('endercaster-wx-preview-open-id', '');
    if (boolval(get_option('endercaster-wx-clear-all-sync-data'))) {
        $init_sql = file_get_contents(dirname(__FILE__) . '/clear.sql');
        $init_sql = str_replace("{{prefix}}", $prefix, $init_sql);
        $sqls = explode(";\n", str_replace("\r\n", "\n", $init_sql));
        foreach ($sqls as $sql) {
            $wpdb->query($sql);
        }
    }
}
register_deactivation_hook(__FILE__, 'clear_information');
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
    add_option('endercaster-wx-affected-tag', '');
    register_setting('endercaster-wx-settings', 'endercaster-wx-affected-tag', ['description' => '起效TAG，留空则对全部文章生效']);
    add_option('endercaster-wx-clear-all-sync-data', false);
    register_setting('endercaster-wx-settings', 'endercaster-wx-clear-all-sync-data', ['description' => '在禁用插件时清空同步表数据']);
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
    add_menu_page("微信接口请求日志", "微信接口请求日志", "manage_options", "endercaster-wx-sync-logs", "endercaster_wx_sync_log_page", null, 99);
}
add_action("admin_menu", "add_menu_item");


//---------------------------------------钩子---------------------------------------------
/**
 * 文章同步钩子
 * @var $post_id int
 * @var $post WP_Post
 */
function sync_post_to_wx($post_id, $post)
{
    //TODO
}
add_action("publish_post", 'sync_post_to_wx', 10, 2);
add_action("edit_post", 'sync_post_to_wx', 10, 2);

//---------------------------------------工具函数---------------------------------------------

function obj_to_array($obj_array)
{
    return json_decode(json_encode($obj_array, JSON_UNESCAPED_UNICODE), true);
}
function build_sync_action($row)
{
    $action = [];
    $action[] = build_link_with_function("上传", "upload_post(" . $row['post_id'] . ")");
    if (array_key_exists('media_id', $row) && $row['media_id']) {
        $action[] = build_link_with_function("预览", "send_preview(" . $row['post_id'] . ")");
    }
    return implode("&nbsp;", $action);
}
function build_table($data, $headers)
{
    $table_html = "<div class='wrap'><table class='wp-list-table widefat fixed striped'>";
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
    $table_html .= "</table></div>";
    return $table_html;
}
function build_link_with_function($label, $function_string)
{
    return "<a href='javascript:void(0);' onclick='{$function_string}'>{$label}</a>";
}


function sync_post_by_id($post_id)
{

    $post = get_post_as_array_by_id($post_id);

    $result = false;
    if (empty($post['media_id'])) {
        $result = add_news_to_wx($post);
    } else {
        $result = update_news_to_wx($post);
    }
    return $result;
}
function sync_posts_table()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $sql = "select post.ID as post_id,post.post_title as title,sync.sync_time as sync_time,sync.media_id as media_id from {$prefix}posts as post left join {$prefix}endercaster_wx_exists as sync on post.ID=sync.post_id and sync.type='news' where post.post_type='post' and post.post_status='publish'";
    $data = $wpdb->get_results($sql);
    $data = obj_to_array($data);
    $headers = [
        'post_id' => 'ID',
        'title' => '标题',
        'media_id' => '微信素材ID',
        'sync_time' => '上次同步时间',
        'action' => '操作'

    ];
    foreach ($data as &$row) {
        $row['action'] = build_sync_action($row);
    }
    $table_html = build_table($data, $headers);
    echo $table_html;
}
function sync_logs_table()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $sql = "select * from {$prefix}endercaster_wx_log;";
    $data = $wpdb->get_results($sql);
    $data = obj_to_array($data);
    $headers = [
        'id' => 'ID',
        'request_data' => '请求参数',
        'status_code' => 'HTTP返回码',
        'request_time' => '请求时间',
        'type' => '请求类型',
        'response_data' => '返回参数'
    ];
    $table_html = build_table($data, $headers);
    echo $table_html;
}
function get_post_as_array_by_id($post_id)
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $sql = "select
    post.ID as id,
    post.post_title as title,
    user.display_name as author,
    post.post_content as content,
    sync.sync_time as update_time,
    sync.media_id as media_id
  from {$prefix}posts as post
  left join {$prefix}users as user on post.post_author = user.ID
  left join {$prefix}endercaster_wx_exists as sync on post.ID = sync.post_id
  where
    post.post_status = 'publish' and post.post_type='post' and post.ID={$post_id}";
    $post = $wpdb->get_row($sql);
    return obj_to_array($post);
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
    <!-- list -->
    <?php sync_posts_table(); ?>
    <style>
        .post_id {
            width: 20px;
        }

        .title,
        .media_id {
            word-break: keep-all;
            white-space: nowrap;
            overflow: hidden;
        }

        .action {
            width: 200px;
        }

        .sync_time {
            width: 150px;
        }

        td.title,
        td.media_id,
        td.sync_time,
        td.action {
            border-left: 1px solid lightgray;
        }
    </style>
    <script>
        function upload_post(post_id) {
            jQuery.ajax({
                type: "POST",
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/sync/one',
                dataType: 'json',
                data: {
                    'post_id': post_id
                },
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo wp_create_nonce('wp_rest'); ?>");
                },
                success: function(resp) {
                    console.log(resp);
                    if (!resp.code) {
                        location.reload(true);
                    } else {
                        alert('同步出错，请查看log');
                    }

                }
            })
        }

        function send_preview(post_id) {
            jQuery.ajax({
                type: "POST",
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/sync/preview',
                dataType: 'json',
                data: {
                    'post_id': post_id
                },
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo wp_create_nonce('wp_rest'); ?>");
                },
                success: function(resp) {
                    console.log(resp);
                    if (!resp.code) {
                        location.reload(true);
                    } else {
                        alert('同步出错，请查看log');
                    }

                }
            })
        }
    </script>
<?php
}
function endercaster_wx_sync_log_page()
{
?>
    <h1>微信接口请求日志</h1>
    <?php sync_logs_table(); ?>
    <style>
        tr>td {
            line-break: keep-all;
            white-space: nowrap;
            overflow: hidden;
        }

        .id {
            width: 20px;
            text-align: center;
        }
    </style>
    <script>
        jQuery('tr>td').each(function() {
            this.title = this.innerText;
        });
    </script>
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
    $result = sync_post_by_id($request['post_id']);

    $data = [
        'code' => $result ? 0 : -1
    ];
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
function sync_preview($request)
{
    if (!is_user_logged_in()) {
        $resp = new WP_REST_Response([]);
        $resp->set_status(403);
        return $resp;
    }
    $post = get_post_as_array_by_id($request['post_id']);
    $result = send_preview($post['media_id']);

    $data = [
        'code' => $result ? 0 : -1
    ];
    $resp = new WP_REST_Response($data);
    $resp->set_status(200);
    return $resp;
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'sync/preview', array(
        'methods'  => 'POST',
        'callback' => 'sync_preview'
    ));
});
//TODO 删除
function test_lib($request)
{
    global $wxlib;
    return new WP_REST_Response(
        $wxlib->get_permanent_media_list("news")
    );
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'test', array(
        'methods'  => 'GET',
        'callback' => 'test_lib'
    ));
});
// ---------------------------------------微信部分---------------------------------------------
require plugin_dir_path(__FILE__) . 'classes/class-endercaster-wx-lib.php';
$wxlib = new WxLib();
/**
 * 添加新图文消息到微信
 * @var $post array
 */
function add_news_to_wx($post)
{
    global $wxlib, $wpdb;
    $thumb_media_id = $thumb_media_id = get_post_thumb_media_id($post['id']);
    if (empty($thumb_media_id)) {
        return false;
    }
    $news_media_id = $wxlib->add_news($post['title'], $post['content'], $thumb_media_id, $post['guid'], $post['author']);

    if (empty($news_media_id)) {
        return false;
    }
    $prefix = $wpdb->prefix;
    $wpdb->insert("{$prefix}endercaster_wx_exists", [
        "post_id" => $post['id'],
        "media_id" => $news_media_id,
        "type" => "news",
        "sync_time" => date_i18n("Y-m-d H:i:s")
    ]);
    send_preview($news_media_id);
    return true;
}
/**
 * 更新微信图文消息
 * @var $post array
 */
function update_news_to_wx($post)
{
    global $wxlib, $wpdb;
    $thumb_media_id = get_post_thumb_media_id($post['id']);
    if (empty($thumb_media_id)) {
        return false;
    }
    $code = $wxlib->update_news($post['media_id'], $post['title'], $post['content'], $thumb_media_id, $post['guid'], $post['author']);
    if (!empty($code)) {
        return false;
    }
    $prefix = $wpdb->prefix;
    $wpdb->update("{$prefix}endercaster_wx_exists", ["sync_time" => date_i18n("Y-m-d H:i:s")], ["post_id" => $post['id']]);
    return true;
}
/**
 * 获取图文消息缩略图的微信素材ID
 * @var $post_id
 */
function get_post_thumb_media_id($post_id)
{
    global $wxlib, $wpdb;
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if (empty($thumbnail_id)) {
        return "";
    }
    $prefix = $wpdb->prefix;
    $thumb_media_id = $wpdb->get_row("select media_id from {$prefix}endercaster_wx_exists where post_id={$thumbnail_id}")->media_id;
    if (!empty($thumb_media_id)) {
        return $thumb_media_id;
    }
    $thumb_url = get_post($thumbnail_id)->guid;
    $thumb_path = ABSPATH . explode(get_option('siteurl'), $thumb_url)[1];
    $thumb_media_id = $wxlib->add_permanent_material($thumb_path, 'thumb');
    if (!empty($thumb_media_id)) {
        $wpdb->insert("{$prefix}endercaster_wx_exists", [
            "post_id" => $thumbnail_id,
            "media_id" => $thumb_media_id,
            "type" => "thumb",
            "sync_time" => date_i18n("Y-m-d H:i:s")
        ]);
    }
    return $thumb_media_id;
}
/**
 * 发送预览
 * @var $media_id 微信图文消息素材ID
 */
function send_preview($media_id)
{
    $to_user = get_option('endercaster-wx-preview-open-id');
    if (empty($to_user)) {
        return false;
    }
    global $wxlib;
    $code = $wxlib->push_to_preview($media_id, $to_user);
    //code=0的时候说明正常
    return empty($code);
}
