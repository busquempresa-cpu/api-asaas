<?php
// Habilita o CORS para o seu app Firebase
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    exit(0); 
}

// 1. Puxa a chave master de forma segura das variáveis de ambiente
$token_asaas = getenv('ASAAS_API_KEY') ?? $_SERVER['ASAAS_API_KEY'] ?? '';

// URL correta do Asaas Sandbox para Transferências
$asaas_url = "https://api-sandbox.asaas.com/v3/transfers";

// Recebe a requisição JSON vinda do app
$input = json_decode(file_get_contents("php://input"), true);

$walletId   = $input['walletId'] ?? '';   // ID da subconta do lojista (salvo no Firebase)
$valor      = $input['valor'] ?? 0;       // Valor do saque
$chavePix   = $input['chavePix'] ?? '';   // Chave Pix destino
$tipoPix    = $input['tipoPix'] ?? '';    // CPF, CNPJ, EMAIL, PHONE ou EVP

// Validação dos campos obrigatórios
if (empty($walletId) || $valor <= 0 || empty($chavePix) || empty($tipoPix)) {
    echo json_encode(["sucesso" => false, "erro" => "Dados insuficientes para realizar o saque."]);
    http_response_code(400);
    exit;
}

// Prepara os dados para o Asaas realizar o Pix saindo da subconta
$dadosTransferencia = [
    "value" => floatval($valor),
    "pixAddressKey" => $chavePix,
    "pixAddressKeyType" => strtoupper($tipoPix)
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosTransferencia));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignora verificação SSL no Sandbox
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $token_asasa", // Corrigido para corresponder à variável do topo
    "walletId: $walletId"         // Passa o ID da subconta no cabeçalho (obrigatório pelo Asaas)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dadosRetorno = json_decode($response, true);

if ($http_code === 200 || $http_code === 201) {
    echo json_encode([
        "sucesso" => true,
        "transferId" => $dadosRetorno['id'] ?? '',
        "status" => $dadosRetorno['status'] ?? ''
    ]);
} else {
    $mensagemErro = "Erro ao processar saque no Asaas.";
    if (isset($dadosRetorno['errors']) && is_array($dadosRetorno['errors'])) {
        $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? $mensagemErro;
    }
    
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro,
        "detalhes" => "HTTP $http_code"
    ]);
    http_response_code(400);
}
