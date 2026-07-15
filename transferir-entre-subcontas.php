<?php
// Habilita o recebimento de requisições de outros domínios (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$token_asaas = getenv('ASAAS_API_KEY');
$asaasUrl = "https://sandbox.asaas.com/api/v3";

// Recebe os dados enviados pelo seu aplicativo
$input = json_decode(file_get_contents("php://input"), true);

$walletIdOrigem  = $input['walletIdOrigem'] ?? null;  // Subconta do lojista que deu os pontos (origem)
$walletIdDestino = $input['walletIdDestino'] ?? null; // Subconta do lojista que está recebendo o cliente (destino)
$valor           = $input['valor'] ?? null;           // Valor a ser transferido (R$)

// Validação simples dos dados recebidos
if (!$walletIdOrigem || !$walletIdDestino || !$valor || $valor <= 0) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Dados insuficientes ou inválidos para realizar a transferência."
    ]);
    exit;
}

// Monta o corpo da requisição para a API do Asaas
$dadosTransferencia = [
    "value" => floatval($valor),
    "walletId" => $walletIdDestino // ID da carteira de destino no Asaas
];

// Inicializa a chamada cURL para o Asaas
$ch = curl_init();

// A URL de transferência no Asaas exige que usemos o ID da subconta de origem no cabeçalho
curl_setopt_array($ch, [
    CURLOPT_URL => "{$asaasUrl}/transfers",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($dadosTransferencia),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "access_token: {$apiToken}",
        "asaas-access-key: {$walletIdOrigem}" // Define de qual subconta o dinheiro vai sair
    ]
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resultado = json_decode($resposta, true);

// Verifica se a transferência deu certo no Asaas
if ($httpCode === 200 && isset($resultado['id'])) {
    echo json_encode([
        "sucesso" => true,
        "transferencia_id" => $resultado['id'],
        "mensagem" => "Transferência de R$ {$valor} realizada com sucesso!"
    ]);
} else {
    // Se o Asaas recusar (por exemplo, por falta de saldo), devolve o erro detalhado
    $erroMensagem = $resultado['errors'][0]['description'] ?? "Erro desconhecido na API do Asaas.";
    echo json_encode([
        "sucesso" => false,
        "erro" => $erroMensagem
    ]);
}
