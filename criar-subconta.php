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

$token_asaas = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';





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



curl_setopt($ch, CURLOPT_HTTPHEADER, [

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

    echo json_encode([

        "sucesso" => true,

        "walletId" => $dadosRetorno['walletId'] ?? ''

    ]);

    // Procure por uma linha parecida com esta no seu código (onde ele captura o walletId da resposta):

$walletId = $dadosConta['walletId']; // O nome da variável pode variar um pouco no seu código



// LOGO ABAIXO DELA, INSIRA ESTA LINHA PARA DISPARAR A CRIAÇÃO DA CHAVE:

criarChavePixAutomatica($walletId, $api_key);

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

    // Define a URL correta (Sandbox ou Produção)

    $url = "https://sandbox.asaas.com/api/v3/pix/addressKeys"; 

    // Se for produção, mude para: https://api.asaas.com/v3/pix/addressKeys



    $ch = curl_init();



    curl_setopt_array($ch, [

        CURLOPT_URL => $url,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CUSTOMREQUEST => "POST",

        CURLOPT_POSTFIELDS => json_encode([

            "type" => "EVP" // EVP = Chave Aleatória (Gerada na hora sem burocracia)

        ]),

        CURLOPT_HTTPHEADER => [

            "Content-Type: application/json",

            "access_token: " . $apiKeyMaster, // Seu Token Master

            "walletId: " . $walletId          // Vincula diretamente à conta do Lojista

        ],

    ]);



    $resposta = curl_exec($ch);

    curl_close($ch);



    return json_decode($resposta, true);

} 

