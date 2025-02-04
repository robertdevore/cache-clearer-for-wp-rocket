<?php
/**
 * Plugin Name: Cache Clearer for WP Rocket
 * Description: A plugin to clear WP Rocket cache on a schedule or on demand.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: cache-clearer-wp-rocket
 */

// Security check to prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the settings page
 */
function ccfwr_register_settings_page() {
    add_options_page(
        __( 'Cache Clearer Settings', 'cache-clearer-wp-rocket' ),
        __( 'Cache Clearer', 'cache-clearer-wp-rocket' ),
        'manage_options',
        'ccfwr-settings',
        'ccfwr_render_settings_page'
    );
}
add_action( 'admin_menu', 'ccfwr_register_settings_page' );

/**
 * Render the settings page
 */
function ccfwr_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Cache Clearer Settings', 'cache-clearer-wp-rocket' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'ccfwr_settings_group' );
            do_settings_sections( 'ccfwr-settings' );
            submit_button();
            ?>
        </form>
        <button id="ccfwr-clear-now" class="button button-primary"><?php esc_html_e( 'Clear Now', 'cache-clearer-wp-rocket' ); ?></button>
        <div id="ccfwr-success-message" style="display:none;">
            <?php esc_html_e( 'Cache cleared successfully!', 'cache-clearer-wp-rocket' ); ?>
        </div>
    </div>
    <style>
        #ccfwr-success-message {
            margin-top: 10px;
            color: green;
        }
    </style>
    <script>
        document.getElementById('ccfwr-clear-now').addEventListener('click', function() {
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ccfwr_clear_cache_now',
                    nonce: '<?php echo wp_create_nonce( 'ccfwr_clear_cache' ); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('ccfwr-success-message').style.display = 'block';
                }
            });
        });
    </script>
    <?php
}

/**
 * Register settings
 */
function ccfwr_register_settings() {
    register_setting( 'ccfwr_settings_group', 'ccfwr_cron_schedule' );

    add_settings_section(
        'ccfwr_settings_section',
        __( 'Cache Clearer Settings', 'cache-clearer-wp-rocket' ),
        '__return_false',
        'ccfwr-settings'
    );

    add_settings_field(
        'ccfwr_cron_schedule',
        __( 'Cron Schedule', 'cache-clearer-wp-rocket' ),
        'ccfwr_cron_schedule_field',
        'ccfwr-settings',
        'ccfwr_settings_section'
    );
}
add_action( 'admin_init', 'ccfwr_register_settings' );

/**
 * Render the cron schedule field
 */
function ccfwr_cron_schedule_field() {
    $schedules = wp_get_schedules();
    $current_schedule = get_option( 'ccfwr_cron_schedule', 'every_30_minutes' );
    ?>
    <select name="ccfwr_cron_schedule">
        <?php foreach ( $schedules as $key => $schedule ) : ?>
            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_schedule, $key ); ?>>
                <?php echo esc_html( $schedule['display'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

/**
 * Schedule the cron event
 */
function ccfwr_schedule_cron() {
    if ( ! wp_next_scheduled( 'ccfwr_clear_cache_event' ) ) {
        wp_schedule_event( time(), 'every_30_minutes', 'ccfwr_clear_cache_event' );
    }
}
add_action( 'wp', 'ccfwr_schedule_cron' );

/**
 * Clear cache on cron event
 */
function ccfwr_clear_cache() {
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    }
}
add_action( 'ccfwr_clear_cache_event', 'ccfwr_clear_cache' );

/**
 * AJAX handler for clearing cache now
 */
function ccfwr_clear_cache_now() {
    check_ajax_referer( 'ccfwr_clear_cache', 'nonce' );

    ccfwr_clear_cache();

    wp_send_json_success();
}
add_action( 'wp_ajax_ccfwr_clear_cache_now', 'ccfwr_clear_cache_now' );

/**
 * Add custom cron schedule
 */
function ccfwr_custom_cron_schedules( $schedules ) {
    $schedules['every_30_minutes'] = [
        'interval' => 1800,
        'display'  => __( 'Every 30 Minutes', 'cache-clearer-wp-rocket' )
    ];
    return $schedules;
}
add_filter( 'cron_schedules', 'ccfwr_custom_cron_schedules' );

/**
 * Update cron schedule on settings save
 */
function ccfwr_update_cron_schedule() {
    $schedule = get_option( 'ccfwr_cron_schedule', 'every_30_minutes' );

    if ( wp_next_scheduled( 'ccfwr_clear_cache_event' ) ) {
        wp_clear_scheduled_hook( 'ccfwr_clear_cache_event' );
    }

    wp_schedule_event( time(), $schedule, 'ccfwr_clear_cache_event' );
}
add_action( 'update_option_ccfwr_cron_schedule', 'ccfwr_update_cron_schedule' );
