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

// Busca a chave de API de forma segura das variáveis de ambiente do Render
$token_asaas = $_ENV['ASAAS_API_KEY'] ?? $_SERVER['ASAAS_API_KEY'] ?? '';

if (empty($token_asaas)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Configuracao do servidor incompleta: Chave API nao encontrada."
    ]);
    exit;
}

// URL Oficial do Asaas Sandbox (sem o /api/)
$asaas_url = "https://api-sandbox.asaas.com/v3/accounts";

// Prepara os dados para enviar ao Asaas
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $documento,
    "mobilePhone" => $whatsapp,
    "companyType" => strlen($documento) > 11 ? "LIMITED" : "INDIVIDUAL",
    "incomeValue" => 5000,
];

// Dispara a requisição Curl para o Asaas
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));

// Evita erros de SSL no servidor de testes
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

curl_setopt($ch, CURLOPT_HTTPHEADER = [
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
    $walletId = $dadosRetorno['walletId'] ?? '';

    // DISPARO AUTOMÁTICO E DEFINITIVO DA CHAVE PIX NA SUBCONTA
    if (!empty($walletId)) {
        criarChavePixAutomatica($walletId, $token_asaas);
    }

    // Retorna o sucesso para o aplicativo apenas APÓS criar a chave
    echo json_encode([
        "sucesso" => true,
        "walletId" => $walletId
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

/**
 * Cria uma Chave Pix Aleatória (EVP) automática para uma subconta recém-criada.
 * 
 * @param string $walletId O ID da carteira retornada pelo Asaas
 * @param string $apiKeyMaster A sua chave token de API Master do Asaas
 * @return array Resposta da API do Asaas
 */
function criarChavePixAutomatica($walletId, $apiKeyMaster) {
    $url = "https://api-sandbox.asaas.com/v3/pix/addressKeys"; 

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode([
            "type" => "EVP" // EVP = Chave Aleatória (Gerada na hora sem burocracia)
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "access_token: " . $apiKeyMaster, // Seu Token dinâmico vindo do Render
            "walletId: " . $walletId          // Vincula diretamente à conta do Lojista
        ],
    ]);

    $resposta = curl_exec($ch);
    curl_close($ch);

    return json_decode($resposta, true);
}
