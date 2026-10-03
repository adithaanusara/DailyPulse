<?php
/**
 * Removes everything the plugin stored when it is deleted from the Plugins screen.
 *
 * @package DailyPulse_Cleanup
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'dailypulse_cleanup_daily' );
wp_clear_scheduled_hook( 'dailypulse_cleanup_resume' );

delete_option( 'dailypulse_cleanup_settings' );
delete_option( 'dailypulse_cleanup_log' );
delete_transient( 'dailypulse_cleanup_lock' );
delete_transient( 'dailypulse_cleanup_activated' );

delete_post_meta_by_key( '_dailypulse_keep' );
delete_post_meta_by_key( '_dailypulse_cleanup_trashed' );
