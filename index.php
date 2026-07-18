<?php
// Habilita o CORS para que seu app Firebase se conecte sem bloqueios
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Responder requisições de comprovação do navegador (CORS Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// URL do Asaas em Sandbox correta para pagamentos
$asaasUrl = 'https://api-sandbox.asaas.com/v3/payments';

// Pega os dados enviados pelo seu app do Firebase
$inputJson = file_get_contents('php://input');
$entrada = json_decode($inputJson, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CAPTURA DINÂMICA: Pega as chaves da subconta do lojista enviadas pelo app
    $tokenSubcontaLojista = $entrada['apiKeyLojista'] ?? '';
    $idClienteNoAsaas     = $entrada['customerIdLojista'] ?? 'cus_000005728491'; // Fallback caso queira usar um fixo por enquanto

    // 2. SUA CARTEIRA MASTER: ID fixo da sua conta MEI que vai receber os R$ 25,00 de comissão
    $suaCarteiraMasterId = 'b1823719-xxxx-xxxx-xxxx-xxxxxxxxxxxx'; // <--- INSIRA O ID DA SUA CARTEIRA MASTER AQUI

    // Pega o valor total enviado pelo aplicativo (Ex: 225.00)
    $valorTotal = floatval($entrada['valentia'] ?? 0); 
    $taxaPlataforma = 25.00; 

    // Validação mínima de segurança
    if ($valorTotal < $taxaPlataforma || empty($tokenSubcontaLojista)) {
        header('Content-Type: application/json');
        echo json_encode([
            "sucesso" => false, 
            "erro" => "Dados insuficientes para gerar a recarga ou valor abaixo de R$ 25,00."
        ]);
        exit;
    }

    // 3. ESTRUTURA DO SPLIT INVERTIDO (Proteção Fiscal do MEI):
    // A cobrança é gerada na conta do lojista. O split puxa os R$ 25,00 para a sua conta Master.
    $dados = [
        "customer" => $idClienteNoAsaas, 
        "billingType" => "PIX",
        "value" => $valorTotal, // Valor cheio que o lojista vai pagar (Ex: R$ 225,00)
        "dueDate" => date('Y-m-d'), // Vencimento imediato para o mesmo dia
        "description" => "Recarga Saldo Cashback - Busque Empresa",
        
        // Retém apenas a sua comissão no MEI
        "split" => [
            [
                "walletId" => $suaCarteiraMasterId, // Sua carteira Master recebe fixo
                "fixedValue" => $taxaPlataforma     // Exatamente R$ 25,00
            ]
        ]
    ];

    // Configuração da chamada cURL para o Asaas enviando com a credencial da Subconta
    $ch = curl_init($asaasUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'access_token: ' . $tokenSubcontaLojista, // Executa em nome da subconta do lojista
        'User-Agent: MeuCashback'
    ]);

    $resposta = curl_exec($ch);
    $codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dadosResultado = json_decode($resposta, true);

    // 4. PASSO AUTOMÁTICO: Captura os dados da cobrança criada e busca os dados de imagem e cópia e cola do Pix
    if ($codigo_http === 200 || $codigo_http === 201) {
        $idCobranca = $dadosResultado['id'] ?? '';

        $chPix = curl_init("https://api-sandbox.asaas.com/v3/payments/{$idCobranca}/pixQrCode");
        curl_setopt($chPix, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chPix, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chPix, CURLOPT_HTTPHEADER, [
            'access_token: ' . $tokenSubcontaLojista
        ]);
        
        $respostaPix = curl_exec($chPix);
        curl_close($chPix);
        
        // Devolve o QR Code (Base64) e o código Copia e Cola diretamente para o app
        header('Content-Type: application/json');
        echo $respostaPix;
        exit;
    }

    // Se falhar, devolve o erro retornado pelo Asaas
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
