<?php
/*
 * Linkate Posts
 */
 
if (defined('ABSPATH') && defined('WP_UNINSTALL_PLUGIN')) {
	global $wpdb, $table_prefix;

	delete_option('linkate-posts');
	delete_option('linkate_posts');
	delete_option('linkate-posts-meta');
	delete_option('linkate_posts_meta');

	$table_name = $table_prefix . 'linkate_posts';
		$wpdb->query("DROP TABLE `$table_name`");
	
	$table_name = $table_prefix . 'linkate_scheme';
		$wpdb->query("DROP TABLE `$table_name`");

	$table_name = $table_prefix . 'linkate_stopwords';
		$wpdb->query("DROP TABLE `$table_name`");
}
