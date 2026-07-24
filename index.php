<?php
// 1. Silencia qualquer mensagem de erro do PHP em HTML
error_reporting(0);
ini_set('display_errors', 0);

// 2. Cabeçalhos CORS e JSON estritos
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Trata a requisição PREFLIGHT (OPTIONS) do navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 🔐 Chave Master e Endpoint Sandbox do Asaas
$token_master = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';
$urlAsaas     = 'https://api-sandbox.asaas.com/v3/payments';

$inputJson = file_get_contents('php://input');
$input     = json_decode($inputJson, true) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Captura os dados enviados pelo Frontend
    $walletIdLojista = $input['walletId'] ?? $input['asaasCustomerId'] ?? '';
    $customerId      = $input['customerId'] ?? ''; 
    $valorTotal      = floatval($input['valor'] ?? 0);
    $taxaPlataforma  = 25.00; // Sua comissão fixa

    // Validação estrita de campos obrigatórios
    if ($valorTotal <= 0 || empty($walletIdLojista) || empty($customerId)) {
        echo json_encode([
            "sucesso" => false,
            "erro"    => "Dados insuficientes: 'valor', 'walletId' e 'customerId' são obrigatórios."
        ]);
        exit();
    }

    // Regra para o Split
    $valorLojista = $valorTotal - $taxaPlataforma;
    if ($valorLojista <= 0) {
        $valorLojista = $valorTotal; 
    }

    // Estrutura da Cobrança PIX com Split
    $dadosPix = [
        "customer"    => $customerId,
        "billingType" => "PIX",
        "value"       => $valorTotal,
        "dueDate"     => date('Y-m-d'),
        "description" => "Recarga Saldo Cashback - Meu Cashback",
        "split"       => [
            [
                "walletId"   => $walletIdLojista,
                "fixedValue" => $valorLojista
            ]
        ]
    ];

    // Request 1: Cria a Cobrança no Asaas (Variáveis Corrigidas: $urlAsaas e $token_master)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlAsaas);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosPix));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'access_token: ' . trim($token_master),
        'User-Agent: MeuCashbackApp/1.0'        
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dados = json_decode($response, true);

    // Request 2: Busca o QR Code Pix se a cobrança foi criada
    if (($httpCode == 200 || $httpCode == 201) && isset($dados['id'])) {
        $paymentId = $dados['id'];
        $urlQrCode = "https://api-sandbox.asaas.com/v3/payments/{$paymentId}/pixQrCode";

        $chPix = curl_init();
        curl_setopt($chPix, CURLOPT_URL, $urlQrCode);
        curl_setopt($chPix, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chPix, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chPix, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'access_token: ' . trim($token_master)
        ]);

        $pixResponse = curl_exec($chPix);
        curl_close($chPix);

        $pixDados = json_decode($pixResponse, true);

// Verifica se o Asaas realmente retornou a imagem do QR Code
if (isset($pixDados['encodedImage']) && !empty($pixDados['encodedImage'])) {
    
    // Sucesso REAL: Devolve os dados para o JavaScript
    echo json_encode([
        "sucesso"        => true,
        "paymentId"      => $paymentId,
        "encodedImage"   => $pixDados['encodedImage'],
        "payload"        => $pixDados['payload'],
        "expirationDate" => $pixDados['expirationDate'] ?? null
    ]);
    exit();

} else {

    // Erro do Asaas na busca do QR Code
    echo json_encode([
        "sucesso"  => false,
        "erro"     => "Não foi possível buscar o QR Code do Pix.",
        "detalhes" => $pixDados
    ]);
    exit();

   }
        
}

// Resposta Padrão para requisição GET (Teste no Navegador)
echo json_encode([
    "status"   => "Servidor Backend de Recargas Ativo",
    "ambiente" => "Sandbox (Testes)"
]);
?>
