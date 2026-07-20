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

// 🟢 Endpoint Oficial Sandbox Subcontas
$urlAsaas = 'https://api-sandbox.asaas.com/v3/accounts';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(["sucesso" => false, "erro" => "Dados de entrada inválidos ou vazios."]);
    exit;
}

// 🧹 Limpeza rigorosa de máscaras
$cpfCnpjClean = preg_replace('/[^0-9]/', '', $input['cpfCnpj'] ?? $input['documento'] ?? $input['cnpj'] ?? $input['cpf'] ?? '');
$cepClean     = preg_replace('/[^0-9]/', '', $input['cep'] ?? $input['postalCode'] ?? '');
$phoneClean   = preg_replace('/[^0-9]/', '', $input['telefone'] ?? $input['whatsapp'] ?? $input['mobilePhone'] ?? '');

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
        "erro" => "Nome, CPF/CNPJ e E-mail são obrigatórios."
    ]);
    exit;
}

// Define tipo de empresa dinamicamente
$companyType = (strlen($cpfCnpjClean) > 11) ? "MEI" : "INDIVIDUAL";

// 🛠️ Payload corrigido e completo
$dadosSubconta = [
    "name"          => $nome,
    "email"         => $email,
    "cpfCnpj"       => $cpfCnpjClean,
    "mobilePhone"   => $phoneClean,
    "incomeValue"   => 5000, // Campo OBRIGATÓRIO pelo Asaas
    "address"       => $endereco,
    "addressNumber" => $numero,
    "province"      => $bairro,
    "postalCode"    => $cepClean,
    "companyType"   => $companyType
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlAsaas);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita falhas de SSL em ambientes de dev/Render

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . trim($apiKeyMaster),
    'User-Agent: MeuCashbackApp/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$subcontaData = json_decode($response, true);

if ($httpCode == 200 || $httpCode == 201) {
    
    $accountCode = $subcontaData['id'] ?? null;
    $walletId    = $subcontaData['walletId'] ?? $subcontaData['accountNumber'] ?? $accountCode;

    echo json_encode([
        "sucesso"         => true,
        "asaasCustomerId" => $accountCode,
        "walletId"        => $walletId,
        "id_carteira"     => $walletId,
        "apiKeySubconta"  => $subcontaData['apiKey'] ?? null
    ]);

} else {

    $mensagemAsaas = "Erro no servidor Asaas (HTTP " . $httpCode . ")";
    
    if (isset($subcontaData['errors'][0]['description'])) {
        $mensagemAsaas = $subcontaData['errors'][0]['description'];
    }

    echo json_encode([
        "sucesso"  => false,
        "erro"     => "Asaas: " . $mensagemAsaas,
        "detalhes" => $subcontaData
    ]);
}
?>
