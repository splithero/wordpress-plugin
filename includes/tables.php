<?php

function splitHeroCreateTables()
{
	global $wpdb;
	
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'splithero_campaigns';
	
	$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	dbDelta($sql);
}

function splitHeroDropTables()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'splithero_campaigns';
	$sql = "DROP TABLE IF EXISTS $table_name";
	$wpdb->query($sql);
	
	delete_option('splithero_token');
}