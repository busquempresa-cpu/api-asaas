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

// Limpeza rigorosa do CPF/CNPJ: remove tudo o que não for número
$documento = isset($entrada['documento']) ? preg_replace('/[^0-9]/', '', $entrada['documento']) : '';
if (empty($documento)) {
    $documento = isset($entrada['cnpj']) ? preg_replace('/[^0-9]/', '', $entrada['cnpj']) : '';
}
if (empty($documento)) {
    $documento = isset($entrada['cpf']) ? preg_replace('/[^0-9]/', '', $entrada['cpf']) : '';
}

$nome = $entrada['nome'] ?? '';
$email = $entrada['email'] ?? '';
$whatsapp = isset($entrada['whatsapp']) ? preg_replace('/[^0-9]/', '', $entrada['whatsapp']) : '';

// Garante que o celular tenha um número válido padrão caso venha vazio do app
if (empty($whatsapp) || strlen($whatsapp) < 10) {
    $whatsapp = "49999999999"; 
}

// Validação simples de campos essenciais antes de enviar ao Asaas
if (empty($nome) || empty($documento) || empty($email)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nome, documento e email sao campos obrigatórios."
    ]);
    exit;
}

// Chave do Asaas Sandbox inserida diretamente no código
$token_asaas = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';

// URL Oficial do Asaas Sandbox
$asaas_url = "https://api-sandbox.asaas.com/v3/accounts";

// Define o tipo de empresa com base no tamanho do documento limpo
$tipoEmpresa = (strlen($documento) > 11) ? "MEI" : "INDIVIDUAL";

// Prepara a estrutura exigida pelo Asaas (incluindo dados obrigatórios de endereço padrão para testes)
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $documento,
    "companyType" => $tipoEmpresa,
    "mobilePhone" => $whatsapp,
    "incomeValue" => 5000,
    // Endereço padrão exigido pela documentação oficial do Asaas
    "postalCode" => "89600000",
    "address" => "Rua XV de Novembro",
    "addressNumber" => "100",
    "province" => "Centro",
];

// Dispara a requisição Curl para o Asaas
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));

// Evita erros de SSL no servidor de testes do Render
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $token_asaas",
    "User-Agent: MeuCashback"
]);

$resposta = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$resposta) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nao foi possivel conectar ao servidor do Asaas."
    ]);
    exit;
}

$dadosRetorno = json_decode($resposta, true);

if ($codigo_http === 200 || $codigo_http === 201) {
    echo json_encode([
        "sucesso" => true,
        "walletId" => $dadosRetorno['walletId'] ?? '',
        "apiKey" => $dadosRetorno['apiKey'] ?? ''
    ]);
} else {
    // Retorna o erro exato que a API do Asaas devolveu para identificarmos qualquer problema
    $mensagemErro = "Erro ao cadastrar no Asaas.";
    if (isset($dadosRetorno['errors']) && is_array($dadosRetorno['errors'])) {
        $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? $mensagemErro;
    }
    
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro,
        "detalhes" => "HTTP $codigo_http - Documento enviado: $documento"
    ]);
}
