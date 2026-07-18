<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Sua Chave Master de Homologação
$apiKeyMaster = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4'; 
$urlCob = 'https://sandbox.asaas.com/v3/payments';

$input = json_decode(file_get_contents('php://input'), true);
$walletIdLojista = $input['asaasCustomerId']; // O ID (SUB_...) vindo do Firebase do lojista

// Monta o Pix travado em R$ 220,00 com divisão automática
$dadosPix = [
    "billingType" => "PIX",
    "value" => 220.00,
    "dueDate" => date('Y-m-d'),
    "description" => "Taxa de Recarga - Meu Cashback",
    "split" => [
        [
            "walletId" => $walletIdLojista,
            "percentualValue" => 100.00 // Todo o valor líquido entra direto na conta do lojista
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

// Se a cobrança foi gerada com sucesso, busca o QR Code e a chave Copia e Cola
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
    
    // Devolve os dados do QR Code prontos para o Frontend
    echo $pixResponse; 
} else {
    echo json_encode([
        "sucesso" => false, 
        "erro" => "Nao foi possivel estruturar o Split via API.",
        "detalhes" => $data
    ]);
}
?>
