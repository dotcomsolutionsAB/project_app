<?php

namespace App\Utils;

use App\Models\WhatsAppMessageLog;

class sendWhatsAppUtility 
{
    public static function sendWhatsApp($customer, $params, $media, $campaignName) 
    {
        $templateName = is_array($params) && isset($params['name']) ? $params['name'] : null;
        $to = is_string($customer) ? preg_replace('/[^0-9]/', '', $customer) : (string) $customer;

        $log = WhatsAppMessageLog::create([
            'to' => $to,
            'template_name' => $templateName,
            'status' => 'sent',
            'sent_at' => now(),
            'request_payload' => [
                'to' => $customer,
                'template' => $params,
                'campaign_name' => $campaignName,
            ],
        ]);

        $response = null;

        if (env('WHATSAPP_SERVICE_ON')) 
        {
            $content = array();
            $content['messaging_product'] = "whatsapp";
            $content['to'] = $customer;
            $content['type'] = 'template';
            $content['biz_opaque_callback_data'] = $campaignName;
            $content['template'] = $params;

            $token = env('WHATSAPP_API_TOKEN');

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => env('WHATSAPP_URL'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($content),
            CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$token
            ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
        }

        $responsePayload = null;
        $messageId = null;
        $derivedStatus = 'sent';

        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $responsePayload = $decoded;
                if (!empty($decoded['messages'][0]['id'])) {
                    $messageId = $decoded['messages'][0]['id'];
                }
                if (!empty($decoded['error'])) {
                    $derivedStatus = 'failed';
                }
            }
        }

        $log->update([
            'message_id' => $messageId,
            'status' => $derivedStatus,
            'response_payload' => $responsePayload,
            'failed_at' => $derivedStatus === 'failed' ? now() : null,
        ]);

        return $response;
    }
}