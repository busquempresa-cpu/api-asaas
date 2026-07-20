<?php
// Habilita o CORS para o seu aplicativo Firebase
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, User-Agent");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// 🔐 Puxe a chave API definida no Ambiente do Render / Servidor
$apiKeyMaster = getenv('ASAAS_API_KEY');

if (!$apiKeyMaster) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Chave de API do Asaas não configurada no servidor"
    ]);
    exit;
}

// 🟢 URL Correta do Sandbox da API do Asaas
$urlAsaas = 'https://api-sandbox.asaas.com/v3/accounts';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Dados de entrada não recebidos"
    ]);
    exit;
}

// 🧹 Limpeza de máscaras para evitar recusas no Asaas
$cpfCnpjClean = preg_replace('/[^0-9]/', '', $input['cpfCnpj'] ?? '');
$cepClean     = preg_replace('/[^0-9]/', '', $input['cep'] ?? '');
$phoneClean   = preg_replace('/[^0-9]/', '', $input['whatsapp'] ?? $input['celular'] ?? '');

// 🛠️ Montagem do Payload ajustado para a API do Asaas
$dadosSubconta = [
    "name"          => trim($input['nome'] ?? ''),
    "email"         => trim($input['email'] ?? ''),
    "cpfCnpj"       => $cpfCnpjClean,
    "mobilePhone"   => $phoneClean,
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
    'access_token: ' . trim($apiKeyMaster),
    'User-Agent: MeuCashback/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$subcontaData = json_decode($response, true);

if ($httpCode == 200 || $httpCode == 201) {

    // Mapeamento completo para garantir que o JS receba os dados
    $accountCode = $subcontaData['id'] ?? null;
    $walletId    = $subcontaData['walletId'] ?? null;
    $apiKey      = $subcontaData['apiKey'] ?? null;

    echo json_encode([
        "sucesso"          => true,
        "asaasCustomerId"  => $accountCode,
        "walletId"         => $walletId,
        "id_carteira"      => $walletId,
        "apiKeySubconta"   => $apiKey
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
