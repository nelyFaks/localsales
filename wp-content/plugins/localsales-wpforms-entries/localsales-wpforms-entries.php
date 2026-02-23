<?php
/**
 * Plugin Name: LocalSales WPForms Entries
 * Description: Stores WPForms Lite submissions in the WordPress database and provides an admin page to view them.
 * Version: 0.1.0
 * Author: LocalSales
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function localsales_wpf_entries_table() {
	global $wpdb;
	return $wpdb->prefix . 'localsales_wpf_entries';
}

function localsales_wpf_entries_install() {
	global $wpdb;

	$table_name      = localsales_wpf_entries_table();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		form_id BIGINT UNSIGNED NOT NULL,
		form_title VARCHAR(255) NOT NULL DEFAULT '',
		page_url TEXT NULL,
		ip_address VARCHAR(64) NOT NULL DEFAULT '',
		user_agent TEXT NULL,
		fields_json LONGTEXT NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY form_id (form_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );
}

register_activation_hook( __FILE__, 'localsales_wpf_entries_install' );

function localsales_wpf_entries_is_wpforms_available() {
	return defined( 'WPFORMS_VERSION' ) || function_exists( 'wpforms' );
}

/**
 * Capture submissions after WPForms validation/processing completes.
 *
 * This runs for WPForms Lite too (Lite doesn't store entries by default).
 */
add_action(
	'wpforms_process_complete',
	function ( $fields, $entry, $form_data, $entry_id ) {
		if ( empty( $form_data['id'] ) ) {
			return;
		}

		global $wpdb;
		$table_name = localsales_wpf_entries_table();

		// Normalize the field payload (remove objects/resources just in case).
		$fields_slim = array();
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field_id => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$fields_slim[ (string) $field_id ] = array(
					'id'    => isset( $field['id'] ) ? (string) $field['id'] : (string) $field_id,
					'type'  => isset( $field['type'] ) ? (string) $field['type'] : '',
					'name'  => isset( $field['name'] ) ? (string) $field['name'] : '',
					'value' => isset( $field['value'] ) ? $field['value'] : '',
				);
			}
		}

		$form_id    = (int) $form_data['id'];
		$form_title = isset( $form_data['settings']['form_title'] ) ? (string) $form_data['settings']['form_title'] : '';

		$page_url  = '';
		$referrer  = isset( $_POST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		$permalink = isset( $_POST['wpforms']['settings']['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpforms']['settings']['page_url'] ) ) : '';
		if ( $permalink ) {
			$page_url = $permalink;
		} elseif ( $referrer ) {
			$page_url = $referrer;
		}

		$ip_address = '';
		if ( function_exists( 'wpforms_get_ip_address' ) ) {
			$ip_address = (string) wpforms_get_ip_address();
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$user_agent = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'form_id'     => $form_id,
				'form_title'  => $form_title,
				'page_url'    => $page_url,
				'ip_address'  => $ip_address,
				'user_agent'  => $user_agent,
				'fields_json' => wp_json_encode( $fields_slim, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			error_log( '[LocalSales WPForms Entries] DB insert failed: ' . $wpdb->last_error );
		}
	},
	10,
	4
);

function localsales_wpf_entries_admin_menu() {
	add_management_page(
		'WPForms Entries (LocalSales)',
		'WPForms Entries',
		'manage_options',
		'localsales-wpforms-entries',
		'localsales_wpf_entries_render_admin_page'
	);
}

add_action( 'admin_menu', 'localsales_wpf_entries_admin_menu' );

