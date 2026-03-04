<?php
/**
 * Plugin Name: Acta
 * Plugin URI:  https://readwithacta.com
 * Description: Acta embeds a seamless checkout directly inside your content. Readers can unlock premium articles, lessons, files, and more using credit/debit cards, Apple Pay, or Google Pay — in seconds.
 * Version:     4.0.0
 * Author:      Acta
 * Author URI:  https://readwithacta.com
 * License:     GPL-2.0+
 * Text Domain: acta-content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// ─── Auto-updates ─────────────────────────────────────────────────────────────
// Checks Acta's own update server for new versions and silently installs them.
// The update server is notified by GitHub Actions after each release — no GitHub
// API calls from publishers, no rate limits.
// TODO: Remove this entire block when the plugin is published on WordPress.org.
//       WP.org handles updates natively; this block is only for direct distribution.

add_filter( 'pre_set_site_transient_update_plugins', 'acta_check_for_updates' );
add_filter( 'site_transient_update_plugins', 'acta_check_for_updates' );
function acta_check_for_updates( $transient ) {
    if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
        return $transient;
    }
    $data = get_site_transient( 'acta_update_data' );
    if ( false === $data ) {
        $response = wp_remote_get(
            'https://api.readwithacta.com/api/v1/public/plugin/acta-content/update-info.json',
            array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) )
        );
        if ( is_wp_error( $response ) ) {
            return $transient;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! empty( $data->version ) ) {
            set_site_transient( 'acta_update_data', $data, 6 * HOUR_IN_SECONDS );
        }
    }
    if ( empty( $data->version ) || empty( $data->download_url ) ) {
        return $transient;
    }
    $plugin_basename = plugin_basename( __FILE__ );
    if ( version_compare( $data->version, ACTA_PLUGIN_VERSION, '>' ) ) {
        if ( isset( $transient->no_update ) ) {
            unset( $transient->no_update[ $plugin_basename ] );
        }
        $transient->response[ $plugin_basename ] = (object) array(
            'slug'        => 'acta-content',
            'plugin'      => $plugin_basename,
            'new_version' => $data->version,
            'url'         => 'https://readwithacta.com',
            'package'     => $data->download_url,
        );
    }
    return $transient;
}

// Force silent background auto-updates — no publisher action needed.
// This filter stays even after moving to WordPress.org.
add_filter( 'auto_update_plugin', function( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'acta-content' ) {
        return true;
    }
    return $update;
}, 10, 2 );

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'ACTA_PLUGIN_VERSION', '4.0.0' );
define( 'ACTA_OPTION_KEY', 'acta_secret_key' );
define( 'ACTA_PUBLISHER_ID_KEY', 'acta_publisher_id' );
define( 'ACTA_STRIPE_URL_KEY', 'acta_stripe_url' );
define( 'ACTA_CONNECTION_STATUS', 'acta_connection_status' ); // not_registered | registered | live
define( 'ACTA_ADMIN_PAGE_SLUG', 'acta-content' );
define( 'ACTA_ACTIVATION_REDIRECT_OPTION', 'acta_do_activation_redirect' );
// Base64 SVG data URI — works without file path; WordPress blocks external SVG in some hosts.
define( 'ACTA_MENU_ICON', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAyIiBoZWlnaHQ9IjExNCIgdmlld0JveD0iMCAwIDEwMiAxMTQiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xLjc1NzgxIDEwMy4xMjVDMi4zOTI1OCAxMDEuODU1IDMuMDUxNzYgMTAwLjQzOSAzLjczNTM1IDk4Ljg3N0M0LjQxODk1IDk3LjI2NTYgNS4wNTM3MSA5NS41MzIyIDUuNjM5NjUgOTMuNjc2OEw3LjYxNzE5IDg4Ljg0MjhDNi43MzgyOCA4Ny41MjQ0IDUuODM0OTYgODYuMTA4NCA0LjkwNzIzIDg0LjU5NDdDNC4wMjgzMiA4My4wMzIyIDMuMjIyNjYgODEuNDQ1MyAyLjQ5MDIzIDc5LjgzNEMxLjc1NzgxIDc4LjIyMjcgMS4xNDc0NiA3Ni42MTEzIDAuNjU5MTggNzVDMC4yMTk3MjcgNzMuMzg4NyAwIDcxLjgyNjIgMCA3MC4zMTI1QzAgNzAuMjE0OCAwLjM2NjIxMSA3MC4xNDE2IDEuMDk4NjMgNzAuMDkyOEMxLjg3OTg4IDY5Ljk5NTEgMi44MzIwMyA2OS45MjE5IDMuOTU1MDggNjkuODczQzUuMTI2OTUgNjkuODI0MiA2LjM3MjA3IDY5Ljc5OTggNy42OTA0MyA2OS43OTk4QzkuMDU3NjIgNjkuNzUxIDEwLjMwMjcgNjkuNzI2NiAxMS40MjU4IDY5LjcyNjZDMTIuNTk3NyA2OS42Nzc3IDEzLjU0OTggNjkuNjUzMyAxNC4yODIyIDY5LjY1MzNDMTUuMDYzNSA2OS42MDQ1IDE1LjQ1NDEgNjkuNTgwMSAxNS40NTQxIDY5LjU4MDFDMTYuNTc3MSA2Ni42NTA0IDE3LjY3NTggNjMuODY3MiAxOC43NSA2MS4yMzA1QzE5LjgyNDIgNTguNTkzOCAyMC44OTg0IDU1LjkzMjYgMjEuOTcyNyA1My4yNDcxQzIzLjA0NjkgNTAuNTYxNSAyNC4xMjExIDQ3LjgyNzEgMjUuMTk1MyA0NS4wNDM5QzI2LjI2OTUgNDIuMjExOSAyNy4zNDM4IDM5LjE4NDYgMjguNDE4IDM1Ljk2MTlDMzAuNDY4OCAyOS45MDcyIDMyLjQ3MDcgMjQuNTg1IDM0LjQyMzggMTkuOTk1MUMzNi4zNzcgMTUuNDA1MyAzOC4yMDggMTEuMTMyOCAzOS45MTcgNy4xNzc3M0M0MC4xMTIzIDYuNjg5NDUgNDAuNDI5NyA2LjI1IDQwLjg2OTEgNS44NTkzOEM0MS4zNTc0IDUuNDE5OTIgNDEuODcwMSA1LjA1MzcxIDQyLjQwNzIgNC43NjA3NEM0Mi45NDQzIDQuNDY3NzcgNDMuNDU3IDQuMjIzNjMgNDMuOTQ1MyA0LjAyODMyQzQ0LjQ4MjQgMy44MzMwMSA0NC44OTc1IDMuNjg2NTIgNDUuMTkwNCAzLjU4ODg3QzQ1LjM4NTcgMy41NDAwNCA0Ni4xMTgyIDMuMzIwMzEgNDcuMzg3NyAyLjkyOTY5QzQ4LjcwNjEgMi41MzkwNiA1MC4xOTUzIDIuMTI0MDIgNTEuODU1NSAxLjY4NDU3QzUzLjUxNTYgMS4yNDUxMiA1NS4xNTE0IDAuODU0NDkyIDU2Ljc2MjcgMC41MTI2OTVDNTguNDIyOSAwLjE3MDg5OCA1OS43NDEyIDAgNjAuNzE3OCAwSDYyLjAzNjFDNjIuODE3NCAwIDYzLjU0OTggMC4xOTUzMTMgNjQuMjMzNCAwLjU4NTkzOEM2NC45NjU4IDAuOTI3NzM0IDY1LjQ1NDEgMS42MzU3NCA2NS42OTgyIDIuNzA5OTZDNjguMDQyIDguNzE1ODIgNzAuNTMyMiAxNS4yODMyIDczLjE2ODkgMjIuNDEyMUM3NS44NTQ1IDI5LjQ5MjIgNzguNDY2OCAzNy4zMDQ3IDgxLjAwNTkgNDUuODQ5NkM4Mi4zNzMgNTAuNDM5NSA4My43MTU4IDU0Ljc4NTIgODUuMDM0MiA1OC44ODY3Qzg2LjM1MjUgNjIuOTg4MyA4Ny42NzA5IDY2Ljk5MjIgODguOTg5MyA3MC44OTg0QzkwLjMwNzYgNzQuODA0NyA5MS42MjYgNzguNzEwOSA5Mi45NDQzIDgyLjYxNzJDOTQuMzExNSA4Ni41MjM0IDk1LjcwMzEgOTAuNTUxOCA5Ny4xMTkxIDk0LjcwMjFDOTcuNTU4NiA5Ni40NiA5Ny45OTggOTcuODUxNiA5OC40Mzc1IDk4Ljg3N0M5OC45MjU4IDk5LjkwMjMgOTkuMzY1MiAxMDAuNzA4IDk5Ljc1NTkgMTAxLjI5NEMxMDAuMTQ2IDEwMS44OCAxMDAuNDY0IDEwMi4zMTkgMTAwLjcwOCAxMDIuNjEyQzEwMC45NTIgMTAyLjg1NiAxMDEuMDc0IDEwMy4xMjUgMTAxLjA3NCAxMDMuNDE4QzEwMS4wNzQgMTAzLjcxMSAxMDAuNjEgMTA0LjAwNCA5OS42ODI2IDEwNC4yOTdMODMuOTM1NSAxMDkuNDI0QzgyLjc2MzcgMTA5Ljg2MyA4MS41Njc0IDExMC4zMDMgODAuMzQ2NyAxMTAuNzQyQzc5LjEyNiAxMTEuMTgyIDc3Ljk3ODUgMTExLjU3MiA3Ni45MDQzIDExMS45MTRDNzUuODMwMSAxMTIuMjU2IDc0Ljg3NzkgMTEyLjUyNCA3NC4wNDc5IDExMi43MkM3My4yNjY2IDExMi45NjQgNzIuNzA1MSAxMTMuMDg2IDcyLjM2MzMgMTEzLjA4Nkg3MS45MjM4QzcxLjI0MDIgMTEzLjA4NiA3MC43NTIgMTEyLjg2NiA3MC40NTkgMTEyLjQyN0M3MC4xNjYgMTEyLjAzNiA2OS45NzA3IDExMS42NDYgNjkuODczIDExMS4yNTVDNjkuMzg0OCAxMDkuNjQ0IDY4Ljg5NjUgMTA4LjIwMyA2OC40MDgyIDEwNi45MzRDNjcuOTE5OSAxMDUuNjE1IDY3LjQzMTYgMTA0LjMyMSA2Ni45NDM0IDEwMy4wNTJDNjYuNTAzOSAxMDEuNzMzIDY2LjA0IDEwMC4zNDIgNjUuNTUxOCA5OC44NzdDNjUuMTEyMyA5Ny40MTIxIDY0LjY3MjkgOTUuNjc4NyA2NC4yMzM0IDkzLjY3NjhDNjQuMTM1NyA5My40MzI2IDYzLjg2NzIgOTMuMjM3MyA2My40Mjc3IDkzLjA5MDhDNjIuOTg4MyA5Mi44OTU1IDYyLjQ1MTIgOTIuNzQ5IDYxLjgxNjQgOTIuNjUxNEM2MS4yMzA1IDkyLjU1MzcgNjAuNTk1NyA5Mi41MDQ5IDU5LjkxMjEgOTIuNTA0OUM1OS4yMjg1IDkyLjQ1NjEgNTguNTkzOCA5Mi40MzE2IDU4LjAwNzggOTIuNDMxNkM1Ni4xNTIzIDkyLjQzMTYgNTQuNjM4NyA5Mi40NTYxIDUzLjQ2NjggOTIuNTA0OUM1Mi4zNDM4IDkyLjUwNDkgNTEuMzQyOCA5Mi41MjkzIDUwLjQ2MzkgOTIuNTc4MUM0OS41ODUgOTIuNTc4MSA0OC43MzA1IDkyLjYwMjUgNDcuOTAwNCA5Mi42NTE0QzQ3LjExOTEgOTIuNjUxNCA0Ni4xNDI2IDkyLjY1MTQgNDQuOTcwNyA5Mi42NTE0QzQzLjU1NDcgOTIuNjUxNCA0Mi4wODk4IDkyLjY1MTQgNDAuNTc2MiA5Mi42NTE0QzM5LjExMTMgOTIuNjAyNSAzNy43Njg2IDkyLjU1MzcgMzYuNTQ3OSA5Mi41MDQ5TDMyLjM3MyA5Mi4zNTg0QzMxLjEwMzUgOTUuODc0IDI5LjgzNCA5OS4xNjk5IDI4LjU2NDUgMTAyLjI0NkMyNy4yOTQ5IDEwNS4zMjIgMjUuOTI3NyAxMDguNDk2IDI0LjQ2MjkgMTExLjc2OEMyNC4zNjUyIDExMi4xNTggMjQuMTIxMSAxMTIuNDc2IDIzLjczMDUgMTEyLjcyQzIzLjM4ODcgMTEyLjk2NCAyMy4wMjI1IDExMy4wODYgMjIuNjMxOCAxMTMuMDg2QzIyLjMzODkgMTEzLjA4NiAyMi4xNjggMTEzLjA2MiAyMi4xMTkxIDExMy4wMTNDMTcuMzgyOCAxMTEuMzA0IDEzLjQwMzMgMTA5LjYxOSAxMC4xODA3IDEwNy45NTlDNi45NTgwMSAxMDYuMjk5IDQuMTUwMzkgMTA0LjY4OCAxLjc1NzgxIDEwMy4xMjVaTTUxLjEyMyAzNy4yODAzQzQ5Ljk1MTIgNDEuNjI2IDQ4Ljc3OTMgNDUuNTgxMSA0Ny42MDc0IDQ5LjE0NTVDNDYuNDg0NCA1Mi42NjExIDQ1LjQzNDYgNTUuNzg2MSA0NC40NTggNTguNTIwNUM0My41MzAzIDYxLjI1NDkgNDIuNzI0NiA2My41NzQyIDQyLjA0MSA2NS40Nzg1QzQxLjM1NzQgNjcuMzM0IDQwLjg2OTEgNjguNzc0NCA0MC41NzYyIDY5Ljc5OThWNzAuNDU5QzQwLjU3NjIgNzAuNzUyIDQwLjU1MTggNzAuOTQ3MyA0MC41MDI5IDcxLjA0NDlINTguMDgxMUM1Ny41NDM5IDY4LjY1MjMgNTYuOTU4IDY2LjIzNTQgNTYuMzIzMiA2My43OTM5QzU1LjczNzMgNjEuMzUyNSA1NS4xNTE0IDU4Ljc4OTEgNTQuNTY1NCA1Ni4xMDM1QzUzLjk3OTUgNTMuNDE4IDUzLjM5MzYgNTAuNTM3MSA1Mi44MDc2IDQ3LjQ2MDlDNTIuMjIxNyA0NC4zODQ4IDUxLjY2MDIgNDAuOTkxMiA1MS4xMjMgMzcuMjgwM1oiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=' );

// Backend URL: defaults to production. Override in wp-config.php for dev/staging:
//   define('ACTA_BACKEND_URL', 'https://api.develop.readwithacta.com');
if ( ! defined( 'ACTA_BACKEND_URL' ) ) {
    define( 'ACTA_BACKEND_URL', 'https://api.readwithacta.com' );
}

// ─── Stripe-supported countries ──────────────────────────────────────────────

function acta_get_supported_countries() {
    return array(
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'BR' => 'Brazil',
        'BG' => 'Bulgaria',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'EE' => 'Estonia',
        'FI' => 'Finland',
        'FR' => 'France',
        'DE' => 'Germany',
        'GR' => 'Greece',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IE' => 'Ireland',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'LV' => 'Latvia',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MY' => 'Malaysia',
        'MT' => 'Malta',
        'MX' => 'Mexico',
        'NL' => 'Netherlands',
        'NZ' => 'New Zealand',
        'NO' => 'Norway',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'ES' => 'Spain',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'TH' => 'Thailand',
        'AE' => 'United Arab Emirates',
    );
}

function acta_get_supported_currencies() {
    return array(
        'usd' => 'USD - US Dollar',
        'eur' => 'EUR - Euro',
        'gbp' => 'GBP - British Pound',
        'cad' => 'CAD - Canadian Dollar',
        'aud' => 'AUD - Australian Dollar',
        'jpy' => 'JPY - Japanese Yen',
        'chf' => 'CHF - Swiss Franc',
        'nzd' => 'NZD - New Zealand Dollar',
        'sek' => 'SEK - Swedish Krona',
        'nok' => 'NOK - Norwegian Krone',
        'dkk' => 'DKK - Danish Krone',
        'sgd' => 'SGD - Singapore Dollar',
        'hkd' => 'HKD - Hong Kong Dollar',
        'mxn' => 'MXN - Mexican Peso',
        'brl' => 'BRL - Brazilian Real',
        'pln' => 'PLN - Polish Zloty',
        'czk' => 'CZK - Czech Koruna',
        'huf' => 'HUF - Hungarian Forint',
        'ron' => 'RON - Romanian Leu',
        'bgn' => 'BGN - Bulgarian Lev',
        'thb' => 'THB - Thai Baht',
        'myr' => 'MYR - Malaysian Ringgit',
        'aed' => 'AED - UAE Dirham',
    );
}

// ─── Activation: generate a secret key ───────────────────────────────────────

register_activation_hook( __FILE__, 'acta_activate' );
function acta_activate() {
    if ( ! get_option( ACTA_OPTION_KEY ) ) {
        update_option( ACTA_OPTION_KEY, bin2hex( random_bytes( 32 ) ) );
    }
    // Initialize connection status if not set
    if ( ! get_option( ACTA_CONNECTION_STATUS ) ) {
        update_option( ACTA_CONNECTION_STATUS, 'not_registered' );
    }
    // Redirect to the Acta settings page after activation.
    update_option( ACTA_ACTIVATION_REDIRECT_OPTION, 1 );
}

// Also redirect after plugin update (upload zip) — WordPress shows "Go to Plugin Installer" link otherwise.
add_action( 'upgrader_process_complete', 'acta_maybe_set_redirect_after_update', 10, 2 );
function acta_maybe_set_redirect_after_update( $upgrader_object, $options ) {
    if ( empty( $options['action'] ) || $options['action'] !== 'update' ) {
        return;
    }
    if ( empty( $options['type'] ) || $options['type'] !== 'plugin' ) {
        return;
    }
    $our_plugin = plugin_basename( __FILE__ );
    $updated = array();
    if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
        $updated = $options['plugins'];
    } elseif ( ! empty( $options['plugin'] ) ) {
        $updated = array( $options['plugin'] );
    }
    if ( in_array( $our_plugin, $updated, true ) ) {
        update_option( ACTA_ACTIVATION_REDIRECT_OPTION, 1 );
    }
}

add_action( 'admin_init', 'acta_maybe_redirect_after_activation' );
function acta_maybe_redirect_after_activation() {
    if ( ! get_option( ACTA_ACTIVATION_REDIRECT_OPTION ) ) {
        return;
    }

    delete_option( ACTA_ACTIVATION_REDIRECT_OPTION );

    if ( ! is_admin() || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
        return;
    }
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
    }
    if ( isset( $_GET['activate-multi'] ) ) {
        return;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=' . ACTA_ADMIN_PAGE_SLUG ) );
    exit;
}

// ─── Admin settings page ─────────────────────────────────────────────────────

add_action( 'admin_menu', 'acta_add_admin_menu' );
function acta_add_admin_menu() {
    add_menu_page(
        'Acta Settings',
        'Acta',
        'manage_options',
        ACTA_ADMIN_PAGE_SLUG,
        'acta_settings_page',
        ACTA_MENU_ICON,
        30
    );
}

add_action( 'admin_init', 'acta_settings_init' );
function acta_settings_init() {
    register_setting( 'acta_settings', ACTA_OPTION_KEY );
    register_setting( 'acta_settings', ACTA_PUBLISHER_ID_KEY, array(
        'sanitize_callback' => 'sanitize_text_field',
    ) );
}

// ─── Backend API functions ───────────────────────────────────────────────────

/**
 * Self-service onboarding: create a new publisher on the Acta backend.
 * Called when publisher clicks "Get Started with Acta".
 *
 * @return array { success: bool, message: string, publisherId?: string, stripeOnboardingUrl?: string }
 */
