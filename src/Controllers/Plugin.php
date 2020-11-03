<?php
/**
 * Plugin auto update class
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Pixolette\AutoUpdate\Controllers;

final class Plugin
{

    /**
     * @var array|string[]
     */
    private static array $data = [
        'api_url' => '',
        'plugin_full_path' => '',
    ];

    /**
     * @var array
     */
    private static array $error_messages = [];

    public function init( array $args )
    {
        self::$data = array_merge( self::$data, $args );

        // Make sure all needed data is defined.
        if ( empty( $args['api_url'] ) ) {
            self::$error_messages[] = 'The "api_url" param is required in Pixolette\AutoUpdate\Loader class.';
        }
        if ( empty( $args['plugin_full_path'] ) ) {
            self::$error_messages[] = 'The "plugin_full_path" param is required in Pixolette\AutoUpdate\Loader class.';
        }

        // Make sure we'll display error messages only in admin.
        if ( ! empty( self::$error_messages ) && is_admin() && current_user_can( 'administrator' ) ) {
            add_action( 'admin_notices', [ $this, 'actionAdminNotice' ] );
        }
    }

    public function actionAdminNotice()
    {
        $class = 'notice notice-error';
        $message = '';
        foreach ( self::$error_messages as $error_message ) {
            $message .= '<p>' . $error_message . '</p>';
        }

        printf( '<div class="%1$s">%2$s</div>', esc_attr( $class ), esc_html( $message ) );
    }

}