<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$token_asaas = getenv('ASAAS_API_KEY');
$asaasUrl = "https://sandbox.asaas.com/api/v3/transfers";

$input = json_decode(file_get_contents("php://input"), true);

$walletId   = $input['walletId'] ?? '';   // ID da subconta do lojista (salvo no Firebase)
$valor      = $input['valor'] ?? 0;       // Valor que ele deseja sacar
$chavePix   = $input['chavePix'] ?? '';   // Chave Pix de destino
$tipoPix    = $input['tipoPix'] ?? '';    // CPF, CNPJ, EMAIL, PHONE ou EVP (Aleatória)

if (empty($walletId) || $valor <= 0 || empty($chavePix) || empty($tipoPix)) {
    echo json_encode(["erro" => "Dados insuficientes para realizar o saque."]);
    http_response_code(400);
    exit;
}

// Prepara os dados para o Asaas realizar a transferência Pix saindo da subconta do lojista
$dadosTransferencia = [
    "value" => $valor,
    "pixAddressKey" => $chavePix,
    "pixAddressKeyType" => strtoupper($tipoPix)
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosTransferencia));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $asaas_token",
    "walletId: $walletId" // 🔥 Essencial: informa ao Asaas de qual subconta o dinheiro está saindo
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dadosRetorno = json_decode($response, true);

if ($http_code === 200 || $http_code === 201) {
    echo json_encode([
        "sucesso" => true,
        "transferId" => $dadosRetorno['id'],
        "status" => $dadosRetorno['status']
    ]);
} else {
    $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? "Erro ao processar saque no Asaas.";
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro
    ]);
    http_response_code(400);
}