function acta_onboard_to_backend( $email, $first_name, $last_name, $country, $currency, $article_price ) {
    $backend_url = rtrim( ACTA_BACKEND_URL, '/' ) . '/api/v1/public/onboard-wordpress';
    $secret      = get_option( ACTA_OPTION_KEY, '' );
    $endpoint    = rest_url( 'acta/v1/content' );

    $response = wp_remote_post( $backend_url, array(
        'timeout' => 30,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array(
            'email'          => $email,
            'firstName'      => $first_name,
            'lastName'       => $last_name,
            'website'        => home_url(),
            'name'           => get_bloginfo( 'name' ),
            'country'        => $country,
            'currency'       => $currency,
            'articlePrice'   => (float) $article_price,
            'pluginEndpoint' => $endpoint,
            'pluginSecretKey'=> $secret,
        ) ),
    ) );

    if ( is_wp_error( $response ) ) {
        return array(
            'success' => false,
            'message' => 'Connection failed: ' . $response->get_error_message(),
        );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code === 201 && ! empty( $body['success'] ) ) {
        return array(
            'success'            => true,
            'message'            => 'Publisher created successfully.',
            'publisherId'        => $body['publisherId'] ?? '',
            'stripeOnboardingUrl'=> $body['stripeOnboardingUrl'] ?? '',
        );
    }

    $error_msg = $body['error'] ?? ( 'HTTP ' . $code );
    return array(
        'success' => false,
        'message' => $error_msg,
    );
}

