<?php

/*
 * Plugin Name: SplitHero
 * Author: SplitHero
 * Description: Split Testing for WordPress. Stop guessing and start testing.
 * Version: 1.4
 */

global $wpdb;

define('SPLITHERO_VERSION', '1.4');
define('SPLITHERO_ENDPOINT', 'https://app.splithero.com/api/');
define('SPLITHERO_GITHUB_ENDPOINT', 'csoutham/splithero-wordpress-plugin');
define('SPLITHERO_GITHUB_TOKEN', '8aef10c5b50f378c058f183f404fa1313fd16478');
define('SPLITHERO_DB_TABLE', $wpdb->prefix . 'splithero_campaigns');

// Unique identifier based on Device Name, IP Address and Device Language
preg_match('/\((.*?)\)/', $_SERVER['HTTP_USER_AGENT'], $matches, PREG_OFFSET_CAPTURE);
define('SPLITHERO_IDENTIFIER', md5($matches[0][0] . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_ACCEPT_LANGUAGE']));

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/puc/plugin-update-checker.php';
require __DIR__ . '/includes/tables.php';

/*
 * Activation and deactivation hooks
 * Create campaign tables on activate
 * Drop campaign tables on deactivate
 */
register_activation_hook(__FILE__, 'splitHeroCreateTables');
register_deactivation_hook(__FILE__, 'splitHeroDropTables');

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
	add_options_page('SplitHero', 'SplitHero', 'manage_options', 'splithero', 'splitheroShowSettings');
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
				'timeout' => 15,
				'blocking' => true,
				'sslverify' => false,
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
				echo '<p>Something went wrong: please check your API key is correct.</p>';
			} else {
				update_option('splithero_token', $splitHeroToken);
			}
		}
	}
	
	$splitHeroToken = get_option('splithero_token');
	
	include 'includes/settings.php';
	
	if ($splitHeroToken) {
		include 'includes/sync.php';
	}
	
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
				'timeout' => 15,
				'blocking' => true,
				'sslverify' => false,
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
				?><p>Pages/Posts synchronisation complete.</p><?php
			}
		}
	}
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
	global $wpdb;
	$table_name = SPLITHERO_DB_TABLE;
	
	if (get_option('splithero_token') !== $request->get_header('token')) {
		return rest_ensure_response('API token failure.');
	}
	
	// Loop through request body
	$campaign = unserialize($request->get_body_params()['campaign']);
	
	if ($campaign['status'] == 'running') {
		$wpdb->insert($table_name, [
			'campaignId' => $campaign['id'],
			'post1Id' => $campaign['post1_id'],
			'post2Id' => $campaign['post2_id'],
			'post3Id' => $campaign['post3_id'],
			'post4Id' => $campaign['post4_id'],
			'conversionId' => $campaign['conversion_id'],
			'created_at' => $campaign['created_at'],
		]);
	} else {
		$wpdb->delete($table_name, ['campaignId' => $campaign['id']]);
	}
	
	return rest_ensure_response('Campaigns added or updated successfully.');
}

/*
 * 302 redirects based on Campaign details
 */
add_action('init', 'splitHeroRedirects', 1);

function splitHeroRedirects()
{
	global $wpdb;
	$table_name = SPLITHERO_DB_TABLE;
	$splitHeroToken = get_option('splithero_token');
	
	// What was requested (strip out home portion, case insensitive)
	$request = str_ireplace(get_option('home'), '', splitHeroUtilityGetAddress());
	$request = rtrim($request, '/');
	
	// Don't allow people to accidentally lock themselves out of admin
	if (strpos($request, '/wp-login') !== 0 && strpos($request, '/wp-admin') !== 0) {
		if ($post = get_page_by_path($request, OBJECT)) {
			$postId = $post->ID;
			
			$result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE (post1Id = %d OR post2Id = %d OR post3Id = %d OR post4Id = %d OR conversionId = %d)", $postId, $postId, $postId, $postId, $postId));
			
			if ($result) {
				$request = wp_remote_post(SPLITHERO_ENDPOINT . 'log', [
						'method' => 'POST',
						'timeout' => 5,
						'blocking' => true,
						'sslverify' => false,
						'headers' => [
							'token' => $splitHeroToken
						],
						'body' => [
							'identifier' => SPLITHERO_IDENTIFIER,
							'campaignId' => $result->campaignId,
							'postId' => $postId
						]
					]
				);
				
				if (is_wp_error($request)) {
					// Ignore this, and render page
				} else {
					if ($request['response']['code'] !== 200) {
						// Ignore this, and render page
					} else {
						$response = json_decode($request['body']);
						
						if ($response->redirect != $postId) {
							header('HTTP/1.1 302 Moved Temporarily');
							header('Location: ' . get_permalink($response->redirect));
							exit();
						}
					}
				}
			}
		}
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