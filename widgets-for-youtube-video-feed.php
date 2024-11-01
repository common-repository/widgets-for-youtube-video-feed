<?php
/*
Plugin Name: Widgets for Youtube Video Feed
Plugin Title: Widgets for Youtube Video Feed Plugin
Plugin URI: https://wordpress.org/plugins/widgets-for-youtube-video-feed/
Description: Youtube Feed Widgets. Display your Youtube feed on your website to increase engagement, sales and SEO.
Tags: youtube, feed, video, widget, gallery
Version: 1.4.5
Author: Trustindex.io <support@trustindex.io>
Author URI: https://www.trustindex.io/
Contributors: trustindex
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: widgets-for-youtube-video-feed
Domain Path: /languages/
Donate link: https://www.trustindex.io/prices/
*/
/*
You should have received a copy of the GNU General Public License
along with Review widget addon for Divi. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/
/*
Copyright 2019 Trustindex Kft (email: support@trustindex.io)
*/
defined('ABSPATH') or die('No script kiddies please!');
require_once plugin_dir_path(__FILE__) . 'include' . DIRECTORY_SEPARATOR . 'cache-plugin-filters.php';
require_once plugin_dir_path( __FILE__ ) . 'trustindex-feed-plugin.class.php';
$trustindex_feed_youtube = new TRUSTINDEX_Feed_Youtube("youtube", __FILE__, "1.4.5", "Widgets for Youtube Video Feed", "Youtube");
$pluginManagerInstance = $trustindex_feed_youtube;
register_activation_hook(__FILE__, [ $pluginManagerInstance, 'activate' ]);
register_deactivation_hook(__FILE__, [ $pluginManagerInstance, 'deactivate' ]);
add_action('plugins_loaded', [ $pluginManagerInstance, 'load' ]);
add_action('admin_menu', [ $pluginManagerInstance, 'addSettingMenu' ], 10);
add_filter('plugin_action_links', [ $pluginManagerInstance, 'addPluginActionLinks' ], 10, 2);
add_filter('plugin_row_meta', [ $pluginManagerInstance, 'addPluginMetaLinks' ], 10, 2);
add_action('init', [ $pluginManagerInstance, 'outputBuffer' ]);
add_action('admin_enqueue_scripts', [ $pluginManagerInstance, 'addScripts' ]);
if (is_file($pluginManagerInstance->getCssFile())) {
add_action('init', function() use ($pluginManagerInstance) {
$path = wp_upload_dir()['baseurl'] .'/'. $pluginManagerInstance->getCssFile(true);
if (is_ssl()) {
$path = str_replace('http://', 'https://', $path);
}
wp_register_style('trustindex-feed-widget-css-'. $pluginManagerInstance->getShortName(), $path, [], filemtime($pluginManagerInstance->getCssFile()));
});
}
add_action('init', [ $pluginManagerInstance, 'shortcode' ]);
add_filter('script_loader_tag', function($tag, $handle, $src) {
if ($handle === 'trustindex-feed-loader-js' && strpos($tag, 'defer async') === false) {
$tag = str_replace(' src', ' defer async src', $tag);
}
if (strpos($handle, 'trustindex-feed-data') !== false) {
$content = [];
$content_pattern = '/script_content_start(.*)script_content_end/';
if (preg_match($content_pattern, $tag, $content)) {
$replace = [
 '/\s+type\s*=\s*["\'][^"\']+["\']/' => '',
'/\s+src\s*=\s*/' => ' type="application/ld+json" data-src=',
$content_pattern => '',
'/><\//' => '>'. base64_decode($content[1]) .'</',
];
$tag = preg_replace(array_keys($replace), array_values($replace), $tag);
}
}
return $tag;
}, 10, 3);
add_action('admin_notices', function() use ($pluginManagerInstance) {
foreach ($pluginManagerInstance->getNotificationOptions() as $type => $options) {
if (!$pluginManagerInstance->isNotificationActive($type)) {
continue;
}
echo '<div class="notice notice-'. esc_attr($options['type']) .' '. ($options['is-closeable'] ? 'is-dismissible' : '') .' trustindex-notification-row '. esc_attr($options['extra-class']).'" data-close-url="'. esc_url(admin_url('admin.php?page='. $pluginManagerInstance->getPluginSlug() .'/admin.php&notification='. $type .'&action=close')) .'">';
if ($type === 'rate-us') {
echo '<div class="trustindex-star-row">&starf;&starf;&starf;&starf;&starf;</div>';
}
echo '<p>'. wp_kses_post($options['text']) .'<p>';
if ($type === 'rate-us') {
echo '
<a href="'. esc_url(admin_url('admin.php?page='. $pluginManagerInstance->getPluginSlug() .'/admin.php&notification='. $type .'&action=open')) .'" class="ti-close-notification" target="_blank">
<button class="button ti-button-primary button-primary">'. esc_html(__('Write a review', 'widgets-for-youtube-video-feed')) .'</button>
</a>
<a href="'. esc_url(admin_url('admin.php?page='. $pluginManagerInstance->getPluginSlug() .'/admin.php&notification='. $type .'&action=later')) .'" class="ti-remind-later">
'. esc_html(__('Maybe later', 'widgets-for-youtube-video-feed')) .'
</a>
<a href="'. esc_url(admin_url('admin.php?page='. $pluginManagerInstance->getPluginSlug() .'/admin.php&notification='. $type .'&action=hide')) .'" class="ti-hide-notification" style="float: right; margin-top: 14px">
'. esc_html(__('Do not remind me again', 'widgets-for-youtube-video-feed')) .'
</a>
';
} else {
echo '
<a href="'. esc_url(admin_url('admin.php?page='. $pluginManagerInstance->getPluginSlug() .'/admin.php&notification='. $type .'&action=open')) .'">
<button class="button button-primary">'. esc_html($options['button-text']) .'</button>
</a>';
if ($options['remind-later-button']) {
echo '
<a href="'. esc_url(admin_url('admin.php?page='. $pluginManagerInstance->getPluginSlug() .'/admin.php&notification='. $type .'&action=later')) .'" class="ti-remind-later" style="margin-left: 5px">
'. esc_html(__('Remind me later', 'widgets-for-youtube-video-feed')) .'
</a>';
}
}
echo '
</p>
</div>';
}
});
unset($pluginManagerInstance);
?>
