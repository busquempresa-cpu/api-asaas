<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, access_token");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Sua Chave Master de Homologação
$apiKeyMaster = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4'; 
$urlAsaas = 'https://sandbox.asaas.com/v3/accounts';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(["sucesso" => false, "erro" => "Dados vazios."]);
    exit;
}

$dadosSubconta = [
    "name" => $input['nome'],
    "email" => $input['email'],
    "cpfCnpj" => $input['cpfCnpj'],
    "phone" => $input['telefone'],
    "address" => $input['endereco'],
    "addressNumber" => $input['numero'],
    "province" => $input['bairro'],
    "postalCode" => $input['cep'],
    "companyType" => "INDIVIDUAL"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlAsaas);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . $apiKeyMaster
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 || $httpCode == 201) {
    $subcontaData = json_decode($response, true);
    // Retorna o ID (SUB_...) para você guardar no Firebase do lojista
    echo json_encode([
        "sucesso" => true,
        "asaasCustomerId" => $subcontaData['id']
    ]);
} else {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro ao criar subconta.",
        "detalhes" => json_decode($response, true)
    ]);
}
?>
