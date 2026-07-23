<?php
// Desativa exibição de erros na tela para não quebrar a resposta JSON
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 Chave Master da API Asaas Sandbox
$apiKeyMaster = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';

// 🟢 Endpoints Sandbox
$urlSubconta = 'https://api-sandbox.asaas.com/v3/accounts';
$urlCustomer = 'https://api-sandbox.asaas.com/v3/customers';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(["sucesso" => false, "erro" => "Dados de entrada inválidos ou vazios."]);
    exit;
}

// 🧹 Limpeza rigorosa de máscaras
$cpfCnpjClean = preg_replace('/[^0-9]/', '', $input['cpfCnpj'] ?? $input['documento'] ?? $input['cnpj'] ?? $input['cpf'] ?? '');
$cepClean     = preg_replace('/[^0-9]/', '', $input['cep'] ?? $input['postalCode'] ?? '');
$phoneClean   = preg_replace('/[^0-9]/', '', $input['telefone'] ?? $input['whatsapp'] ?? $input['mobilePhone'] ?? '');
$uidFirebase  = trim($input['uid'] ?? $input['userId'] ?? $input['firebaseUid'] ?? '');

// 🛡️ Fallbacks para evitar campos vazios que a API do Asaas exige
if (empty($phoneClean) || strlen($phoneClean) < 10) {
    $phoneClean = "49999999999"; 
}
if (empty($cepClean)) {
    $cepClean = "89600000";
}

$nome      = trim($input['nome'] ?? $input['name'] ?? '');
$email     = trim($input['email'] ?? '');
$endereco  = trim($input['endereco'] ?? $input['address'] ?? 'Rua XV de Novembro');
$numero    = trim($input['numero'] ?? $input['addressNumber'] ?? '100');
$bairro    = trim($input['bairro'] ?? $input['province'] ?? 'Centro');

// Validação dos dados essenciais
if (empty($nome) || empty($cpfCnpjClean) || empty($email)) {
    echo json_encode([
        "sucesso" => false,
        "erro"    => "Nome, CPF/CNPJ e E-mail são obrigatórios."
    ]);
    exit;
}

// Define tipo de empresa dinamicamente
$companyType = (strlen($cpfCnpjClean) > 11) ? "MEI" : "INDIVIDUAL";

// =========================================================================
// PASSO 1: CRIAÇÃO DA SUBCONTA NO ASAAS (Para receber splits)
// =========================================================================
$dadosSubconta = [
    "name"          => $nome,
    "email"         => $email,
    "cpfCnpj"       => $cpfCnpjClean,
    "mobilePhone"   => $phoneClean,
    "incomeValue"   => 5000,
    "address"       => $endereco,
    "addressNumber" => $numero,
    "province"      => $bairro,
    "postalCode"    => $cepClean,
    "companyType"   => $companyType
];

$chAcc = curl_init();
curl_setopt($chAcc, CURLOPT_URL, $urlSubconta);
curl_setopt($chAcc, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chAcc, CURLOPT_POST, true);
curl_setopt($chAcc, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($chAcc, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chAcc, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . trim($apiKeyMaster),
    'User-Agent: MeuCashbackApp/1.0'
]);

$resAcc = curl_exec($chAcc);
$httpCodeAcc = curl_getinfo($chAcc, CURLINFO_HTTP_CODE);
curl_close($chAcc);

$subcontaData = json_decode($resAcc, true);

if ($httpCodeAcc != 200 && $httpCodeAcc != 201) {
    $mensagemAsaas = "Erro na criação da subconta (HTTP " . $httpCodeAcc . ")";
    if (isset($subcontaData['errors'][0]['description'])) {
        $mensagemAsaas = $subcontaData['errors'][0]['description'];
    }
    echo json_encode([
        "sucesso"  => false,
        "erro"     => "Asaas Subconta: " . $mensagemAsaas,
        "detalhes" => $subcontaData
    ]);
    exit;
}

$walletId = $subcontaData['walletId'] ?? $subcontaData['accountNumber'] ?? $subcontaData['id'] ?? null;

// =========================================================================
// PASSO 2: CRIAÇÃO DO CLIENTE PAGADOR NO ASAAS (Para realizar recargas)
// =========================================================================
$dadosCustomer = [
    "name"              => $nome,
    "email"             => $email,
    "cpfCnpj"           => $cpfCnpjClean,
    "mobilePhone"       => $phoneClean,
    "externalReference" => $uidFirebase // Liga o UID do Firebase de forma nativa no Asaas
];

$chCus = curl_init();
curl_setopt($chCus, CURLOPT_URL, $urlCustomer);
curl_setopt($chCus, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chCus, CURLOPT_POST, true);
curl_setopt($chCus, CURLOPT_POSTFIELDS, json_encode($dadosCustomer));
curl_setopt($chCus, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chCus, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . trim($apiKeyMaster),
    'User-Agent: MeuCashbackApp/1.0'
]);

$resCus = curl_exec($chCus);
$httpCodeCus = curl_getinfo($chCus, CURLINFO_HTTP_CODE);
curl_close($chCus);

$customerData = json_decode($resCus, true);

if ($httpCodeCus != 200 && $httpCodeCus != 201) {
    $mensagemAsaas = "Erro na criação do cliente (HTTP " . $httpCodeCus . ")";
    if (isset($customerData['errors'][0]['description'])) {
        $mensagemAsaas = $customerData['errors'][0]['description'];
    }
    echo json_encode([
        "sucesso"  => false,
        "erro"     => "Asaas Customer: " . $mensagemAsaas,
        "detalhes" => $customerData
    ]);
    exit;
}

$asaasCustomerId = $customerData['id'] ?? null; // ID no formato cus_...

// =========================================================================
// RESPOSTA FINAL PADRONIZADA PARA O FIREBASE / APP
// =========================================================================
echo json_encode([
    "sucesso"         => true,
    "walletId"        => $walletId,        // Para o Split no recebimento
    "asaasCustomerId" => $asaasCustomerId, // Para a criação da cobrança PIX (cus_...)
    "apiKeySubconta"  => $subcontaData['apiKey'] ?? null
]);
exit;
?>
