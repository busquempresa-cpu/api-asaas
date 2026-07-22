<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 Chave Master e Endpoints Corretos do Asaas (Adicionado /api)
$token_master = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';
$urlCob       = 'https://sandbox.asaas.com/api/v3/payments';

$inputJson = file_get_contents('php://input');
$input = json_decode($inputJson, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Captura os dados enviados pelo Frontend
    $walletIdLojista = $input['walletId'] ?? $input['asaasCustomerId'] ?? '';
    $customerId      = $input['customerId'] ?? ''; // OBRIGATÓRIO (ID do Pagador cus_...)
    $valorTotal      = floatval($input['valor'] ?? 0);
    $taxaPlataforma  = 25.00; // Sua comissão fixa

    // 1. Validação estrita de campos obrigatórios
    if ($valorTotal <= 0 || empty($walletIdLojista) || empty($customerId)) {
        echo json_encode([
            "sucesso" => false,
            "erro"    => "Dados insuficientes: 'valor', 'walletId' e 'customerId' são obrigatórios."
        ]);
        exit;
    }

    // Regra para o Split (o valor do split não pode exceder o valor total)
    $valorLojista = $valorTotal - $taxaPlataforma;
    if ($valorLojista <= 0) {
        $valorLojista = $valorTotal; // Ajuste de segurança caso a recarga seja menor que a taxa
    }

    // 2. Estrutura da Cobrança PIX com Split
    $dadosPix = [
        "customer"    => $customerId,
        "billingType" => "PIX",
        "value"       => $valorTotal,
        "dueDate"     => date('Y-m-d'),
        "description" => "Recarga Saldo Cashback - Meu Cashback",
        
        // --- SPLIT DE PAGAMENTO ---
        "split" => [
            [
                "walletId"   => $walletIdLojista,
                "fixedValue" => $valorLojista
            ]
        ]
    ];

    // Request 1: Cria a Cobrança no Asaas
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlCob);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosPix));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'access_token: ' . trim($apiKeyMaster)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dados = json_decode($response, true);

    // Request 2: Busca o QR Code Pix se a cobrança foi criada
    if (($httpCode == 200 || $httpCode == 201) && isset($dados['id'])) {
        $paymentId = $dados['id'];
        $urlQrCode = "https://sandbox.asaas.com/api/v3/payments/{$paymentId}/pixQrCode";

        $chPix = curl_init();
        curl_setopt($chPix, CURLOPT_URL, $urlQrCode);
        curl_setopt($chPix, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chPix, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'access_token: ' . trim($apiKeyMaster)
        ]);

        $pixResponse = curl_exec($chPix);
        curl_close($chPix);

        $pixDados = json_decode($pixResponse, true);

        // Retorna um JSON padronizado para o app
        echo json_encode([
            "sucesso"       => true,
            "paymentId"     => $paymentId,
            "encodedImage"  => $pixDados['encodedImage'] ?? null,
            "payload"       => $pixDados['payload'] ?? null,
            "expirationDate"=> $pixDados['expirationDate'] ?? null
        ]);
        exit;
    } else {
        // Se o Asaas recusou, devolve o motivo exato
        echo json_encode([
            "sucesso"  => false,
            "erro"     => "Erro na API do Asaas ao gerar a cobrança.",
            "detalhes" => $dados
        ]);
        exit;
    }
}

// Mensagem padronizada para requisições GET
echo json_encode([
    "status"   => "Servidor Backend de Recargas Ativo",
    "ambiente" => "Sandbox (Testes)"
]);
?>
