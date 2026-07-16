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

// Validação simples de campos obrigatórios
if (empty($nome) || empty($documento) || empty($email)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nome, documento e email sao campos obrigatorios."
    ]);
    exit;
}

// Busca a chave de API de forma segura das variáveis de ambiente do Render
$token_asaas = $_ENV['ASAAS_API_KEY'] ?? $_SERVER['ASAAS_API_KEY'] ?? '';

if (empty($token_asaas)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Configuracao do servidor incompleta: Chave API nao encontrada."
    ]);
    exit;
}

// URL Oficial do Asaas Sandbox
$asaas_url = "https://api-sandbox.asaas.com/v3/accounts";

// Regra crucial para o modelo BaaS:
// CPF sempre é INDIVIDUAL. CNPJ de teste no Sandbox sempre enviamos como MEI para evitar exigir dados de sócios.
$tipoEmpresa = (strlen($documento) > 11) ? "MEI" : "INDIVIDUAL";

// Prepara a estrutura exata aceita pelo BaaS Sandbox
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $documento,
    "companyType" => $tipoEmpresa,
    "mobilePhone" => $whatsapp,
    "incomeValue" => 5000
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
        "walletId" => $dadosRetorno['walletId'] ?? ''
    ]);
} else {
    // Retorna o erro exato que o Asaas devolver para sabermos o que corrigir se travar
    $mensagemErro = "Erro ao cadastrar no Asaas.";
    if (isset($dadosRetorno['errors']) && is_array($dadosRetorno['errors'])) {
        $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? $mensagemErro;
    }
    
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro
    ]);
}
