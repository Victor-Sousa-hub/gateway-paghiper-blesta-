<?php

function configurarApiHiper($apiKey, $pedido, $tipoPagamento) {
    // URL para diferentes tipos de pagamento
    $url = ($tipoPagamento === 'pix')
        ? "https://pix.paghiper.com/invoice/create/"
        : "https://api.paghiper.com/transaction/create/";

    // Dados comuns para ambas as opções
    $dados = [
        "apiKey" => $apiKey,
        "order_id" => $pedido['id'],
        "payer_email" => $pedido['email'],
        "payer_name" => $pedido['nome'],
        "payer_cpf_cnpj" => $pedido['cpf'],
        "payer_phone" => $pedido['telefone'],
        "notification_url" => $pedido['notificacao'],
        "discount_cents" => $pedido['desconto'],
        "shipping_price_cents" => $pedido['frete'],
        "shipping_methods" => $pedido['metodo_envio'],
        "number_ntfiscal" => $pedido['nota_fiscal'],
        "fixed_description" => true,
        "days_due_date" => $pedido['dias'],
        "items" => $pedido['itens'],
    ];

    // Adiciona o tipo específico do pagamento
    if ($tipoPagamento === 'boleto') {
        $dados["type_bank_slip"] = "boletoA4";
    }

    return [$url, $dados];
}

function realizarPagamento($apiKey, $pedido, $tipoPagamento) {
    // Configurar os dados e URL
    [$url, $dados] = configurarApiHiper($apiKey, $pedido, $tipoPagamento);

    // Converter os dados para JSON
    $data_post = json_encode($dados);

    // Configuração dos headers
    $mediaType = "application/json";
    $charSet = "UTF-8";
    $headers = [
        "Accept: $mediaType",
        "Accept-Charset: $charSet",
        "Accept-Encoding: $mediaType",
        "Content-Type: $mediaType;charset=$charSet",
    ];

    // Configurando cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Executar a requisição
    $result = curl_exec($ch);

    // Capturar o código HTTP
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Fechar a conexão cURL
    curl_close($ch);

    // Processar a resposta
    $response = json_decode($result, true);

    // Retorno baseado no código HTTP
    if ($httpCode == 201) {
        echo "Pagamento gerado com sucesso!\n";
        if ($tipoPagamento === 'pix') {
            return [
                "transaction_id" => $response['create_request']['transaction_id'],
                "qrcode" => $response['create_request']['pix']['qrcode'],
                "qrcode_image" => $response['create_request']['pix']['qrcode_image_url'],
            ];
        } elseif ($tipoPagamento === 'boleto') {
            return [
                "transaction_id" => $response['create_request']['transaction_id'],
                "url_slip" => $response['create_request']['bank_slip']['url_slip'],
                "digitable_line" => $response['create_request']['bank_slip']['digitable_line'],
            ];
        }
    } else {
        echo "Erro na API: " . ($response['create_request']['response_message'] ?? 'Erro desconhecido') . "\n";
        return null;
    }
}

// Testando a função
$apiKey = "apk_44983899-QcogYcEpxkjJApJAtEjqshBNsGNAsmle";
$pedido = [
    "id" => "96874",
    "email" => "poulsilva@myexemple.com",
    "nome" => "Poul Silva",
    "cpf" => "12745737007",
    "telefone" => "1140638785",
    "notificacao" => "https://mysite.com/notification/paghiper/",
    "desconto" => 1100,
    "frete" => 2595,
    "metodo_envio" => "PAC",
    "nota_fiscal" => "1554123",
    "dias" => 5,
    "itens" => [
        [
            "description" => "Piscina de bolinha",
            "quantity" => 1,
            "item_id" => "1",
            "price_cents" => 1012,
        ],
        [
            "description" => "Pula pula",
            "quantity" => 2,
            "item_id" => "2",
            "price_cents" => 2000,
        ],
        [
            "description" => "Mala de viagem",
            "quantity" => 3,
            "item_id" => "3",
            "price_cents" => 4000,
        ],
    ],
];

try {
    $tipoPagamento = "pix"; // ou "boleto"
    $resultado = realizarPagamento($apiKey, $pedido, $tipoPagamento);
    if ($resultado) {
        print_r($resultado);
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
