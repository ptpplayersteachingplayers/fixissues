<?php
/**
 * Voice service using Twilio
 */
class PTP_Comms_Hub_Voice_Service {
    
    private $twilio_sid;
    private $twilio_token;
    private $twilio_from;
    
    public function __construct() {
        $this->twilio_sid = ptp_comms_get_setting('twilio_account_sid');
        $this->twilio_token = ptp_comms_get_setting('twilio_auth_token');
        $this->twilio_from = ptp_comms_get_setting('twilio_phone_number');
    }
    
    public function make_call($to, $message) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $to = ptp_comms_normalize_phone($to);
        if (!$to) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        $twiml_url = add_query_arg(
            array('message' => urlencode($message)),
            get_rest_url(null, 'ptp-comms/v1/twiml')
        );
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Calls.json";
        
        $data = array(
            'From' => $this->twilio_from,
            'To' => $to,
            'Url' => $twiml_url
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->twilio_sid}:{$this->twilio_token}")
            ),
            'body' => $data
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['error_code'])) {
            return array('success' => false, 'error' => $body['message']);
        }
        
        return array('success' => true, 'sid' => $body['sid'], 'status' => $body['status']);
    }
    
    public function is_configured() {
        return !empty($this->twilio_sid) && !empty($this->twilio_token) && !empty($this->twilio_from);
    }
    
    public function generate_twiml($message) {
        header('Content-Type: text/xml');
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="alice">' . esc_html($message) . '</Say>
</Response>';
    }
}
