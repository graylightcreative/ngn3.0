<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use GuzzleHttp\Psr7\Request;
class SMTP
{
   public function account(){

       $client = new Client();
       $headers = [
           'Authorization' => 'Bearer 6e4e54e92fef0cbc1af79bb59c6bc91c2a158be3'
       ];
       $request = new Request('GET', 'https://api.smtp.com/v4/account', $headers);
       $res = $client->sendAsync($request)->wait();
       return json_decode($res->getBody(), true);
   }

    public function send($to, $from, $subject, $message)
    {

        $client = new Client();
        $headers = [
            'Authorization' => 'Bearer 6e4e54e92fef0cbc1af79bb59c6bc91c2a158be3',
            'Content-Type' => 'application/json'
        ];

        $body = json_encode([
            "channel" => "graylightcreative",
            "recipients" => [
                "to" => [
                    [
                        "name" => $to['name'],
                        "address" => $to['address']
                    ]
                ],
                "cc" => $to['cc'] ?? [],
                "bcc" => $to['bcc'] ?? [],
                "bulk_list" => $to['bulk_list'] ?? []
            ],
            "originator" => [
                "from" => [
                    "name" => $from['name'],
                    "address" => $from['address']
                ],
                "reply_to" => [
                    "name" => $from['name'],
                    "address" => $from['address']
                ],
            ],
//            "custom_headers" => $from['custom_headers'],
            "subject" => $subject,
            "body" => [
                "parts" => [
                    [
                        "version" => "1.0",
                        "type" => "text/plain",
                        "charset" => "UTF-8",
                        "encoding" => "quoted-printable",
                        "content" => strip_tags($message)
                    ],
                    [
                        "version" => "1.0",
                        "type" => "text/html",
                        "charset" => "UTF-8",
                        "encoding" => "quoted-printable",
                        "content" => $message
                    ]
                ],
                "attachments" => $message['attachments'] ?? []
            ]
        ]);

        $request = new Request('POST', 'https://api.smtp.com/v4/messages', $headers, $body);
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody(), true);
    }
}