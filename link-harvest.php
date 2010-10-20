<?php
/*
Plugin Name: Link Harvest
Plugin URI: http://crowdfavorite.com/wordpress/plugins/link-harvest/
Description: This will harvest links from your WordPress database, creating a links list sorted by popularity. Once you have activated the plugin, you can configure your settings and see your <a href="index.php?page=link-harvest.php">list of links</a>. Also see <a href="options-general.php?page=link-harvest.php#aklh_template_tags">how to show the list of links</a> in your blog. Questions on configuration, etc.? Make sure to read the README.
Version: 1.3
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// Copyright (c) 2006-2010 
//   Crowd Favorite, Ltd. - http://crowdfavorite.com
//   Alex King - http://alexking.org
// All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress - http://wordpress.org
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

// ini_set('display_errors', '1'); ini_set('error_reporting', E_ALL);

if (!defined('AKLH_LOADED')) :

define('AKLH_LOADED', true);
define('AKLH_DEBUG', false);

if (AKLH_DEBUG) {
//	ini_set('display_errors', '1');
//	ini_set('error_reporting', E_ALL);
	$logfile = dirname(__FILE__).'/aklh_log.txt';
	if (!is_file($logfile) || !is_writeable($logfile)) {
		die(__('Debugging is enabled, but there is no wp-content/plugins/aklh_log.txt file or the file is not writeable.', 'link-harvest'));
	}
}
define('AKLH_VERSION', '1.3');

load_plugin_textdomain('link-harvest');

if (is_file(trailingslashit(WP_PLUGIN_DIR).'link-harvest.php')) {
	define('AKLH_FILE', trailingslashit(WP_PLUGIN_DIR).'link-harvest.php');
	define('AKLH_DIR_URL', trailingslashit(WP_PLUGIN_URL));
}
else if (is_file(trailingslashit(WP_PLUGIN_DIR).'link-harvest/link-harvest.php')) {
	define('AKLH_FILE', trailingslashit(WP_PLUGIN_DIR).'link-harvest/link-harvest.php');
	define('AKLH_DIR_URL', trailingslashit(WP_PLUGIN_URL).'link-harvest/');
}

define('CF_ADMIN_DIR', 'link-harvest/cf-admin/');
require_once('cf-admin/cf-admin.php');

function aklh_activate() {
 	if (aklh_is_multisite() && aklh_is_network_activation()) {
		aklh_activate_for_network();
	}
	else {
		aklh_activate_single();
	}
}
register_activation_hook(AKLH_FILE, 'aklh_activate');

function aklh_activate_single() {
 	global $wpdb;
 	$wpdb->ak_domains = $wpdb->prefix.'ak_domains';
 	$wpdb->ak_linkharvest = $wpdb->prefix.'ak_linkharvest';
 	$tables = $wpdb->get_col("
 		SHOW TABLES
 	");
 	if (!in_array($wpdb->ak_linkharvest, $tables) || !in_array($wpdb->ak_domains, $tables)) {
 		$aklh = new ak_link_harvest;
 		$aklh->install();
	}
}

function aklh_init() {
	global $aklh, $wpdb;
	$wpdb->ak_domains = $wpdb->prefix.'ak_domains';
	$wpdb->ak_linkharvest = $wpdb->prefix.'ak_linkharvest';
	
	$aklh = new ak_link_harvest;
	$aklh->get_settings();
	if (!is_admin()) {
		wp_enqueue_script('jquery');
		wp_enqueue_script('aklh_js', esc_url(site_url('index.php?ak_action=lh_js')), array('jquery'));
		wp_enqueue_style('aklh_css', esc_url(site_url('index.php?ak_action=lh_css')));
	}
}
add_action('init', 'aklh_init');

function aklh_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if (basename($file) == $plugin_file) {
		$settings_link = '<a href="'.esc_url(admin_url('options-general.php?page='.$plugin_file)).'">'.__('Settings', 'link-harvest').'</a>';
		array_unshift($links, $settings_link);
		$links_list_link = '<a href="'.esc_url(admin_url('index.php?page='.$plugin_file)).'">'.__('Links List', 'link-harvest').'</a>';
		array_unshift($links, $links_list_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'aklh_plugin_action_links', 10, 2);

class ak_link_harvest {
	var $exclude;
	var $table_length;
	var $harvest_enabled;
	var $options;
	var $default_limit;
	var $timeout;
	var $token;
	var $credit;
	var $show_num_links;
	
	function ak_link_harvest() {
		$this->exclude = array();
		$this->table_length = 100;
		$this->harvest_enabled = 1;
		$this->default_limit = 5;
		$this->timeout = 2;
		$this->token = 0;
		$this->credit = 1;
		$this->options = array(
			'exclude' => 'explode',
			'table_length' => 'int',
			'harvest_enabled' => 'int',
			'token' => 'int',
			'credit' => 'int',
			'show_num_links' => 'int'
		);
		$this->excluded_file_extensions = array(
			'.mov',
			'.jpg',
			'.gif',
			'.png',
			'.pdf',
			'.mpg',
			'.mp3',
			'.mpeg',
			'.avi',
			'.swf',
			'.doc',
			'.xls',
			'.wmv',
			'.wmf',
			'.wma',
			'.txt',
			'.m4p'
		);
	}
	
	function install() {
		global $wpdb;
		$tables = $wpdb->get_col("
			SHOW TABLES
		");
		if (!in_array($wpdb->ak_linkharvest, $tables)) {
			$result = $wpdb->query("
				CREATE TABLE `$wpdb->ak_domains` (
				`id` int(11) NOT NULL auto_increment,
				`domain` varchar(255) NOT NULL,
				`title` varchar(255) NULL,
				`count` int(11) NOT NULL default '0',
				PRIMARY KEY  (`id`),
				UNIQUE KEY `domain` (`domain`)
				)
			");
		}
		
		if (!in_array($wpdb->ak_domains, $tables)) {
			$result = $wpdb->query("
				CREATE TABLE `$wpdb->ak_linkharvest` (
				`id` int(11) NOT NULL auto_increment,
				`post_id` int(11) NOT NULL,
				`url` text NOT NULL,
				`title` varchar(255) NULL,
				`domain_id` varchar(255) NOT NULL,
				`modified` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `post_id` (`post_id`),
				KEY `domain_id` (`domain_id`)
				)
			");
		}
		add_option('aklh_exclude', '');
		add_option('aklh_table_length', '50');
		add_option('aklh_harvest_enabled', '1');
		add_option('aklh_token', '0');
		add_option('aklh_show_num_links', '1');
		add_option('aklh_credit', '1');
	}
	
	function clear_data() {
		global $wpdb;
		$wpdb->query("
			TRUNCATE TABLE $wpdb->ak_domains
		");
		$wpdb->query("
			TRUNCATE TABLE $wpdb->ak_linkharvest
		");
	}
	
	function get_settings() {
		foreach ($this->options as $option => $type) {
			$this->$option = get_option('aklh_'.$option);
			switch ($type) {
				case 'explode':
					$this->$option = explode(' ', $this->$option);
					break;
				case 'int':
					$this->$option = intval($this->$option);
					break;
			}
		}
	}
	
	function update_settings() {
		$this->install();
		foreach ($this->options as $option => $type) {
			if (isset($_POST[$option])) {
				switch ($type) {
					case 'explode':
						$value = stripslashes($_POST[$option]);
						break;
					case 'int':
						$value = intval($_POST[$option]);
						break;
					default:
						$value = stripslashes($_POST[$option]);
				}
				update_option('aklh_'.$option, $value);
			}
		}
	}
	
	function excluded_file_type($link) {
		$excluded_file_extensions = $this->excluded_file_extensions;
		$excluded_file_extensions = apply_filters('aklh_excluded_file_type', $excluded_file_extensions);
		foreach ($excluded_file_extensions as $ext) {
			if (substr($link, strlen($ext) * -1) == $ext) {
				return true;
			}
		}
		return false;
	}
	
	function get_links($body) {
// From: http://www.onaje.com/php/article.php4/46
		$href_regex ="<";            // 1 start of the tag
		$href_regex .="\s*";         // 2 zero or more whitespace
		$href_regex .="a";           // 3 the a of the <a> tag itself
		$href_regex .="\s+";         // 4 one or more whitespace
		$href_regex .="[^>]*";       // 5 zero or more of any character that is _not_ the end of the tag
		$href_regex .="href";        // 6 the href bit of the tag
		$href_regex .="\s*";         // 7 zero or more whitespace
		$href_regex .="=";           // 8 the = of the tag
		$href_regex .="\s*";         // 9 zero or more whitespace
		$href_regex .="[\"']?";      // 10 none or one of " or '
		$href_regex .="(";           // 11 opening parenthesis, start of the bit we want to capture
		$href_regex .="[^\"' >]+";   // 12 one or more of any character _except_ our closing characters
		$href_regex .=")";           // 13 closing parenthesis, end of the bit we want to capture
		$href_regex .="[\"' >]";     // 14 closing chartacters of the bit we want to capture
		
		$regex  = "/";         // regex start delimiter
		$regex .= $href_regex; //
		$regex .= "/";         // regex end delimiter
		$regex .= "i";         // Pattern Modifier - makes regex case insensative
		$regex .= "s";         // Pattern Modifier - makes a dot metacharater in the pattern 
					// match all characters, including newlines
		$regex .= "U";         // Pattern Modifier - makes the regex ungready

		$urls = array();

		if (preg_match_all($regex, $body, $links)) {
			foreach ($links[1] as $link) {
				if (in_array(substr($link, 0, 7), array('http://', 'https:/')) && !$this->excluded_file_type($link)) {
					$urls[] = trim($link);
				}
			}
		}
		return $urls;
	}
	
	function get_num_links($url) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare("
			SELECT COUNT(*)
			FROM $wpdb->ak_linkharvest
			WHERE url = %s",
			$url
		));
		if ($count === false) {
			return 0;
		}
		return $count;
	}
	
	function get_domain_id($domain) {
		global $wpdb;
		$domain_id = $wpdb->get_var( $wpdb->prepare("
			SELECT id
			FROM $wpdb->ak_domains
			WHERE domain = %s",
			$domain
		));
		if ($domain_id && !is_null($domain_id)) {
			return $domain_id;
		}
		else {
			$id = $this->add_domain($domain);
			if ($id != false) {
				return $id;
			}
		}
		return false;
	}

	function get_domain_ids($domains) {
		global $wpdb;
		if (!is_array($domains) || count($domains) == 0) {
			return false;
		}
		$domain_ids = array();
		$results = $wpdb->get_results("
			SELECT id, domain
			FROM $wpdb->ak_domains
			WHERE domain IN ('".implode("', '", $wpdb->escape($domains))."')"
		);

		if ($results && count($results) > 0) {
			foreach ($results as $data) {
				$domain_ids[$data->domain] = $data->id;
			}
		}
		$missing = array();
		foreach ($domains as $domain) {
			if (!isset($domain_ids[$domain])) {
				$domain_ids[$domain] = $this->add_domain($domain);
			}
		}
		return $domain_ids;
	}
	
	function process_content($content, $post_id) {

		$links = $this->get_links($content);
		if ($links == false) {
			return;
		}
		aklh_log(sprintf(__('Found %s links in post id: %s.', 'link-harvest'), count($links), $post_id));

		$domains = array();
		$domain_count = array();
		$harvest = array();
		
		foreach ($links as $link) {
// FeedBurner hack, working around this:
// http://alexking.org/blog/2006/12/01/why-i-dont-use-feedburner
			if (!empty($link)) {
				if (strstr($link, '/feeds.feedburner.com/') || strstr($link, '/~r/')) {
					require_once(ABSPATH.WPINC.'/class-snoopy.php');
					$snoop = new Snoopy;
					$snoop->maxlength = 2000;
					$snoop->read_timeout = $this->timeout;
					$snoop->fetch($link);
					if (!empty($snoop->lastredirectaddr)) {
						$link = $snoop->lastredirectaddr;
					}
				}
				$domain = $this->get_domain($link);
				if (!empty($domain)) {
					if (isset($domain_count[$domain])) {
						$domain_count[$domain]++;
					}
					else {
						$domains[] = $domain;
						$domain_count[$domain] = 1;
					
					}
					$harvest[] = array($link, $domain);
				}
				aklh_log(sprintf(__('Processed link: %s', 'link-harvest'), $link));

			}
			else {
				aklh_log(sprintf(__('Skipped link: %s', 'link-harvest'), $link));
			}
		}
		
		if (count($domains) > 0) {
			$domain_ids = $this->get_domain_ids($domains);

			foreach ($harvest as $data) {
				$link = $data[0];
				$domain = $data[1];
				$this->add_link($link, $domain_ids[$domain], $post_id);
			}
			
			foreach ($domain_ids as $domain => $domain_id) {
				$this->set_domain_counter(null, $domain_id, $domain_count[$domain], '+');
			}
		}
	}
	
	function add_link($url, $domain_id, $post_id) {
		aklh_log(sprintf(__('About to add link for post id: %s - %s', 'link-harvest'), $post_id, $url));
		global $wpdb;
		$title = stripslashes($this->get_page_title($url));
		$result = $wpdb->insert(
			$wpdb->ak_linkharvest,
			array(
				'post_id' => $post_id,
				'url' => $url,
				'title' => $title,
				'domain_id' => $domain_id,
				'modified' => current_time('mysql', 1)
			)
		);
		if (!$result) {
			aklh_log(sprintf(__('Failed to add link for post id: %s - %s', 'link-harvest'), $post_id, $url));
			return false;
		}
		else {
			aklh_log(sprintf(__('Added link for for post id:  %s - %s', 'link-harvest'), $post_id, $url));
			return true;
		}
	}
	
	function add_domain($domain) {
		global $wpdb;
		$title = stripslashes($this->get_page_title('http://'.$domain));
		$result = $wpdb->insert(
			$wpdb->ak_domains,
			array(
				'domain' => $domain,
				'count' => '0',
				'title' => $title
			)
		);
		if (!$result) {
			aklh_log(sprintf(__('Failed to add domain: %s', 'link-harvest'), $domain));
			return false;
		}
		else {
			aklh_log(sprintf(__('Added domain: %s', 'link-harvest'), $domain));
			return mysql_insert_id();
		}
	}
	
	function set_domain_counter($domain = null, $domain_id = null, $count, $mod = '+') {
		global $wpdb;
		if (!is_null($domain)) {
			$where = $wpdb->prepare("WHERE domain = %s", $domain);
		}
		else if (!is_null($domain_id)) {
			$where = $wpdb->prepare("WHERE id = %d", $domain_id);
		}
		if (isset($where)) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_domains
				SET count = count $mod %d
				$where",
				$count
			));
			$domain_count = $wpdb->get_var("
				SELECT count 
				FROM $wpdb->ak_domains
				$where
			");
			if ($domain_count < 1) {
				$wpdb->query("
					DELETE 
					FROM $wpdb->ak_domains
					$where
				");
			}
			if ($result != false) {
				return true;
			}
		}
		return false;
	}
	
	function harvest_posts($post_ids = array(), $start = 0, $limit = null, $delete_old = false) {
		global $wpdb;
		if (is_array($post_ids) && count($post_ids) > 0) {
			if ($delete_old) {
				foreach ($post_ids as $post_id) {
					$links = $wpdb->get_results( $wpdb->prepare("
						SELECT *
						FROM $wpdb->ak_linkharvest
						WHERE post_id = %d",
						$post_id
					));
					if (count($links) > 0) {
						$domains = array();
						foreach ($links as $link) {
							if (!isset($domains[$link->domain_id])) {
								$domains[$link->domain_id] = 1;
							}
							else {
								$domains[$link->domain_id]++;
							}
						}
						if (count($domains) > 0) {
							foreach ($domains as $id => $count) {
								$this->set_domain_counter(null, $id, $count, $mod = '-');
							}
						}
						$delete = $wpdb->query( $wpdb->prepare("
							DELETE
							FROM $wpdb->ak_linkharvest
							WHERE post_id = %d",
							$post_id
						));
					}
				}
			}
			$posts = $wpdb->get_results("
				SELECT *
				FROM $wpdb->posts
				WHERE (
					post_status = 'publish'
					OR post_status = 'static'
				)
				AND ID IN (".implode(',', $wpdb->escape($post_ids)).")
			");
		}
		else {
			if ($start == 0) {
				$this->clear_data();
			}
			if (is_null($limit)) {
				$limit = $this->default_limit;
			}
			$post_types = aklh_public_post_types();
			$pt_statement = '';
			
			$pt_statement = "AND post_type IN ('".implode('\', \'', $wpdb->escape($post_types))."') ";
			$posts = $wpdb->get_results( $wpdb->prepare("
				SELECT *
				FROM $wpdb->posts
				WHERE (
					post_status = 'publish'
					OR post_status = 'static'
				)
				AND (
					post_content LIKE '%%http://%%'
					OR post_content LIKE '%%https://%%'
				) 
				$pt_statement
				ORDER BY ID
				LIMIT %d, %d",
				$start, $limit
			));
		}		

		if (count($posts)) {
			foreach ($posts as $post) {
				if (function_exists('wp_is_post_revision') && wp_is_post_revision($post->ID)) {
					continue;
				}

				aklh_log(sprintf(__('== Start processing post id: %s', 'link-harvest'), $post->ID));
				$this->process_content($post->post_content, $post->ID);
				$external = get_post_meta($post->ID, 'external_link', true);
				if (!empty($external)) {
					$this->process_content($external, $post->ID);
				}
				$via = get_post_meta($post->ID, 'via_link', true);
				if (!empty($via)) {
					$this->process_content($via, $post->ID);
				}

				aklh_log(sprintf(__("== Done processing post id: %s\n", 'link-harvest'), $post->ID));
			}
		}
	}
	
	function post_update($post_id) {
		$this->harvest_posts(array($post_id), 0, null, true);
		
	}
	
	function post_delete($post_id) {
		global $wpdb;
		$links = $wpdb->get_results( $wpdb->prepare("
			SELECT *
			FROM $wpdb->ak_linkharvest
			WHERE post_id = %d",
			$post_id
		));
		$urls = array();
		foreach ($links as $link) {
			$urls[] = $link->url;
		}
		$domains = $this->domain_counts($urls);
		foreach ($domains as $domain => $count) {
			$this->set_domain_counter($domain, null, $count, $mod = '-');
		}
		$wpdb->get_results( $wpdb->prepare("
			DELETE
			FROM $wpdb->ak_linkharvest
			WHERE post_id = %d",
			$post_id
		));
	}
	
	function domain_counts($links) {
		$domain_counts = array();
		foreach ($links as $link) {
			$domain = $this->get_domain($link);
			if (!empty($domain)) {		
				if (isset($domain_counts[$domain])) {
					$domain_counts[$domain]++;
				}
				else {
					$domain_counts[$domain] = 1;
				}
			}
		}
		return $domain_counts;
	}
	
	function get_domain($link) {
		$domain = '';
		$domain = str_replace(array('http://', 'https://', 'www.'), array(), $link);
		$end = strpos($domain, '/');
		if ($end === false) {
			$end = strlen($domain);
		}
		$domain = substr($domain, 0, $end);
		return $domain;
	}
	
	function get_page_title($url) {
// getting web page title code found here
// http://www.drquincy.com/resources/tutorials/webserverside/getremotewebpageinfo/#complete
		$title = '';
		require_once(ABSPATH.WPINC.'/class-snoopy.php');
		$snoop = new Snoopy;
		$snoop->maxlength = 2000;
		$snoop->read_timeout = $this->timeout;
		$snoop->fetch($url);

		$start = '<title>';
		$end = '<\/title>';
		preg_match("/$start(.*)$end/si", $snoop->results, $match);
		if (isset($match[1])) {
			$title = $match[1];
			$title = str_replace("\r", "\n", $title);
			if (strstr($title, "\n")) {
				$parts = explode("\n", $title);
				$title = $parts[0];
			}
			$title = strip_tags($title);
			$title = wp_filter_kses(trim($title));
		}

		return $title;
	}
	function sql_domain_exclude() {
		global $wpdb;
		return "WHERE domain NOT IN ('".implode('\', \'', $wpdb->escape($this->exclude))."') ";
	}
	
	function show_harvest($limit = 50, $type = 'table') {
		global $wpdb;
		$i = 1;
		switch ($type) {
			case 'table':
			if (is_admin()) {
				$per_page = 20;
				$pagenum = isset($_GET['paged']) ? absint($_GET['paged']) : 0;
				if (empty($pagenum)) $pagenum = 1;
				$offset = ($pagenum - 1) * $per_page;
				$domain_excludes = $this->sql_domain_exclude();
				$domains = $wpdb->get_results( $wpdb->prepare("
					SELECT SQL_CALC_FOUND_ROWS *
					FROM $wpdb->ak_domains
					$domain_excludes
					ORDER BY count DESC
					LIMIT %d, %d",
					$offset, $per_page
				));
				$overall_count = $wpdb->get_var("SELECT FOUND_ROWS()");
				if ($overall_count > 0) {
					$num_pages = ceil($overall_count / $per_page);
					$page_links = paginate_links(array(
						'base' => add_query_arg('paged', '%#%'),
						'format' => '',
						'prev_text' => __('&laquo;'),
						'next_text' => __('&raquo;'),
						'total' => $num_pages,
						'current' => $pagenum
					));
					if ($page_links) {
						echo '<div class="tablenav"><div class="tablenav-pages">';
						$page_links_text = sprintf('<span class="displaying-num">'.__('Displaying %s&#8211;%s of %s', 'link0harvest').'</span>%s',
							number_format_i18n(($pagenum-1) * $per_page+1),
							number_format_i18n(min($pagenum * $per_page, $overall_count)),
							number_format_i18n($overall_count),
							$page_links
						);
						echo $page_links_text.'</div><div class="clear"></div></div>';
					}
				}
				echo '<table class="widefat post aklh_harvest">';
			}
			else {
				$domain_excludes = $this->sql_domain_exclude();
				$domains = $wpdb->get_results( $wpdb->prepare("
					SELECT *
					FROM $wpdb->ak_domains
					$domain_excludes
					ORDER BY count DESC
					LIMIT %d", 
					$limit
				));
				echo '<table class="aklh_harvest">';
			}
				echo('
	<thead>
		<tr>
			<th>'.__('Web Site', 'link-harvest').'</th>
			<th>'.__('# of Links', 'link-harvest').'</th>
			<th><span class="hide">'.__('Show Links', 'link-harvest').'</span></th>
			<th><span class="hide">'.__('Show Posts', 'link-harvest').'</span></th>
		</tr>
	</thead>
	<tbody>
				');
				if (count($domains) == 0) {
					echo ('
		<tr><td colspan="4">'.sprintf(__('No links harvested yet. (<a href="%s">Start Harvesting</a>)', 'link-harvest'), admin_url('options-general.php?ak_action=harvest')).'</td></tr>
					');
				}
				else {
					foreach ($domains as $domain) {
						if (!empty($domain->title)) {
							$title = $domain->title.' ('.$domain->domain.')';
						}
						else {
							$title = '('.$domain->domain.')';
						}
						if ($i % 2 == 0) {
							$class = ' class="alternate"';
						}
						else {
							$class = '';
						}
						echo ('
		<tr id="'.esc_attr('aklh_table_row_'.$domain->id).'"'.$class.'>
			<td><a href="'.esc_url('http://'.$domain->domain).'">'.esc_html($title).'</a>
				<div id="'.esc_attr('domain_'.$domain->id).'">
					<div class="'.esc_attr('aklh-links-list-'.$domain->id).'" style="display:none;"></div>
					<div class="'.esc_attr('aklh-posts-list-'.$domain->id).'" style="display:none;"></div>
				</div>
			</td>
			<td class="count">'.esc_html($domain->count).'</td>
			<td class="action"><a href="javascript:void(aklh_show_for_domain(\''.esc_js($domain->id).'\', \'links\'));">'.__('Show Links', 'link-harvest').'</td>
			<td class="action"><a href="javascript:void(aklh_show_for_domain(\''.esc_js($domain->id).'\', \'posts\'));">'.__('Show Posts', 'link-harvest').'</td>
		</tr>
						');
						$i++;
					}
				}
				echo ('
	</tbody>
</table>
				');
				break;
			case 'list':
				$domain_excludes = $this->sql_domain_exclude();
				$domains = $wpdb->get_results( $wpdb->prepare("
					SELECT *
					FROM $wpdb->ak_domains
					$domain_excludes
					ORDER BY count DESC
					LIMIT %d",
					$limit
				));
				if (count($domains)) {
					$items = array();
					foreach ($domains as $domain) {						
						if (!empty($domain->title)) {
							$title = esc_html($domain->title).' ('.$domain->domain.')';
						}
						else {
							$title = '('.$domain->domain.')';
						}
						$items[$domain->title]['id'] = $domain->id;
						$items[$domain->title]['url'] = 'http://'.$domain->domain;
						$items[$domain->title]['count'] = $domain->count;
						$items[$domain->title]['title'] = $domain->title.' ('.$domain->domain.')';
					}
					echo ('
	<h3>'.__('List of External Links', 'link-harvest').':</h3>
	<ul class="aklh_harvest">'.$this->get_list_nodes($items).'</ul>
					');
				}
				else {
					__e('No Links have been Harvested', 'link-harvest');
				}
					break;
		}
	}
	function get_list_nodes($items) {
		global $wpdb;
		$output = '';
		foreach ($items as $item) {
			$count_markup ='';
			if (get_option('aklh_show_num_links')) {
				$count_markup = '('.esc_html($item['count']).') ';
			}
			$output .= '<li>'.$count_markup.'<a href="'.esc_url($item['url']).'"'.$data.'>'.esc_html($item['title']).'</a></li>'."\n";
		}
		return $output;
	}
	
	function get_list_nodes_posts($domain_id) {
		global $wpdb;
		$posts = $wpdb->get_results( $wpdb->prepare("
			SELECT p.*
			FROM $wpdb->posts p
			LEFT JOIN $wpdb->ak_linkharvest lh
			ON p.ID = lh.post_id
			WHERE lh.domain_id = %s
			AND (
				post_status = 'publish'
				OR post_status = 'static'
			)
			GROUP BY p.ID
			ORDER BY p.post_date DESC",
			$domain_id
		));
			
		$output .= '';
		if (count($posts) > 0) {
			global $post;
			foreach ($posts as $post) {
				if (get_the_title($post->ID) == '') {
					$title = get_permalink($post->ID);
				}
				else {
					$title = get_the_title($post->ID);
				}
				$output .= '<li><a href="'.get_permalink($post->ID).'">'.esc_html($title).'</a> ('.get_the_time('Y-m-d').')</li>';
			}
		}
		else {
			$output .= '<li>'.__('(none found)', 'link-harvest').'</li>';
		}
		
		return $output;
	}
	
	function options_form() {
		$token_options = '
			<option value="1" '.selected($this->token, '1', false).'>'.__('Yes', 'link-harvest').'</option>
			<option value="0" '.selected($this->token, '0', false).'>'.__('No', 'link-harvest').'</option>	
		';
		$credit_options = '
			<option value="1" '.selected($this->credit, '1', false).'>'.__('Yes', 'link-harvest').'</option>
			<option value="0" '.selected($this->credit, '0', false).'>'.__('No', 'link-harvest').'</option>
		';
		$show_num_options = '
			<option value="1" '.selected($this->show_num_links, '1', false).'>'.__('Yes', 'link-harvest').'</option>
			<option value="0" '.selected($this->show_num_links, '0', false).'>'.__('No', 'link-harvest').'</option>
		';
		
		echo('
<div id="cf" class="wrap">
	<div id="cf-header">	
		');
		CF_Admin::admin_header(__('Link Harvest Options', 'link-harvest'), 'Link Harvest', AKLH_VERSION, 'link-harvest');
		CF_Admin::admin_tabs(array(__('Options', 'link-harvest'), __('Harvest Links', 'link-harvest'), __('Usage', 'link-harvest') ));

		echo('
	</div>
	
	<div class="cf-tab-content-1 cf-content cf-hidden">
		<form name="ak_linkharvest" action="'.admin_url('options-general.php').'" method="post" class="cf-form">
			<fieldset class="cf-lbl-pos-left">
			<legend>Domains</legend>
			<p>'.__('You may want to exclude certain domains from your harvested links. For example, you may have a number of links to pages/posts on your own site and including your own links in the harvest will make your own site appear in your <a href="index.php?page=link-harvest.php">links list</a>.', 'link-harvest').'</p>
				<div class="cf-elm-block cf-elm-width-500">
					<label for="exclude">'.__('Domains to exclude', 'link-harvest').'</label>
					<textarea name="exclude" class="cf-elm-textarea" id="exclude">'.htmlspecialchars(implode(' ', $this->exclude)).'</textarea>
					<span class="cf-elm-help cf-elm-align-bottom">'.__('(separated by spaces, example: <code>example.com','link-harvest').' '.$this->get_domain(get_bloginfo('home')).' google.com</code></span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label class="cf-lbl-text" for="table_length">'.__('Number of domains to show:', 'link-harvest').'</label>
					<input class="cf-elm-text" type="text" size="5" name="table_length" id="table_length" value="'.esc_html($this->table_length).'" />
				</div>
				</fieldset>
				<fieldset class="cf-lbl-pos-left">
				<legend>Harvest Actions</legend>
				<p>'.__('Once the initial link harvest is complete, it is a good idea to disable link harvesting as it can be resource intensive for your server. This is done for you automatically after a successful harvest, but you can manually control it here.', 'link-harvest').'</p>
				<div class="cf-elm-block cf-has-radio">
				<p class="cf-lbl-radio-group"> Link Harvest Actions</p>
				<ul>
					<li><input class="cf-elm-radio" type="radio" name="harvest_enabled" value="1" id="harvest_enabled_y" '.checked($this->harvest_enabled, '1', false).'/> <label class="cf-lbl-radio" for="harvest_enabled_y"><strong>Enable</strong></label>
					</li>
					<li><input class="cf-elm-radio" type="radio" name="harvest_enabled" value="0" id="harvest_enabled_n" '.checked($this->harvest_enabled, '0', false).'/> <label class="cf-lbl-radio" for="harvest_enabled_n"><strong>Disable</strong></label>
					</li>
				</ul>	
				</div>						
				</fieldset>
				<fieldset class="cf-lbl-pos-left">
					<legend>Link Count Display</legend>
					<p>'.__('Display the number of times a specific URL has been used in the Link List table', 'link-harvest').'</p> 
					<div class="cf-elm-block cf-elm-width-300">
						<label class="cf-lbl-select" for="aklh_show_num_links">'.__('Show number of links', 'link-harvest').'</label>
						<select class="cf-elm-select" name="show_num_links" id="aklh_show_num_links">'.$show_num_options.'</select>
					</div>
				</fieldset>
				<fieldset class="cf-lbl-pos-left">
					<legend>Credit</legend>
					<p>'.__('This displays a link harvest plugin link below your link list', 'link-harvest').'</p> 
					<div class="cf-elm-block cf-elm-width-300">
						<label class="cf-lbl-select" for="aklh_credit">'.__('Display credit link', 'link-harvest').'</label>
						<select class="cf-elm-select" name="credit" id="aklh_credit">'.$credit_options.'</select>
					</div>
				</fieldset>
				<fieldset class="cf-lbl-pos-left">
				<legend>Token Method</legend>
				<p>'.__('The token method has been deprecated as of version 1.3, please use shortcodes when displaying your links list on a page or post', 'link-harvest').'</p> 
				<div class="cf-elm-block cf-elm-width-300">
					<label class="cf-lbl-select" for="aklh_token">'.__('Enable token method', 'link-harvest').'</label>
					<select class="cf-elm-select" name="token" id="aklh_token">'.$token_options.'</select>
				</div>
				<input type="hidden" name="ak_action" value="update_linkharvest_settings" />
			</fieldset>'
			.wp_nonce_field('link-harvest' , 'link-harvest-settings-nonce', true, false).wp_referer_field(false).
			'<p class="submit">
				<input class="button-primary" type="submit" name="submit" value="'.__('Save Changes', 'link-harvest').'" />
			</p>
		</form>
	</div>
	
	<div class="cf-tab-content-2 cf-content cf-hidden">
		<form class="cf-form">
			<fieldset>
				<p>'.__('When you are ready to harvest (or re-harvest) your links, press this button', 'link-harvest').'</p>
				<p class="submit">
					<input type="button" name="recount" value="'.__('(Re) Harvest All Links', 'link-harvest').'" onclick="if (jQuery(\'#harvest_enabled_y:checked\').size()) { location.href=\''.admin_url('options-general.php?ak_action=harvest').'\'; } else { alert(\''.__('Please enable link harvesting, save your settings, then try again.', 'link-harvest').'\'); }" />
				</p>					
			</fieldset>
		</form>
		<h3>'.__('Backfill Empty Titles', 'link-harvest').'</h3>
		<p>'.__('If a few domains or pages did not get proper titles the first time around, you can try filling them in here.', 'link-harvest').'</p>
		<ul style="margin-bottom: 40px;">
			<li><a href="'.admin_url('options-general.php?ak_action=backfill_domains').'">'.__('Backfill empty domain titles', 'link-harvest').'</a></li>
			<li><a href="'.admin_url('options-general.php?ak_action=backfill_pages').'">'.__('Backfill empty page titles', 'link-harvest').'</a></li>
		</ul>
	</div>
	
	<div class="cf-tab-content-3 cf-content cf-hidden">
		<div id="aklh_template_tags">
			<h3>'.__('Shortcode', 'link-harvest').'</h3>
			<p>'.__('Use the shortcode <code>[linkharvest]</code> on any post or page to display links list on that post or page.</p><p> To set the limit of links to display, add the limit parameter: <code>[linkharvest limit=&quot;10&quot;]</code>.</p><p> To display the link lists in a table format instead of a list format, add the type parameter: <code>[linkharvest limit=&quot;10&quot; type=&quot;table&quot;]</code>','link-harvest').'</p>
			<h3>'.__('Template Tag Method', 'link-harvest').'</h3>
			<p>'.__('You can always add a template tag to your theme (in a page template perhaps) to show your links list.', 'link-harvest').'</p>
			<dl>
				<dt><code>aklh_show_harvest($limit = 10, $type = &quot;table&quot; or &quot;list&quot;)</code></dt>
				<dd>
					<p>'.__('Put this tag outside of <a href="http://codex.wordpress.org/The_Loop">The Loop</a> (perhaps in your sidebar?) to show a list (like the archives/categories/links list) of the sites you link to most. All arguments are optional, the defaults are included in the example above.', 'link-harvest').'</p>
					<p>Examples:</p> 
					<ul>
						<li><code>&lt;?php aklh_show_harvest(50); ?></code></li>
						<li><code>&lt;?php aklh_show_harvest(50, \'list\'); ?></code></li>
					</ul>
				</dd>
			</dl>
		</div> <!-- #aklh_template_tags -->
	</div> <!-- # -->
</div> <!--#cf-->
	');
	
	CF_Admin::callouts('link-harvest');

	}
	
	function show_processing_page($type) {
		global $wpdb;
		switch ($type) {

			case 'harvest':
				$count = $wpdb->get_var("
					SELECT count(ID)
					FROM $wpdb->posts
					WHERE (
						post_status = 'publish'
						OR post_status = 'static'
					)
					AND (
						post_content LIKE '%http://%'
						OR post_content LIKE '%https://%'
						)
				");
				$js = '
	var count = '.esc_js($count).';
	function harvest_links(start, limit) {
		jQuery("#submit").hide();
		jQuery("#progress, #cancel").show();
		jQuery("#count").load(
			"'.admin_url('options-general.php').'",
			{
				"ak_action": "harvest_posts",
				"start": start,
				"limit": limit
			},
			harvest_progress
		);
	}
	function harvest_progress() {
		var progress = jQuery("#count").html();
		if (progress == "") {
			harvest_error();
			return;
		}
		var processed_count = parseInt(progress);
		if (processed_count > 0 && processed_count < count) {
			harvest_links(processed_count, '.esc_js($this->default_limit).');
		}
		else if (processed_count >= count) {
			jQuery("#progress, #cancel").hide();
			jQuery("#complete").show();
		}
		else {
			harvest_error();
			return;
		}
	}
	function harvest_error() {
		jQuery("#progress, #cancel").hide();
		jQuery("#complete").show();
	}
				';
				$body = '
		<h1>'.__('Harvest Links', 'link-harvest').'</h1>
				';
				if ($count == 0) {
					$body .= '
		<p>'.sprintf(__('Oops, didn\'t find any links to harvest. Go <a href="%s">back</a> to the settings page.', 'link-harvest'), admin_url('options-general.php?page=link-harvest.php')).'</p>
					';
				}
				else {
					$body .= '
		<p>'.__('Harvesting links from your posts (including pages and custom post types) can take a little while. Once you click the <strong>Start Link Harvest</strong> button below, all of your posts will be scanned for links and those links will be pulled out and stored in the Link Harvest database so we can do cool things with them for you.', 'link-harvest').'</p>
		<p>'.__('Note: This is a one time step, future posts will be added to the Link Harvest as you create them (and removed if you delete them).', 'link-harvest').'</p>
		<p id="progress" class="center">'.__('Processed: ', 'link-harvest').'<strong><span id="count">0</span> / '.esc_html($count).'</strong> '.__('posts.', 'link-harvest').'</p>
		<form action="#" method="get" onsubmit="return false;">
			<fieldset>
				<legend>'.__('Harvest Links', 'link-harvest').'</legend>
				<p id="submit" class="center"><input type="button" name="harvest_button" value="'.__('Start Link Harvest', 'link-harvest').'" onclick="harvest_links(0, '.esc_js($this->default_limit).'); return false;" /></p>
				<p id="cancel" class="center"><input type="button" name="cancel_button" value="'.__('Cancel', 'link-harvest').'" onclick="location.href=\''.admin_url('options-general.php?page=link-harvest.php').'\';" />
			</fieldset>
		</form>
		<p id="complete" class="center">'.__('The link harvest has completed successfully. View your <a href="'.admin_url('index.php?page=link-harvest.php').'">links list</a>.', 'link-harvest').'</p>
		<p id="error" class="center">'.__('The link harvest failed, make sure you have harvesting enabled in your <a href="'.admin_url('options-general.php?page=link-harvest.php').'">options</a>.', 'link-harvest').'</p>
		<p class="center"><a href="'.admin_url('options-general.php?page=link-harvest.php').'">'.__('Back to WordPress Admin', 'link-harvest').'</a></p>
					';
				}
				break;

			case 'backfill_domains':
				$count = $wpdb->get_var("
					SELECT count(id)
					FROM $wpdb->ak_domains
					WHERE title = ''
				");
				$js = '
	function backfill_titles(last, limit) {
		jQuery("#submit").hide();
		jQuery("#progress, #cancel").show();
		var url = "'.admin_url('options-general.php').'";
		var pars = "ak_action=backfill_domain_titles&last=" + last + "&limit=" + limit;
		jQuery("#last").load(
			"'.admin_url('options-general.php').'",
			{
				"ak_action": "backfill_domain_titles",
				"last": last,
				"limit": limit
			},
			backfill_progress
		);
	}
	function backfill_progress() {
		var last = jQuery("#last").html();
		if (last == "") {
			backfill_error();
			return;
		}
		if (last == "done") {
			jQuery("#progress, #cancel").hide();
			jQuery("#complete").show();
			return;
		}
		var counter = jQuery("#count");
		var processed_count = parseInt(counter.html());
		processed_count++;
		counter.html(processed_count);
		backfill_titles(last, 1);
	}
	function backfill_error() {
		jQuery("#progress, #cancel").hide();
		jQuery("#error").show();
	}
				';
				$body = '
		<h1>'.__('Backfill Empty Domain Titles', 'link-harvest').'</h1>
				';
				if ($count == '0') {
					$body .= '
		<p>'.__('Oops, didn\'t find any domains without titles.', 'link-harvest').'</p>
					';
				}
				else {
					$body .= '
		<p>'.sprintf(__('Sometimes a web page can be slow to load (for whatever reason). This will go through the <strong>%s</strong> domain(s) that have empty titles and try to fill them in for you.', 'link-harvest'), esc_html($count)).'</p>
		<p id="progress" class="center">'.__('Processed: ', 'link-harvest').'<strong><span id="count">0</span> / '.esc_html($count).'</strong> '.__('empty domain titles.', 'link-harvest').'</p>
		<p id="last">0</p>
		<form action="#" method="get" onsubmit="return false;">
			<fieldset>
				<legend>'.__('Backfill Empty Domain Titles', 'link-harvest').'</legend>
				<p id="submit" class="center"><input type="button" name="backfill_button" value="'.__('Start', 'link-harvest').'" onclick="backfill_titles(0, 1); return false;" /></p>
				<p id="cancel" class="center"><input type="button" name="cancel_button" value="'.__('Cancel', 'link-harvest').'" onclick="location.href=\''.admin_url('options-general.php?page=link-harvest.php').'\';" />
			</fieldset>
		</form>
		<p id="complete" class="center">'.__('The domain title backfill has completed successfully. View your <a href="'.admin_url('index.php?page=link-harvest.php').'">updated links list</a>.', 'link-harvest').'</p>
		<p id="error" class="center">'.__('The domain title backfill failed, make sure you have harvest actions enabled in your <a href="'.admin_url('options-general.php?page=link-harvest.php').'">options</a>.', 'link-harvest').'</p>
					';
				}
				$body .= '
		<p class="center"><a href="'.admin_url('options-general.php?page=link-harvest.php').'">'.__('Back to WordPress Admin', 'link-harvest').'</a></p>
				';
				break;

			case 'backfill_pages':
				$count = $wpdb->get_var("
					SELECT count(id)
					FROM $wpdb->ak_linkharvest
					WHERE title = ''
				");
				$js = '
	function backfill_titles(last, limit) {
		jQuery("#submit").hide();
		jQuery("#progress, #cancel").show();
		jQuery("#last").load(
			"'.admin_url('options-general.php').'",
			{
				"ak_action": "backfill_page_titles",
				"last": last,
				"limit": limit
			},
			backfill_progress
		);
	}
	function backfill_progress() {
		var last = jQuery("#last").html();
		if (last == "") {
			backfill_error();
			return;
		}
		if (last == "done") {
			jQuery("#progress, #cancel").hide();
			jQuery("#complete").show();
			return;
		}
		var counter = jQuery("#count");
		var processed_count = parseInt(counter.html());
		processed_count++;
		counter.html(processed_count);
		backfill_titles(last, 1);
	}
	function backfill_error() {
		jQuery("#progress").hide();
		jQuery("#cancel, #error").show();
	}
				';
				$body = '
		<h1>'.__('Backfill Empty Page Titles', 'link-harvest').'</h1>
				';
				if ($count == '0') {
					$body .= '
		<p>'.__('Oops, didn\'t find any pages without titles.', 'link-harvest').'</p>
					';
				}
				else {
					$body .= '
		<p>'.sprintf(__('Sometimes a web page can be slow to load (for whatever reason). This will go through the <strong>%s</strong> page(s) that have empty titles and try to fill them in for you.', 'link-harvest'), esc_html($count)).'</p>
		<p id="progress" class="center">'.__('Processed: ', 'link-harvest').'<strong><span id="count">0</span> / '.esc_html($count).'</strong> '.__('empty page titles.', 'link-harvest').'</p>
		<p id="last">0</p>
		<form action="#" method="get" onsubmit="return false;">
			<fieldset>
				<legend>'.__('Backfill Empty Page Titles', 'link-harvest').'</legend>
				<p id="submit" class="center"><input type="button" name="backfill_button" value="'.__('Start', 'link-harvest').'" onclick="backfill_titles(0, 1); return false;" /></p>
				<p id="cancel" class="center"><input type="button" name="cancel_button" value="'.__('Cancel', 'link-harvest').'" onclick="location.href=\''.admin_url('options-general.php?page=link-harvest.php').'\';" />
			</fieldset>
		</form>
		<p id="complete" class="center">'.__('The page title backfill has completed successfully. View your <a href="'.admin_url('index.php?page=link-harvest.php').'">updated links list</a>.', 'link-harvest').'</p>
		<p id="error" class="center">'.__('The page title backfill failed, make sure you have harvest actions enabled in your <a href="'.admin_url('options-general.php?page=link-harvest.php').'">options</a>.', 'link-harvest').'</p>
					';
				}
				$body .= '
		<p class="center"><a href="'.admin_url('options-general.php?page=link-harvest.php').'">'.__('Back to WordPress Admin', 'link-harvest').'</a></p>
				';
				break;
		}
		if (!$this->harvest_enabled) {
			$body = '
				<h1>'.__('Link Harvest', 'link-harvest').'</h1>
				<p>'.__('Oops, harvest actions aren\'t enabled. Turn them on <a href="'.admin_url('options-general.php?page=link-harvest.php').'">in your options</a>', 'link-harvest').'</p>
			';
		}
		header("Content-type: text/html");
		echo ('
<?xml version="1.0"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
        "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>'.__('Link Harvest', 'link-harvest').'</title>
	<script src="'.includes_url('js/jquery/jquery.js').'" type="text/javascript"></script>
	<script type="text/javascript">
	'.$js.'
	</script>
	<style type="text/css">
	body {
		background: #efefef;
		font: 12px Verdana, sans-serif;
		line-height: 150%;
		text-align: center;
	}
	.center {
		text-align: center;
	}
	form, fieldset {
		border: 0;
		margin: 0 auto;
		padding: 0;
	}
	legend {
		display: none;
	}
	input {
		font-weight: bold;
		padding: 10px 20px;
	}
	#body {
		background: #fff;
		border: 1px solid #ccc;
		margin: 20px auto;
		padding: 20px;
		text-align: left;
		width: 400px;
	}
	h1, #submit, #cancel {
		margin: 0 auto;
		padding: 10px 0;
	}
	#cancel {
		display: none;
	}
	#progress {
		background: #ffc;
		border: 1px solid #999;
		display: none;
		font-size: 14px;
		padding: 10px;
	}
	#complete {
		background: #3c6;
		border: 1px solid #999;
		display: none;
		font-size: 14px;
		font-weight: bold;
		padding: 10px;
	}
	#error {
		background: #f66;
		border: 1px solid #999;
		display: none;
		font-size: 14px;
		font-weight: bold;
		padding: 10px;
	}
	#last {
		display: none;
	}
	</style>
</head>
<body>
	<div id="body">
'.$body.'
	</div>
</body>
</html>
		');
		die();
	}
}

function aklh_public_post_types() {
	$args = array(
	  'public' => true,
	);
	return get_post_types($args, 'names');
}

// -- HOOKABLE FUNCTIONS
function aklh_publish($post) {
	$post_types = aklh_public_post_types();
	if (in_array($post->post_type, $post_types)) {
		global $aklh;
		$aklh->harvest_posts(array($post->ID));
	} 
	else {
		return;
	}
}
add_action('new_to_publish', 'aklh_publish', 999);
add_action('draft_to_publish', 'aklh_publish', 999);
add_action('pending_to_publish', 'aklh_publish', 999);
add_action('future_to_publish', 'aklh_publish', 999);


function aklh_post_delete($post_id) {
	global $aklh;
	$aklh->post_delete($post_id);
}
add_action('delete_post', 'aklh_post_delete');

//This prevents posts, that when updated, don't add data twice, but reset it
function aklh_post_update($post) {
	$post_types = aklh_public_post_types();
	if (in_array($post->post_type, $post_types)) {
		global $aklh;
		$aklh->post_update($post->ID);
	} 
	else {
		return;
	}
}
add_action('publish_to_publish', 'aklh_post_update', 999);

function aklh_options_form() {
	global $aklh;
	$aklh->options_form();
}
add_action('admin_menu', 'aklh_options');

function aklh_options() {
	add_options_page(
		__('Link Harvest Options', 'link-harvest'),
		__('Link Harvest', 'link-harvest'),
		'manage_options',
		basename(__FILE__),
		'aklh_options_form'
	);
	add_submenu_page(
		'index.php',
		__('Link Harvest', 'link-harvest'),
		__('Link Harvest', 'link-harvest'),
		'read',
		basename(__FILE__),
		'aklh_admin_show_harvest'
	);
}

function aklh_admin_init() {
	if ($_GET['page'] == basename(__FILE__)) {
		CF_Admin::load_js();
		CF_Admin::load_css();
		wp_enqueue_script('jquery');
		wp_enqueue_script('aklh_js', esc_url(site_url('index.php?ak_action=lh_js')), 'jquery');
		wp_enqueue_style('aklh_css', esc_url(site_url('index.php?ak_action=lh_css')));
	}
}
add_action('admin_init', 'aklh_admin_init');

function aklh_admin_show_harvest() {
	global $aklh;
	echo ('
<div id="cf" class="wrap">
	<div id="cf-header">
	');
	CF_Admin::admin_header(__('Link Harvest', 'link-harvest'), 'Link Harvest', AKLH_VERSION, 'link-harvest');
	echo '</div>';
	echo ('
		<p>'.__('Set domains to exclude and how many links to display in this list on the <a href="'.admin_url('options-general.php?page=link-harvest.php').'">options page</a>.', 'link-harvest').'</p>
	');
	$aklh->show_harvest(get_option('aklh_table_length'));
	echo '</div> <!--#cf-->';
	CF_Admin::callouts('link-harvest');
}

function aklh_request_handler() {
	global $wpdb, $aklh;
	if (!empty($_POST['ak_action'])) {

		ini_set('display_errors', '0');
		ini_set('error_reporting', E_ERROR);

		switch($_POST['ak_action']) {
			case 'update_linkharvest_settings': 
				if (!check_admin_referer('link-harvest', 'link-harvest-settings-nonce')) {
					die();
				}
				$aklh = new ak_link_harvest;
				$aklh->update_settings();
				header('Location: '.admin_url('options-general.php?page=link-harvest.php&updated=true'));
				die();
				break;
			case 'harvest_posts':
				ini_set('display_errors', '0');
				ini_set('error_reporting', E_PARSE);

				@set_time_limit(999999999999999999999);
				
				if ($aklh->harvest_enabled != 1) {
					die('disabled');
				}
				if (!empty($_POST['start'])) {
					$start = intval($_POST['start']);
				}
				else {
					$start = 0;
				}
				if (!empty($_POST['limit'])) {
					$limit = intval($_POST['limit']);
				}
				else {
					$limit = $aklh->default_limit;
				}
				
				if ($start == 0) {
					aklh_log(sprintf(__("\n\n\nStarting a new harvest\nPHP Version is: %s", 'link-harvest'), phpversion()));
					$plugins = get_option('active_plugins');
					if (is_array($plugins) && count($plugins) > 0) {
						foreach ($plugins as $plugin) {
							aklh_log(sprintf(__('Active plugin: %s', 'link-harvest'), $plugin));
						}
					}
				}
				aklh_log(sprintf(__("\nStarting with offset \"%s\"\n", 'link-harvest'), $start));

				$aklh->harvest_posts(null, $start, $limit);

				$count = $wpdb->get_var("
					SELECT count(ID)
					FROM $wpdb->posts
					WHERE (
						post_status = 'publish'
						OR post_status = 'static'
					)
					AND (
						post_content LIKE '%http://%'
						OR post_content LIKE '%https://%'
					)
				");
				$completed = ($start + $limit);
				if ($completed >= $count) {
					//update_option('aklh_harvest_enabled', '0');
					$completed = $count;
				}
				
				aklh_log(sprintf(__("Returning \"%s\"\n", 'link-harvest'), $completed));
				
				die(''.$completed);
				break;
			case 'backfill_domain_titles':
				$aklh->timeout = 6;
				if (!empty($_POST['last'])) {
					$last = intval($_POST['last']);
				}
				else {
					$last = 0;
				}
				if (!empty($_POST['limit'])) {
					$limit = intval($_POST['limit']);
				}
				else {
					$limit = 1;
				}
				$domains = $wpdb->get_results( $wpdb->prepare("
					SELECT *
					FROM $wpdb->ak_domains
					WHERE title = ''
					AND id > %d
					ORDER BY id
					LIMIT %d",
					$last, $limit
				));
				if (count($domains) > 0) {
					foreach ($domains as $domain) {
						$title = $aklh->get_page_title('http://'.$domain->domain);
						if (empty($title)) {
							$title = $aklh->get_page_title('http://www.'.$domain->domain);
						}
						if (!empty($title)) {
							$result = $wpdb->update(
								$wpdb->ak_domains,
								array('title' => $title),
								array('id' => $domain->id)
							);
						}
					}
					$completed = $domain->id;
				}
				else {
					$completed = 'done';
				}
				die(''.$completed);
				break;
			case 'backfill_page_titles':
				$aklh->timeout = 6;
				if (!empty($_POST['last'])) {
					$last = intval($_POST['last']);
				}
				else {
					$last = 0;
				}
				if (!empty($_POST['limit'])) {
					$limit = intval($_POST['limit']);
				}
				else {
					$limit = 1;
				}
				$links = $wpdb->get_results( $wpdb->prepare("
					SELECT *
					FROM $wpdb->ak_linkharvest
					WHERE title = ''
					AND id > %d
					ORDER BY id
					LIMIT %d",
					$last, $limit
				));
				if (count($links) > 0) {
					foreach ($links as $link) {
						$title = $aklh->get_page_title($link->url);
						if (!empty($title)) {
							$result = $wpdb->query("
								UPDATE $wpdb->ak_linkharvest
								SET title = '".mysql_real_escape_string($title)."'
								WHERE id = '$link->id'
							");
						}
					}
					$completed = $link->id;
				}
				else {
					$completed = 'done';
				}
				die(''.$completed);
				break;
		}
	}
	if (!empty($_GET['ak_action'])) {
		switch($_GET['ak_action']) {
			case 'harvest':
				$aklk = new ak_link_harvest;
				$aklk->get_settings();
				$aklk->show_processing_page('harvest');
				break;
			case 'backfill_domains':
				$aklk = new ak_link_harvest;
				$aklk->get_settings();
				$aklk->show_processing_page('backfill_domains');
				break;
			case 'backfill_pages':
				$aklk = new ak_link_harvest;
				$aklk->get_settings();
				$aklk->show_processing_page('backfill_pages');
				break;

			case 'list_links':
				if (!$_GET['domain_id']) {
					die();
				}
				else {
					$domain_id = $_GET['domain_id'];
					$links = $wpdb->get_results( $wpdb->prepare("
						SELECT * 
						FROM $wpdb->ak_linkharvest 
						WHERE domain_id = %d",
						$domain_id
					));
					if ($links === false) {
						die();
					}
					$items = array();
					foreach ($links as $link) {
						$items[$link->url]['count'] = $aklh->get_num_links($link->url);
						$items[$link->url]['url'] = $link->url;
						$items[$link->url]['title'] = $link->title;
					}
					echo $aklh->get_list_nodes($items);
				}
				die();
				break;
			case 'list_posts':
				if (!$_GET['domain_id']) {
					die();
				}
				else {
					$domain_id = $_GET['domain_id'];
					echo $aklh->get_list_nodes_posts($domain_id);
				}
				die();
				break;
			case 'lh_css':
				header("Content-type: text/css");
?>
table.aklh_harvest h4 {
	margin: 0;
	padding: 0;
	display:inline-block;
}
table.aklh_harvest ul{
	margin-left: 10px;
	padding-left: 10px;
}
table.aklh_harvest {
}
table.aklh_harvest th, table.aklh_harvest td {
	padding: 3px;
}
table.aklh_harvest td {
	vertical-align: top;
}
table.aklh_harvest th span.hide {
	display: none;
}
table.aklh_harvest td.count {
	text-align: left;
	width: 10%;
}
table.aklh_harvest td.action {
	text-align: center;
	width: 10%;
}
table.aklh_harvest a.close {
	display: inline;
	float: right;
}
#aklh_template_tags dl {
	margin-left: 10px;
}
#aklh_template_tags dl dt {
	font-weight: bold;
	margin: 0 0 5px 0;
}
#aklh_template_tags dl dd {
	margin: 0 0 15px 0;
	padding: 0 0 0 15px;
}
#ak_readme {
	height: 300px;
	width: 95%;
}
<?php
				die();
				break;
			case 'lh_js':
				header("Content-type: text/javascript");
?>
function aklh_show_for_domain(domain_id, type) {
	echo_type = '<?php _e('Links', 'link-harvest') ?>';
	switch (type) {
		case 'posts':
			echo_type = '<?php _e('Posts', 'link-harvest') ?>';
		case 'links':
			var pars = {
				"ak_action": "list_" + type,
				"domain_id": domain_id
			};
	}
	var header_html = '<a href="javascript:void(jQuery(\'.aklh-' + type + '-list-' + domain_id +'\').slideUp());" class="close">' + '<?php _e('Close', 'link-harvest'); ?>'+'</a> <h4 class="aklh-h4">' + echo_type + '</h4>';
	var target = jQuery('.aklh-'+type+'-list-' + domain_id);
	if (target.html() == '') {
		jQuery.get("<?php bloginfo('wpurl'); ?>/index.php", pars, function(data) {
			target.hide().html(header_html + '<ul class="aklh_ul">'+data+'</ul>').slideToggle();
		});
	}
	else {
		target.slideToggle();
	}
}
<?php
			die();
			break;
		}
	}
}
add_action('init', 'aklh_request_handler');

function aklh_shortcode($atts) {
	extract(shortcode_atts(array(
		'limit' => '50',
		'type' => 'list'
	), $atts));

	return aklh_get_harvest($limit, $type);
}
add_shortcode('linkharvest', 'aklh_shortcode');

//Deprecated
function aklh_the_content($content) {
	if (strstr($content, '###linkharvest###')) {
		$content = str_replace('###linkharvest###', aklh_get_harvest(), $content);
	}
	return $content;
}
if (get_option('aklh_token')) {
	add_action('the_content', 'aklh_the_content');
}

function aklh_the_excerpt($content) {
	return str_replace('###linkharvest###', '', $content);;
}
if (get_option('aklh_token')) {
	add_action('the_excerpt', 'aklh_the_excerpt');
}

function aklh_get_harvest($count = 50, $type = 'table') {
	ob_start();
	aklh_show_harvest($count, $type);
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

function aklh_powered_by_link() {
	echo ('
		<p id="aklh_credit">'.__('Powered by ', 'link-harvest').'<a href="http://crowdfavorite.com/wordpress/plugins/link-harvest/">Link Harvest</a>.</p>
	');
}

// -- TEMPLATE FUNCTIONS

function aklh_show_harvest($count = 50, $type = 'table') {
	global $aklh;
	$aklh->show_harvest($count, $type);
	if (get_option('aklh_credit')) {
		aklh_powered_by_link();
	}
}
//Deprecated, just use aklh_show_harvest
function aklh_top_links($count = 10, $type = 'list') {
	global $aklh;
	$aklh->show_harvest($count, $type);
	if (get_option('aklh_credit')) {
		aklh_powered_by_link();
	}
}

// debug logging

function aklh_log($msg) {
	if (!AKLH_DEBUG) {
		return;
	}
	$logfile = dirname(__FILE__).'/aklh_log.txt';
	$file = fopen($logfile, 'a');
	if ($file) {
		if (!fwrite($file, $msg."\n")) {
			die(__('Error writing to log file.', 'link-harvest'));
		}
	}
}

//Multisite

function aklh_is_multisite() {
	return CF_Admin::is_multisite();
}

function aklh_is_network_activation() {
	return CF_Admin::is_network_activation();
}

function aklh_activate_for_network() {
	CF_Admin::activate_for_network('aklh_activate_single');
}

function aklh_activate_plugin_for_new_blog($blog_id) {
	CF_Admin::activate_plugin_for_new_blog(AKLH_FILE, $blog_id, 'aklh_activate_single');
}
add_action('wpmu_new_blog', 'aklh_activate_plugin_for_new_blog');

function aklh_switch_blog() {
	global $wpdb;
 	$wpdb->ak_domains = $wpdb->prefix.'ak_domains';
 	$wpdb->ak_linkharvest = $wpdb->prefix.'ak_linkharvest';
}
add_action('switch_blog' , 'aklh_switch_blog');

endif;

?>