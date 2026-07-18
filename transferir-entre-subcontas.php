<?php
// Habilita o recebimento de requisições de outros domínios (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Puxa a chave master com segurança
$token_asaas = getenv('ASAAS_API_KEY') ?? $_SERVER['ASAAS_API_KEY'] ?? '';

// URL correta do Asaas Sandbox para Transferências/Saques
$asaas_url = "https://api-sandbox.asaas.com/v3/transfers";

// Recebe os dados enviados pelo seu aplicativo
$input = json_decode(file_get_contents("php://input"), true);

$walletIdOrigem  = $input['walletIdOrigem'] ?? null;  // Subconta de origem (quem envia)
$walletIdDestino = $input['walletIdDestino'] ?? null; // Subconta de destino (quem recebe)
$valor           = $input['valor'] ?? null;           // Valor a ser transferido

// Validação simples dos dados recebidos
if (!$walletIdOrigem || !$walletIdDestino || !$valor || $valor <= 0) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Dados insuficientes ou inválidos para realizar a transferência."
    ]);
    exit;
}

// Monta o corpo da requisição exatamente como o Asaas exige para conta de destino
$dadosTransferencia = [
    "value" => floatval($valor),
    "walletId" => $walletIdDestino
];

// Inicializa a chamada cURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $asaas_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($dadosTransferencia),
    CURLOPT_SSL_VERIFYPEER => false, // Desabilita verificação SSL em ambiente Sandbox
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "access_token: $token_asaas",  // Corrigido para usar a variável correta do topo
        "walletId: $walletIdOrigem"    // Define de qual subconta o dinheiro vai sair (Origem)
    ]
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resultado = json_decode($resposta, true);

// Verifica o sucesso da operação (Asaas retorna 200 ou 201)
if (($httpCode === 200 || $httpCode === 201) && isset($resultado['id'])) {
    echo json_encode([
        "sucesso" => true,
        "transferencia_id" => $resultado['id'],
        "status" => $resultado['status'] ?? 'PENDING',
        "mensagem" => "Transferência de R$ " . number_format($valor, 2, ',', '.') . " realizada com sucesso!"
    ]);
} else {
    // Trata e repassa o erro exato retornado pelo Asaas
    $erroMensagem = "Erro desconhecido na API do Asaas.";
    if (isset($resultado['errors']) && is_array($resultado['errors'])) {
        $erroMensagem = $resultado['errors'][0]['description'] ?? $erroMensagem;
    }
    
    echo json_encode([
        "sucesso" => false,
        "erro" => $erroMensagem,
        "detalhes" => "HTTP $httpCode"
    ]);
}
