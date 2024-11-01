<?php
defined('ABSPATH') or die('No script kiddies please!');
if (isset($_REQUEST['command'])) {
if ($_REQUEST['command'] === 'connect-source') {
check_admin_referer('ti-connect-source');
$source = null;
if (isset($_POST['data'])) {
$source = $pluginManagerInstance->sanitizeJsonData($_POST['data']);
}
if ($source) {
if ((int)$source['token_expires'] <= 0) {
update_option($pluginManagerInstance->getOptionName('token-expires'), 0, false);
} else {
update_option($pluginManagerInstance->getOptionName('token-expires'), time() + (int)$source['token_expires'], false);
}
if (isset($source['is_reconnecting']) && $source['is_reconnecting']) {
$pluginManagerInstance->updateFeedData($source['feed_data']);
$oldSource = $pluginManagerInstance->getConnectedSource();
$oldSource['access_token'] = $source['access_token'];
$source = $oldSource;
} else {
$pluginManagerInstance->saveFeedData($source['feed_data']);
update_option($pluginManagerInstance->getOptionName('public-id'), $source['public_id'], false);
unset($source['avatar_url']);
unset($source['feed_data']);
unset($source['token_expires']);
unset($source['access_token_expires']);
unset($source['public_id']);
unset($source['is_reconnecting']);
}
if ($source['name']) {
$source['name'] = wp_json_encode($source['name']);
}
update_option($pluginManagerInstance->getOptionName('source'), $source, false);
$pluginManagerInstance->setNotificationParam('token-renew', 'active', false);
$pluginManagerInstance->setNotificationParam('token-renew', 'do-check', true);
$pluginManagerInstance->setNotificationParam('token-expired', 'active', false);
$pluginManagerInstance->setNotificationParam('token-expired', 'do-check', true);
}
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
exit;
}
else if ($_REQUEST['command'] === 'disconnect-source') {
check_admin_referer('ti-disconnect-source');
$pluginManagerInstance->deleteConnectedSource();
delete_option($pluginManagerInstance->getOptionName('source'));
delete_option($pluginManagerInstance->getOptionName('feed-data'));
delete_option($pluginManagerInstance->getOptionName('feed-data-saved'));
delete_option($pluginManagerInstance->getOptionName('public-id'));
delete_option($pluginManagerInstance->getOptionName('token-expires'));
delete_option($pluginManagerInstance->getOptionName('layout'));
delete_option($pluginManagerInstance->getOptionName('template'));
delete_option($pluginManagerInstance->getOptionName('css-content'));
$pluginManagerInstance->setNotificationParam('token-renew', 'active', false);
$pluginManagerInstance->setNotificationParam('token-expired', 'active', false);
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
exit;
}
else if ($_REQUEST['command'] === 'select-layout') {
check_admin_referer('ti-select-layout');
$layout = isset($_GET['layout']) ? sanitize_text_field(wp_unslash($_GET['layout'])) : "";
update_option($pluginManagerInstance->getOptionName('layout'), $layout, false);
delete_option($pluginManagerInstance->getOptionName('template'));
delete_option($pluginManagerInstance->getOptionName('css-content'));
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
exit;
}
else if ($_REQUEST['command'] === 'select-template') {
check_admin_referer('ti-select-template');
$templateId = isset($_GET['template']) ? sanitize_text_field(wp_unslash($_GET['template'])) : "";
update_option($pluginManagerInstance->getOptionName('template'), $templateId, false);
delete_option($pluginManagerInstance->getOptionName('css-content'));
$feedData = $pluginManagerInstance->getFeedData();
$feedData['style'] = ['locales' => $feedData['style']['locales']];
$pluginManagerInstance->updateFeedDataWidthDefaultTemplateParams($feedData, $templateId);
$pluginManagerInstance->saveFeedData($feedData, false);
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
exit;
}
else if ($_REQUEST['command'] === 'save-feed-widget') {
check_admin_referer('ti-save-feed-widget');
$data = null;
if (isset($_POST['data'])) {
$data = $pluginManagerInstance->sanitizeJsonData($_POST['data']);
}
if ($data) {
$data['css'] = preg_replace('/\.ti-widget([\s\.\[])/', '.ti-widget[data-wkey="feed-'. $pluginManagerInstance->getShortName() .'"]$1', $data['css']);
update_option($pluginManagerInstance->getOptionName('css-content'), $data['css'], false);
unset($data['css']);
$pluginManagerInstance->saveFeedData($data, false);
$pluginManagerInstance->handleCssFile();
}
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
exit;
}
}
$layout = get_option($pluginManagerInstance->getOptionName('layout'));
$template = get_option($pluginManagerInstance->getOptionName('template'));
$css = get_option($pluginManagerInstance->getOptionName('css-content'));
$isReconnectingSource = isset($_GET['reconnect-source']);
?>
<?php
$stepUrl = '?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=feed-configurator&step=%step%';
$stepList = [
/* translators: %s: Platform name */
sprintf(__('Connect %s', 'widgets-for-youtube-video-feed'), 'Youtube'),
__('Select Layout', 'widgets-for-youtube-video-feed'),
__('Select Template', 'widgets-for-youtube-video-feed'),
__('Widget Editor', 'widgets-for-youtube-video-feed'),
__('Get Shortcode', 'widgets-for-youtube-video-feed')
];
$stepDone = 0;
$stepCurrent = isset($_GET['step']) ? (int)sanitize_text_field(wp_unslash($_GET['step'])) : 0;
if ($css) {
$stepDone = 4;
}
else if ($template) {
$stepDone = 3;
}
else if ($layout) {
$stepDone = 2;
}
else if ($pluginManagerInstance->getConnectedSource()) {
$stepDone = 1;
}
if (!$stepCurrent) {
$stepCurrent = $stepDone + 1;
} else if ($stepCurrent > ($stepDone + 1)) {
$stepCurrent = $stepDone + 1;
}
if ($stepCurrent === 4) {
$stepRightButton = [
'class' => 'btn-feed-editor-save',
'text' => __('Save and get code', 'widgets-for-youtube-video-feed')
];
}
if (!isset($_GET['step']) && $pluginManagerInstance->getNotificationParam('token-expired', 'active')) {
$stepCurrent = 1;
}
include(plugin_dir_path(__FILE__) . '../include/step-list.php');
?>
<div class="ti-container<?php if (!in_array($stepCurrent, [ 1, 5 ])): ?> ti-narrow-page<?php endif; ?><?php if ($stepCurrent === 4): ?> ti-no-maxwidth<?php endif; ?>">
<?php if ($stepCurrent !== 4): ?>
<h1 class="ti-header-title"><?php echo esc_html($stepList[ $stepCurrent - 1 ]); ?></h1>
<?php endif; ?>
<?php if ($stepCurrent === 1): ?>
<?php
$source = $pluginManagerInstance->getConnectedSource();
if ($source && !$isReconnectingSource): ?>
<div class="ti-source-box">
<?php
$feedData = $pluginManagerInstance->getFeedData();
$avatarUrl = isset($feedData['sources']['feed-plugin']['user']['avatar_url']) ? $feedData['sources']['feed-plugin']['user']['avatar_url'] : "";
if ($avatarUrl): ?>
<img src="<?php echo esc_url($avatarUrl); ?>" />
<?php endif; ?>
<?php if (isset($source['name']) && $source['name']): ?>
<div class="ti-source-info">
<strong><?php echo esc_html($source['name']); ?></strong>
</div>
<?php endif; ?>
<?php if (isset($source['subtype'])): ?>
<div class="ti-source-type">
<?php if (substr($source['subtype'], 0, 8) === 'business'): ?>
<?php echo esc_html(__('Business Account', 'widgets-for-youtube-video-feed')); ?>
<?php else: ?>
<?php echo esc_html(__('Personal Account', 'widgets-for-youtube-video-feed')); ?>
<?php endif; ?>
</div>
<?php endif; ?>
<a href="<?php echo esc_url(wp_nonce_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&command=disconnect-source', 'ti-disconnect-source')); ?>" class="ti-btn ti-btn-default ti-btn-loading-on-click"><?php echo esc_html(__('Disconnect', 'widgets-for-youtube-video-feed')); ?></a>
</div>
<?php if ($tokenExpireTimestamp = (int)get_option($pluginManagerInstance->getOptionName('token-expires'))): ?>
<div class="ti-box ti-notice-<?php if ($tokenExpireTimestamp < time()): ?>error<?php elseif ($tokenExpireTimestamp < time() + (86400 * 7)): ?>warning<?php else: ?>info<?php endif; ?>">
<p>
<strong><?php
$tokenExpireDate = gmdate('Y-m-d H:i', $tokenExpireTimestamp);
if ($tokenExpireTimestamp > time()) {
/* translators: 1: Platform name, 2: Date string */
echo esc_html(sprintf(__('Your %1$s Access Token expires on %2$s.', 'widgets-for-youtube-video-feed'), 'Youtube', $tokenExpireDate));
} else {
/* translators: 1: Platform name, 2: Date string */
echo esc_html(sprintf(__('Your %1$s Access Token expired on %2$s.', 'widgets-for-youtube-video-feed'), 'Youtube', $tokenExpireDate));
}
?></strong><br />
<?php echo esc_html(__('Please renew your token by clicking the "Reconnect" button on the Connect Page.', 'widgets-for-youtube-video-feed')); ?><br />
<?php
/* translators: %s: Platform name */
echo esc_html(sprintf(__('This will ensure that your %s Feed Widget continues to update automatically.', 'widgets-for-youtube-video-feed'), 'Youtube'));
?><br /><br />
<a href="<?php echo esc_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&step=1&reconnect-source'); ?>" class="ti-btn ti-btn-loading-on-click"><?php echo esc_html(__('Go to Connect Page', 'widgets-for-youtube-video-feed')); ?></a>
</p>
</div>
<?php endif; ?>
<?php else: ?>