/**
 * Reconnect the plugin to an existing publisher on the Acta backend.
 * Used for plugin reinstall or v1 → v2 migration.
 */
function acta_connect_to_backend( $publisher_id, $plugin_endpoint, $plugin_secret_key ) {
    $backend_url = rtrim( ACTA_BACKEND_URL, '/' ) . '/api/v1/public/connect-wordpress';

    $response = wp_remote_post( $backend_url, array(
        'timeout' => 15,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array(
            'publisherId'     => $publisher_id,
            'siteUrl'         => home_url(),
            'pluginEndpoint'  => $plugin_endpoint,
            'pluginSecretKey' => $plugin_secret_key,
        ) ),
    ) );

    if ( is_wp_error( $response ) ) {
        return array(
            'success' => false,
            'message' => 'Connection failed: ' . $response->get_error_message(),
        );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code === 200 && ! empty( $body['success'] ) ) {
        return array(
            'success' => true,
            'message' => $body['message'] ?? 'Connected successfully.',
        );
    }

    $error_msg = $body['error'] ?? ( 'HTTP ' . $code );
    return array(
        'success' => false,
        'message' => $error_msg,
    );
}

/**
 * Fetch publisher info (article price, rate limit) from Acta backend.
 *
 * @return array{ articlePrice: float, changesRemaining: int }|null
 */
