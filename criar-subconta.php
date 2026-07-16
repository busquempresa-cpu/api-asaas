<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 1. Pega os dados enviados pelo seu aplicativo
$entrada = json_decode(file_get_contents('php://input'), true);

// IMPORTANTE: Busca a chave 'documento' que está vindo do seu Firebase
$documento = preg_replace('/\D/', '', $entrada['documento'] ?? $entrada['cnpj'] ?? $entrada['cpf'] ?? '');
$nome = $entrada['nome'] ?? '';
$email = $entrada['email'] ?? '';
$whatsapp = preg_replace('/\D/', '', $entrada['whatsapp'] ?? '');

// Validação simples
if (empty($nome) || empty($documento) || empty($email)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nome, documento e email sao campos obrigatorios."
    ]);
    exit;
}

// 2. Chave do Asaas configurada no Render
$token_asaas = getenv('ASAAS_API_KEY');
$asaas_url = "https://sandbox.asaas.com/v3/accounts";

// Prepara os dados para enviar ao Asaas
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $documento,
    "mobilePhone" => $whatsapp,
    "companyType" => strlen($documento) > 11 ? "LIMITED" : "INDIVIDUAL"
];

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $token_asaas",
    "User-Agent: MeuCashback"
]);

$resposta = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dadosRetorno = json_decode($resposta, true);

if ($codigo_http === 200 || $codigo_http === 201) {
    // Retorna com sucesso o walletId
    echo json_encode([
        "sucesso" => true,
        "walletId" => $dadosRetorno['walletId'] ?? ''
    ]);
} else {
    // Trata erros de validação retornados pelo Asaas
    $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? "Erro desconhecido na API do Asaas.";
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro
    ]);
    http_response_code(400);
}
