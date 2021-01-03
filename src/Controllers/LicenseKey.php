<?php
/**
 * Plugin license key class
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace WpFleet\AutoUpdate\Controllers;

final class LicenseKey
{
    /**
     * @var string
     */
    private static string $meta_prefix = 'wp-fleet-plugin-license-keys';

    /**
     * @var array|string[]
     */
    private static array $data = [
        'api_url' => '',
        'plugin_full_path' => '',
        'allowed_hosts' => '',
        'license_key' => false,
    ];

    /**
     * Function to init package functionality
     *
     * @param array $args
     */
    public function init( array $args ) : void
    {
        self::$data = array_merge( self::$data, $args );

        if ( in_array( self::$data, [ 1, true, 'required' ] ) ) {
            $this->setupActionsAndFilters();
        }
    }

    /**
     * Function to register actions and filters
     */
    private function setupActionsAndFilters() : void
    {
        add_filter( 'auto_update_plugin_license_keys', [ $this, 'filterLicenseKeysPage' ] );
        add_action( 'admin_menu', [ $this, 'adminPageInit' ] );
    }

    public function filterLicenseKeysPage( array $license_keys ) : array
    {
        $license_keys[ self::$data['plugin_full_path'] ] = self::$data['plugin_name'];

        return $license_keys;
    }

    /**
     * Admin pages
     *
     * @method adminPageInit
     * @since 1.0.0
     */
    public function adminPageInit() : void
    {
        if ( ! did_action( 'wp_fleet_auto_update_license_page_displayed' ) ) {
            do_action( 'wp_fleet_auto_update_license_page_displayed' );

            add_submenu_page(
                'plugins.php',
                esc_html__('License Keys', 'wp-fleet'),
                esc_html__('License Keys', 'wp-fleet'),
                'manage_options',
                'license-keys',
                [$this, 'licenseKeysPage'],
                9
            );
        }
    }

    /**
     * License keys manage page
     */
    public function licenseKeysPage() : void
    {
        if ( ! empty( $_POST['action'] )
            && 'wp-fleet-plugin-save-keys' === $_POST['action']
            && ! empty( $_POST['license-keys'] )
        ) {
            if ( ! isset( $_POST['wp-fleet-plugin-save-keys-nonce'] )
                || !wp_verify_nonce( $_POST['wp-fleet-plugin-save-keys-nonce'], 'wp-fleet-plugin-save-keys-nonce' ) ) {
                esc_html_e( 'Invalid nonce.', 'wp-fleet' );
                exit;
            }
            self::updateLicenseKeys( $_POST['license-keys'] );
        }

        $fields = apply_filters( 'auto_update_plugin_license_keys', [] );

        $license_keys = self::getLicenseKeys();

        // include view file.
        include  dirname( __DIR__ ) . '/Views/license-keys.php';
    }

    /**
     * Save license key
     *
     * @param array $data
     */
    public static function updateLicenseKeys( array $data ) : void
    {
        update_option( self::$meta_prefix, $data );
    }

    /**
     * Get license keys
     *
     * @return array|null
     */
    public static function getLicenseKeys() : ?array
    {
        return get_option( self::$meta_prefix, [] );
    }

    /**
     * Get license key
     *
     * @param string $plugin
     *
     * @return string|null
     */
    public static function getLicenseKey( string $plugin ) : ?string
    {
        $keys = get_option( self::$meta_prefix, [] );

        return $keys[ $plugin ] ?? '';
    }

}