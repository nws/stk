create table if not exists `user` (
	`user_id` int unsigned not null auto_increment,
	`email` varchar(255) not null,
	`password` varchar(255) null,
	`picture` varchar(255) not null default '',
	`name` varchar(255) not null,
	`first_name` varchar(255) not null,
	`last_name` varchar(255) not null,
	`birth_dt` date null,
	`register_dt` datetime not null,
	`update_dt` datetime not null,
	`zip` varchar(255) null,
	unique(`email`),
	primary key(`user_id`)
) charset=utf8 engine=innodb;

create table if not exists `oauth` (
	`oauth_id` int unsigned not null auto_increment,
	`version` int unsigned not null default '1',
	`user_id` int unsigned not null,
	`service` varchar(64) not null,
	`access_token` varchar(255) null,
	`access_secret` varchar(255) null,
	`username` varchar(255) null,
	`remote_user_id` varchar(255) null,
	`connected` datetime not null,
	primary key (`oauth_id`),
	unique(`user_id`, `service`),
	unique(`remote_user_id`, `service`),
	index(`service`)
) charset=utf8 engine=innodb;

create table if not exists `kv` (
	`kv_id` varchar(255) not null,
	`value` mediumtext not null,
	`expiry_dt` datetime null,
	primary key(`kv_id`),
	index(`expiry_dt`)
) charset=utf8 engine=innodb;

create table if not exists `session` (
	`session_id` varchar(255) not null,
	`data` text not null,
	`last_modified` timestamp not null,
	`persistent` bool not null default '0',
	primary key (`session_id`),
	index (`last_modified`),
	index (`persistent`)
) charset=utf8 engine=innodb;

