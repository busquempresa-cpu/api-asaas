<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 Puxa a chave API configurada no Environment do Render
$apiKeyMaster = getenv('ASAAS_API_KEY');

if (!$apiKeyMaster) {
    echo json_encode([
        "sucesso" => false, 
        "erro" => "Chave de API do Asaas não configurada no servidor (ASAAS_API_KEY)."
    ]);
    exit;
}

// ⚠️ Endpoint do Sandbox (sem a barra final para evitar erros de POST)
// 🟢 URL Correta do Sandbox da API do Asaas
$urlAsaas = 'https://api-sandbox.asaas.com/v3/accounts';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(["sucesso" => false, "erro" => "Dados de entrada inválidos ou vazios."]);
    exit;
}

// 🧹 Limpeza de máscaras para evitar recusa no Asaas
$cpfCnpjClean = preg_replace('/[^0-9]/', '', $input['cpfCnpj'] ?? '');
$cepClean     = preg_replace('/[^0-9]/', '', $input['cep'] ?? '');
$phoneClean   = preg_replace('/[^0-9]/', '', $input['telefone'] ?? '');

// 🛠️ Montagem do Payload ajustado para a API do Asaas
$dadosSubconta = [
    "name"          => trim($input['nome'] ?? ''),
    "email"         => trim($input['email'] ?? ''),
    "cpfCnpj"       => $cpfCnpjClean,
    "mobilePhone"   => $phoneClean, // Corrigido de 'phone' para 'mobilePhone'
    "address"       => trim($input['endereco'] ?? ''),
    "addressNumber" => trim($input['numero'] ?? ''),
    "province"      => trim($input['bairro'] ?? ''),
    "postalCode"    => $cepClean,
    "companyType"   => "INDIVIDUAL"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlAsaas);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . trim($apiKeyMaster)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$subcontaData = json_decode($response, true);

if ($httpCode == 200 || $httpCode == 201) {
    
    // Mapeamento completo para garantir que o JS receba o ID sem falhas
    $accountCode = $subcontaData['id'] ?? null;
    $walletId    = $subcontaData['walletId'] ?? $subcontaData['accountNumber'] ?? $accountCode;

    echo json_encode([
        "sucesso"         => true,
        "asaasCustomerId" => $accountCode,
        "walletId"        => $walletId,
        "id_carteira"     => $walletId, // Mapeado para compatibilidade com o JS
        "apiKeySubconta"  => $subcontaData['apiKey'] ?? null
    ]);

} else {

    // Tratamento de erro detalhado caso o Asaas recuse
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
