<?php
class TRUSTINDEX_Feed_Youtube
{
private $pluginFilePath;
private $pluginName;
private $platformName;
private $version;
private $shortname;
public function __construct($shortname, $pluginFilePath, $version, $pluginName, $platformName)
{
$this->shortname = $shortname;
$this->pluginFilePath = $pluginFilePath;
$this->version = $version;
$this->pluginName = $pluginName;
$this->platformName = $platformName;
}
public function getShortName()
{
return $this->shortname;
}


public function getPluginTabs()
{
$tabs = [];
$tabs[] = [
'place' => 'left',
'slug' => 'feed-configurator',
'name' => __('Feed Configurator', 'widgets-for-youtube-video-feed')
];
$tabs[] = [
'place' => 'left',
'slug' => 'get-more-features',
'name' => __('Get more features', 'widgets-for-youtube-video-feed')
];
$tabs[] = [
'place' => 'right',
'slug' => 'advanced',
'name' => __('Advanced', 'widgets-for-youtube-video-feed')
];
return $tabs;
}
public function getPluginDir()
{
return plugin_dir_path($this->pluginFilePath);
}
public function getPluginFileUrl($file, $addVersioning = true)
{
$url = plugins_url($file, $this->pluginFilePath);
if ($addVersioning) {
$appendMark = strpos($url, '?') === FALSE ? '?' : '&';
$url .= $appendMark . 'ver=' . $this->getVersion();
}
return $url;
}
public function getPluginSlug()
{
return basename($this->getPluginDir());
}
public function getPluginCurrentVersion()
{
$response = wp_remote_get('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]='. $this->getPluginSlug());
$json = json_decode($response['body'], true);
if (!$json || !isset($json['version'])) {
return false;
}
return $json['version'];
}


public function activate()
{
include $this->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'activate.php';
if (!$this->getNotificationParam('rate-us', 'hidden', false) && $this->getNotificationParam('rate-us', 'active', true)) {
$this->setNotificationParam('rate-us', 'active', true);
$this->setNotificationParam('rate-us', 'timestamp', time() + 86400);
}
update_option($this->getOptionName('activation-redirect'), 1, false);
}
public function load()
{
$this->loadI18N();
include $this->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'update.php';
if (get_option($this->getOptionName('activation-redirect'))) {
delete_option($this->getOptionName('activation-redirect'));
wp_redirect(admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php'));
exit;
}
$tokenExpireTimestamp = (int)get_option($this->getOptionName('token-expires'));
if ($tokenExpireTimestamp &&
$tokenExpireTimestamp < time() + (86400 * 7) &&
$this->getNotificationParam('token-renew', 'do-check', true)
) {
$this->setNotificationParam('token-renew', 'active', true);
$this->setNotificationParam('token-renew', 'do-check', false);
$this->setNotificationParam('token-expired', 'do-check', true);
}
if ($tokenExpireTimestamp &&
$tokenExpireTimestamp < time() &&
$this->getNotificationParam('token-expired', 'do-check', true)
) {
$this->setNotificationParam('token-renew', 'active', false);
$this->setNotificationParam('token-expired', 'active', true);
$this->setNotificationParam('token-expired', 'do-check', false);
}
}
public function deactivate()
{
update_option($this->getOptionName('active'), '0');
}
public function uninstall()
{
$this->deleteConnectedSource();
include $this->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'uninstall.php';
if (is_file($this->getCssFile())) {
wp_delete_file($this->getCssFile());
}
}
public function outputBuffer()
{
ob_start();
}
public function loadI18N()
{
load_plugin_textdomain($this->getPluginSlug(), false, $this->getPluginSlug() . DIRECTORY_SEPARATOR . 'languages');
}


public function getShortcodeName()
{
return 'trustindex-feed-'. $this->getShortName();
}
public function shortcode()
{
$pluginManager = $this;
add_shortcode($this->getShortcodeName(), function($atts) use($pluginManager) {
if (!$pluginManager->getConnectedSource()) {
return $pluginManager->errorBoxForAdmins(__('You have to connect your source!', 'widgets-for-youtube-video-feed'));
}
return $pluginManager->getWidget();
});
}


public function getConnectedSource()
{
$source = get_option($this->getOptionName('source'));
if (isset($source['name'])) {
$source['name'] = json_decode($source['name']);
}
return $source;
}
public function deleteConnectedSource()
{
$publicId = get_option($this->getOptionName('public-id'));
if (!$publicId) {
return false;
}
wp_remote_post('https://admin.trustindex.io/source/saveFeedWordpress', [
'body' => [
'is-delete' => 1,
'public-id' => $publicId
],
'timeout' => '30',
'redirection' => '5',
'blocking' => true
]);
return true;
}
public function getFeedData()
{
$data = [];
if ($jsonStr = get_option($this->getOptionName('feed-data'), "")) {
$data = json_decode($jsonStr, true);
$data['style'] = array_merge($data['style'], [
'settings' => [ 'platform_style' => ucfirst($this->getShortName()) ]
]);
if (!isset($data['style']['type'])) {
$data['style']['type'] = 'custom-style';
}
}
$dataSaved = time() - (int)get_option($this->getOptionName('feed-data-saved'), 0);
$allImageReplaced = true;
if ($data && $dataSaved > 600) {
foreach ($data['posts'] as $post) {
foreach ($post['media_content'] as $media) {
if (isset($media['image_url']) && !isset($media['image_urls'])) {
$allImageReplaced = false;
break 2;
}
}
}
}
if (!$data || $dataSaved > (24 * 3600) || !$allImageReplaced) {
$publicId = get_option($this->getOptionName('public-id'));
if (!$publicId) {
return $data;
}
$response = wp_remote_get('https://cdn.trustindex.io/wp-feeds/'. substr($publicId, 0, 2) .'/'. $publicId .'/data.json', [
'timeout' => 30,
'sslverify' => false
]);
if (is_wp_error($response)) {
echo wp_kses_post($this->errorBoxForAdmins(__('Could not download the posts for the widget.<br />Please reload the page.<br />If the problem persists, please write an email to support@trustindex.io.', 'widgets-for-youtube-video-feed') .'<br /><br />'. print_r($response, true)));
die;
}
$data = $this->updateFeedData(json_decode($response['body'], true), $data);
if ($tokenExpires = strtotime($data['token_expires'] ?? '')) {
update_option($this->getOptionName('token-expires'), $tokenExpires, false);
if (time() > $tokenExpires) {
$this->setNotificationParam('token-renew', 'active', false);
$this->setNotificationParam('token-expired', 'active', true);
}
}
}
return $data;
}
public function saveFeedData($arr = [], $saveTime = true)
{
update_option($this->getOptionName('feed-data'), wp_json_encode($arr), false);
if ($saveTime) {
update_option($this->getOptionName('feed-data-saved'), time(), false);
}
}
public function updateFeedData($newData = [], $data = null)
{
if (!$data) {
$data = $this->getFeedData();
}
if (!$newData) {
return $data;
}
if ($data) {
foreach ($data as $key => $value) {
if (!in_array($key, [ 'posts', 'sources', 'source_types', 'sprite' ])) {
$newData[ $key ] = $value;
}
}
}
switch (isset($newData['order']) ? $newData['order'] : "") {
default:
case 'newer_sooner':
usort($newData['posts'], function ($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
break;
case 'older_sooner':
usort($newData['posts'], function ($a, $b) { return strtotime($a['created_at']) - strtotime($b['created_at']); });
break;
case 'random':
shuffle($newData['posts']);
break;
}
$this->saveFeedData($newData);
return $newData;
}
public function updateFeedDataWidthDefaultTemplateParams(&$data, $templateId)
{
$params = self::$widgetParams;
foreach ($params as $component => $param) {
$layoutParam = isset(self::$widgetTemplates[ $templateId ]['params'][ $component ]) ? self::$widgetTemplates[ $templateId ]['params'][ $component ] : null;
if (is_array($param) && is_array($layoutParam)) {
$params[ $component ] = array_merge($param, $layoutParam);
}
}

$params['layout']['target_col_width'] = '350';
$params['card']['show_username'] = 'false';
$params['card']['show_media_icon'] = 'false';
$params['card']['show_post_title'] = 'true';
$params['card']['ratio'] = 'landscape';
$params['custom_style']['header-btn-background-color'] = '#cc0000';
$params['custom_style']['header-btn-border-radius'] = '20';
$params['custom_style']['card-border-radius'] = '0';

$params['type'] = 'custom-style';
$data['style'] = array_merge($data['style'], $params);
return $data;
}
public function sanitizeJsonData($data, $decode = true)
{
if ($decode) {
$data = json_decode(stripcslashes($data), true);
}
foreach ($data as $key => $value) {
if (is_array($value)) {
$data[ $key ] = $this->sanitizeJsonData($value, false);
continue;
}
switch ($key) {
case 'profile_url':
case 'image_url':
case 'url':
$data[ $key ] = sanitize_url($value);
break;
default:
$data[ $key ] = sanitize_text_field($value);
break;
}
}
return $data;
}


public static $widgetCategories = array (
 0 => 'slider',
 1 => 'grid',
 2 => 'list',
 3 => 'masonry',
);
public static $widgetTemplates = array (
 94 => 
 array (
 'name' => 'Grid - Card 1',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '14',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 93 => 
 array (
 'name' => 'Grid - Card 2',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 96 => 
 array (
 'name' => 'Grid - Card 3',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'hover-only',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '6',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'media_layout' => 'carousel',
 'align' => 'top',
 'ratio' => 'square',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 63 => 
 array (
 'name' => 'Grid - Card 4',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '6',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '4',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'grid',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 92 => 
 array (
 'name' => 'Grid - Mini',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'false',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 64 => 
 array (
 'name' => 'Slider - Card 1',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '14',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '15',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 85 => 
 array (
 'name' => 'Slider - Card 2',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '200',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '15',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 86 => 
 array (
 'name' => 'Slider - Card 3',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '300',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '15',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '10',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 87 => 
 array (
 'name' => 'Slider - Single Card 1',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'false',
 'target_col_width' => '300',
 'cols_num' => '1',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 88 => 
 array (
 'name' => 'Slider - Single Card 4',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'false',
 'target_col_width' => '300',
 'cols_num' => '1',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'true',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'hover-only',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '4',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'true',
 'interval' => '5',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'left',
 'click_action' => 'redirect',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'false',
 'type' => 'scroller',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => false,
 ),
 65 => 
 array (
 'name' => 'List - Card 1',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '14',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '15',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 89 => 
 array (
 'name' => 'List - Card 3',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '14',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '50',
 'header-muted-color' => '#555555',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 91 => 
 array (
 'name' => 'List - Card 4',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '14',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#555555',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '15',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '4',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 90 => 
 array (
 'name' => 'List Horizontal - Card 3',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '14',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#555555',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'grid',
 'align' => 'left',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 66 => 
 array (
 'name' => 'Masonry - Card 1',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '14',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 83 => 
 array (
 'name' => 'Masonry - Card 2',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 82 => 
 array (
 'name' => 'Masonry - Card 3',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '14',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'true',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 84 => 
 array (
 'name' => 'Masonry - Mini',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '60',
 'header-muted-color' => '#536471',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'false',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
);
public static $widgetParams = array (
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => 3,
 'width' => 'lg',
 'infinity_loop' => 'false',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'false',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 'switch' => 'true',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'true',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'square',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'summary' => 
 array (
 'author_name' => NULL,
 'author_bio' => '',
 ),
 'settings' => 
 array (
 'media_only' => 'true',
 'selected_source_id' => '',
 'platform_style' => 'custom',
 'hide_copyright' => 'false',
 ),
);
public static $widgetHalfWidthLayouts = array (
 0 => 84,
 1 => 87,
 2 => 88,
 3 => 92,
);
public function getWidget($templateId = null)
{
$isPreview = true;
if (!$templateId) {
$templateId = (int)get_option($this->getOptionName('template'));
$isPreview = false;
}
$id = uniqid($templateId);
$feedData = $this->getFeedData();
if (!$feedData || !$templateId) {
return;
}
if ($isPreview) {
$this->updateFeedDataWidthDefaultTemplateParams($feedData, $templateId);
$feedData['widget-key'] = $templateId;
wp_enqueue_style('trustindex-feed-widget-css-'. $this->getShortName() .'-'. $id, 'https://cdn.trustindex.io/assets/widget-presetted-css/'. $templateId .'-'. ucfirst($this->platformName) .'.css', [], $this->getVersion());
}
else {
$cssCdnVersion = $this->getCdnVersion('feed-css');
if ($cssCdnVersion && version_compare($cssCdnVersion, $this->getVersion('feed-css'))) {
$response = wp_remote_post('https://admin.trustindex.io/api/getFeedCss', [
'body' => [
'data' => $feedData,
'template-id' => $templateId,
'public-id' => get_option($this->getOptionName('public-id'))
],
'timeout' => '60'
]);
if (!is_wp_error($response)) {
$json = json_decode($response['body'], true);
if (isset($json['content'])) {
update_option($this->getOptionName('css-content'), $json['content'], false);
$this->handleCssFile();
}
}
$this->updateVersion('feed-css', $cssCdnVersion);
}
$feedData['widget-key'] = 'feed-'. $this->getShortName();
$cssKey = 'trustindex-feed-widget-css-' . $this->getShortName();
if (!wp_style_is($cssKey, 'registered')) {
$cssContent = get_option($this->getOptionName('css-content'));
if (!get_option($this->getOptionName('load-css-inline'), 0) || !$cssContent) {
if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
}
else {
return $this->errorBoxForAdmins(__('CSS file could not saved.', 'widgets-for-youtube-video-feed'));
}
}
wp_register_style($cssKey, false, [], true);
wp_enqueue_style($cssKey);
wp_add_inline_style($cssKey, wp_strip_all_tags($cssContent));
}
else {
wp_enqueue_style($cssKey);
}
}
wp_enqueue_script('trustindex-feed-loader-js', 'https://cdn.trustindex.io/loader-feed.js', [], $this->getVersion(), [ 'in_footer' => true ]);
$fn = function() use ($id, $feedData) {
$data = [
'@context' => 'http://schema.org',
'container' => 'trustindex-feed-container-'. $id,
'data' => $feedData,
];
$data = 'script_content_start'. base64_encode(wp_json_encode($data, JSON_UNESCAPED_SLASHES)) .'script_content_end';
wp_enqueue_script('trustindex-feed-data-'. $id, 'https://cdn.trustindex.io/loader-feed.js', [], $id .'|wordpress'. $data, [ 'in_footer' => false ]);
};
add_action('wp_footer', $fn);
add_action('admin_footer', $fn);
return '<div id="trustindex-feed-container-'. $id .'"></div>';
}


public function getCssFile($returnOnlyFile = false)
{
$file = 'trustindex-feed-'. $this->getShortName() .'-widget.css';
if ($returnOnlyFile) {
return $file;
}
$uploadDir = wp_upload_dir();
return trailingslashit($uploadDir['basedir']) . $file;
}
public function handleCssFile()
{
$css = get_option($this->getOptionName('css-content'));
if (!$css) {
return;
}
if (get_option($this->getOptionName('load-css-inline'), 0)) {
return;
}
$fileExists = is_file($this->getCssFile());
$success = false;
$errorType = null;
$errorMessage = "";
if ($fileExists && !is_readable($this->getCssFile())) {
$errorType = 'permission';
}
else {
require_once(ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'file.php');
global $wp_filesystem;
add_filter('filesystem_method', array($this, 'filterFilesystemMethod'));
WP_Filesystem();
if ($fileExists && $css === $wp_filesystem->get_contents($this->getCssFile())) {
return;
}
set_error_handler(function ($errSeverity, $errMsg, $errFile, $errLine, $errContext = []) {
throw new ErrorException(wp_kses_post($errMsg), 0, esc_html($errSeverity), esc_html($errFile), esc_html($errLine));
}, E_WARNING);
try {
$success = $wp_filesystem->put_contents($this->getCssFile(), $css, 0777);
}
catch (Exception $e) {
if (strpos($e->getMessage(), 'Permission denied') !== FALSE) {
$errorType = 'permission';
}
else {
$errorType = 'filesystem';
$errorMessage = $e->__toString();
}
}
restore_error_handler();
remove_filter('filesystem_method', array($this, 'filterFilesystemMethod'));
}
if (!$success) {
add_action('admin_notices', function() use ($fileExists, $errorType, $errorMessage) {
$html = '
<div class="notice notice-error" style="margin: 5px 0 15px">
<p>' .
'<strong>'. __('ERROR with the following plugin:', 'widgets-for-youtube-video-feed') .'</strong> '. $this->pluginName .'<br /><br />' .
__('CSS file could not saved.', 'widgets-for-youtube-video-feed') .' <strong>('. $this->getCssFile() .')</strong> '. __('Your widgets do not display properly!', 'widgets-for-youtube-video-feed') . '<br />';
if ($errorType === 'filesystem') {
$html .= '<br />
<strong>There is an error with your filesystem. We got the following error message:</strong>
<pre style="display: block; margin: 10px 0; padding: 20px; background: #eee">'. $errorMessage .'</pre>
<strong>Maybe you configured your filesystem incorrectly.<br />
<a href="https://wordpress.org/support/article/editing-wp-config-php/#wordpress-upgrade-constants" target="_blank">Here you can read about how to configure filesystem in your WordPress.</a></strong>';
}
else {
if ($fileExists) {
$html .= __('CSS file exists and it is not writeable. Delete the file', 'widgets-for-youtube-video-feed');
}
else {
$html .= __('Grant write permissions to upload folder', 'widgets-for-youtube-video-feed');
}
$html .= '<br />' .
__('or', 'widgets-for-youtube-video-feed') . '<br />' .
/* translators: %s: URL of Advanced page */
sprintf(__("enable 'CSS internal loading' in the %s page!", 'widgets-for-youtube-video-feed'), '<a href="'. admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php&tab=advanced') .'>'. __('Advanced', 'widgets-for-youtube-video-feed') .'</a>');
}
echo wp_kses_post($html) . '</p></div>';
});
}
return $success;
}
public function filterFilesystemMethod($method)
{
if ($method !== 'direct' && !defined('FS_METHOD')) {
return 'direct';
}
return $method;
}


public function getTableName($name = "")
{
global $wpdb;
return $wpdb->prefix .'trustindex_feed_' . $name;
}
public function isTableExists($name = "")
{

return false;
}


public function addSettingMenu()
{
global $menu, $submenu;
$permission = 'edit_pages';
$adminPageUrl = $this->getPluginSlug() . "/admin.php";
$adminPageTitle = $this->platformName . ' Feed';
add_menu_page(
$adminPageTitle,
$adminPageTitle,
$permission,
$adminPageUrl,
'',
$this->getPluginFileUrl('assets/img/trustindex-sign-logo.png')
);
}
public function addPluginActionLinks($links, $file)
{
if (basename($file) === $this->getPluginSlug() . '.php') {
$newItem2 = '<a target="_blank" href="https://www.trustindex.io" target="_blank">by <span style="background-color: #4067af; color: white; font-weight: bold; padding: 1px 8px;">Trustindex.io</span></a>';
$newItem1 = '<a href="' . admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php') . '">' . __('Settings', 'widgets-for-youtube-video-feed') . '</a>';
array_unshift($links, $newItem2, $newItem1);
}
return $links;
}
public function addPluginMetaLinks($meta, $file)
{
if (basename($file) === $this->getPluginSlug() . '.php') {
$meta[] = '<a href="http://wordpress.org/support/view/plugin-reviews/'. $this->getPluginSlug() .'" target="_blank" rel="noopener noreferrer">'. __('Rate our plugin', 'widgets-for-youtube-video-feed') . '</a>';
}
return $meta;
}
public function addScripts($hook)
{
$tmp = explode('/', $hook);
$currentSlug = array_shift($tmp);
if ($this->getPluginSlug() === $currentSlug) {
if (file_exists($this->getPluginDir() . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin.css')) {
wp_enqueue_style('trustindex-feed-admin-'. $this->getShortName(), $this->getPluginFileUrl('assets/css/admin.css'), [], $this->getVersion());
}
if (file_exists($this->getPluginDir() . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'admin.js')) {
wp_enqueue_script('trustindex-feed-admin-'. $this->getShortName(), $this->getPluginFileUrl('assets/js/admin.js'), [], $this->getVersion(), [ 'in_footer' => false ]);
}
}
wp_register_script('trustindex_admin_notification', $this->getPluginFileUrl('assets/js/admin-notification.js'), [], $this->getVersion(), [ 'in_footer' => false ]);
wp_enqueue_script('trustindex_admin_notification');
wp_enqueue_style('trustindex_admin_notification', $this->getPluginFileUrl('assets/css/admin-notification.css'), [], $this->getVersion());
}


public static function getAlertBox($type, $content, $newline_content = true)
{
$types = [
'warning' => [
'css' => 'color: #856404; background-color: #fff3cd; border-color: #ffeeba;',
'icon' => 'dashicons-warning'
],
'info' => [
'css' => 'color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb;',
'icon' => 'dashicons-info'
],
'error' => [
'css' => 'color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;',
'icon' => 'dashicons-info'
]
];
return '<div style="margin:20px 0px; padding:10px; '. $types[ $type ]['css'] .' border-radius: 5px">'
. '<span class="dashicons '. $types[ $type ]['icon'] .'"></span> <strong>'. strtoupper($type) .'</strong>'
. ($newline_content ? '<br />' : "")
. $content
. '</div>';
}
public function errorBoxForAdmins($text)
{
if (!current_user_can('manage_options')) {
return "";
}
return self::getAlertBox('error', ' @ <strong>Trustindex plugin</strong> <i style="opacity: 0.65">('. __('This message is not be visible to visitors in public mode.', 'widgets-for-youtube-video-feed') .')</i><br /><br />'. $text, false);
}


public function getNotificationOptions($type = "")
{
$tokenExpireDate = gmdate('Y-m-d H:i', (int)get_option($this->getOptionName('token-expires')));
$list = [
'rate-us' => [
'type' => 'warning',
'extra-class' => 'trustindex-popup',
'button-text' => "",
'is-closeable' => true,
'hide-on-close' => false,
'hide-on-open' => true,
'remind-later-button' => false,
'redirect' => 'https://wordpress.org/support/plugin/'. $this->getPluginSlug() .'/reviews/?rate=5#new-post',
'text' =>
/* translators: %s: Name of the plugin */
sprintf(__('We have worked a lot on the free "%s" plugin.', 'widgets-for-youtube-video-feed'), $this->pluginName) . '<br />' .
__('If you love our features, please write a review to help us make the plugin even better.', 'widgets-for-youtube-video-feed') . '<br />' .
/* translators: %s: Trustindex CEO */
sprintf(__('Thank you. Gabor, %s', 'widgets-for-youtube-video-feed'), 'Trustindex CEO'),
],
'token-renew' => [
'type' => 'warning',
'extra-class' => "",
'button-text' => __('Go to Connect Page', 'widgets-for-youtube-video-feed'),
'is-closeable' => false,
'hide-on-close' => false,
'hide-on-open' => false,
'remind-later-button' => false,
'redirect' => '?page='. $this->getPluginSlug() .'/admin.php&tab=feed-configurator&step=1&reconnect-source',
'text' =>
'<strong>'.
__('Important: ', 'widgets-for-youtube-video-feed').
/* translators: 1: Platform name, 2: Date string */
sprintf(__('Your %1$s Access Token expires on %2$s.', 'widgets-for-youtube-video-feed'), ucfirst($this->getShortName()), $tokenExpireDate).
'</strong><br />'.
__('Please renew your token by clicking the "Reconnect" button on the Connect Page.', 'widgets-for-youtube-video-feed').'<br />'.
/* translators: %s: Platform name */
sprintf(__('This will ensure that your %s Feed Widget continues to update automatically.', 'widgets-for-youtube-video-feed'), ucfirst($this->getShortName())),
],
'token-expired' => [
'type' => 'error',
'extra-class' => "",
'button-text' => __('Go to Connect Page', 'widgets-for-youtube-video-feed'),
'is-closeable' => false,
'hide-on-close' => false,
'hide-on-open' => false,
'remind-later-button' => false,
'redirect' => '?page='. $this->getPluginSlug() .'/admin.php&tab=feed-configurator&step=1&reconnect-source',
'text' =>
'<strong>'.
__('Important: ', 'widgets-for-youtube-video-feed').
/* translators: 1: Platform name, 2: Date string */
sprintf(__('Your %1$s Access Token expired on %2$s.', 'widgets-for-youtube-video-feed'), ucfirst($this->getShortName()), $tokenExpireDate).
'</strong><br />'.
__('Please renew your token by clicking the "Reconnect" button on the Connect Page.', 'widgets-for-youtube-video-feed').'<br />'.
/* translators: %s: Platform name */
sprintf(__('This will ensure that your %s Feed Widget continues to update automatically.', 'widgets-for-youtube-video-feed'), ucfirst($this->getShortName())),
]
];
return $type ? $list[$type] : $list;
}
public function setNotificationParam($type, $param, $value)
{
$notifications = get_option($this->getOptionName('notifications'), []);
if (!isset($notifications[ $type ])) {
$notifications[ $type ] = [];
}
$notifications[ $type ][ $param ] = $value;
update_option($this->getOptionName('notifications'), $notifications, false);
}
public function getNotificationParam($type, $param, $default = null)
{
$notifications = get_option($this->getOptionName('notifications'), []);
if (!isset($notifications[ $type ]) || !isset($notifications[ $type ][ $param ])) {
return $default;
}
return $notifications[ $type ][ $param ];
}
public function isNotificationActive($type)
{
$notifications = get_option($this->getOptionName('notifications'), []);
if (
!isset($notifications[ $type ]) ||
!isset($notifications[ $type ]['active']) || !$notifications[ $type ]['active'] ||
(isset($notifications[ $type ]['hidden']) && $notifications[ $type ]['hidden']) ||
(isset($notifications[ $type ]['timestamp']) && $notifications[ $type ]['timestamp'] > time())
) {
return false;
}
return true;
}


public function getOptionName($optName)
{
if (!in_array($optName, $this->getOptionNames()) && !in_array($optName, $this->getNotUsedOptionNames())) {
echo "Option not registered in plugin (TrustindexFeed class)";
}
if (in_array($optName, [ 'proxy-check', 'cdn-version-control' ])) {
return 'trustindex-'. $optName;
}
else {
return 'trustindex-feed-'. $this->getShortName() .'-'. $optName;
}
}
public function getOptionNames()
{
return [
'active',
'activation-redirect',
'proxy-check',
'notifications',
'rate-us-feedback',
'cdn-version-control',
'version-control',
'preview',
'source',
'feed-data',
'feed-data-saved',
'public-id',
'token-expires',
'css-content',
'load-css-inline',
'layout',
'template',
];
}
public function getNotUsedOptionNames()
{
return [
'version',
'update-version-check',
];
}


public function getCdnVersionControl()
{
$data = get_option($this->getOptionName('cdn-version-control'), []);
if (!$data || $data['last-saved-at'] < time() + 60) {
$response = wp_remote_get('https://cdn.trustindex.io/version-control.json', [ 'timeout' => 60 ]);
if (!is_wp_error($response)) {
$data = array_merge($data, json_decode($response['body'], true));
}
$data['last-saved-at'] = time();
update_option($this->getOptionName('cdn-version-control'), $data, false);
}
return $data;
}
public function getCdnVersion($name = "")
{
$data = $this->getCdnVersionControl();
return isset($data[ $name ]) ? $data[ $name ] : "";
}
public function getVersion($name = "")
{
if (!$name) {
return $this->version;
}
$data = get_option($this->getOptionName('version-control'), []);
return isset($data[ $name ]) ? $data[ $name ] : "1.0";
}
public function updateVersion($name, $value)
{
$data = get_option($this->getOptionName('version-control'), []);
$data[ $name ] = $value;
return update_option($this->getOptionName('version-control'), $data, false);
}
}
?>