<form method="post" id="ti-connect-source-form">
<?php wp_nonce_field('ti-connect-source'); ?>
<input type="hidden" name="command" value="connect-source" />
<input type="hidden" name="data" required="required" value="" />
</form>

<?php $connectUrl = 'https://admin.trustindex.io/source/edit_feed/type/Youtube/iframe/1'; ?>
<?php
if ($isReconnectingSource) {
$connectUrl .= '/public_id/'.get_option($pluginManagerInstance->getOptionName('public-id'));
}
?>
<div class="ti-box" style="padding: 0">
<iframe src="<?php echo esc_attr($connectUrl); ?>?email=<?php echo esc_attr(urlencode(get_option('admin_email'))); ?>&website=<?php echo esc_attr(urlencode(get_option('siteurl'))); ?>" id="ti-admin-iframe" scrolling="no" allowfullscreen="true"></iframe>
</div>
<?php endif; ?>
<?php elseif ($stepCurrent === 2): ?>
<div class="ti-category-container">
<?php foreach ($pluginManager::$widgetCategories as $category): ?>
<div class="ti-box">
<div class="ti-box-header"><?php echo esc_html(ucfirst($category)); ?></div>
<img src="<?php echo esc_url($pluginManagerInstance->getPluginFileUrl('assets/img/select-'. $category .'.png')); ?>" />
<div class="ti-box-footer">
<a href="<?php echo esc_url(wp_nonce_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&command=select-layout&layout='. esc_attr($category), 'ti-select-layout')); ?>" class="ti-btn ti-btn-loading-on-click"><?php echo esc_html(__('Select', 'widgets-for-youtube-video-feed')); ?></a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php elseif ($stepCurrent === 3): ?>
<div class="ti-preview-boxes-container">
<?php foreach ($pluginManager::$widgetTemplates as $id => $template): ?>
<?php
$className = 'ti-full-width';
if (in_array($template['category'], [ 'list' ]) || in_array($id, $pluginManager::$widgetHalfWidthLayouts)) {
$className = 'ti-half-width';
}
if ($template['is-active'] && $template['category'] === $layout):
?>
<div class="<?php echo esc_attr($className); ?>">
<div class="ti-box ti-preview-boxes">
<div class="ti-box-inner">
<div class="ti-box-header ti-box-header-normal">
<?php echo esc_html(__('Template', 'widgets-for-youtube-video-feed')); ?>:
<strong><?php echo esc_html($template['name']); ?></strong>
<a href="<?php echo esc_url(wp_nonce_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&command=select-template&template='. esc_attr($id), 'ti-select-template')); ?>" class="ti-btn ti-btn-sm ti-btn-loading-on-click ti-pull-right"><?php echo esc_html(__('Select', 'widgets-for-youtube-video-feed')); ?></a>
<div class="clear"></div>
</div>
<div class="preview"><?php echo wp_kses_post($pluginManagerInstance->getWidget($id)); ?></div>
</div>
</div>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php elseif ($stepCurrent === 4): ?>
<form method="post" id="ti-widget-editor-form">
<?php wp_nonce_field('ti-save-feed-widget'); ?>
<input type="hidden" name="command" value="save-feed-widget" />
<input type="hidden" name="data" required="required" value="" />
<?php
$feedData = $pluginManagerInstance->getFeedData();
$iframeUrl = 'https://admin.trustindex.io/widget/edit/layout_id/'. esc_attr($template) .'/source/'. esc_attr(ucfirst($pluginManagerInstance->getShortName())) .'/iframe/1/layout-set/'. esc_attr($feedData['style']['type']);
?>
<script type="application/ld+json">{"@context":"http://schema.org","data":<?php echo wp_json_encode($feedData); ?>}</script>
<iframe id="ti-admin-iframe" class="ti-narrow-iframe" name="ti-widget-editor-iframe" data-src="<?php echo esc_url($iframeUrl . '?version='. esc_attr($pluginManagerInstance->getVersion())); ?>" id="ti-admin-iframe" scrolling="no" allowfullscreen="true"></iframe>
</form>
<?php else: ?>
<div class="ti-box ti-mb-2">
<div class="ti-box-header"><?php echo esc_html(__('Insert this shortcode into your website', 'widgets-for-youtube-video-feed')); ?></div>
<div class="ti-form-group" style="margin-bottom: 2px">
<label>Shortcode</label>
<code class="ti-shortcode">[<?php echo esc_html($pluginManagerInstance->getShortcodeName()); ?>]</code>
<a href=".ti-shortcode" class="ti-btn ti-tooltip ti-toggle-tooltip btn-copy2clipboard">
<?php echo esc_html(__('Copy to clipboard', 'widgets-for-youtube-video-feed')); ?>
<span class="ti-tooltip-message">
<span style="color: #00ff00; margin-right: 2px">✓</span>
<?php echo esc_html(__('Copied', 'widgets-for-youtube-video-feed')); ?>
</span>
</a>
</div>
<div class="ti-info-text"><?php echo esc_html(__('Copy and paste this shortcode into post, page or widget.', 'widgets-for-youtube-video-feed')); ?></div>
</div>
<?php if (!get_option($pluginManagerInstance->getOptionName('rate-us-feedback'), 0)): ?>
<?php include(plugin_dir_path(__FILE__) . '../include/rate-us-feedback-box.php'); ?>
<?php endif; ?>
<?php
$tiCampaign1 = 'wp-feed-youtube-1';
$tiCampaign2 = 'wp-feed-youtube-2';
include(plugin_dir_path(__FILE__) . '../include/get-more-customers-box.php');
?>
<?php endif; ?>
</div>
