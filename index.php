<?php
// Habilita o CORS para que seu app Firebase se conecte sem bloqueios
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Responder requisições de comprovação do navegador (CORS Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// O PHP vai buscar a chave de forma totalmente segura direto do ambiente
$chave_de_api = $_ENV['ASAAS_API_KEY'] ?? $_SERVER['ASAAS_API_KEY'] ?? '';

// URL do Asaas em Sandbox correta
$asaasUrl = 'https://api-sandbox.asaas.com/v3/payments';

// Pega os dados enviados pelo seu app do Firebase
$inputJson = file_get_contents('php://input');
$entrada = json_decode($inputJson, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Pega o valor total enviado pelo aplicativo (Ex: 225.00)
    $valorTotal = floatval($entrada['valentia'] ?? 0); 

    // Define a sua comissão fixa da plataforma (R$ 25,00)
    $taxaPlataforma = 25.00; 

    // Calcula quanto vai para a carteira do lojista (Ex: 200.00)
    $valorLojista = $valorTotal - $taxaPlataforma;

    // Proteção simples para evitar valores negativos caso enviem um valor abaixo de R$ 25
    if ($valorLojista < 0) {
        $valorLojista = 0;
    }

    // Pega o ID da carteira (walletId) enviado pelo aplicativo
    $walletIdLojista = $entrada['ID da carteira'] ?? '';

    // Dados da cobrança do PIX com o sistema de Split integrado
    $dados = [
        "customer" => "cus_000005728491", // ID do cliente cadastrado na sua conta Master
        "billingType" => "PIX",
        "value" => $valorTotal, // Valor cheio que o lojista vai pagar no banco dele
        "dueDate" => date('Y-m-d', strtotime('+1 day')), // Vencimento para o próximo dia
        "description" => "Recarga Saldo Cashback - Busque Empresa",
        
        // === DIVISÃO AUTOMÁTICA DE VALORES ===
        "split" => [
            [
                "walletId" => $walletIdLojista, // ID da subconta do lojista que recebe o saldo
                "fixedValue" => $valorLojista   // O valor limpo calculado (Total - R$ 25)
            ]
        ]
    ];

    // Configuração da chamada cURL para o Asaas
    $ch = curl_init($asaasUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignora verificação SSL se estiver em Sandbox
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'access_token: ' . $chave_de_api,
        'User-Agent: MeuCashback'
    ]);

    $resposta = curl_exec($ch);
    curl_close($ch);

    // Devolve a resposta do Asaas (incluindo o QR Code e a linha digitável do Pix) para o seu App
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
