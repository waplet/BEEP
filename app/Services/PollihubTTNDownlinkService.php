<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class PollihubTTNDownlinkService
{
    private Client $client;
    private string $url;
    private string $token;
    private string $appId;
    private string $webhookId;

    public function __construct(
        Client $client,
        string $url,
        string $token,
        string $appId,
        string $webhookId
    ) {
        $this->client = $client;
        $this->url = $url;
        $this->token = $token;
        $this->appId = $appId;
        $this->webhookId = $webhookId;
    }

    public function setAlarm(string $deviceId): void
    {
        // 0x01
        $this->sendRequest($deviceId, base64_encode(chr(0x01)));
    }
    
    public function unsetAlarm(string $deviceId): void
    {
        // 0x02
        $this->sendRequest($deviceId, base64_encode(chr(0x02)));
    }

    public function setLed(string $deviceId): void
    {
        // 0x03
        $this->sendRequest($deviceId, base64_encode(0x03));
    }

    protected function sendRequest(string $deviceId, string $base64Payload): void
    {
        $this->client->request(
            'POST',
            str_replace(
                ['{appId}', '{webhookId}', '{deviceId}'],
                [$this->appId, $this->webhookId, $deviceId],
                $this->url
            ),
            [
                RequestOptions::HEADERS => $this->getHeaders(),
                RequestOptions::JSON => [
                    'downlinks' => [
                        [
                            "frm_payload" => $base64Payload,
                            "f_port" => 1,
                            "priority" => "NORMAL",
                        ],
                    ],
                ],
            ]
        );
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }
}
