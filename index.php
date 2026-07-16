<?php
// Habilita o CORS para que seu app Firebase se conecte sem bloqueios
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Responde requisições de comprovação do navegador (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// O PHP vai buscar a chave de forma totalmente segura direto do Render
$chave_de_api = $_ENV['ASAAS_API_KEY'] ?? $_SERVER['ASAAS_API_KEY'] ?? getenv('ASAAS_API_KEY');

// URL do Asaas em Sandbox correta (Sem o /api/ no meio)
$asaasUrl = 'https://api-sandbox.asaas.com/v3/payments';

// Pega os dados enviados pelo seu app do Firebase (Valor)
$inputJson = file_get_contents('php://input');
$entrada = json_decode($inputJson, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($entrada['valor'])) {

    // Dados da cobrança do PIX exigidos pelo Asaas (Nomes originais em inglês)
    $dados = [
        "customer" => "cus_000005728411", // Certifique-se de usar um ID de cliente de teste válido do seu Sandbox
        "billingType" => "PIX",
        "value" => floatval($entrada['valor']),
        "dueDate" => date('Y-m-d', strtotime('+1 days')),
        "description" => "Recarga Saldo Cashback - Busque Empresa"
    ];

    // Configuração da chamada cURL para o Asaas
    $ch = curl_init($asaasUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignora verificação SSL no ambiente de testes
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'access_token: ' . $chave_de_api,
        'User-Agent: MeuCashback' // Identificador obrigatório para o Asaas aceitar
    ]);

    $resposta = curl_exec($ch);
    curl_close($ch);

    // Devolve a resposta do Asaas (incluindo o QR Code e Copia e Cola) para o Firebase
    header('Content-Type: application/json');
    echo $resposta;
    exit;
}

// Mensagem simples caso acesse o link direto pelo navegador
header('Content-Type: application/json');
echo json_encode([
    "status" => "Servidor Backend do Busque Empresa Rodando",
    "ambiente" => "Sandbox (Testes)"
]);
