<?php

/*
 * Plugin Name: Split Hero
 * Author: Split Hero
 * Description: Split testing for WordPress. Stop guessing and start testing.
 * Version: 1.6.1
 */

global $wpdb;

define('SPLITHERO_VERSION', '1.6.1');
define('SPLITHERO_ENDPOINT', 'https://app.splithero.com/api/');
define('SPLITHERO_GITHUB_ENDPOINT', 'csoutham/splithero-wordpress-plugin');
define('SPLITHERO_GITHUB_TOKEN', '8aef10c5b50f378c058f183f404fa1313fd16478');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/cache.php';
require __DIR__ . '/includes/puc/plugin-update-checker.php';

/*
 * Plugin update checker
 * Via GitHub repo
 */
$updateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/' . SPLITHERO_GITHUB_ENDPOINT,
	__FILE__,
	'splithero'
);

$updateChecker->setAuthentication(SPLITHERO_GITHUB_TOKEN);
$updateChecker->setBranch('master');

/*
 * Menu options added
 * Under Settings
 */
add_action('admin_menu', 'splitheroMenu');

function splitheroMenu()
{
	add_options_page('Split Hero', 'Split Hero', 'manage_options', 'splithero', 'splitheroShowSettings');
}

function splitheroShowSettings()
{
	include 'includes/branding.php';

	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized user');
	}

	if (!empty($_POST['splithero_token'])) {
		$splitHeroToken = $_POST['splithero_token'];

		$request = wp_remote_post(SPLITHERO_ENDPOINT . 'token_check', [
				'method' => 'POST',
				'timeout' => 30,
				'blocking' => true,
				'headers' => [
					'token' => $splitHeroToken
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
			}
		}
	}

	$splitHeroToken = get_option('splithero_token'); ?>

    <table class="widefat striped" style="width: 98%;">
    <tbody>
    <tr>
        <td class="desc">
            <h3>Step 1</h3>
            <p>Enter your API key below and press Save.</p>

            <form method="post">
                <label for="API Token">Your API token</label>
                <br/>
                <input type="text" name="splithero_token" id="splithero_token" value="<?php echo $splitHeroToken; ?>"
                       size="40">
                <br/><br/>
                <input type="submit" value="Save" class="button button-primary">
                <br/><br/>
                <p>You can find your API token via <a href="https://app.splithero.com/domains" target="_blank">Split
                        Hero > Domains</a>.</p>
            </form>
        </td>

		<?php if ($splitHeroToken) { ?>
            <td class="desc">
                <h3>Step 2</h3>
                <p>Click the button below to sync your posts & pages to Split Hero.</p>

                <form method="post">
                    <input type="hidden" name="splithero_sync" value="true"/>
                    <input type="submit" value="Sync" class="button button-primary">
                </form>
            </td>
            <td class="desc">
                <h3>Step 3</h3>
                <p>Return to the Split Hero dashboard and proceed to create a campaign.</p>
                <a href="https://app.splithero.com/campaigns" class="button button-primary" target="_blank">Create a
                    campaign</a>
            </td><?php
		} ?>
    </tr>
    </tbody>
    </table><?php

	if (!empty($_POST['splithero_sync'])) {
		$sync['config'] = [
			'domain' => get_option('siteurl')
		];

		$posts = get_posts([
			'post_type' => 'any',
			'post_status' => 'any',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		]);

		foreach ($posts as $post) {
			if ($post->post_type !== 'attachment') {
				$sync['posts'][] = [
					'id' => $post->ID,
					'title' => $post->post_title,
					'type' => $post->post_type,
					'date' => $post->post_date,
					'url' => esc_url(get_permalink($post->ID)),
					'status' => $post->post_status
				];
			}
		}

		$request = wp_remote_post(SPLITHERO_ENDPOINT . 'sync', [
				'method' => 'POST',
				'timeout' => 30,
				'blocking' => true,
				'headers' => [
					'token' => $splitHeroToken
				],
				'body' => ['sync' => serialize($sync)]
			]
		);

		if (is_wp_error($request)) {
			$error_message = $request->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			if ($request['response']['code'] !== 200) {
				echo '<p>Something went wrong: please check your API key is correct.</p>';
			} else {
				splitheroCacheClear();

				?><h3>Pages/Posts synchronisation complete.</h3><?php
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
	// What was requested (strip out home portion, case insensitive)
	$request = str_ireplace(get_option('home'), '', splitHeroUtilityGetAddress());

	echo '<script src="' . SPLITHERO_ENDPOINT . 'js?r=' . site_url($request) . '"></script>';
}

/*
 * API endpoint created
 * Under WP REST API for injection of the campaign details
 */
add_action('rest_api_init', 'splitheroApiRoutes');

function splitheroApiRoutes()
{
	register_rest_route('splithero', 'campaigns', [
			'methods' => 'POST',
			'callback' => 'splitheroInsertUpdateCampaign'
		]
	);
}

function splitheroInsertUpdateCampaign(WP_REST_Request $request)
{
	// Nothing to do here
	// Left in to prevent old campaign errors
	// Will remove in v2.0
	return rest_ensure_response('Campaigns added or updated successfully.');
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
		$settings_link = '<a href="' . admin_url('options-general.php?page=splithero') . '">' . __('Settings', 'splithero') . '</a>';

		array_unshift($links, $settings_link);
	}

	return $links;
}