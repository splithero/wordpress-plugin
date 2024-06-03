<?php

/*
 * Plugin Name: Split Hero
 * Author: Split Hero
 * Description: WordPress A/B testing made easy.
 * Version: 2.0.1
 */

global $wpdb;

define('SPLITHERO_VERSION', '2.0.1');
define('SPLITHERO_GITHUB_ENDPOINT', 'splithero/wordpress-plugin');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/cache.php';
require __DIR__ . '/includes/puc/plugin-update-checker.php';

/*
 * Plugin update checker via GitHub repo
 */
$updateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/' . SPLITHERO_GITHUB_ENDPOINT,
	__FILE__,
	'splithero'
);

$updateChecker->setBranch('main');

if (is_admin()) {
	add_filter('all_plugins', 'splitheroPluginsPage');
}

/*
 * Menu options added
 * Under Settings
 */
add_action('admin_menu', 'splitheroMenu');

function splitheroMenu()
{
	$pluginName = (get_option('splithero_plugin_name')) ? get_option('splithero_plugin_name') : 'Split Hero';
	add_options_page($pluginName, $pluginName, 'manage_options', 'splithero', 'splitheroShowSettings');
}

function splitheroShowSettings()
{
	$splitHeroToken = get_option('splithero_token', null);
	$splitHeroBranding = get_option('splithero_branding', 'https://app.splithero.com/images/logo.svg');
	$splitHeroDomain = 'https://app.splithero.com';
	$splitHeroPluginName = get_option('splithero_plugin_name', 'Split Hero');

	include 'includes/branding.php';

	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized user');
	}

	if (!empty($_POST['splithero_token'])) {
		$splitHeroToken = $_POST['splithero_token'];

		add_filter('https_ssl_verify', '__return_false');

		$request = wp_remote_post($splitHeroDomain . '/api/token_check', [
				'method' => 'POST',
				'timeout' => 30,
				'blocking' => true,
				'ssl_verify' => false,
				'headers' => [
					'token' => $splitHeroToken,
				],
			]
		);

		if (is_wp_error($request)) {
			$error_message = $request->get_error_message();
			echo "<p>Something went wrong: $error_message</p>";
		} else {
			if ($request['response']['code'] !== 200) {
				echo '<h3>Something went wrong: please check your API key is correct.</h3>';
			} else {
				update_option('splithero_token', $splitHeroToken);

				$whiteLabelSettings = json_decode($request['body'], true);

				$splitHeroBranding = $whiteLabelSettings['branding'];
				$splitHeroDomain = $whiteLabelSettings['domain'];
				$splitHeroPluginName = $whiteLabelSettings['plugin']['name'];
				$splitHeroPluginDescription = $whiteLabelSettings['plugin']['description'];
				$splitHeroPluginAuthor = $whiteLabelSettings['team'];

				update_option('splithero_branding', $splitHeroBranding);
				update_option('splithero_domain', $splitHeroDomain);
				update_option('splithero_plugin_name', $splitHeroPluginName);
				update_option('splithero_plugin_description', $splitHeroPluginDescription);
				update_option('splithero_plugin_author', $splitHeroPluginAuthor);
			}
		}
	} ?>

	<table class="widefat striped" style="width: 98%;">
	<tbody>
		<tr>
			<td class="desc">
				<h3>Step 1</h3>
				<p>Enter your API key below and press Save.</p>

				<form method="post">
					<label for="API Token">Your API token</label>
					<br />
					<input <?php
					       if ($splitHeroToken) { ?>type="password" <?php
					       } else { ?>type="text"<?php
					} ?> name="splithero_token" id="splithero_token" value="<?php
					echo $splitHeroToken; ?>" size="40">
					<br /><br />
					<input type="submit" value="Save" class="button button-primary">
					<br /><br />
					<p>You can find your API token via <a href="<?php
						echo $splitHeroDomain; ?>/domains" target="_blank"><?php
							echo $splitHeroPluginName; ?> > Domains</a>.</p>
				</form>
			</td>

			<?php
			if ($splitHeroToken) { ?>
				<td class="desc">
					<h3>Step 2</h3>
					<p>Click the button below to sync your posts & pages to <?php
						echo $splitHeroPluginName; ?>.</p>

					<form method="post">
						<input type="hidden" name="splithero_sync" value="true" />
						<input type="submit" value="Sync" class="button button-primary">
					</form>
				</td>
				<td class="desc">
				<h3>Step 3</h3>
				<p>Return to the <?php
					echo $splitHeroPluginName; ?> dashboard and proceed to create a campaign.</p>
				<a href="<?php
				echo $splitHeroDomain; ?>/campaigns" class="button button-primary" target="_blank">Create a campaign</a>
				</td><?php
			} ?>
		</tr>
	</tbody>
	</table><?php

	if (!empty($_POST['splithero_sync'])) {
		$sync['config'] = [
			'domain' => home_url(),
		];

		// WooCommerce
		if (class_exists('\WooCommerce')) {
			$order = new WC_Order();
			$order_received_url = strtok($order->get_checkout_order_received_url(), '?');

			$sync['posts'][] = [
				'title' => 'Order Received',
				'type' => 'woocommerce',
				'url' => esc_url($order_received_url),
				'status' => 'publish',
			];
		}

		// CartFlows
		$posts = get_posts([
			'post_type' => ['cartflows_flow', 'cartflows_step'],
			'post_status' => 'any',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		]);

		foreach ($posts as $post) {
			if ($post->post_type !== 'attachment') {
				$sync['posts'][] = [
					'title' => $post->post_title,
					'type' => $post->post_type,
					'url' => esc_url(get_permalink($post->ID)),
					'status' => $post->post_status,
				];
			}
		}

		// Posts/Pages
		$posts = get_posts([
			'post_type' => 'any',
			'post_status' => 'any',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		]);

		foreach ($posts as $post) {
			if ($post->post_type !== 'attachment') {
				$sync['posts'][] = [
					'title' => $post->post_title,
					'type' => $post->post_type,
					'url' => esc_url(get_permalink($post->ID)),
					'status' => $post->post_status,
				];
			}
		}

		add_filter('https_ssl_verify', '__return_false');

		$request = wp_remote_post($splitHeroDomain . '/api/sync', [
				'method' => 'POST',
				'timeout' => 30,
				'blocking' => true,
				'ssl_verify' => false,
				'headers' => [
					'token' => $splitHeroToken,
				],
				'body' => ['sync' => serialize($sync)],
			]
		);

		if (is_wp_error($request)) {
			$error_message = $request->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			if ($request['response']['code'] !== 200) {
				echo '<p>Something went wrong: please check your API key is correct.</p>';
			} else {
				splitheroCacheClear(); ?>
				<h3>Pages/Posts synchronisation complete.</h3><?php
			}
		}
	}
}

