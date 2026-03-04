<?php
/**
 * Plugin Name: Acta
 * Plugin URI:  https://readwithacta.com
 * Description: Acta embeds a seamless checkout directly inside your content. Readers can unlock premium articles, lessons, files, and more using credit/debit cards, Apple Pay, or Google Pay — in seconds.
 * Version:     1.1.0
 * Author:      Acta
 * Author URI:  https://readwithacta.com
 * License:     GPL-2.0+
 * Text Domain: acta-content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'ACTA_PLUGIN_VERSION', '1.1.0' );
define( 'ACTA_OPTION_KEY', 'acta_secret_key' );

// ─── Activation: generate a secret key ───────────────────────────────────────

register_activation_hook( __FILE__, 'acta_activate' );
function acta_activate() {
    if ( ! get_option( ACTA_OPTION_KEY ) ) {
        // Generate a 32-byte random secret key on first activation
        update_option( ACTA_OPTION_KEY, bin2hex( random_bytes( 32 ) ) );
    }
}

// ─── Admin settings page ─────────────────────────────────────────────────────

add_action( 'admin_menu', 'acta_add_admin_menu' );
function acta_add_admin_menu() {
    add_options_page(
        'Acta Content Delivery',
        'Acta',
        'manage_options',
        'acta-content',
        'acta_settings_page'
    );
}

add_action( 'admin_init', 'acta_settings_init' );
function acta_settings_init() {
    register_setting( 'acta_settings', ACTA_OPTION_KEY );
}

function acta_settings_page() {
    $secret = get_option( ACTA_OPTION_KEY, '' );
    $endpoint = rest_url( 'acta/v1/content' );
    ?>
    <div class="wrap">
        <h1>Acta Content Delivery</h1>
        <p>Version <?php echo esc_html( ACTA_PLUGIN_VERSION ); ?> — Copy the values below into your Acta publisher settings.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Plugin Endpoint URL</th>
                <td>
                    <code><?php echo esc_html( $endpoint ); ?></code>
                    <p class="description">Enter this as the "Plugin Endpoint" in Acta admin.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Secret Key</th>
                <td>
                    <code><?php echo esc_html( $secret ); ?></code>
                    <p class="description">Enter this as the "Plugin Secret Key" in Acta admin.</p>
                    <form method="post" action="" style="margin-top:8px;">
                        <?php wp_nonce_field( 'acta_regenerate_key' ); ?>
                        <input type="hidden" name="acta_action" value="regenerate_key">
                        <input type="submit" class="button button-secondary" value="Regenerate Key"
                               onclick="return confirm('Regenerate the secret key? You will need to update it in Acta admin.');">
                    </form>
                    <?php
                    // Handle key regeneration
                    if (
                        isset( $_POST['acta_action'] ) &&
                        $_POST['acta_action'] === 'regenerate_key' &&
                        check_admin_referer( 'acta_regenerate_key' )
                    ) {
                        update_option( ACTA_OPTION_KEY, bin2hex( random_bytes( 32 ) ) );
                        echo '<div class="notice notice-success"><p>Secret key regenerated.</p></div>';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
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
    // We temporarily remove known paywall filter hooks to prevent them re-gating the content
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
 *
 * Each paywall plugin stores a marker in the raw post content.
 * We strip these markers so the_content filter won't re-gate the content.
 *
 * Supported plugins:
 * - Jetpack Subscriptions (paywall block)
 * - MemberPress
 * - Restrict Content Pro
 * - Paid Memberships Pro
 * - WooCommerce Memberships
 * - Paid Member Subscriptions
 * - Ultimate Member
 * - Simple Membership
 * - s2Member
 */
