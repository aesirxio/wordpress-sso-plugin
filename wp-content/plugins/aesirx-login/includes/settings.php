<?php

add_action('admin_init', function () {
	register_setting(
		'aesirx_login_plugin_options',
		'aesirx_login_plugin_options',
		function ($value) {
			$valid = true;
			$input = (array) $value;

			if (empty($input['client_id']))
			{
				$valid = false;
				add_settings_error('aesirx_login_plugin_options', 'client_id', __('Client id is empty.', 'aesirx-login'));
			}

			if (empty($input['client_secret']))
			{
				$valid = false;
				add_settings_error('aesirx_login_plugin_options', 'client_secret', __('Client secret is empty.', 'aesirx-login'));
			}

			if (empty($input['endpoint']))
			{
				$valid = false;
				add_settings_error('aesirx_login_plugin_options', 'endpoint', __('Endpoint is empty.', 'aesirx-login'));
			}
			elseif (filter_var($input['endpoint'], FILTER_VALIDATE_URL) === FALSE)
			{
				$valid = false;
				add_settings_error('aesirx_login_plugin_options', 'json_hostname', __('Invalid endpoint format.', 'aesirx-login'));
			}

			if (empty($input['logins']))
			{
				$valid = false;
				add_settings_error('aesirx_login_plugin_options', 'logins', __('Select one of allowed login.', 'aesirx-login'));
			}

			// Ignore the user's changes and use the old database value.
			if (!$valid)
			{
				$value = get_option('aesirx_login_plugin_options');
			}

			return $value;
		});
	add_settings_section('aesirx_settings', 'aesirx', function () {
		echo '<p>' . __('Here you can set all the options for using the aesirx log-in', 'aesirx-login') . '</p>';
	}, 'aesirx_login_plugin');

	add_settings_field("aesirx_login_logins", __('Allowed Logins', 'concordium-login'), function () {
		$options = get_option('aesirx_login_plugin_options', []);
		$list = [
			'concordium' => __('Concordium', 'concordium-login'),
			'metamask' => __('Metamask', 'concordium-login'),
			'regular' => __('Regular Login', 'concordium-login'),
		];

		if (!array_key_exists('logins', $options))
		{
			$options['logins'] = ['concordium', 'metamask', 'regular'];
		}

		foreach ($list as $key => $item)
		{
			$checked = in_array($key, $options['logins'] ?? []) ? ' checked="checked" ' : '';
			echo "<label><input " . $checked . " value='$key' name='aesirx_login_plugin_options[logins][]' type='checkbox' /> $item</label><br />";
		}
	}, 'aesirx_login_plugin', 'aesirx_settings');

	add_settings_field('aesirx_login_endpoint', __('Endpoint <i>(Use next format: http://example.com)</i>', 'aesirx-login'), function () {
		$options = get_option('aesirx_login_plugin_options', []);
		echo "<input id='aesirx_login_endpoint' name='aesirx_login_plugin_options[endpoint]' type='text' value='" . esc_attr($options['endpoint'] ?? 'https://api.aesirx.io') . "' />";
	}, 'aesirx_login_plugin', 'aesirx_settings');

	add_settings_field('aesirx_login_client_id', __('Client id', 'aesirx-login'), function () {
		$options = get_option('aesirx_login_plugin_options', []);
		echo "<input id='aesirx_login_client_id' name='aesirx_login_plugin_options[client_id]' type='text' value='" . esc_attr($options['client_id'] ?? '') . "' />";
	}, 'aesirx_login_plugin', 'aesirx_settings');

	add_settings_field('aesirx_login_client_secret', __('Client secret', 'aesirx-login'), function () {
		$options = get_option('aesirx_login_plugin_options', []);
		echo "<input id='aesirx_login_client_secret' name='aesirx_login_plugin_options[client_secret]' type='text' value='" . esc_attr($options['client_secret'] ?? '') . "' />";
	}, 'aesirx_login_plugin', 'aesirx_settings');
});

add_action('admin_menu', function () {
	add_options_page(
		__('aesirx', 'aesirx-login'),
		__('aesirx', 'aesirx-login'),
		'manage_options',
		'aesirx-login-plugin',
		function () {
			?>
			<form action="options.php" method="post">
				<?php
				settings_fields('aesirx_login_plugin_options');
				do_settings_sections('aesirx_login_plugin'); ?>
				<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>"/>
			</form>
			<?php
		}
	);
});
