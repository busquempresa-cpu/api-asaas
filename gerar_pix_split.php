<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// COLOQUE SUA CHAVE MASTER DO ASAAS ABAIXO
$apiKeyMaster = 'SUA_CHAVE_MASTER_AQUI'; 
$urlCob = 'https://sandbox.asaas.com/v3/payments';

$input = json_decode(file_get_contents('php://input'), true);
$walletIdLojista = $input['asaasCustomerId']; // O ID SUB_... que veio do app

// Monta a cobrança travada em R$ 220.00 com a divisão automática
$dadosPix = [
    "billingType" => "PIX",
    "value" => 220.00,
    "dueDate" => date('Y-m-d'),
    "description" => "Taxa de Recarga - Meu Cashback",
    "split" => [
        [
            "walletId" => $walletIdLojista,
            "percentualValue" => 100.00 // Garante que 100% do líquido vá direto para o lojista
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlCob);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosPix));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . $apiKeyMaster
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

// Com a cobrança criada, solicita a string Copia e Cola e a imagem do QR Code
if (isset($data['id'])) {
    $paymentId = $data['id'];
    
    $chPix = curl_init();
    curl_setopt($chPix, CURLOPT_URL, "https://sandbox.asaas.com/v3/payments/$paymentId/pixQrCode");
    curl_setopt($chPix, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPix, CURLOPT_HTTPHEADER, [
        'access_token: ' . $apiKeyMaster
    ]);
    
    $pixResponse = curl_exec($chPix);
    curl_close($chPix);
    
    // Retorna a imagem e o copia-e-cola pro aplicativo
    echo $pixResponse; 
} else {
    echo json_encode([
        "sucesso" => false, 
        "erro" => "Não foi possível estruturar o Split via API.",
        "detalhes" => $data
    ]);
}
?>
