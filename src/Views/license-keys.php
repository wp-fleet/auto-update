<?php
/**
 * Backend view to manage license keys
 */

?>
<div class="wrap">
    <h1><?php esc_html_e( 'Manage License Keys', 'wp-fleet' ); ?></h1>
    <?php
    if ( ! empty( $fields ) ) :
        if ( !is_array( $fields ) ) :
            $fields = (array) $fields;
        endif;
        ?>
        <p><?php esc_html_e( 'Add the license key for each plugin in form below.', 'wp-fleet' ); ?></p>
        <form method="post" action="">
            <table class="form-table">
                <?php foreach ( $fields as $key => $field ) : ?>
                    <?php $plugin_data = get_plugin_data( $key ); ?>
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