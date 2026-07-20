<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 Puxa a chave API do Render
$apiKeyMaster = getenv('ASAAS_API_KEY') ?: '$aact_hmlg_000MzkwODA2MWY2OGM3MWRmDU2NWM3...';

// URL de Homologação com a barra no final para evitar o Redirecionamento (302)
$urlAsaas = 'https://sandbox.asaas.com/v3/accounts/';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(["sucesso" => false, "erro" => "Dados de entrada inválidos ou vazios."]);
    exit;
}

$dadosSubconta = [
    "name"          => $input['nome'] ?? '',
    "email"         => $input['email'] ?? '',
    "cpfCnpj"       => $input['cpfCnpj'] ?? '',
    "phone"         => $input['telefone'] ?? '',
    "address"       => $input['endereco'] ?? '',
    "addressNumber" => $input['numero'] ?? '',
    "province"      => $input['bairro'] ?? '',
    "postalCode"    => $input['cep'] ?? '',
    "companyType"   => "INDIVIDUAL"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlAsaas);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 👈 Força o cURL a seguir redirecionamentos (302)
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . trim($apiKeyMaster)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 || $httpCode == 201) {
    $subcontaData = json_decode($response, true);
    
    echo json_encode([
        "sucesso"         => true,
        "asaasCustomerId" => $subcontaData['id'] ?? null,
        "walletId"        => $subcontaData['walletId'] ?? $subcontaData['id'] ?? null
    ]);
} else {
    $detalhesErro = json_decode($response, true);
    
    if (isset($detalhesErro['errors'][0]['description'])) {
        $mensagemAsaas = $detalhesErro['errors'][0]['description'];
    } else {
        $mensagemAsaas = "Erro no servidor Asaas (Código HTTP: " . $httpCode . ")";
    }

    echo json_encode([
        "sucesso"  => false,
        "erro"     => "Asaas: " . $mensagemAsaas,
        "detalhes" => $detalhesErro
    ]);
}
?>
