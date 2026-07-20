<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 Chave Master e Endpoint do Asaas
$apiKeyMaster = getenv('ASAAS_API_KEY') ?: '$aact_hmlg_000MzkwODA2MWY2OGM3MWRmDU2NWM3...';
$urlCob = 'https://sandbox.asaas.com/v3/payments/';

$inputJson = file_get_contents('php://input');
$input = json_decode($inputJson, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Captura o walletId da subconta do lojista enviada pelo Frontend
    $walletIdLojista = $input['walletId'] ?? $input['asaasCustomerId'] ?? '';
    $valorTotal      = floatval($input['valor'] ?? 0);
    $taxaPlataforma  = 25.00; // Sua comissão fixa (exemplo)

    // Validação mínima de segurança
    if ($valorTotal <= 0 || empty($walletIdLojista)) {
        echo json_encode([
            "sucesso" => false,
            "erro"    => "Dados insuficientes para gerar a recarga (Valor ou Wallet ID ausentes)."
        ]);
        exit;
    }

    // Calcula o valor líquido que repassa para o lojista
    $valorLojista = $valorTotal - $taxaPlataforma;

    // Estrutura de Cobrança Pix com Split
    $dadosPix = [
        "customer"    => $input['customerId'] ?? null, // Opcional se gerado dinâmico
        "billingType" => "PIX",
        "value"       => $valorTotal,
        "dueDate"     => date('Y-m-d'),
        "description" => "Recarga Saldo Cashback - Meu Cashback",
        
        // --- SPLIT DE PAGAMENTO ---
        "split" => [
            [
                "walletId"   => $walletIdLojista,
                "fixedValue" => $valorLojista // Envia a parte do lojista direto para a subconta dele
            ]
        ]
    ];

    // Request 1: Cria a Cobrança no Asaas
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlCob);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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

    // Request 2: Busca o QR Code Pix se a cobrança foi gerada
    if (($httpCode == 200 || $httpCode == 201) && isset($dados['id'])) {
        $paymentId = $dados['id'];
        $urlQrCode = "https://sandbox.asaas.com/v3/payments/{$paymentId}/pixQrCode";

        $chPix = curl_init();
        curl_setopt($chPix, CURLOPT_URL, $urlQrCode);
        curl_setopt($chPix, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chPix, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($chPix, CURLOPT_HTTPHEADER, [
            'access_token: ' . trim($apiKeyMaster)
        ]);

        $pixResponse = curl_exec($chPix);
        curl_close($chPix);

        // Devolve os dados do QR Code (encodedImage e payload Copia e Cola)
        echo $pixResponse;
        exit;
    } else {
        echo json_encode([
            "sucesso"  => false,
            "erro"     => "Não foi possível gerar a cobrança no Asaas.",
            "detalhes" => $dados
        ]);
        exit;
    }
}

// Mensagem padronizada para requisições GET diretas
echo json_encode([
    "status"   => "Servidor Backend de Recargas Ativo",
    "ambiente" => "Sandbox (Testes)"
]);
?>
