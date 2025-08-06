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
    $api_url = 'http://container_intranet/wp-json/intranet/v1/submissions/auditorium/reservation/';
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
    if (is_wp_error($response)) {
        $error_message = 'Request failed: ' . $response->get_error_message();
        error_log($error_message);

        return [
            'error_msg' => __('O sistema está passando por manutenção...', 'fafar-cf7crud'),
        ];
    }

    // Log the API response
    $response_body = wp_remote_retrieve_body($response);
    error_log('API Response: ' . $response_body);

    // Return true to indicate success
    return $form_data;
}