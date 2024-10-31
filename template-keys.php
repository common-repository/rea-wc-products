<?php

defined( 'ABSPATH' ) || exit;

?>

<div id="key-fields" class="settings-panel">
    <h2><?php esc_html_e( 'Key details', 'woocommerce' ); ?></h2>

    <input type="hidden" id="key_id" value="<?php echo esc_attr( 10 ); ?>" />

    <table id="api-keys-options" class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="key_description">
                    <?php esc_html_e( 'Description', 'woocommerce' ); ?>
                    <?php echo wc_help_tip( __( 'Friendly name for identifying this key.', 'woocommerce' ) ); ?>
                </label>
            </th>
            <td class="forminp">
                <input id="key_description" type="text" class="input-text regular-input" value="<?php echo esc_attr( 'desc' ); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="key_user">
                    <?php esc_html_e( 'User', 'woocommerce' ); ?>
                    <?php echo wc_help_tip( __( 'Owner of these keys.', 'woocommerce' ) ); ?>
                </label>
            </th>
            <td class="forminp">
                <?php
                $current_user_id = get_current_user_id();
                $user_id        =$current_user_id;
                $user           = get_user_by( 'id', $user_id );
                $user_string    = sprintf(
                /* translators: 1: user display name 2: user ID 3: user email */
                    esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'woocommerce' ),
                    $user->display_name,
                    absint( $user->ID ),
                    $user->user_email
                );
                ?>
                <select class="wc-customer-search" id="key_user" data-placeholder="<?php esc_attr_e( 'Search for a user&hellip;', 'woocommerce' ); ?>" data-allow_clear="true">
                    <option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo htmlspecialchars( wp_kses_post( $user_string ) ); // htmlspecialchars to prevent XSS when rendered by selectWoo. ?></option>
                </select>
            </td>
        </tr>
        </tbody>
    </table>
</div>

