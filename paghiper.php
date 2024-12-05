<?php

class PagHiper extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new non-merchant gateway.
     */
    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . "config.json");

        // Load components required by this gateway
        Loader::loadComponents($this, ["Input"]);

        // Load the language required by this gateway
        Language::loadLang("paghiper", null, dirname(__FILE__) . DS . "language" . DS);
    }

     /**
     * Valida os dados fornecidos para processar o pagamento.
     *
     * @param array $fields Dados do pagamento.
     * @return array Uma lista de erros, ou vazio se os dados forem válidos.
     */
    public function validate(array $get, array $post) {
        $errors = [];

        if (empty($fields['invoice_id'])) {
            $errors['invoice_id'] = Language::_("PagHiper.error.invoice_id", true);
        }

        if (empty($fields['amount']) || !is_numeric($fields['amount']) || $fields['amount'] <= 0) {
            $errors['amount'] = Language::_("PagHiper.error.amount", true);
        }

        if (empty($fields['email']) || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = Language::_("PagHiper.error.email", true);
        }

        return $errors;
    }

    /**
     * Determina se o pagamento foi concluído com sucesso.
     *
     * @param array $get Dados retornados pela URL de notificação ou callback.
     * @return bool Verdadeiro se o pagamento foi bem-sucedido.
     */
    public function success(array $get, array $post) {
        // Verifica se os dados de sucesso do PagHiper estão presentes
        if (isset($get['status']) && $get['status'] === 'paid') {
            return true;
        }

        return false;
    }

    /**
     * Sets the currency code to be used for all subsequent payments.
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway.
     *
     * @param  array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ["Form", "Html"]);

        $this->view->set("meta", $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway.
     *
     * @param  array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [];

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database.
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ["access_token"];
    }

    /**
     * Sets the meta data for this particular gateway.
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }
    /**
     * Processa o pagamento
     */
    public function process(array $fields) {
        // Carrega a integração com a API PagHiper
        require_once dirname(__FILE__) . DS . "integration.php";

        $pedido = [
            "id" => $fields['invoice_id'],
            "email" => $fields['email'],
            "nome" => $fields['client_name'],
            "cpf" => $fields['client_cpf'],
            "telefone" => $fields['client_phone'],
            "notificacao" => $this->getSetting("notification_url"),
            "desconto" => 0,
            "frete" => 0,
            "metodo_envio" => "Digital",
            "nota_fiscal" => "N/A",
            "dias" => 3,
            "itens" => [
                [
                    "description" => "Fatura #" . $fields['invoice_id'],
                    "quantity" => 1,
                    "item_id" => $fields['invoice_id'],
                    "price_cents" => $fields['amount'] * 100
                ]
            ],
        ];

        // Tipo de pagamento
        $tipoPagamento = $this->getSetting("payment_type");

        // Processa a requisição
        try {
            $resultado = realizarPagamento($this->getSetting("api_key"), $pedido, $tipoPagamento);
            return [
                'status' => 'success',
                'transaction_id' => $resultado['transaction_id'],
                'message' => $tipoPagamento === 'pix'
                    ? $resultado['qrcode_image']
                    : $resultado['url_slip']
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Captura os campos requeridos para processar o pagamento
     */
    public function getRequiredFields() {
        return [
            'invoice_id',
            'amount',
            'email',
            'client_name',
            'client_cpf',
            'client_phone'
        ];
    }

    /**
     * Indica se o cliente precisa estar presente no momento do pagamento.
     * Para PagHiper (PIX e boleto), o cliente não precisa estar presente.
     * 
     * @return bool Retorna false
     */
    public function requiresCustomerPresent() {
        return true; // O cliente não precisa estar presente
    }
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
{
    // Carrega a API do PagHiper
    require_once dirname(__FILE__) . DS . "lib" . DS . "paghiper_api.php";

    $api = new PagHiperApi($this->meta['api_key']); // Inicializa a API com a chave API

    // Monta os dados do pedido
    $pedido = [
        "id" => preg_replace('/[^a-zA-Z0-9]+/', '-', str_replace('#', '', $options["description"])),
        "email" => $contact_info['email'],
        "nome" => $contact_info['first_name'] . ' ' . $contact_info['last_name'],
        "cpf" => $contact_info['cpf'],
        "telefone" => $contact_info['phone'],
        "notificacao" => $this->meta['notification_url'],
        "itens" => [
            [
                "description" => $options["description"],
                "quantity" => 1,
                "price_cents" => $amount * 100
            ]
        ],
        "dias" => 3, // Dias para vencimento
    ];

    // Tipo de pagamento (PIX ou boleto)
    $tipo_pagamento = $this->meta['payment_type'];

    // Envia o pedido para a API e obtém a URL de pagamento
    try {
        $resultado = $api->gerarPagamento($pedido, $tipo_pagamento);

        // URL de redirecionamento
        $url_pagamento = $tipo_pagamento === 'pix'
            ? $resultado['qrcode_image']
            : $resultado['url_slip'];

        // Retorna o formulário HTML para redirecionamento
        return $this->buildForm($url_pagamento);
    } catch (Exception $e) {
        // Registra o erro nos logs
        $this->log('buildProcess', $e->getMessage(), 'output', false);
        return null;
    }


}

private function buildForm($post_to)
{
    $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

    // Carrega os helpers necessários
    Loader::loadHelpers($this, ["Form", "Html"]);

    $this->view->set("post_to", $post_to);
    return $this->view->fetch();
}
}





   