<?php
// Desativa a exibição de erros na tela para não quebrar o JSON do seu app
ini_set('display_errors', 0);
error_reporting(0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 1. Pega os dados enviados pelo seu aplicativo
$entrada = json_decode(file_get_contents('php://input'), true);

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
$token_asaas = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';


// URL Oficial do Asaas Sandbox (sem o /api/)
$asaas_url = "https://api-sandbox.asaas.com/v3/accounts";

// Prepara os dados para enviar ao Asaas
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $documento,
    "mobilePhone" => $whatsapp,
    "companyType" => strlen($documento) > 11 ? "LIMITED" : "INDIVIDUAL"
];

// Dispara a requisição Curl para o Asaas
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));

// Evita erros de SSL no servidor de testes
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $token_asaas",
    "User-Agent: MeuCashback"
]);

$resposta = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Trata caso a resposta venha totalmente vazia
if (!$resposta) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nao foi possivel conectar ao servidor do Asaas. Verifique a internet ou o SSL."
    ]);
    exit;
}

$dadosRetorno = json_decode($resposta, true);

if ($codigo_http === 200 || $codigo_http === 201) {
    echo json_encode([
        "sucesso" => true,
        "walletId" => $dadosRetorno['walletId'] ?? ''
    ]);
} else {
    // Trata erros retornados de forma segura sem gerar Warnings no PHP
    $mensagemErro = "Erro desconhecido na API do Asaas.";
    if (isset($dadosRetorno['errors']) && is_array($dadosRetorno['errors'])) {
        $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? $mensagemErro;
    }
    
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro
    ]);
}
