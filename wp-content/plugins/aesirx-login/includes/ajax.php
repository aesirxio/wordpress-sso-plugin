<?php

use League\OAuth2\Client\Token\AccessToken;

add_action('wp_ajax_nopriv_aesirx_login_auth', function () {
	$result = [
		'message' => '',
		'data' => null,
		'success' => true,
	];
	header('Content-Type: application/json; charset=utf-8');

	try
	{
		global $wpdb;
		$post = $_POST;

		$accessToken = (array) $post['access_token'] ?? [];
		$resourceOwner = aesirx_login_get_provider()->getResourceOwner(new AccessToken($accessToken))->toArray();
		$remoteUserId = $resourceOwner['profile']['id'];

		aesirx_start_session();

		$table = $wpdb->prefix . 'aesirx_user_xref';

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE aesirx_id = %s",
				$remoteUserId
			)
		);

		if (!$record)
		{
			$wpdb->insert(
				$table, [
					'aesirx_id' => $remoteUserId,
					'created_at' => (new DateTime)->format('Y-m-d H:i:s'),
				]
			);
		}

		$_SESSION['aesirx_login_aesirx_id'] = $remoteUserId;

		if ($record
			&& $record->user_id)
		{
			$_SESSION['aesirx_login_user_id'] = $record->user_id;

			/** @var WP_User|WP_Error $user WP_User on success, WP_Error on failure. */
			$user = wp_signon();

			if ($user instanceof WP_Error)
			{
				throw new Exception($user->get_error_message());
			}
		}
		else
		{
			if (get_option('users_can_register'))
			{
				throw new Exception(
					__('Aesirx user has not been linked yet, you can log-in or signup then it will be linked for future logins', 'aesirx-login')
				);
			}
			else
			{
				throw new Exception(
					__('Aesirx user has not been linked yet, you can log-in then it will be linked for future logins', 'aesirx-login')
				);
			}
		}

		ob_clean();
		echo json_encode($result);
	}
	catch (Throwable $e)
	{
		ob_clean();
		status_header(500);

		if (WP_DEBUG)
		{
			$result['trace'] = $e->getTrace();

			if ($e instanceof ResponseException)
			{
				$result['response'] = $e->getResponse();
			}
		}

		$result['message'] = $e->getMessage();
		$result['success'] = false;

		echo json_encode($result);
	}

	die();
});
