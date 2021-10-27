<?php
/**
 * Plugin auto update class
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace WpFleet\AutoUpdate\Controllers;

#use WpFleet\Models\LicenceKey;
use WpFleet\AutoUpdate\Controllers\LicenseKey;

final class Plugin
{
    /**
     * @var string
     */
    private static $transient_prefix = 'wp_fleet_auto_update_';

    /**
     * @var array|string[]
     */
    private static $data = [
        'api_url' => '',
        'plugin_full_path' => '',
        'allowed_hosts' => '',
        'license_key' => false,
        'plugin_path' => '',
        'plugin_basename' => '',
        'plugin_data' => [],
    ];

    /**
     * @var array|string[]
     */
    private static $all_plugins_data = [];

    /**
     * @var array
     */
    private static $error_messages = [];

    /**
     * @var string
     */
    private static $plugin_basename = '';

    /**
     * @var int
     */
    private static $transient_validity = 12;

    /**
     * Function to init package functionality
     *
     * @param array $args
     * @param string $license_key
     */
    public function init( array $args, string $license_key = '' )
    {
        self::$data = array_merge( self::$data, $args );
        self::$data['api_url'] = trailingslashit( self::$data['api_url'] ) . 'wp-json/wp-fleet/v1/plugin/';
        if ( empty( self::$data['allowed_hosts'] ) ) {
            self::$data['allowed_hosts'] = [
                $args['api_url']
            ];
        } else {
            if ( ! is_array( self::$data['allowed_hosts'] ) ) {
                self::$data['allowed_hosts'] = [ self::$data['allowed_hosts'] ];
            }
            if ( ! in_array( $args['api_url'], self::$data['allowed_hosts'] ) ) {
                self::$data['allowed_hosts'][] = $args['api_url'];
            }
        }

        self::$data['plugin_basename'] = plugin_basename( self::$data['plugin_full_path'] );
        self::$all_plugins_data[ self::$data['plugin_basename'] ] = self::$data;

        // Make sure all needed data is defined.
        if ( empty( $args['api_url'] ) ) {
            self::$error_messages[] = 'The "api_url" param is required in WpFleet\AutoUpdate\Loader class.';
        }
        if ( empty( $args['plugin_full_path'] ) ) {
            self::$error_messages[] = 'The "plugin_full_path" param is required in WpFleet\AutoUpdate\Loader class.';
        }

        // Make sure we'll display error messages only in admin. Bail early if error.
        if ( ! empty( self::$error_messages ) && is_admin() && current_user_can( 'administrator' ) ) {
            add_action( 'admin_notices', [ $this, 'actionAdminNotice' ] );
            return;
        }

        if ( in_array( $args['license_key'], [ 'required', true, 1 ] ) ) {
            self::$all_plugins_data[ self::$data['plugin_basename'] ]['license_key'] = LicenseKey::getLicenseKey( self::$data['plugin_full_path'] );
        }
        if ( ! empty( $args['transient_validity'] ) && 0 < $args['transient_validity'] ) {
            self::$transient_validity = (int) $args['transient_validity'];
        }

        ( new LicenseKey( self::$data ) )->init();

        $this->setupPluginData();
        $this->setupActionsAndFilters();

    }

    private function setupPluginData()
    {
        self::$all_plugins_data[ self::$data['plugin_basename'] ]['plugin_path'] = plugin_dir_path( self::$data['plugin_full_path'] );
        self::$all_plugins_data[ self::$data['plugin_basename'] ]['plugin_basename'] = self::$data['plugin_basename'];

        // Bail early if plugin is not active
        if ( ! $this->currentPluginIsActive() ) {
            return;
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        self::$all_plugins_data[ self::$data['plugin_basename'] ]['plugin_data'] = get_plugin_data( self::$data['plugin_full_path'] );
    }

    /**
     * Function to register actions and filters
     */
    private function setupActionsAndFilters()
    {
        if ( ! empty( self::$all_plugins_data[ self::$data['plugin_basename'] ]['plugin_data'] )
            && ! did_action( 'wp_fleet_auto_update_plugin_actions' )
        ) {
            do_action( 'wp_fleet_auto_update_plugin_actions' );

            // Append update information to transient.
            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'filterCustomPluginsTransient' ], 10, 1 );

            // Modify plugin data visible in the 'View details' popup.
            add_filter( 'plugins_api', [ $this, 'filterGetRemotePluginDetails' ], 10, 3 );

            add_filter( 'http_request_host_is_external', [ $this, 'filterAllowPluginUpdateFromCustomHost' ], 10, 3 );
        }

        // Admin action to display message.
        if ( is_admin() ) {
            add_action(
                'in_plugin_update_message-' . self::$all_plugins_data[ self::$data['plugin_basename'] ]['plugin_basename'],
                [ $this, 'actionCustomPluginUpdateMessage' ],
                10,
                2
            );
        }
    }

    /**
     * Function to check if current plugin is active
     *
     * @return bool
     */
    private function currentPluginIsActive() : bool
    {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        return is_plugin_active( self::$all_plugins_data[ self::$data['plugin_basename'] ]['plugin_basename'] );
    }

    /**
     * Function to get transient name.
     *
     * @param string $transient
     * @return string
     */
    private function getTransientName( string $transient = 'plugin_info', $plugin_basename ) : string
    {
        return self::$transient_prefix . $transient . '_' . ( $plugin_basename ?? self::$plugin_basename );
    }

    /**
     * Function to display admin messages.
     */
    public function actionAdminNotice()
    {
        $class = 'notice notice-error';
        $message = '';
        foreach ( self::$error_messages as $error_message ) {
            $message .= '<p>' . $error_message . '</p>';
        }

        printf( '<div class="%1$s">%2$s</div>', esc_attr( $class ), esc_html( $message ) );
    }

    /**
     * Function to customize the plugin transient
     *
     * @param mixed $transient
     *
     * @return mixed $transient
     */
    public function filterCustomPluginsTransient( $transient )
    {
        foreach ( self::$all_plugins_data as $plugin_full_path => $data ) {
            // Get the remote version.
            $remote_plugin_data = $this->getRemotePluginData( $plugin_full_path );

            if ( ! is_wp_error( $remote_plugin_data ) && ! empty( $remote_plugin_data->new_version ) ) {
                // If a newer version is available, add the update.
                if (version_compare($data['plugin_data']['Version'], $remote_plugin_data->new_version, '<')) {
                    $transient->response[ $data['plugin_basename'] ] = $remote_plugin_data;
                } else {
                    $transient->no_update[ $data['plugin_basename'] ] = $remote_plugin_data;
                }
            } else {
                $empty_plugin_data = (object)[
                    'id' => $data['plugin_basename'],
                    'slug' => $data['plugin_basename'],
                    'plugin' => $data['plugin_basename'],
                    'new_version' => $data['plugin_data']->Version ?? '1.0.0',
                    'url' => $data['plugin_data']->PluginURI ?? '',
                    'package' => '',
                    'icons' => [],
                    'banners' => [],
                    'banners_rtl' => [],
                    'tested' => '',
                    'requires_php' => '',
                    'compatibility' => new \stdClass(),
                ];

                $transient->no_update[ $data['plugin_basename'] ] = $empty_plugin_data;
            }
        }
        
        return $transient;
    }

    /**
     * Function to get the product remote data
     *
     * @return mixed $transient
     */
    public function getRemotePluginData( $plugin_full_path = '' )
    {
        $data = self::$all_plugins_data[ $plugin_full_path ];
        if ( empty( $data ) ) {
            return '';
        }
        $transient_name = $this->getTransientName( 'plugin_info', $data['plugin_basename'] );

        // Delete transient when force-check is used to refresh.
        if ( isset( $_GET['force-check'] ) && 1 == $_GET['force-check'] ) {
            delete_transient( $transient_name );
        }

        // Check if transient is set.
        $transient = get_transient( $transient_name );

        if ( false !== $transient ) {
            return $transient;
        }

        // data that request update
        $update_data = [
            'action' => 'wp-fleet-plugin-info',
            'license-code' => $data['license_key'],
            'product-slug' => $data['plugin_basename'],
            'product-name' => $data['plugin_data']['Name'],
            'website' => home_url(),
        ];

        $response = $this->request( $update_data );

        self::$all_plugins_data[ $data['plugin_basename'] ]['is_valid_license_key'] = (array) $response->data->is_valid_license_key;

        if ( ! is_wp_error( $response ) && ! empty( $response->success ) && 1 == $response->success && ! empty( $response->data ) ) {
            $transient = $response->data;

            // Update transient
            set_transient( $transient_name, $transient, self::$transient_validity * HOUR_IN_SECONDS );

            return $transient;
        }
    }

    /**
     * Function to get the product remote data
     *
     * @param mixed $result
     * @param mixed $action
     * @param mixed $args
     *
     * @return mixed $transient
     */
    public function filterGetRemotePluginDetails( $result, $action = null, $args = null )
    {
        $plugin = false;

        // only for 'plugin_information' action
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        $plugin_data = self::$all_plugins_data[ $args->slug ];

        if ( $args->slug == $plugin_data['plugin_basename'] ) {
            $plugin = true;
        }

        if ( ! $plugin ) {
            return $result;
        }

        $transient_name = self::getTransientName( 'plugin_details', $plugin_data['plugin_basename'] );

        // Delete transient if force-check is used to refresh.
        if ( isset( $_GET['force-check'] ) && 1 == $_GET['force-check'] ) {
            delete_transient( $transient_name );
        }

        // try transient
        $transient = get_transient( $transient_name );
        if ( false !== $transient ) {
            return $transient;
        }

        // data that request update
        $update_data = [
            'action' => 'wp-fleet-plugin-details',
            'license-code' => $plugin_data['license_key'],
            'product-slug' => $plugin_data['plugin_basename'],
            'product-name' => $plugin_data['plugin_data']['Name'],
            'website' => home_url(),
        ];

        $response = $this->request( $update_data );

        if ( ! is_wp_error( $response ) && ! empty( $response->success ) && 1 == $response->success
            && ! empty( $response->product )
        ) {
            $transient = $response->product;
            $transient->icons = (array) $transient->icons;
            $transient->banners = (array) $transient->banners;
            $transient->sections = (array) $transient->sections;

            // update transient.
            set_transient( $transient_name, $transient, self::$transient_validity * HOUR_IN_SECONDS );

            return $transient;
        }
    }

    /**
     * Function to call API and retrieve data
     *
     * @param array $body
     * @param bool $return_array
     *
     * @return mixed $json
     */
    private function request( $body = [], $return_array = false )
    {
        $raw_response = wp_remote_post(
            self::$data['api_url'],
            [
                'timeout' => 10,
                'body' => $body,
            ]
        );

        if ( is_wp_error( $raw_response ) ) {

            // wp error.
            return $raw_response;
        } elseif ( 200 != wp_remote_retrieve_response_code( $raw_response ) ) {

            // http error
            return new \WP_Error( 'server_error', wp_remote_retrieve_response_message( $raw_response ) );
        }

        return json_decode( wp_remote_retrieve_body( $raw_response ), $return_array );
    }

    /**
     * Function to allow WordPress updates from custom url
     *
     * @param bool $allow
     * @param string $host
     * @param string $url
     *
     * @return bool
     */
    public function filterAllowPluginUpdateFromCustomHost( $allow, $host, $url ) : bool
    {
        $allowed_hosts = array_map( function ( $value ) {
            return $value['allowed_hosts'];
        }, self::$all_plugins_data );

        if ( ! empty( $allowed_hosts ) && in_array( $host, $allowed_hosts ) ) {
            return true;
        }

        return $allow;
    }

    /**
     * Function to display custom message when new version is available
     *
     * @param mixed $plugin_data
     * @param mixed $response
     */
    public function actionCustomPluginUpdateMessage( $plugin_data, $response )
    {
        if ( ! empty( $plugin_data['is_valid_license_key']->status )
            && 'valid' !== $plugin_data['is_valid_license_key']->status
        ) {
            switch ( $plugin_data['is_valid_license_key']->status ) {
                case 'not valid':
                    $text = '<em> <strong>Reason: </strong> Invalid license key. <a href="' . esc_url( admin_url( 'plugins.php?page=license-keys' ) ) . '">Please submit a valid key to enable plugin updates</a>.</em>';
                    echo wp_kses_post( apply_filters( 'wp_fleet_auto_update_licence_not_valid', $text ) );
                    break;
                case 'expired':
                    $text = '<em> <strong>Reason: </strong> License key is expired. <a href="' . esc_url( admin_url( 'plugins.php?page=license-keys' ) ) . '">Please submit a valid key to enable plugin updates</a>.</em>';
                    echo wp_kses_post( apply_filters( 'wp_fleet_auto_update_licence_expired', $text ) );
                    break;
                default:
                    $text = '<em> <strong>Reason: </strong>' . $plugin_data['is_valid_license_key']->status . '</em>';
                    echo wp_kses_post( apply_filters( 'wp_fleet_auto_update_licence_error_message', $text ) );
                    break;
            }
        }
        $promo = apply_filters( 'wp_fleet_auto_update_licence_custom_message', '' );
        echo wp_kses_post( $promo );
    }

}
