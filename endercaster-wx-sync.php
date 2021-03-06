<?php

/**
 * @wordpress-plugin
 * Plugin Name: 微信素材同步添加插件
 * Plugin URI: https://wordpress.endercaster.com/
 * Description: 特定标签的文章自动同步到素材库
 * Version: 0.1.0
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
    update_option('endercaster-wx-app-id', '');
    update_option('endercaster-wx-app-secret', '');
    update_option('endercaster-wx-access-key', '');
    update_option('endercaster-wx-expire-at', time());
    update_option('endercaster-wx-preview-open-id', '');
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

    add_menu_page("公众号管理", "公众号管理", "manage_options", "endercaster-wx-settings", "endercaster_wx_settings_page", null, 99);
    add_submenu_page('endercaster-wx-settings', '基础信息', '基础信息', 'manage_options', 'endercaster-wx-settings', 'endercaster_wx_settings_page');
    add_submenu_page('endercaster-wx-settings', '文章同步', '文章同步', 'manage_options', 'endercaster-wx-sync-settings', 'endercaster_wx_sync_settings_page');
    add_submenu_page('endercaster-wx-settings', '请求日志', '请求日志', 'manage_options', 'endercaster-wx-sync-logs', 'endercaster_wx_sync_log_page');
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
    $valid_tag_id = get_option('endercaster-wx-affected-tag');
    $post_tags = wp_get_post_tags($post_id);
    $post_tags = obj_to_array($post_tags);
    $post_tags = array_column($post_tags, 'term_id');
    if (0 == $valid_tag_id || in_array($valid_tag_id, $post_tags)) {
        sync_post_by_id($post_id);
    }
}
add_action("publish_post", 'sync_post_to_wx', 10, 2);
add_action("edit_post", 'sync_post_to_wx', 10, 2);

/**
 * 删除文章钩子
 * @var $post_id
 */
function delete_post_id_from_exists($post_id)
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $wpdb->update("{$prefix}endercaster_wx_exists", ["post_id" => null], ['post_id' => $post_id]);
}
add_action("delete_post", 'delete_post_id_from_exists', 10, 1);
//---------------------------------------工具函数---------------------------------------------

