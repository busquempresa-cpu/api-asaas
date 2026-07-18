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

// Limpeza rigorosa do CPF/CNPJ: remove tudo o que não for número
$documento = isset($entrada['documento']) ? preg_replace('/[^0-9]/', '', $entrada['documento']) : '';
if (empty($documento)) {
    $documento = isset($entrada['cnpj']) ? preg_replace('/[^0-9]/', '', $entrada['cnpj']) : '';
}
if (empty($documento)) {
    $documento = isset($entrada['cpf']) ? preg_replace('/[^0-9]/', '', $entrada['cpf']) : '';
}

$nome = $entrada['nome'] ?? '';
$email = $entrada['email'] ?? '';
$whatsapp = isset($entrada['whatsapp']) ? preg_replace('/[^0-9]/', '', $entrada['whatsapp']) : '';

if (empty($whatsapp) || strlen($whatsapp) < 10) {
    $whatsapp = "49999999999"; 
}

// Validação simples de campos essenciais antes de enviar ao Asaas
if (empty($nome) || empty($documento) || empty($email)) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Nome, documento e email sao campos obrigatórios."
    ]);
    exit;
}

// Chave da sua conta Master do Asaas Sandbox
$token_master = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmRlYzM1MzVkLTM5NWEtNDg0OC04ZDVlLTI2NjQxNjI0YzZlYzo6JGFhY2hfMDdmNTRkOGUtNTk0Ni00ZWE3LTljMWEtZWQxYTY4ZjI2NzQ4';

// URL Oficial do Asaas Sandbox para criação de contas
$asaas_url = "https://api-sandbox.asaas.com/v3/accounts";

// Define o tipo de empresa com base no tamanho do documento limpo
$tipoEmpresa = (strlen($documento) > 11) ? "MEI" : "INDIVIDUAL";

// Prepara a estrutura exigida pelo Asaas para a subconta
$dadosSubconta = [
    "name" => $nome,
    "email" => $email,
    "cpfCnpj" => $documento,
    "companyType" => $tipoEmpresa,
    "mobilePhone" => $whatsapp,
    "incomeValue" => 5000,
    "postalCode" => "89600000",
    "address" => "Rua XV de Novembro",
    "addressNumber" => "100",
    "province" => "Centro",
];

// 1º PASSO: Criar a Subconta no Asaas
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $asaas_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosSubconta));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "access_token: $token_master",
    "User-Agent: MeuCashback"
]);

$resposta = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$resposta) {
    echo json_encode(["sucesso" => false, "erro" => "Nao foi possivel conectar ao servidor do Asaas."]);
    exit;
}

$dadosRetorno = json_decode($resposta, true);

// Se a conta foi criada com sucesso, vamos para o Passo 2 criar a Chave Pix dela
if ($codigo_http === 200 || $codigo_http === 201) {
    
    $walletId = $dadosRetorno['walletId'] ?? '';
    $subconta_apiKey = $dadosRetorno['apiKey'] ?? ''; // Chave de API própria da nova subconta
    $chavePixGerada = '';

    // 2º PASSO: Se a subconta retornou uma apiKey, criamos a chave Pix Aleatória para ela
    if (!empty($subconta_apiKey)) {
        
        $url_pix = "https://api-sandbox.asaas.com/v3/pix/addressKeys";
        $dadosPix = ["type" => "EVP"]; // EVP significa chave aleatória (Chave de Endereçamento Virtual)

        $ch_pix = curl_init();
        curl_setopt($ch_pix, CURLOPT_URL, $url_pix);
        curl_setopt($ch_pix, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_pix, CURLOPT_POST, true);
        curl_setopt($ch_pix, CURLOPT_POSTFIELDS, json_encode($dadosPix));
        curl_setopt($ch_pix, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_pix, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "access_token: $subconta_apiKey", // Executa a chamada EM NOME da subconta
            "User-Agent: MeuCashback"
        ]);

        $resposta_pix = curl_exec($ch_pix);
        $codigo_http_pix = curl_getinfo($ch_pix, CURLINFO_HTTP_CODE);
        curl_close($ch_pix);

        if ($codigo_http_pix === 200 || $codigo_http_pix === 201) {
            $dadosRetornoPix = json_decode($resposta_pix, true);
            $chavePixGerada = $dadosRetornoPix['key'] ?? ''; // Captura o hash da chave aleatória gerada
        }
    }

    // Retorna a resposta estruturada para o JavaScript do seu App ler e persistir no Firebase
    echo json_encode([
        "sucesso" => true,
        "walletId" => $walletId,
        "apiKey" => $subconta_apiKey,
        "chavePix" => $chavePixGerada,
        "statusPix" => (!empty($chavePixGerada)) ? "Chave Pix Aleatoria criada com sucesso" : "Nao foi possivel criar a chave Pix automaticamente"
    ]);

} else {
    // Trata o erro caso a criação da subconta falhe
    $mensagemErro = "Erro ao cadastrar no Asaas.";
    if (isset($dadosRetorno['errors']) && is_array($dadosRetorno['errors'])) {
        $mensagemErro = $dadosRetorno['errors'][0]['description'] ?? $mensagemErro;
    }
    
    echo json_encode([
        "sucesso" => false,
        "erro" => $mensagemErro,
        "detalhes" => "HTTP $codigo_http - Documento enviado: $documento"
    ]);
}
?>