function acta_fetch_publisher_info( $publisher_id, $secret, $site_url ) {
    if ( empty( $publisher_id ) || empty( $secret ) ) {
        return null;
    }
    $url = add_query_arg(
        array(
            'publisherId' => $publisher_id,
            'key'         => $secret,
            'siteUrl'     => $site_url,
        ),
        rtrim( ACTA_BACKEND_URL, '/' ) . '/api/v1/public/wordpress-publisher-info'
    );
    $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
    if ( is_wp_error( $response ) ) {
        return null;
    }
    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        return null;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || ! isset( $body['articlePrice'] ) ) {
        return null;
    }
    return array(
        'articlePrice'      => (float) $body['articlePrice'],
        'changesRemaining'  => isset( $body['changesRemaining'] ) ? (int) $body['changesRemaining'] : 3,
    );
}

/**
 * Update default article price via Acta backend.
 *
 * @return array{ success: bool, message: string, articlePrice?: float, changesRemaining?: int }
 */
function acta_update_default_price( $publisher_id, $secret, $site_url, $article_price ) {
    $url  = rtrim( ACTA_BACKEND_URL, '/' ) . '/api/v1/public/wordpress-update-article-price';
    $body = array(
        'publisherId' => $publisher_id,
        'key'         => $secret,
        'siteUrl'     => $site_url,
        'articlePrice' => (float) $article_price,
    );
    $response = wp_remote_post( $url, array(
        'timeout' => 15,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( $body ),
    ) );
    if ( is_wp_error( $response ) ) {
        return array(
            'success' => false,
            'message' => $response->get_error_message(),
        );
    }
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code === 200 && ! empty( $body['success'] ) ) {
        return array(
            'success'          => true,
            'message'          => 'Default price updated.',
            'articlePrice'     => $body['articlePrice'] ?? $article_price,
            'changesRemaining' => $body['changesRemaining'] ?? 0,
        );
    }
    return array(
        'success' => false,
        'message' => $body['error'] ?? ( 'HTTP ' . $code ),
    );
}