function obj_to_array($obj_array)
{
    return json_decode(json_encode($obj_array, JSON_UNESCAPED_UNICODE), true);
}
function build_sync_action($row)
{
    $action = [];
    if (array_key_exists('media_id', $row) && $row['post_id']) {
        $action[] = build_link_with_function("上传", "upload_post(" . $row['post_id'] . ")");
    }

    if (array_key_exists('media_id', $row) && $row['media_id']) {
        $action[] = build_link_with_function("预览", "send_preview(" . $row['sync_id'] . ")");
        $action[] = build_link_with_function("存储", "sync_local(" . $row['sync_id'] . ")");
        $action[] = build_link_with_function("删除", "delete_remote(" . $row['sync_id'] . ")");
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
    [$current_page, $page_size] = build_page_parameter();
    $start = ($current_page - 1) * $page_size;
    $sql = "select sync.id as sync_id,post.ID as post_id,post.post_title as title,sync.sync_time as sync_time,sync.media_id as media_id,sync.wechat_title as wechat_title,sync.wechat_content as wechat_content from {$prefix}posts as post left join {$prefix}endercaster_wx_exists as sync on post.ID=sync.post_id and sync.type='news' where post.post_type='post' and post.post_status='publish' UNION ALL (select sync.id as sync_id,'' as post_id,'' as title,sync.sync_time as sync_time,sync.media_id as media_id,sync.wechat_title as wechat_title,sync.wechat_content as wechat_content from {$prefix}endercaster_wx_exists as sync where sync.post_id is null) limit {$start},{$page_size}";
    $sql_count = "select count(post.ID) as total from {$prefix}posts as post left join {$prefix}endercaster_wx_exists as sync on post.ID=sync.post_id and sync.type='news' where post.post_type='post' and post.post_status='publish'";
    $sql_count_2 = "select count(media_id) as total from {$prefix}endercaster_wx_exists where post_id is null";
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
        if (empty($row['title'])) {
            $row['title'] = "微信：" . $row['wechat_title'];
        } elseif (!empty($row['wechat_title'])) {
            $row['title'] = $row['title'] . "(" . $row['wechat_title'] . ")";
        }
        $row['action'] = build_sync_action($row);
    }
    $table_html = build_table($data, $headers);
    echo $table_html;
    $total = $wpdb->get_row($sql_count)->total;
    $total += $wpdb->get_row($sql_count_2)->total;
    echo build_pageination($total, $page_size, $current_page);
    endercaster_pagination_css();
}
function sync_logs_table()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    [$current_page, $page_size] = build_page_parameter();
    $start = ($current_page - 1) * $page_size;
    $sql = "select * from {$prefix}endercaster_wx_log limit {$start},{$page_size};";
    $sql_count = "select count(id) as total from {$prefix}endercaster_wx_log;";
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
    $total = $wpdb->get_row($sql_count)->total;
    echo build_pageination($total, $page_size, $current_page);
    endercaster_pagination_css();
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
    sync.id as sync_id,
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
function get_wx_exists_record($id)
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $sql = "select * from {$prefix}endercaster_wx_exists where id={$id}";
    $record = $wpdb->get_row($sql);
    return obj_to_array($record);
}
function build_page_parameter()
{
    $current_page = array_key_exists('paged', $_REQUEST) ? $_REQUEST['paged'] : 1;
    $page_size = get_option('posts_per_page');
    return [$current_page, $page_size];
}
function build_pageination($total, $page_size, $current_page)
{
    $total_page = intval($total / $page_size) + ($total % $page_size >= 1 ? 1 : 0);
    $current_page_name = $_REQUEST['page'];
    $post_url = $current_page <= 1 ? "" : admin_url() . "/admin.php?page=" . $current_page_name . "&paged=" . ($current_page - 1);
    $next_url = $current_page >= $total_page ? "" : admin_url() . "/admin.php?page=" . $current_page_name . "&paged=" . ($current_page + 1);
    $pageination_html = "<div class='endercaster_pagination_wrapper'>";
    if ($post_url) {
        $pageination_html .= "<a class='post_page' href='{$post_url}'>&lt;</a>";
    } else {
        $pageination_html .= "<a class='post_page disabled' href='#' onclick='void(0)'>&lt;</a>";
    }
    $pageination_html .= "<a class='current_page' href='#' onclick='void(0)'>{$current_page}</a>";
    if ($next_url) {
        $pageination_html .= "<a class='next_page' href='{$next_url}'>&gt;</a>";
    } else {
        $pageination_html .= "<a class='next_page disabled' href='#' onclick='void(0)'>&gt;</a>";
    }
    $pageination_html .= "</div>";
    return $pageination_html;
}
function get_default_post()
{
    require_once ABSPATH . '/wp-admin/includes/admin.php';
    $post = get_default_post_to_edit('post', true);
    return $post;
}
function save_post_as_revision($post_id, $post_data)
{
    global $wxlib;
    $url = get_bloginfo('url') . "/wp-json/wp/v2/posts/{$post_id}";
    $data = [
        'content' => $post_data['content'],
        'id' => $post_id,
        'status' => 'publish',
        'title' => $post_data['title']
    ];
    $header = [
        'X-WP-Nonce:' . $_SERVER['HTTP_X_WP_NONCE'],
        'Cookie:' . $_SERVER['HTTP_COOKIE'],
    ];
    $curl = $wxlib->base_post($url, $data);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    $resp_data = $wxlib->get_curl_resp_data($curl);
    curl_close($curl);
    return $resp_data;
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
                <td><input type="password" id="endercaster-wx-app-id" name="endercaster-wx-app-id" value="<?php echo get_option('endercaster-wx-app-id'); ?>" /></td>
            </tr>
            <!-- *app_secret -->
            <tr>
                <th>APP Secret</th>
                <td><input type="password" id="endercaster-wx-app-secret" name="endercaster-wx-app-secret" value="<?php echo get_option('endercaster-wx-app-secret'); ?>" /></td>
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
                        <?php
                        $selected = get_option('endercaster-wx-affected-tag');
                        $tags = get_tags();
                        foreach ($tags as $tag) {
                            if ($tag->term_id == $selected) {
                                echo "<option value='" . $tag->term_id . "' selected>" . $tag->name . "</option>";
                            } else {
                                echo "<option value='" . $tag->term_id . "'>" . $tag->name . "</option>";
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>禁用时清空所有数据</th>
                <td><input type="checkbox" id="endercaster-wx-clear-all-sync-data" name="endercaster-wx-clear-all-sync-data" <?php echo get_option('endercaster-wx-clear-all-sync-data') ? "checked" : ""; ?> /></td>
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
    $wp_nonce = wp_create_nonce('wp_rest');
?>
    <!-- header -->
    <h1>文章同步列表</h1>
    <a class="button button-default" href="#" onclick="download_all()">拉取微信素材</a>
    <!-- list -->
    <?php sync_posts_table(); ?>
    <style>
        .post_id {
            width: 25px;
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
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/upload/one',
                dataType: 'json',
                data: {
                    'post_id': post_id
                },
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo $wp_nonce; ?>");
                },
                success: function(resp) {
                    if (!resp.code) {
                        location.reload();
                    } else {
                        alert('同步出错，请查看log');
                    }

                }
            })
        }

        function send_preview(sync_id) {
            jQuery.ajax({
                type: "POST",
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/sync/preview',
                dataType: 'json',
                data: {
                    'sync_id': sync_id
                },
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo $wp_nonce; ?>");
                },
                success: function(resp) {
                    console.log(resp);
                    if (!resp.code) {
                        location.reload();
                    } else {
                        alert('同步出错，请查看log');
                    }

                }
            });
        }

        function delete_remote(sync_id) {
            jQuery.ajax({
                type: "POST",
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/delete/remote',
                dataType: 'json',
                data: {
                    'sync_id': sync_id
                },
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo $wp_nonce; ?>");
                },
                success: function(resp) {
                    console.log(resp);
                    if (!resp.code) {
                        location.reload();
                    } else {
                        alert('同步出错，请查看log');
                    }

                }
            });
        }

        function sync_local(sync_id) {
            jQuery.ajax({
                type: "POST",
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/sync/save',
                dataType: 'json',
                data: {
                    'sync_id': sync_id
                },
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo $wp_nonce; ?>");
                },
                success: function(resp) {
                    console.log(resp);
                    if (!resp.code) {
                        location.reload();
                    }

                }
            });
        }

        function download_all() {
            jQuery.ajax({
                type: "POST",
                url: '<?php bloginfo('url'); ?>/wp-json/endercaster/wx/v1/download/all',
                dataType: 'json',
                data: {},
                beforeSend: function(request) {
                    request.setRequestHeader('X-WP-Nonce', "<?php echo $wp_nonce; ?>");
                },
                success: function(resp) {
                    console.log(resp);
                    if (!resp.code) {
                        location.reload();
                    } else {
                        alert('拉取出错，请查看log');
                    }

                }
            });
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
function endercaster_pagination_css()
{
?>
    <style>
        .endercaster_pagination_wrapper {
            margin: 10px 20px 0 2px;
            text-align: right;
        }

        .post_page,
        .next_page,
        .current_page {
            text-align: center;
            text-decoration: none;
            display: inline-block;
            width: 20px;
            height: 20px;
            line-height: 20px;
            border-radius: 2px;
            border: 1px darkcyan solid;
            cursor: pointer;
        }

        .post_page.disabled,
        .next_page.disabled {
            border: 1px lightgray solid;
            color: lightgray;
            cursor: not-allowed;
        }

        .current_page {
            background-color: #0073aa;
            color: white !important;
        }
    </style>
<?php
}
// ---------------------------------------REST部分---------------------------------------------
function download_all($request)
{
    if (!is_user_logged_in()) {
        $resp = new WP_REST_Response([]);
        $resp->set_status(403);
        return $resp;
    }
    global $wxlib, $wpdb;
    //循环获取全部news
    $from = 0;
    $media_list = [];
    $wx_result = $wxlib->get_permanent_media_list('news', $from);
    $total = $wx_result['total_count'];
    while (count($media_list) < $total) {
        foreach ($wx_result['item'] as $post) {
            $media_list[] = [
                'media_id' => $post['media_id'],
                'guid' => $post['content']['news_item'][0]['content_source_url'],
                'title' => $post['content']['news_item'][0]['title'],
                'content' => $post['content']['news_item'][0]['content'],
                'sync_time' => wp_date('Y-m-d H:i:s', $post['update_time'], wp_timezone())
            ];
        }
        $from += 20;
        $wx_result = $wxlib->get_permanent_media_list('news', $from);
    }

    //获取全部符合条件的post
    $prefix = $wpdb->prefix;
    $sql = "select ID as id,post_title as title,guid from {$prefix}posts as post where post.post_type='post' and post.post_status='publish'";
    $posts = obj_to_array($wpdb->get_results($sql));
    $post_map = [];
    foreach ($posts as $post) {
        $post_map[$post['guid']] = $post;
    }
    //获取现在同步列表中存在的posts
    $sql = "select post_id,media_id from {$prefix}endercaster_wx_exists";
    $records = obj_to_array($wpdb->get_results($sql));
    $exists_ids = array_column($records, 'post_id');
    $exists_media_ids = array_column($records, 'media_id');
    //逐条对比news的resp.item[.].content.news_item[0].url与post.guid
    $to_insert = [];
    $to_update = [];
    foreach ($media_list as $media) {
        $post_id = $posts[$media['guid']];
        if (empty($post_id)) {
            $post_id = get_default_post()->ID;
            $to_insert[] = [
                'post_id' => $post_id,
                'media_id' => $media['media_id'],
                'wechat_title' => $media['title'],
                'wechat_content' => $media['content'],
                'sync_time' => $media['sync_time'],
                'type' => 'news'
            ];
        } else {
            if (array_search($post_id, $exists_ids) !== false) {
                $to_update[] = [
                    'where' => ['post_id' => $post_id],
                    'data' => [
                        'media_id' => $media['media_id'],
                        'wechat_title' => $media['title'],
                        'wechat_content' => $media['content'],
                        'sync_time' => $media['sync_time']
                    ]
                ];
            } else if (array_search($media['media_id'], $exists_media_ids) !== false) {
                $to_update[] = [
                    'where' => ['media_id' => $media['media_id']],
                    'data' => [
                        'post_id' => $post_id,
                        'wechat_title' => $media['title'],
                        'wechat_content' => $media['content'],
                        'sync_time' => $media['sync_time']
                    ]
                ];
            } else {
                $to_insert[] = [
                    'post_id' => $post_id,
                    'media_id' => $media['media_id'],
                    'wechat_title' => $media['title'],
                    'wechat_content' => $media['content'],
                    'sync_time' => $media['sync_time'],
                    'type' => 'news'
                ];
            }
        }
    }
    //update
    foreach ($to_update as $row) {
        $wpdb->update("{$prefix}endercaster_wx_exists", $row['data'], $row['where']);
    }
    //TODO batch insert
    foreach ($to_insert as $row) {
        $wpdb->insert("{$prefix}endercaster_wx_exists", $row);
    }
    $resp = new WP_REST_Response(['code' => 0]);
    $resp->set_status(200);
    return $resp;
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'download/all', array(
        'methods'  => 'POST',
        'callback' => 'download_all'
    ));
});
function upload_one($request)
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
    register_rest_route('endercaster/wx/v1', 'upload/one', array(
        'methods'  => 'POST',
        'callback' => 'upload_one'
    ));
});
function sync_preview($request)
{
    if (!is_user_logged_in()) {
        $resp = new WP_REST_Response([]);
        $resp->set_status(403);
        return $resp;
    }
    $sync_id = $request['sync_id'];
    $post = get_wx_exists_record($sync_id);
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
function delete_remote($request)
{
    if (!is_user_logged_in()) {
        $resp = new WP_REST_Response([]);
        $resp->set_status(403);
        return $resp;
    }
    $sync_id = $request['sync_id'];

    $post = get_wx_exists_record($sync_id);
    $media_id = $post['media_id'];
    global $wxlib;
    $code = $wxlib->delete_material($media_id);
    if (empty($code)) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->update("{$prefix}endercaster_wx_exists", ["media_id" => '', "sync_time" => date_i18n("Y-m-d H:i:s")], ["id" => $sync_id]);
    }
    $data['code'] = $code;
    $resp = new WP_REST_Response($data);
    $resp->set_status(200);
    return $resp;
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'delete/remote', array(
        'methods' => 'POST',
        'callback' => 'delete_remote'
    ));
});
function save_to_local($request)
{
    if (!is_user_logged_in()) {
        $resp = new WP_REST_Response([]);
        $resp->set_status(403);
        return $resp;
    }
    global $wxlib;

    $sync_id = $request['sync_id'];
    $record = get_wx_exists_record($sync_id);
    $post_id = $record['post_id'];
    if (empty($post_id)) {
        $post_id = get_default_post()->ID;
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->update("{$prefix}endercaster_wx_exists", ["post_id" => $post_id], ['id' => $sync_id]);
    }
    $data = [
        'content' => $record['wechat_content'],
        'id' => $post_id,
        'status' => 'publish',
        'title' => $record['wechat_title']
    ];
    $resp_data = save_post_as_revision($post_id, $data);
    return new WP_REST_Response($resp_data);
}
add_action('rest_api_init', function () {
    register_rest_route('endercaster/wx/v1', 'sync/save', array(
        'methods'  => 'POST',
        'callback' => 'save_to_local'
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
    if (empty($post['sync_id'])) {
        $wpdb->insert("{$prefix}endercaster_wx_exists", [
            "post_id" => $post['id'],
            "media_id" => $news_media_id,
            "type" => "news",
            "sync_time" => date_i18n("Y-m-d H:i:s")
        ]);
    } else {
        $wpdb->insert("{$prefix}endercaster_wx_exists", [
            "media_id" => $news_media_id,
            "type" => "news",
            "sync_time" => date_i18n("Y-m-d H:i:s")
        ], ['id' => $post['sync_id']]);
    }

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
