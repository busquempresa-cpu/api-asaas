<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Impede que requisições de teste (OPTIONS) quebrem a execução
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$token_asaas = getenv('ASAAS_API_KEY');
$asaasUrl = "https://sandbox.asaas.com/api/v3/accounts";

// Recebe os dados enviados pelo seu Aplicativo
$input = json_decode(file_get_contents("php://input"), true);

$nome      = $input['nome'] ?? '';
$documento = preg_replace('/\D/', '', $input['documento'] ?? ''); // Remove pontos e traços
$email     = $input['email'] ?? '';
$whatsapp  = preg_replace('/\D/', '', $input['whatsapp'] ?? '');

if (empty($nome) || empty($documento) || empty($email)) {
    echo json_encode(["erro" => "Nome, documento e email são campos obrigatórios."]);
    http_response_code(400);
    exit;
}

// Monta o payload para o Asaas criar a Subconta/Conta filha
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $cnpj,
    "mobilePhone" => $whatsapp,
    "companyType" => strlen($documento) > 11 ? "LIMITED" : "INDIVIDUAL" // Define se é MEI/LTDA ou Pessoa Física
];

// Dispara a requisição Curl para o Asaas
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $asaas_token"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dadosRetorno = json_decode($response, true);

if ($http_code === 200 || $http_code === 201) {
    // Retorna com sucesso o walletId (ID de carteira/subconta gerado pelo Asaas)
    echo json_encode([
        "sucesso" => true,
        "walletId" => $dadosRetorno['walletId'] // Esse é o ID de identificação que gravamos no Firebase
    ]);
} else {
    // Caso ocorra erro de validação (ex: CPF inválido ou e-mail já usado no Asaas)
    $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? "Erro desconhecido ao criar subconta.";
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro
    ]);
    http_response_code(400);
}