/**
 * Insert JS script to handle redirects
 */
add_action('wp_head', 'splitheroJsScript', -1000);

function splitheroJsScript()
{
	if (get_option('splithero_token', null)) {
		$splitHeroDomain = 'https://app.splithero.com';

		// What was requested (strip out home portion, case insensitive)
		$request = str_ireplace(get_option('home'), '', splitHeroUtilityGetAddress());
		$loggedInUser = (is_user_logged_in()) ? 'true' : 'false';

		if ($loggedInUser) {
			if (in_array('customer', (array) wp_get_current_user()->roles)) {
				$loggedInUser = 'false';
			}
		}

		echo '<script src="' . $splitHeroDomain . '/api/js?r=' . home_url($request) . '&wpliu=' . $loggedInUser . '" nitro-exclude></script>';
	}
}

/**
 * Utility function to get the full address of the current request
 *
 * @return string
 */
function splitHeroUtilityGetAddress()
{
	return splitHeroUtilityGetProtocol() . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Utility function to get the request protocol
 *
 * @return string
 */
function splitHeroUtilityGetProtocol()
{
	$protocol = 'http';

	if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
		$protocol .= "s";
	}

	return $protocol;
}

function splitheroCacheClear()
{
	\SplitHero\Autooptimize::run();
	\SplitHero\Breeze::run();
	\SplitHero\Cacheenabler::run();
	\SplitHero\Fastest::run();
	\SplitHero\Godaddy::run();
	\SplitHero\Hummingbird::run();
	\SplitHero\Kinsta::run();
	\SplitHero\Litespeed::run();
	\SplitHero\Pagely::run();
	\SplitHero\Pantheon::run();
	\SplitHero\Siteground::run();
	\SplitHero\Supercache::run();
	\SplitHero\Swift::run();
	\SplitHero\W3cache::run();
	\SplitHero\Wpengine::run();
}

add_filter('plugin_action_links', 'splitheroPluginActionLinks', 10, 2);

function splitheroPluginActionLinks($links, $file)
{
	static $this_plugin;

	if (!$this_plugin) {
		$this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin) {
		$settings_link = '<a href="' . admin_url('options-general.php?page=splithero') . '">' . __(
				'Settings',
				'splithero'
			) . '</a>';

		array_unshift($links, $settings_link);
	}

	return $links;
}

function splitheroPluginsPage($plugins)
{
	$key = plugin_basename(__FILE__);

	$splitHeroDomain = 'https://app.splithero.com';
	$splitHeroPluginName = get_option('splithero_plugin_name', 'Split Hero');
	$splitHeroPluginDescription = get_option('splithero_plugin_description', 'Split Hero');
	$splitHeroPluginAuthor = get_option('splithero_plugin_author', 'Split Hero');

	if (isset($plugins[$key]) && false !== 'Split Hero') {
		$plugins[$key]['Name'] = $splitHeroPluginName;
		$plugins[$key]['Description'] = $splitHeroPluginDescription;

		$plugins[$key]['Author'] = $splitHeroPluginAuthor;
		$plugins[$key]['AuthorName'] = $splitHeroPluginAuthor;

		$plugins[$key]['AuthorURI'] = $splitHeroDomain;
		$plugins[$key]['PluginURI'] = $splitHeroDomain;
	}

	return $plugins;
}

function splitheroConversionShortcode($attributes = [])
{
	$splitHeroDomain = 'https://app.splithero.com';
	$attributes = array_change_key_case((array) $attributes, CASE_LOWER);
	$js = null;

	if (isset($attributes['campaign'])) {
		$js .= '<script>fetch("' . $splitHeroDomain . '/api/conversion", { method: "post", headers: { "Content-Type": "application/json" }, mode: "cors", body: JSON.stringify({ campaign: ' . $attributes['campaign'] . ' }) });</script>';
	}

	return $js;
}

/**
 * Central location to create all shortcodes.
 */
function splitheroConversionShortcodeInit()
{
	add_shortcode('sh-convert', 'splitheroConversionShortcode');
}

add_action('init', 'splitheroConversionShortcodeInit');
