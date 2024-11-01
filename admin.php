<?php
defined('ABSPATH') or die('No script kiddies please!');
$pluginManager = 'TRUSTINDEX_Feed_Youtube';
$pluginManagerInstance = $trustindex_feed_youtube;
$pluginNameForEmails = 'Youtube feed';
$noContainerElementTabs = [ 'feed-configurator' ];
$logoCampaignId = 'wp-feed-youtube-l';
$logoFile = 'assets/img/trustindex.svg';
$assetCheckJs = [
'common' => 'assets/js/admin.js',
];
$assetCheckCssId = 'trustindex-feed-admin-youtube';
$assetCheckCssFile = 'assets/css/admin.css';
include(plugin_dir_path(__FILE__) . 'include' . DIRECTORY_SEPARATOR . 'admin.php');
?>