function acta_strip_paywall_markers( $content ) {
    // ── Jetpack / WordPress.com ──────────────────────────────────────────────
    // Block marker: <!-- wp:jetpack/paywall /-->
    $content = preg_replace( '/<!--\s*wp:jetpack\/paywall\s*\/-->/', '', $content );
    // Jetpack paid-content wrapper blocks
    $content = preg_replace( '/<!--\s*wp:jetpack\/paid-content\b[^>]*-->[\s\S]*?<!--\s*\/wp:jetpack\/paid-content\s*-->/', '', $content );

    // ── MemberPress ──────────────────────────────────────────────────────────
    // [mepr-active memberships="123"] ... [/mepr-active]  →  keep inner content
    $content = preg_replace_callback(
        '/\[mepr-active[^\]]*\]([\s\S]*?)\[\/mepr-active\]/i',
        function( $m ) { return $m[1]; },
        $content
    );
    // [mepr-unauthorized] ... [/mepr-unauthorized]  →  strip entirely
    $content = preg_replace( '/\[mepr-unauthorized[^\]]*\][\s\S]*?\[\/mepr-unauthorized\]/i', '', $content );

    // ── Restrict Content Pro ─────────────────────────────────────────────────
    // [restrict paid="1"] ... [/restrict]  →  keep inner content
    $content = preg_replace_callback(
        '/\[restrict[^\]]*\]([\s\S]*?)\[\/restrict\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── Paid Memberships Pro ─────────────────────────────────────────────────
    // [membership level="1"] ... [/membership]  →  keep inner content
    $content = preg_replace_callback(
        '/\[membership[^\]]*\]([\s\S]*?)\[\/membership\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── WooCommerce Memberships ──────────────────────────────────────────────
    // [wcm_restrict] ... [/wcm_restrict]  →  keep inner content
    $content = preg_replace_callback(
        '/\[wcm_restrict[^\]]*\]([\s\S]*?)\[\/wcm_restrict\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── Paid Member Subscriptions ────────────────────────────────────────────
    // [pms-content subscription_plans="1"] ... [/pms-content]  →  keep inner
    $content = preg_replace_callback(
        '/\[pms-content[^\]]*\]([\s\S]*?)\[\/pms-content\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── Simple Membership ────────────────────────────────────────────────────
    // [swpm_protected] ... [/swpm_protected]  →  keep inner content
    $content = preg_replace_callback(
        '/\[swpm_protected[^\]]*\]([\s\S]*?)\[\/swpm_protected\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── s2Member ─────────────────────────────────────────────────────────────
    // [s2If current_user_can("access_s2member_level1")] ... [/s2If]  →  keep inner
    $content = preg_replace_callback(
        '/\[s2If[^\]]*\]([\s\S]*?)\[\/s2If\]/i',
        function( $m ) { return $m[1]; },
        $content
    );

    // ── LeakeyPaywall ────────────────────────────────────────────────────────
    $content = preg_replace( '/\[leaky_paywall_content\][\s\S]*?\[\/leaky_paywall_content\]/i', '', $content );

    // ── <!--more--> based paywalls (PMPro, WCFM, etc.) ──────────────────────
    // When a paywall uses <!--more--> as the gate, content after <!--more--> is
    // only shown to members. We remove the <!--more--> tag so all content flows.
    // Note: this only applies when the paywall plugin uses <!--more--> as a gate.
    // We detect this by checking if the plugin is active. If not, we leave <!--more--> alone
    // so normal "read more" behaviour works for non-paywall posts.
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
 *
 * We remove known paywall plugin filter hooks before applying the_content,
 * then restore them afterward. This ensures embeds, images, shortcodes all
 * render correctly, but paywall plugins can't strip content again.
 */
function acta_apply_content_filters( $content, $post_id ) {
    // Store and remove paywall filter hooks
    $removed_hooks = acta_remove_paywall_hooks();

    // Set global $post so shortcodes and filters have the right context
    global $post;
    $original_post = $post;
    $post = get_post( $post_id );
    setup_postdata( $post );

    // Apply standard content filters (images, embeds, shortcodes, etc.)
    $rendered = apply_filters( 'the_content', $content );

    // Restore global post
    $post = $original_post;
    if ( $original_post ) {
        setup_postdata( $original_post );
    } else {
        wp_reset_postdata();
    }

    // Restore removed paywall hooks
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

    // Method name patterns to match paywall plugin callbacks
    $method_patterns = array(
        // Jetpack (WordPress.com) — actual hook: Automattic\Jetpack\Extensions\Subscriptions::add_paywall
        'add_paywall',
        'jetpack_memberships_protect_content',
        'jetpack_subscriptions_protect_content',
        // MemberPress
        'mepr_content_protection',
        'meprContentProtection',
        // Restrict Content Pro
        'rcp_restrict_content',
        'rcp_post_content_filter',
        // Paid Memberships Pro
        'pmpro_the_content',
        'pmpro_protect_content',
        // WooCommerce Memberships
        'wc_memberships_restrict_content',
        // Paid Member Subscriptions
        'pms_restrict_content',
        // Simple Membership
        'swpm_the_content_filter',
        // s2Member
        's2member_the_content_filter',
        // LeakyPaywall
        'leaky_paywall_content_filter',
        // Ultimate Member
        'um_restrict_content',
        // Subscriptions for WooCommerce
        'sumo_restrict_content',
    );

    // Class name patterns — match when the callback's class contains one of these strings
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

            // Check method name patterns
            foreach ( $method_patterns as $pattern ) {
                if ( stripos( $func_name, $pattern ) !== false ) {
                    $should_remove = true;
                    break;
                }
            }

            // Check class name patterns (for class-based callbacks)
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
