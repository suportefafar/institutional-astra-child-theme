<?php
// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'fafar_cf7crud_before_create', 'site_fafar_before_send_mail_handler', 10, 1 );

/**
 * Sends auditorium reservation data to the intranet API before creating a submission.
 *
 * @param array $form_data Form data to be processed.
 * @return array Modified form data or error message.
 */
function site_fafar_before_send_mail_handler( $form_data ) {

    // Validate the form data
    if (
        ! isset( $form_data['object_name'] ) || 
        $form_data['object_name'] !== 'auditorium_reservation'
    ) {
        return $form_data;
    }

    // Prepare the API request
    $api_url = 'http://intranet-website/wp-json/intranet/v1/submissions/auditorium/reservation/';
    $args = [
        'method'  => 'POST',
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
        ],
        'body'    => json_encode( $form_data['data'] ), // Ensure the data is properly encoded
    ];

    // Send the request to the intranet API
    $response = wp_remote_request( $api_url, $args );

    // Handle API errors
    if ( is_wp_error( $response ) ) {
        $error_message = 'Request failed: ' . $response->get_error_message();
        error_log( $error_message );

        return [
            'error_msg' => __( 'O sistema está passando por manutenção...', 'fafar-cf7crud' ),
        ];
    }

    // Log the API response
    $response_body = wp_remote_retrieve_body( $response );
    error_log( 'API Response: ' . $response_body ); 

    // Return true to indicate success
    return $form_data;
}

add_filter( 'pre_wp_mail', 'hermes_custom_wp_mail', 10, 2 );

/**
 * Intercepts wp_mail and sends email via Hermes API.
 *
 * @param null|bool $return Null to let wp_mail handle it, or true to short-circuit.
 * @param array $atts Array of email attributes.
 * @return bool True if email sent successfully.
 */
function hermes_custom_wp_mail( $return, $atts ) {
    $to = $atts['to'];
    if ( is_array( $to ) ) {
        $to = implode( ',', $to );
    }

    $subject = $atts['subject'];
    $message = $atts['message'];

    $payload = array(
        'to_addresses' => explode( ',', $to ),
        'subject'      => $subject,
        'body_html'    => $message
    );

    $api_token = defined( 'HERMES_API_TOKEN' ) ? HERMES_API_TOKEN : '';

    $response = wp_remote_post( 'https://hermes.farmacia.ufmg.br/api/v1/messages', array(
        'headers'     => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json; charset=utf-8'
        ),
        'body'        => wp_json_encode( $payload ),
        'data_format' => 'body',
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'Hermes API Error: ' . $response->get_error_message() );
        return false;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code !== 202 && $response_code !== 200 ) {
        error_log( 'Hermes API Error Response Code: ' . $response_code );
        return false;
    }

    return true; // Short-circuits wp_mail and indicates success.
}