drop table IF EXISTS `{{prefix}}endercaster_wx_exists`;
create table `{{prefix}}endercaster_wx_exists`(
  `id` int unsigned PRIMARY KEY AUTO_INCREMENT,
  `post_id` bigint(20) unsigned comment 'wordpress发布文章对应的id',
  `media_id` varchar(100) comment '微信端对应MEDIA ID',
  `type` varchar(20) comment 'thumb,news',
  `sync_time` datetime default CURRENT_TIMESTAMP comment '更新时间'
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
drop table IF EXISTS `{{prefix}}endercaster_wx_log`;
create table `{{prefix}}endercaster_wx_log`(
  `id` int unsigned PRIMARY KEY AUTO_INCREMENT,
  `request_data` text comment 'json format request data',
  `status_code` int comment 'HTTP CODE',
  `request_time` datetime default CURRENT_TIMESTAMP comment '请求时间',
  `type` varchar(20) comment 'thumb,news',
  `response_data` text comment 'json response'
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;