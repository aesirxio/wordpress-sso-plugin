<?php
/**
 * Plugin Name: aesirx-login
 * Description: Let users be auth via Aesirx.
 * Version: 0.1
 * Author: aesirx.io
 * Author URI: https://aesirx.io/
 * Domain Path: /languages
 * Text Domain: aesirx-login
 * Requires PHP: 7.2
 **/

use League\OAuth2\Client\Provider\GenericProvider;

require_once 'vendor/autoload.php';
require_once 'includes/settings.php';
require_once 'includes/ajax.php';

function aesirx_start_session(): void
{
	if (session_status() === PHP_SESSION_NONE)
	{
		session_start();
	}
}

function aesirx_login_get_provider(): GenericProvider
{
	static $provider;

	if (is_null($provider))
	{
		$options  = get_option('aesirx_login_plugin_options');
		$domain   = rtrim($options['endpoint'] ?? '', ' /') . '/index.php?api=oauth2&option=';

		$provider = new GenericProvider([
			'clientId'                => $options['client_id'] ?? '',
			'clientSecret'            => $options['client_secret'] ?? '',
			'urlAuthorize'            => $domain . 'authorize',
			'urlAccessToken'          => $domain . 'token',
			'urlResourceOwnerDetails' => $domain . 'profile',
		]);
	}

	return $provider;
}

$get = $_GET;
aesirx_start_session();

if ((!empty($get['code']) || !empty($get['error']))
	&& !empty($get['state'])
	&& $get['state'] == $_SESSION['aesirx_login_oauth2state'])
{
	if (!empty($get['code']))
	{
		$accessToken = aesirx_login_get_provider()->getAccessToken('authorization_code', [
			'code' => $get['code'],
		]);

		$_SESSION['aesirx_login_oauth2state'] = null;

		$response = array_replace(
			$accessToken->getValues(),
			$accessToken->jsonSerialize()
		);
	}
	else
	{
		$response = [
			'error'             => $get['error'] ?? '',
			'error_description' => $get['error_description'] ?? '',
		];
	}
	?>
	<script>
		window.opener.sso_response = <?php echo json_encode($response) ?>;
		window.close();
	</script>
	<?php
	die;
}

function aesirx_login_form_button(): void
{
	$rand = sprintf("%06d", rand(0, 999999));

	$svg = file_get_contents(ABSPATH . '/wp-content/plugins/aesirx-login/assets/images/aesirx_black.svg');

	wp_register_script(
		'aesirx',
		plugins_url('assets/js/login.js', __FILE__),
		['wp-i18n']
	);
	aesirx_start_session();
	wp_set_script_translations('aesirx', 'aesirx-login');
	wp_register_style('aesirx', '/wp-content/plugins/aesirx-login/assets/css/login.css');
	wp_enqueue_style('aesirx');
	wp_enqueue_script('aesirx');

	$provider = aesirx_login_get_provider();

	// Fetch the authorization URL from the provider; this returns the
	// urlAuthorize option and generates and applies any necessary parameters
	// (e.g. state).
	$provider->getAuthorizationUrl();

	$state = $provider->getState();
	aesirx_start_session();
	$_SESSION['aesirx_login_oauth2state'] = $state;
	$options  = get_option('aesirx_login_plugin_options');
	wp_localize_script(
		'aesirx',
		'AESIRX_VAL',
		[
			'ajaxurl' => admin_url('admin-ajax.php'),
			'aesirxEndpoint' => rtrim($options['endpoint'] ?? '', ' /'),
			'aesirxClientID' => $options['client_id'] ?? '',
			'aesirxAllowedLogins' => $options['logins'] ?? ['concordium', 'metamask', 'regular'],
			'aesirxState' => $state,
		]
	);

	?>
	<div class="aesirx-wrap">
		<button type="button" name="aesirx_submit" id="aesirx_submit_<?php echo $rand ?>"
				class="button button-default aesirx_submit">
			<?php echo $svg ?> <?php echo __('Login', 'aesirx-login') ?>
		</button>
	</div>
	<?php
}

add_action('woocommerce_login_form_end', 'aesirx_login_form_button');
add_action('login_form', 'aesirx_login_form_button');

function aesirx_set_user_to_remote(int $userId): void
{
	aesirx_start_session();

	if (empty($_SESSION['aesirx_login_aesirx_id']))
	{
		return;
	}

	$aesirxId = $_SESSION['aesirx_login_aesirx_id'];

	global $wpdb;

	$table = $wpdb->prefix . 'aesirx_user_xref';

	$record = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE user_id = %s",
			$userId
		)
	);

	if ($record)
	{
		// All matched
		if ($record->aesirx_id == $aesirxId)
		{
			$_SESSION['aesirx_login_user_id'] = $userId;

			return;
		}

		// User matched to another Aesirx account
		else
		{
			$_SESSION['aesirx_login_aesirx_id'] = null;

			return;
		}
	}

	$wpdb->update(
		$table,
		[
			'user_id' => $userId,
		],
		[
			'aesirx_id' => $aesirxId,
		]
	);

	$_SESSION['aesirx_login_user_id'] = $userId;
}

add_filter('authenticate', function ($user) {
	aesirx_start_session();

	if ($user instanceof WP_User
		|| empty($_SESSION['aesirx_login_aesirx_id'])
		|| empty($_SESSION['aesirx_login_user_id']))
	{
		return $user;
	}

	global $wpdb;

	$table = $wpdb->prefix . 'aesirx_user_xref';
	$record = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE aesirx_id = %s AND user_id = %s",
			$_SESSION['aesirx_login_aesirx_id'],
			$_SESSION['aesirx_login_user_id']
		)
	);

	if (!$record)
	{
		return $user;
	}

	return new WP_User($_SESSION['aesirx_login_user_id']);
});

add_action('woocommerce_created_customer', 'aesirx_set_user_to_remote');
add_action('register_new_user', 'aesirx_set_user_to_remote');
add_action('wp_login', function ($user_login, $user): void {
	aesirx_set_user_to_remote($user->ID);
}, 10, 2);

add_action('wp_logout', function (): void {
	aesirx_start_session();

	$_SESSION['aesirx_login_aesirx_id'] = null;
	$_SESSION['aesirx_login_user_id'] = null;
});

add_action('delete_user', function (int $id): void {
	global $wpdb;

	$wpdb->delete($wpdb->prefix . 'aesirx_user_xref', ['user_id' => $id]);
});

register_activation_hook(__FILE__, function () {
	global $wpdb;

	$table_name = $wpdb->prefix . "aesirx_user_xref";
	$users = $wpdb->prefix . "users";

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name
(
    user_id         bigint(20) unsigned NULL DEFAULT NULL,
    aesirx_id  int(11)  NULL DEFAULT NULL,
    created_at datetime NOT NULL,
    UNIQUE KEY  idx_aesirx_id (aesirx_id),
    UNIQUE KEY  idx_user_id (user_id) USING BTREE,
    FOREIGN KEY  (user_id) REFERENCES $users(id) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE = InnoDB
  $charset_collate";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
});

function aesirx_uninstall(): void
{
	delete_option('aesirx_login_plugin_options');
	global $wpdb;
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aesirx_user_xref");
}

register_uninstall_hook(__FILE__, 'aesirx_uninstall');

add_action('plugins_loaded', function () {
	load_plugin_textdomain('aesirx-login', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	$url = esc_url(add_query_arg(
		'page',
		'aesirx-login-plugin',
		get_admin_url() . 'admin.php'
	));
	array_push(
		$links,
		"<a href='$url'>" . __('Settings') . '</a>'
	);
	return $links;
});
