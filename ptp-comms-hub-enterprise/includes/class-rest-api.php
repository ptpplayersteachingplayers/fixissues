<?php
/**
 * REST API endpoints
 */
class PTP_Comms_Hub_REST_API {
    
    public static function register_routes() {
        register_rest_route('ptp-comms/v1', '/send-sms', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'send_sms'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/contacts', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_contacts'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/twiml', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_twiml'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public static function check_permission() {
        return current_user_can('manage_options');
    }
    
    public static function send_sms($request) {
        $to = $request->get_param('to');
        $message = $request->get_param('message');
        
        if (empty($to) || empty($message)) {
            return new WP_Error('missing_params', 'Phone and message required', array('status' => 400));
        }
        
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($to, $message);
        
        if ($result['success']) {
            return rest_ensure_response(array('success' => true, 'sid' => $result['sid']));
        }
        
        return new WP_Error('send_failed', $result['error'], array('status' => 500));
    }
    
    public static function get_contacts($request) {
        $contacts = PTP_Comms_Hub_Contacts::get_all_contacts(array('limit' => 100));
        return rest_ensure_response($contacts);
    }
    
    public static function get_twiml($request) {
        $message = $request->get_param('message');
        if (empty($message)) {
            $message = 'Thank you for calling PTP Soccer Camps.';
        }
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->generate_twiml($message);
        exit;
    }
}
