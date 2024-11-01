<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
die;
}
require_once plugin_dir_path( __FILE__ ) . 'trustindex-feed-plugin.class.php';
$trustindex_feed_youtube = new TRUSTINDEX_Feed_Youtube("youtube", __FILE__, "1.4.5", "Widgets for Youtube Video Feed", "Youtube");
$trustindex_feed_youtube->uninstall();
?>