// ─── Settings page renderer ─────────────────────────────────────────────────

function acta_settings_page() {
    $secret       = get_option( ACTA_OPTION_KEY, '' );
    $publisher_id = get_option( ACTA_PUBLISHER_ID_KEY, '' );
    $stripe_url   = get_option( ACTA_STRIPE_URL_KEY, '' );
    $conn_status  = get_option( ACTA_CONNECTION_STATUS, 'not_registered' );
    $endpoint     = rest_url( 'acta/v1/content' );

    // ── Handle form actions ──────────────────────────────────────────────────

    // Handle "Get Started with Acta" (self-service onboarding)
    if (
        isset( $_POST['acta_action'] ) &&
        $_POST['acta_action'] === 'onboard' &&
        check_admin_referer( 'acta_onboard' )
    ) {
        $email         = sanitize_email( wp_unslash( $_POST['acta_email'] ?? '' ) );
        $first_name    = sanitize_text_field( wp_unslash( $_POST['acta_first_name'] ?? '' ) );
        $last_name     = sanitize_text_field( wp_unslash( $_POST['acta_last_name'] ?? '' ) );
        $country       = sanitize_text_field( wp_unslash( $_POST['acta_country'] ?? '' ) );
        $currency      = sanitize_text_field( wp_unslash( $_POST['acta_currency'] ?? '' ) );
        $article_price = floatval( wp_unslash( $_POST['acta_article_price'] ?? 0 ) );

        if ( empty( $email ) || empty( $first_name ) || empty( $last_name ) || empty( $country ) || empty( $currency ) || $article_price <= 0 ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'All fields are required. Please fill in your email, name, country, currency, and a price greater than 0.', 'acta-content' ) . '</p></div>';
        } else {
            $result = acta_onboard_to_backend( $email, $first_name, $last_name, $country, $currency, $article_price );

            if ( $result['success'] ) {
                update_option( ACTA_PUBLISHER_ID_KEY, $result['publisherId'] );
                update_option( ACTA_STRIPE_URL_KEY, $result['stripeOnboardingUrl'] );
                update_option( ACTA_CONNECTION_STATUS, 'registered' );
                $publisher_id = $result['publisherId'];
                $stripe_url   = $result['stripeOnboardingUrl'];
                $conn_status  = 'registered';
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Successfully registered! Complete your Stripe setup below to start accepting payments.', 'acta-content' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html( $result['message'] ) . '</p></div>';
            }
        }
    }

    // Handle "I've completed Stripe setup" — check if Stripe is done
    if (
        isset( $_POST['acta_action'] ) &&
        $_POST['acta_action'] === 'check_stripe' &&
        check_admin_referer( 'acta_check_stripe' )
    ) {
        // The Stripe onboarding URL becomes invalid once completed.
        // We mark as live — the backend's /connect/success endpoint already set account_url to null.
        update_option( ACTA_CONNECTION_STATUS, 'live' );
        update_option( ACTA_STRIPE_URL_KEY, '' );
        $conn_status = 'live';
        $stripe_url  = '';
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Status updated. If your Stripe setup is complete, you\'re live!', 'acta-content' ) . '</p></div>';
    }

    // Handle "Update default price"
    if (
        $conn_status === 'live' &&
        isset( $_POST['acta_action'] ) &&
        $_POST['acta_action'] === 'update_default_price' &&
        check_admin_referer( 'acta_update_default_price' )
    ) {
        $new_price = floatval( wp_unslash( $_POST['acta_default_price'] ?? 0 ) );
        if ( $new_price > 0 ) {
            $result = acta_update_default_price( $publisher_id, $secret, home_url(), $new_price );
            if ( $result['success'] ) {
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Default price updated to ', 'acta-content' ) . esc_html( number_format( $result['articlePrice'], 2 ) ) . '.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html( $result['message'] ) . '</p></div>';
            }
        }
    }

    // ── Render the settings page ─────────────────────────────────────────────
    $countries  = acta_get_supported_countries();
    $currencies = acta_get_supported_currencies();
    ?>
    <div class="wrap">
        <h1>Acta Settings</h1>
        <p style="color: #1d2327; font-size: 15px; margin-bottom: 8px;">Acta is a pay-per-content solution for publishers and creators.</p>
        <p style="color: #666;">Version <?php echo esc_html( ACTA_PLUGIN_VERSION ); ?></p>

        <?php if ( $conn_status === 'not_registered' || empty( $conn_status ) ) : ?>
            <?php $current_user = wp_get_current_user(); ?>
            <!-- ═══ STATE: NOT REGISTERED — Show onboarding form ═══ -->
            <div style="max-width: 600px; margin-top: 20px;">
                <h2>Get started accepting payments on your content</h2>
                <p>Fill in the details below and we'll set everything up for you. You'll be redirected to Stripe to complete your payment setup.</p>

                <form method="post" action="">
                    <?php wp_nonce_field( 'acta_onboard' ); ?>
                    <input type="hidden" name="acta_action" value="onboard">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="acta_email">Email</label></th>
                            <td>
                                <input type="email" id="acta_email" name="acta_email"
                                       value="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>"
                                       class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="acta_first_name">First name</label></th>
                            <td>
                                <input type="text" id="acta_first_name" name="acta_first_name"
                                       value="<?php echo esc_attr( $current_user->first_name ?? '' ); ?>"
                                       class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="acta_last_name">Last name</label></th>
                            <td>
                                <input type="text" id="acta_last_name" name="acta_last_name"
                                       value="<?php echo esc_attr( $current_user->last_name ?? '' ); ?>"
                                       class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="acta_country">Country</label></th>
                            <td>
                                <select id="acta_country" name="acta_country" class="regular-text" required>
                                    <option value="">Select country...</option>
                                    <?php foreach ( $countries as $code => $label ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>"
                                            <?php selected( $code, 'US' ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="acta_currency">Currency</label></th>
                            <td>
                                <select id="acta_currency" name="acta_currency" class="regular-text" required>
                                    <?php foreach ( $currencies as $code => $label ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>"
                                            <?php selected( $code, 'usd' ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="acta_article_price">Price per article</label></th>
                            <td>
                                <input type="number" id="acta_article_price" name="acta_article_price"
                                       value="2.00" min="0.01" step="1.00"
                                       class="small-text" required>
                                <p class="description">How much readers pay to unlock a single article.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( 'Get Started with Acta', 'primary large', 'submit', true ); ?>
                </form>

            </div>

        <?php elseif ( $conn_status === 'registered' ) : ?>
            <!-- ═══ STATE: REGISTERED — Show Stripe setup prompt ═══ -->
            <div style="max-width: 600px; margin-top: 20px;">
                <div style="background: #f0f6fc; border: 1px solid #c8d6e5; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0; color: #0a6f0a;">&#10003; Connected to Acta</h3>
                    <p><strong>Publisher ID:</strong> <code style="font-size: 14px;"><?php echo esc_html( $publisher_id ); ?></code></p>
                </div>

                <?php if ( ! empty( $stripe_url ) ) : ?>
                <div style="background: #fffbe6; border: 1px solid #ffe58f; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">Complete your Stripe setup</h3>
                    <p>You need to set up your Stripe account to receive payments from readers. This takes about 5 minutes.</p>
                    <p>
                        <a href="<?php echo esc_url( $stripe_url ); ?>" class="button button-primary button-large" target="_blank" rel="noopener">
                            Complete Stripe Setup &rarr;
                        </a>
                    </p>
                    <form method="post" action="" style="margin-top: 16px;">
                        <?php wp_nonce_field( 'acta_check_stripe' ); ?>
                        <input type="hidden" name="acta_action" value="check_stripe">
                        <?php submit_button( "I've completed Stripe setup", 'secondary', 'submit', false ); ?>
                    </form>
                </div>
                <?php endif; ?>

            </div>

        <?php elseif ( $conn_status === 'live' ) : ?>
            <!-- ═══ STATE: LIVE — Show success ═══ -->
            <div style="max-width: 600px; margin-top: 20px;">
                <div style="background: #f6ffed; border: 1px solid #b7eb8f; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0; color: #0a6f0a;">&#10003; You're live with Acta!</h3>
                    <p style="margin-bottom: 4px; color: #0a6f0a;">&#10003; Stripe setup complete &mdash; readers can now purchase your articles.</p>
                    <p style="margin-bottom: 0;"><strong>Publisher ID:</strong> <code style="font-size: 14px;"><?php echo esc_html( $publisher_id ); ?></code></p>
                </div>

                <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">Set a custom price on an article</h3>
                    <p>By default, all articles use your default price. To set a different price on a specific article, add a <strong>Custom HTML</strong> block in the post editor (before the paywall break) with this snippet:</p>
                    <div style="display: flex; align-items: center; gap: 12px; background: #1d2327; border-radius: 4px; padding: 12px 16px;">
                        <pre style="flex: 1; margin: 0; background: transparent; color: #f0f0f1; padding: 0; overflow-x: auto; font-size: 13px; line-height: 1.6;">&lt;div id="acta-price" data-price="<strong style="color: #f0c674;">ENTER_PRICE_HERE</strong>"&gt;&lt;/div&gt;</pre>
                        <button type="button" class="button button-secondary acta-copy-price-btn" title="Copy" style="padding: 4px 8px; min-height: 28px; flex-shrink: 0; background: #1d2327; border-color: #3c434a; color: #f0f0f1;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                    <p class="description">Replace <code>ENTER_PRICE_HERE</code> with the price (e.g. <code>2.99</code>). This overrides the default price for that article only.</p>
                    <script>
                    (function() {
                        var btn = document.querySelector('.acta-copy-price-btn');
                        if (btn) {
                            btn.addEventListener('click', function() {
                                var text = '<div id="acta-price" data-price="ENTER_PRICE_HERE"></div>';
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(text).then(function() {
                                        btn.innerHTML = '<span style="font-size:11px;">Copied!</span>';
                                        setTimeout(function() { btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'; }, 2000);
                                    });
                                } else {
                                    var ta = document.createElement('textarea');
                                    ta.value = text;
                                    ta.style.position = 'fixed';
                                    ta.style.left = '-9999px';
                                    document.body.appendChild(ta);
                                    ta.select();
                                    document.execCommand('copy');
                                    document.body.removeChild(ta);
                                    btn.innerHTML = '<span style="font-size:11px;">Copied!</span>';
                                    setTimeout(function() { btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'; }, 2000);
                                }
                            });
                        }
                    })();
                    </script>
                </div>

                <?php
                $publisher_info = ( $conn_status === 'live' && ! empty( $publisher_id ) && ! empty( $secret ) )
                    ? acta_fetch_publisher_info( $publisher_id, $secret, home_url() )
                    : null;
                $current_default = $publisher_info['articlePrice'] ?? 2.00;
                $changes_remaining = $publisher_info['changesRemaining'] ?? 3;
                ?>
                <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">Change default price</h3>
                    <p style="margin-bottom: 12px;">Current default price: <strong><?php echo esc_html( number_format( $current_default, 2 ) ); ?></strong></p>
                    <form method="post" action="" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <?php wp_nonce_field( 'acta_update_default_price' ); ?>
                        <input type="hidden" name="acta_action" value="update_default_price">
                        <input type="number" id="acta_default_price" name="acta_default_price"
                               value="<?php echo esc_attr( number_format( $current_default, 2, '.', '' ) ); ?>"
                               min="0.01" step="any" class="small-text" style="width: 80px;">
                        <?php submit_button( 'Update default price', 'primary', 'submit', false ); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <p style="margin-top: 32px; color: #666;">Contact us at <a href="mailto:contact@readwithacta.com">contact@readwithacta.com</a> or visit <a href="https://readwithacta.com" target="_blank" rel="noopener">readwithacta.com</a></p>
    </div>
    <?php
}

// ─── Frontend: inject Acta JS snippet ────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'acta_enqueue_frontend_script' );
function acta_enqueue_frontend_script() {
    $publisher_id = get_option( ACTA_PUBLISHER_ID_KEY, '' );
    if ( empty( $publisher_id ) ) {
        return;
    }

    $script_url = rtrim( ACTA_BACKEND_URL, '/' ) . '/api/v1/public/static/' . urlencode( $publisher_id ) . '.js';
    // Version is false so WordPress does not append ?ver= — the external server manages caching.
    wp_enqueue_script( 'acta-frontend', $script_url, array(), false, false );
}

// Add crossorigin="anonymous" to the Acta frontend script tag.
add_filter( 'script_loader_tag', 'acta_add_crossorigin_attr', 10, 2 );
function acta_add_crossorigin_attr( $tag, $handle ) {
    if ( 'acta-frontend' === $handle ) {
        return str_replace( ' src=', ' crossorigin="anonymous" src=', $tag );
    }
    return $tag;
}

// ─── REST API endpoint ────────────────────────────────────────────────────────

add_action( 'rest_api_init', 'acta_register_routes' );
function acta_register_routes() {
    register_rest_route( 'acta/v1', '/content', array(
        'methods'             => 'GET',
        'callback'            => 'acta_get_content',
        'permission_callback' => 'acta_verify_secret',
        'args'                => array(
            'slug' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'key' => array(
                'required' => true,
                'type'     => 'string',
            ),
        ),
    ) );
}

/**
 * Verify the shared secret key sent in the request.
 * This is our auth layer — only Acta backend (which knows the key) can call this.
 */
function acta_verify_secret( WP_REST_Request $request ) {
    $secret = get_option( ACTA_OPTION_KEY, '' );
    if ( empty( $secret ) ) {
        return new WP_Error( 'acta_not_configured', 'Acta plugin not configured.', array( 'status' => 503 ) );
    }

    $provided = $request->get_param( 'key' );
    // Constant-time comparison to prevent timing attacks
    if ( ! hash_equals( $secret, (string) $provided ) ) {
        return new WP_Error( 'acta_unauthorized', 'Invalid secret key.', array( 'status' => 401 ) );
    }

    return true;
}

/**
 * Main content delivery callback.
 * Gets raw post content from DB, strips all paywall markers, then renders via the_content filter.
 */
function acta_get_content( WP_REST_Request $request ) {
    $slug = $request->get_param( 'slug' );
    $debug = $request->get_param( 'debug' ) === '1';

    // Try post types in order: post, page, then any custom post type
    $post = acta_get_post_by_slug( $slug, array( 'post', 'page' ) );

    if ( ! $post ) {
        return new WP_Error( 'acta_not_found', 'Post not found for slug: ' . $slug, array( 'status' => 404 ) );
    }

    // Get the raw content from the database (no filters applied yet)
    $raw_content = $post->post_content;

    // Strip all known paywall markers from raw content
    $clean_content = acta_strip_paywall_markers( $raw_content );

    // In debug mode, return raw content at each stage to help diagnose issues
    if ( $debug ) {
        // Collect all the_content filter hooks BEFORE removal
        global $wp_filter;
        $content_hooks_before = array();
        if ( isset( $wp_filter['the_content'] ) ) {
            foreach ( $wp_filter['the_content']->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    $func = $callback['function'];
                    if ( is_string( $func ) ) {
                        $content_hooks_before[] = $priority . ': ' . $func;
                    } elseif ( is_array( $func ) && isset( $func[1] ) ) {
                        $class = is_object( $func[0] ) ? get_class( $func[0] ) : (string) $func[0];
                        $content_hooks_before[] = $priority . ': ' . $class . '::' . $func[1];
                    } elseif ( $func instanceof Closure ) {
                        $content_hooks_before[] = $priority . ': {closure}';
                    }
                }
            }
        }

        // Run the full filtering pipeline (same as non-debug mode)
        $final_content = acta_apply_content_filters( $clean_content, $post->ID );

        return new WP_REST_Response( array(
            'version'           => ACTA_PLUGIN_VERSION,
            'slug'              => $slug,
            'id'                => $post->ID,
            'status'            => $post->post_status,
            'raw_content'       => $raw_content,
            'raw_length'        => strlen( $raw_content ),
            'stripped_content'  => $clean_content,
            'stripped_length'   => strlen( $clean_content ),
            'final_content'     => $final_content,
            'final_length'      => strlen( $final_content ),
            'has_paywall_marker'=> (bool) preg_match( '/wp:jetpack\/paywall/', $raw_content ),
            'content_hooks'     => $content_hooks_before,
        ), 200 );
    }

    // Apply the_content filters (image sizing, shortcodes, embeds, etc.)
    // but NOT the paywall filters (they've been stripped above)
    $clean_content = acta_apply_content_filters( $clean_content, $post->ID );

    return new WP_REST_Response( array(
        'slug'    => $slug,
        'id'      => $post->ID,
        'status'  => $post->post_status,
        'content' => $clean_content,
        'version' => ACTA_PLUGIN_VERSION,
    ), 200 );
}

/**
 * Find a post by slug across multiple post types.
 */
function acta_get_post_by_slug( $slug, $post_types = array( 'post', 'page' ) ) {
    $args = array(
        'name'           => $slug,
        'post_type'      => $post_types,
        'post_status'    => array( 'publish', 'private' ),
        'posts_per_page' => 1,
    );

    $posts = get_posts( $args );
    return ! empty( $posts ) ? $posts[0] : null;
}

/**
 * Strip all known paywall markers from raw Gutenberg/classic post content.
 */
function acta_strip_paywall_markers( $content ) {
    // ── Jetpack / WordPress.com
    $content = preg_replace( '/<!--\s*wp:jetpack\/paywall\s*\/-->/', '', $content );
    $content = preg_replace( '/<!--\s*wp:jetpack\/paid-content\b[^>]*-->[\s\S]*?<!--\s*\/wp:jetpack\/paid-content\s*-->/', '', $content );

    // ── MemberPress
    $content = preg_replace_callback(
        '/\[mepr-active[^\]]*\]([\s\S]*?)\[\/mepr-active\]/i',
        function( $m ) { return $m[1]; },
        $content
    );
    $content = preg_replace( '/\[mepr-unauthorized[^\]]*\][\s\S]*?\[\/mepr-unauthorized\]/i', '', $content );

    // ── Restrict Content Pro
    $content = preg_replace_callback(
        '/\[restrict[^\]]*\]([\s\S]*?)\[\/restrict\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── Paid Memberships Pro
    $content = preg_replace_callback(
        '/\[membership[^\]]*\]([\s\S]*?)\[\/membership\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── WooCommerce Memberships
    $content = preg_replace_callback(
        '/\[wcm_restrict[^\]]*\]([\s\S]*?)\[\/wcm_restrict\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── Paid Member Subscriptions
    $content = preg_replace_callback(
        '/\[pms-content[^\]]*\]([\s\S]*?)\[\/pms-content\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── Simple Membership
    $content = preg_replace_callback(
        '/\[swpm_protected[^\]]*\]([\s\S]*?)\[\/swpm_protected\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── s2Member
    $content = preg_replace_callback(
        '/\[s2If[^\]]*\]([\s\S]*?)\[\/s2If\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── LeakyPaywall
    $content = preg_replace( '/\[leaky_paywall_content\][\s\S]*?\[\/leaky_paywall_content\]/i', '', $content );

    // ── <!--more--> based paywalls
    if ( acta_uses_more_tag_as_gate() ) {
        $content = str_replace( '<!--more-->', '', $content );
        $content = str_replace( '<!--More-->', '', $content );
    }

    return $content;
}

/**
 * Detect whether any active plugin uses <!--more--> as its paywall gate.
 */
function acta_uses_more_tag_as_gate() {
    $more_gate_plugins = array(
        'paid-memberships-pro/paid-memberships-pro.php',
        'woocommerce-memberships/woocommerce-memberships.php',
        'subscriptions-for-woocommerce/subscriptions-for-woocommerce.php',
    );

    foreach ( $more_gate_plugins as $plugin ) {
        if ( is_plugin_active( $plugin ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Apply WordPress content filters while temporarily suppressing paywall re-gating.
 */
function acta_apply_content_filters( $content, $post_id ) {
    $removed_hooks = acta_remove_paywall_hooks();

    global $post;
    $original_post = $post;
    $post = get_post( $post_id );
    setup_postdata( $post );

    $rendered = apply_filters( 'the_content', $content );

    $post = $original_post;
    if ( $original_post ) {
        setup_postdata( $original_post );
    } else {
        wp_reset_postdata();
    }

    acta_restore_paywall_hooks( $removed_hooks );

    return $rendered;
}

/**
 * Remove known paywall plugin filter hooks from the_content.
 * Returns the removed hooks so they can be restored.
 */
function acta_remove_paywall_hooks() {
    global $wp_filter;
    $removed = array();

    if ( ! isset( $wp_filter['the_content'] ) ) {
        return $removed;
    }

    $method_patterns = array(
        'add_paywall',
        'jetpack_memberships_protect_content',
        'jetpack_subscriptions_protect_content',
        'mepr_content_protection',
        'meprContentProtection',
        'rcp_restrict_content',
        'rcp_post_content_filter',
        'pmpro_the_content',
        'pmpro_protect_content',
        'wc_memberships_restrict_content',
        'pms_restrict_content',
        'swpm_the_content_filter',
        's2member_the_content_filter',
        'leaky_paywall_content_filter',
        'um_restrict_content',
        'sumo_restrict_content',
    );

    $class_patterns = array(
        'Jetpack\\Extensions\\Subscriptions',
        'Jetpack\\Memberships',
        'MeprContent',
        'RCP_Content',
        'PMPro',
        'WC_Memberships',
    );

    foreach ( $wp_filter['the_content']->callbacks as $priority => $callbacks ) {
        foreach ( $callbacks as $key => $callback ) {
            $func = $callback['function'];
            $func_name = '';
            $class_name = '';
            $should_remove = false;

            if ( is_string( $func ) ) {
                $func_name = $func;
            } elseif ( is_array( $func ) && isset( $func[1] ) ) {
                $func_name = is_string( $func[1] ) ? $func[1] : '';
                $class_name = is_object( $func[0] ) ? get_class( $func[0] ) : (string) $func[0];
            }

            foreach ( $method_patterns as $pattern ) {
                if ( stripos( $func_name, $pattern ) !== false ) {
                    $should_remove = true;
                    break;
                }
            }

            if ( ! $should_remove && $class_name ) {
                foreach ( $class_patterns as $pattern ) {
                    if ( stripos( $class_name, $pattern ) !== false ) {
                        $should_remove = true;
                        break;
                    }
                }
            }

            if ( $should_remove ) {
                $removed[] = array(
                    'priority' => $priority,
                    'callback' => $func,
                    'accepted_args' => $callback['accepted_args'],
                );
                remove_filter( 'the_content', $func, $priority );
            }
        }
    }

    return $removed;
}

/**
 * Restore previously removed paywall filter hooks.
 */
function acta_restore_paywall_hooks( $removed_hooks ) {
    foreach ( $removed_hooks as $hook ) {
        add_filter( 'the_content', $hook['callback'], $hook['priority'], $hook['accepted_args'] );
    }
}
