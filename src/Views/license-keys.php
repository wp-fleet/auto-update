<?php
/**
 * Backend view to manage license keys
 */

$queried_object = get_queried_object();
?>
<div class="wrap">
    <h1><?php esc_html_e( 'License Key', 'wp-fleet' ); ?></h1>
    <?php
    if ( ! empty( $fields ) ) :
        if ( !is_array( $fields ) ) :
            $fields = (array) $fields;
        endif;
        ?>
        <form method="post" action="">
            <table class="form-table">
                <?php foreach ( $fields as $key => $field ) : ?>
                    <?php
                    if( ! empty( $field['license_page_parent_slug'] ) && ! empty( $_REQUEST['page'] ) ) {
                        $slugs = $this->getPageSlugs( $field );
                        if( ! empty( $slugs['page_slug'] ) && $_REQUEST['page'] !== $slugs['page_slug'] ) {
                            continue;
                        }
                    }
                    if( empty( $field['license_page_parent_slug'] ) && ! empty( $_REQUEST['page'] ) ) {
                        $slugs = $this->getDefaultPageSlugs();
                        if( ! empty( $slugs['page_slug'] ) && $_REQUEST['page'] !== $slugs['page_slug'] ) {
                            continue;
                        }
                    }
                    $plugin_data = get_plugin_data( $key );
                    ?>
                    <?php if ( ! empty( $field['license_page_description'] ) ) : ?>
                        <tr>
                            <th colspan="2">
                                <?php echo esc_html( $field['license_page_description'] ) ?>
                            </th>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row">
                            <label for="wp-fleet-<?php echo esc_attr( $key ); ?>"><?php echo esc_html(
                                    $plugin_data['Name'] ?? $field ); ?></label>
                        </th>
                        <td>
                            <input name="license-keys[<?php echo esc_attr( $key ); ?>]" type="text"
                                   id="wp-fleet-<?php echo esc_attr( $key ); ?>"
                                   value="<?php echo esc_attr( $license_keys[ $key ] ?? '' ); ?>" class="regular-text"
                            />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="submit">
                <input type="hidden" name="action" value="wp-fleet-plugin-save-keys" />
                <?php wp_nonce_field( 'wp-fleet-plugin-save-keys-nonce', 'wp-fleet-plugin-save-keys-nonce' ); ?>
                <input type="submit" name="submit" id="submit" class="button button-primary"
                     value="<?php esc_attr_e( 'Save Changes', 'wp-fleet' ); ?>" />
            </p>
        </form>
    <?php else : ?>
        <p><?php esc_html_e( 'There is no active plugin that requires a license key.', 'wp-fleet' ); ?></p>
    <?php endif; ?>
</div>