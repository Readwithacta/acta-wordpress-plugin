<?php
/**
 * Plugin Name: Acta
 * Plugin URI:  https://readwithacta.com
 * Description: Acta embeds a seamless checkout directly inside your content. Readers can unlock premium articles, lessons, files, and more using credit/debit cards, Apple Pay, or Google Pay — in seconds.
 * Version:     2.0.1
 * Author:      Acta
 * Author URI:  https://readwithacta.com
 * License:     GPL-2.0+
 * Text Domain: acta-content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// ─── Auto-updates ─────────────────────────────────────────────────────────────
// Checks GitHub Releases for new versions and silently installs them.
// Remove this block once the plugin is published on WordPress.org.

require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$acta_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Readwithacta/acta-wordpress-plugin/',
    __FILE__,
    'acta-content'
);
$acta_update_checker->getVcsApi()->enableReleaseAssets();

// Force silent background auto-updates — no publisher action needed.
// This filter stays even after moving to WordPress.org.
add_filter( 'auto_update_plugin', function( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'acta-content' ) {
        return true;
    }
    return $update;
}, 10, 2 );

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'ACTA_PLUGIN_VERSION', '2.0.1' );
define( 'ACTA_OPTION_KEY', 'acta_secret_key' );
define( 'ACTA_PUBLISHER_ID_KEY', 'acta_publisher_id' );
define( 'ACTA_STRIPE_URL_KEY', 'acta_stripe_url' );
define( 'ACTA_CONNECTION_STATUS', 'acta_connection_status' ); // not_registered | registered | live
define( 'ACTA_ADMIN_PAGE_SLUG', 'acta-content' );
define( 'ACTA_ACTIVATION_REDIRECT_OPTION', 'acta_do_activation_redirect' );

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
        $email         = sanitize_email( $_POST['acta_email'] ?? '' );
        $first_name    = sanitize_text_field( $_POST['acta_first_name'] ?? '' );
        $last_name     = sanitize_text_field( $_POST['acta_last_name'] ?? '' );
        $country       = sanitize_text_field( $_POST['acta_country'] ?? '' );
        $currency      = sanitize_text_field( $_POST['acta_currency'] ?? '' );
        $article_price = floatval( $_POST['acta_article_price'] ?? 0 );

        if ( empty( $email ) || empty( $first_name ) || empty( $last_name ) || empty( $country ) || empty( $currency ) || $article_price <= 0 ) {
            echo '<div class="notice notice-error"><p>All fields are required. Please fill in your email, name, country, currency, and a price greater than 0.</p></div>';
        } else {
            $result = acta_onboard_to_backend( $email, $first_name, $last_name, $country, $currency, $article_price );

            if ( $result['success'] ) {
                update_option( ACTA_PUBLISHER_ID_KEY, $result['publisherId'] );
                update_option( ACTA_STRIPE_URL_KEY, $result['stripeOnboardingUrl'] );
                update_option( ACTA_CONNECTION_STATUS, 'registered' );
                $publisher_id = $result['publisherId'];
                $stripe_url   = $result['stripeOnboardingUrl'];
                $conn_status  = 'registered';
                echo '<div class="notice notice-success"><p>Successfully registered with Acta! Complete your Stripe setup below to start accepting payments.</p></div>';
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
        echo '<div class="notice notice-success"><p>Status updated. If your Stripe setup is complete, you\'re live!</p></div>';
    }

    // ── Render the settings page ─────────────────────────────────────────────
    $countries  = acta_get_supported_countries();
    $currencies = acta_get_supported_currencies();
    ?>
    <div class="wrap">
        <h1>Acta Settings</h1>
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
                    <div style="position: relative;">
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">
                            <button type="button" class="button button-secondary acta-copy-price-btn">Copy</button>
                        </div>
                        <pre style="background: #1d2327; color: #f0f0f1; padding: 12px 16px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.6;">&lt;div id="acta-price" data-price="<strong style="color: #f0c674;">ENTER_PRICE_HERE</strong>"&gt;&lt;/div&gt;</pre>
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
                                        btn.textContent = 'Copied!';
                                        setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
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
                                    btn.textContent = 'Copied!';
                                    setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
                                }
                            });
                        }
                    })();
                    </script>
                </div>
            </div>
        <?php endif; ?>

        <p style="margin-top: 32px; color: #666;">Contact us at <a href="mailto:contact@readwithacta.com">contact@readwithacta.com</a> or visit <a href="https://readwithacta.com" target="_blank" rel="noopener">readwithacta.com</a></p>
    </div>
    <?php
}

// ─── Frontend: inject Acta JS snippet ────────────────────────────────────────

add_action( 'wp_head', 'acta_enqueue_frontend_script' );
function acta_enqueue_frontend_script() {
    $publisher_id = get_option( ACTA_PUBLISHER_ID_KEY, '' );
    if ( empty( $publisher_id ) ) {
        return;
    }

    $script_url = rtrim( ACTA_BACKEND_URL, '/' ) . '/api/v1/public/static/' . urlencode( $publisher_id ) . '.js';
    echo '<script src="' . esc_url( $script_url ) . '" crossorigin="anonymous"></script>' . "\n";
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