function localsales_wpf_entries_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table_name = localsales_wpf_entries_table();

	// Handle delete action.
	if ( isset( $_GET['action'], $_GET['entry_id'] ) && $_GET['action'] === 'delete' ) {
		check_admin_referer( 'localsales_wpf_entry_delete' );
		$entry_id = (int) $_GET['entry_id'];
		if ( $entry_id > 0 ) {
			$wpdb->delete( $table_name, array( 'id' => $entry_id ), array( '%d' ) );
		}
		echo '<div class="notice notice-success"><p>Entry deleted.</p></div>';
	}

	$entry_id = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0;

	echo '<div class="wrap">';
	echo '<h1>WPForms Entries (LocalSales)</h1>';

	if ( ! localsales_wpf_entries_is_wpforms_available() ) {
		echo '<div class="notice notice-warning"><p>WPForms does not appear to be active. Activate WPForms Lite to start collecting submissions.</p></div>';
	}

	if ( $entry_id > 0 ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $entry_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			echo '<div class="notice notice-error"><p>Entry not found.</p></div>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'tools.php?page=localsales-wpforms-entries' ) ) . '">Back</a></p>';
			echo '</div>';
			return;
		}

		$fields = json_decode( (string) $row->fields_json, true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		echo '<p><a class="button" href="' . esc_url( admin_url( 'tools.php?page=localsales-wpforms-entries' ) ) . '">Back</a></p>';
		echo '<h2>Entry #' . (int) $row->id . '</h2>';

		echo '<table class="widefat striped" style="max-width: 1000px">';
		echo '<tbody>';
		echo '<tr><th style="width:220px">Date</th><td>' . esc_html( (string) $row->created_at ) . '</td></tr>';
		echo '<tr><th>Form</th><td>' . esc_html( (string) $row->form_title ) . ' (ID: ' . (int) $row->form_id . ')</td></tr>';
		if ( ! empty( $row->page_url ) ) {
			echo '<tr><th>Page URL</th><td><a href="' . esc_url( (string) $row->page_url ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( (string) $row->page_url ) . '</a></td></tr>';
		}
		if ( ! empty( $row->ip_address ) ) {
			echo '<tr><th>IP</th><td>' . esc_html( (string) $row->ip_address ) . '</td></tr>';
		}
		echo '</tbody>';
		echo '</table>';

		echo '<h2 style="margin-top:18px">Fields</h2>';
		echo '<table class="widefat striped" style="max-width: 1000px">';
		echo '<thead><tr><th style="width:340px">Label</th><th>Value</th></tr></thead>';
		echo '<tbody>';
		foreach ( $fields as $field ) {
			$label = isset( $field['name'] ) ? (string) $field['name'] : '';
			$value = isset( $field['value'] ) ? $field['value'] : '';

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_scalar( $value ) ) {
				$value = (string) $value;
			} else {
				$value = '';
			}

			echo '<tr><td>' . esc_html( $label ) . '</td><td><pre style="margin:0;white-space:pre-wrap">' . esc_html( $value ) . '</pre></td></tr>';
		}
		if ( empty( $fields ) ) {
			echo '<tr><td colspan="2">No fields found.</td></tr>';
		}
		echo '</tbody></table>';

		$delete_url = wp_nonce_url(
			admin_url( 'tools.php?page=localsales-wpforms-entries&action=delete&entry_id=' . (int) $row->id ),
			'localsales_wpf_entry_delete'
		);
		echo '<p style="margin-top:12px"><a class="button button-secondary" href="' . esc_url( $delete_url ) . '">Delete this entry</a></p>';
		echo '</div>';
		return;
	}

	$rows = $wpdb->get_results( "SELECT id, form_id, form_title, page_url, created_at FROM {$table_name} ORDER BY id DESC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	echo '<p>This stores WPForms submissions in MySQL (useful for local/XAMPP MVPs).</p>';
	echo '<table class="widefat striped">';
	echo '<thead><tr><th style="width:90px">ID</th><th style="width:220px">Date</th><th>Form</th><th>Page</th><th style="width:140px">Actions</th></tr></thead>';
	echo '<tbody>';

	if ( empty( $rows ) ) {
		echo '<tr><td colspan="5">No entries yet. Submit the contact form once and refresh this page.</td></tr>';
	} else {
		foreach ( $rows as $row ) {
			$view_url = admin_url( 'tools.php?page=localsales-wpforms-entries&entry_id=' . (int) $row->id );
			echo '<tr>';
			echo '<td>' . (int) $row->id . '</td>';
			echo '<td>' . esc_html( (string) $row->created_at ) . '</td>';
			echo '<td>' . esc_html( (string) $row->form_title ) . ' (ID: ' . (int) $row->form_id . ')</td>';
			echo '<td>' . ( ! empty( $row->page_url ) ? esc_html( wp_parse_url( (string) $row->page_url, PHP_URL_PATH ) ?: (string) $row->page_url ) : '' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $view_url ) . '">View</a></td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';

	echo '<p style="margin-top:14px"><strong>Privacy note:</strong> This stores personal data (name/email/message). For GDPR, only keep what you need and delete entries when no longer required.</p>';
	echo '</div>';
}

