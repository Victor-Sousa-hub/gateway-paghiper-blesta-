<?php
class PagHiperApi
{
    private $api_key;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    public function gerarPagamento(array $pedido, $tipo_pagamento)
    {
        $url = ($tipo_pagamento === 'pix')
            ? "https://pix.paghiper.com/invoice/create/"
            : "https://api.paghiper.com/transaction/create/";

        $payload = json_encode($pedido);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['create_request']['result']) && $result['create_request']['result'] === 'reject') {
            throw new Exception($result['create_request']['response_message']);
        }

        return $result['create_request'];
    }
}
