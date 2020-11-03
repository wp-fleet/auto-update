<?php
/**
 * Loader class
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Pixolette\AutoUpdate;

final class Loader
{

    /**
     * Loader constructor.
     *
     * @param array $args
     * @param string $product_type
     */
    public function __construct( array $args = [], string $product_type = 'plugin' )
    {
        if ( ! defined( 'ABSPATH' ) || empty( $args ) ) {
            return;
        }

        switch ( $product_type ) {
            case 'plugin':
                ( new Controllers\Plugin() )->init( $args );
                break;
        }
    }
}