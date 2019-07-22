<?php

namespace SplitHero;

class Autooptimize
{
	var $name = 'Autoptimize',
		$url = 'https://wordpress.org/plugins/autoptimize/',
		$filters = array('fl_builder_init_ui');

	static function run()
	{
		if (class_exists('\autoptimizeCache')) {
			\autoptimizeCache::clearall();
		}
	}

	function filters()
	{
		add_filter('autoptimize_filter_noptimize', '__return_true');
	}
}

class Breeze
{
	var $name = 'Breeze',
		$url = 'https://wordpress.org/plugins/breeze/';

	static function run()
	{
		if (class_exists('\Breeze_PurgeCache')) {
			\Breeze_PurgeCache::breeze_cache_flush();
		}
	}
}

class Cacheenabler
{
	var $name = 'Cache Enabler',
		$url = 'https://wordpress.org/plugins/cache-enabler/';

	static function run()
	{
		if (class_exists('\Cache_Enabler')) {
			\Cache_Enabler::clear_total_cache();
		}
	}
}

class Fastest
{
	var $name = 'WP Fastest Cache',
		$url = 'https://wordpress.org/plugins/wp-fastest-cache/';

	static function run()
	{
		if (class_exists('\WpFastestCache')) {
			global $wp_fastest_cache;
			$wp_fastest_cache->deleteCache(true);
		}
	}
}

class Godaddy
{
	var $name = 'Godaddy Hosting';

	static function run()
	{
		if (class_exists('\WPaaS\Cache')) {
			if (method_exists('\WPaaS\Cache', 'ban')) {
				\WPaaS\Cache::ban();
			}
		}
	}
}

class Hummingbird
{
	var $name = 'Hummingbird Page Speed Optimization',
		$url = 'https://wordpress.org/plugins/hummingbird-performance/';

	static function run()
	{
		if (class_exists('\WP_Hummingbird_Utils') && class_exists('\WP_Hummingbird')) {
			if (\WP_Hummingbird_Utils::get_module('page_cache')->is_active()) {
				\WP_Hummingbird_Utils::get_module('page_cache')->clear_cache();
				\WP_Hummingbird_Module_Page_Cache::log_msg('Cache cleared by Beaver Builder.');
			}
		}
	}
}


class Kinsta
{
	var $name = 'Kinsta Hosting',
		$url = 'https://kinsta.com/';

	static function run()
	{
		global $kinsta_cache;

		if (class_exists('\Kinsta\CDN_Enabler') && is_object($kinsta_cache) && isset($kinsta_cache->kinsta_cache_purge)) {
			$kinsta_cache->kinsta_cache_purge->purge_complete_caches();
		}
	}
}

class Litespeed
{
	var $name = 'LiteSpeed Cache',
		$url = 'https://wordpress.org/plugins/litespeed-cache/';

	static function run()
	{
		if (class_exists('\LiteSpeed_Cache_API')) {
			\LiteSpeed_Cache_API::purge_all();
		}
	}
}

class Pagely
{
	var $name = 'Pagely Hosting',
		$url = 'https://pagely.com/plans-pricing/';

	static function run()
	{
		if (class_exists('\PagelyCachePurge')) {
			$purger = new \PagelyCachePurge();
			$purger->purgeAll();
		}
	}
}

class Pantheon
{
	var $name = 'Pantheon Hosting',
		$url = 'https://pantheon.io/';

	static function run()
	{
		if (function_exists('pantheon_wp_clear_edge_all')) {
			$ret = pantheon_wp_clear_edge_all();
		}
	}
}

class Siteground
{
	var $name = 'SiteGround Hosting',
		$url = 'https://wordpress.org/plugins/sg-cachepress/';

	static function run()
	{
		if (function_exists('\sg_cachepress_purge_cache')) {
			\sg_cachepress_purge_cache();
		}
	}
}

class Supercache
{
	var $name = 'WP Super Cache',
		$url = 'https://wordpress.org/plugins/wp-super-cache/';

	static function run()
	{
		if (function_exists('\wp_cache_clear_cache')) {
			\wp_cache_clear_cache();
		}
	}
}

class Swift
{
	var $name = 'Swift Performance',
		$url = 'https://wordpress.org/plugins/swift-performance-lite/';

	static function run()
	{
		if (class_exists('\Swift_Performance_Cache')) {
			\Swift_Performance_Cache::clear_all_cache();
		}
	}
}

class W3cache
{
	var $name = 'W3 Total Cache',
		$url = 'https://wordpress.org/plugins/w3-total-cache/';

	static function run()
	{
		if (function_exists('\w3tc_pgcache_flush')) {
			\w3tc_pgcache_flush();
		}
	}
}

class Wpengine
{
	var $name = 'WPEngine Hosting',
		$url = 'https://wpengine.com/';

	static function run()
	{
		if (class_exists('\WpeCommon')) {
			\WpeCommon::purge_memcached();
			\WpeCommon::clear_maxcdn_cache();
			\WpeCommon::purge_varnish_cache();
		}
	}
}