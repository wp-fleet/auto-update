<?php
/**
 * Plugin auto update class
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace WpFleet\AutoUpdate\Controllers;

final class Plugin
{
    /**
     * @var string
     */
    private static string $transient_prefix = 'wp_fleet_auto_update_';

    /**
     * @var array|string[]
     */
    private static array $data = [
        'api_url' => '',
        'plugin_full_path' => '',
        'allowed_hosts' => '',
        'licence_key' => false,
    ];

    /**
     * @var array
     */
    private static array $error_messages = [];

    /**
     * @var string
     */
    private static string $plugin_path = '';

    /**
     * @var string
     */
    private static string $plugin_basename = '';

    /**
     * @var array
     */
    private static array $plugin_data = [];

    /**
     * @var string
     */
    private static string $license_key = '';

    /**
     * @var int
     */
    private static int $transient_validity = 12;

    /**
     * Function to init package functionality
     *
     * @param array $args
     * @param string $license_key
     */
    public function init( array $args, string $license_key = '' ) : void
    {
        self::$data = array_merge( self::$data, $args );

        // Make sure all needed data is defined.
        if ( empty( $args['api_url'] ) ) {
            self::$error_messages[] = 'The "api_url" param is required in Pixolette\AutoUpdate\Loader class.';
        }
        if ( empty( $args['plugin_full_path'] ) ) {
            self::$error_messages[] = 'The "plugin_full_path" param is required in Pixolette\AutoUpdate\Loader class.';
        }

        // Make sure we'll display error messages only in admin. Bail early if error.
        if ( ! empty( self::$error_messages ) && is_admin() && current_user_can( 'administrator' ) ) {
            add_action( 'admin_notices', [ $this, 'actionAdminNotice' ] );
            return;
        }

        self::$license_key = $license_key;
        if ( ! empty( $args['transient_validity'] ) && 0 < $args['transient_validity'] ) {
            self::$transient_validity = (int) $args['transient_validity'];
        }

        ( new LicenseKey() )->init( self::$data );

        $this->setupPluginData();
        $this->setupActionsAndFilters();
    }

    private function setupPluginData() : void
    {
        self::$plugin_path = plugin_dir_path( self::$data['plugin_full_path'] );
        self::$plugin_basename = plugin_basename( self::$data['plugin_full_path'] );

        // Bail early if plugin is not active
        if ( ! $this->currentPluginIsActive() ) {
            return;
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        self::$plugin_data = get_plugin_data( self::$data['plugin_full_path'] );
    }

    /**
     * Function to register actions and filters
     */
    private function setupActionsAndFilters() : void
    {
        if ( ! empty( self::$plugin_data ) ) {

            // Admin action to display message.
            if ( is_admin() ) {
                add_action( 'in_plugin_update_message-' . self::$plugin_basename, [ $this, 'actionCustomPluginUpdateMessage' ], 10, 2 );
            }

            // Append update information to transient.
            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'filterCustomPluginsTransient' ], 10, 1 );

            // Modify plugin data visible in the 'View details' popup.
            add_filter( 'plugins_api', [ $this, 'filterGetRemotePluginDetails' ], 10, 3 );

            add_filter( 'http_request_host_is_external', [ $this, 'filterAllowPluginUpdateFromCustomHost' ], 10, 3 );
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

        return is_plugin_active( self::$plugin_basename );
    }

    /**
     * Function to get transient name.
     *
     * @param string $transient
     * @return string
     */
    private function getTransientName( string $transient = 'plugin_info' ) : string
    {
        return self::$transient_prefix . $transient . '_' . self::$plugin_basename;
    }

    /**
     * Function to display admin messages.
     */
    public function actionAdminNotice() : void
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
        if ( ! isset( $transient->response ) ) {
            return $transient;
        }

        // Get the remote version.
        $remote_plugin_data = $this->getRemotePluginData();
//        print_r('<pre>');
//        print_r($remote_plugin_data);
//        die;
        if (!is_wp_error($remote_plugin_data) && isset($remote_plugin_data->new_version) && $remote_plugin_data->new_version) {

            // If a newer version is available, add the update.
            if ( version_compare( self::$plugin_data['Version'], $remote_plugin_data->new_version, '<' ) ) {
                $transient->response[ self::$plugin_basename ] = $remote_plugin_data;
            }
        }

        return $transient;
    }

    /**
     * Function to get the product remote data
     *
     * @return mixed $transient
     */
    public function getRemotePluginData()
    {
        $transient_name = $this->getTransientName();

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
            'license-code' => self::$license_key,
            'product-slug' => self::$plugin_basename,
            'product-name' => self::$plugin_data['Name'],
            'website' => home_url(),
        ];

        $response = $this->request( $update_data );

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

        if ( $args->slug == self::$plugin_basename ) {
            $plugin = true;
        }

        if ( ! $plugin ) {
            return $result;
        }

        $transient_name = self::getTransientName( 'plugin_details' );

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
            'license-code' => self::$license_key,
            'product-slug' => self::$plugin_basename,
            'product-name' => self::$plugin_data['Name'],
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
        if ( ! empty( self::$data['allowed_hosts'] ) && in_array( $host, self::$data['allowed_hosts'] ) ) {
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
    public function actionCustomPluginUpdateMessage( $plugin_data, $response ) : void
    {
        // TODO: implement functionality

//        $license_key_status = $this->getLicenseKeyStatus();
//
//        if (isset($license_key_status['license-code']) && isset($license_key_status['license-status']) && $license_key_status['license-status'] == 'valid' && $license_key_status['license-code'] == 'valid') {
//            return;
//        }
//        if ('invalid' == $license_key_status['license-code']) {
//            echo '<br />' . sprintf( __('The Purchase Code you\'ve submitted is not valid. If you don\'t have a Purchase Code, please see <a href="%s">details & pricing</a>.', 'pixolette-product'), 'https://wp.pixolette.com/wordpress-plugins/' );
//        } elseif ('expired' == $license_key_status['license-status']) {
//            echo '<br />' . sprintf( __('The Purchase Code you\'ve submitted is expired. You can update the plugin, but can\'t get support. If you want to renew the support, please see <a href="%s">details & pricing</a>.', 'pixolette-product'), 'https://wp.pixolette.com/wordpress-plugins/' );
//        } else {
//            echo '<br />' . sprintf( __('To enable updates, please enter Envato Purchase Code on the plugin page. If you don\'t have a Purchase Code, please see <a href="%s">details & pricing</a>.', 'pixolette-product'), 'https://wp.pixolette.com/wordpress-plugins/' );
//        }
    }

}