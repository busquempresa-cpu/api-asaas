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

// Se a conta foi criada com sucesso, avançamos
if ($codigo_http === 200 || $codigo_http === 201) {
    
    $subconta_id = $dadosRetorno['id'] ?? ''; // O ID único da subconta criada (ex: b6bff0c5...)
    $walletId = $dadosRetorno['walletId'] ?? '';
    $subconta_apiKey = '';
    $chavePixGerada = '';

    // 2º PASSO NOVO: Como o Asaas não entrega a API Key de bandeja, nós geramos ela usando o endpoint correto
    if (!empty($subconta_id)) {
        $url_gerar_chave = "https://api-sandbox.asaas.com/v3/accounts/{$subconta_id}/apiKey";
        
        // Define o nome de identificação da chave que aparecerá no painel
        $dadosChave = ["name" => "Chave de Acesso Integrada - Meu Cashback"];

        $ch_chave = curl_init();
        curl_setopt($ch_chave, CURLOPT_URL, $url_gerar_chave);
        curl_setopt($ch_chave, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_chave, CURLOPT_POST, true);
        curl_setopt($ch_chave, CURLOPT_POSTFIELDS, json_encode($dadosChave));
        curl_setopt($ch_chave, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_chave, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "access_token: $token_master", // A conta Master solicita a criação usando a permissão que você ativou
            "User-Agent: MeuCashback"
        ]);

        $resposta_chave = curl_exec($ch_chave);
        $codigo_http_chave = curl_getinfo($ch_chave, CURLINFO_HTTP_CODE);
        curl_close($ch_chave);

        if ($codigo_http_chave === 200 || $codigo_http_chave === 201) {
            $dadosRetornoChave = json_decode($resposta_chave, true);
            $subconta_apiKey = $dadosRetornoChave['apiKey'] ?? ''; // Chave de API gerada com sucesso!
        }
    }

    // 3º PASSO: Criar a chave Pix Aleatória para ela usando a chave que acabamos de gerar
    if (!empty($subconta_apiKey)) {
        
        $url_pix = "https://api-sandbox.asaas.com/v3/pix/addressKeys";
        $dadosPix = ["type" => "EVP"]; // EVP significa chave aleatória

        $ch_pix = curl_init();
        curl_setopt($ch_pix, CURLOPT_URL, $url_pix);
        curl_setopt($ch_pix, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_pix, CURLOPT_POST, true);
        curl_setopt($ch_pix, CURLOPT_POSTFIELDS, json_encode($dadosPix));
        curl_setopt($ch_pix, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_pix, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "access_token: $subconta_apiKey", // Executa a chamada EM NOME da subconta com sua nova chave
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

    // Retorna a resposta estruturada contendo a 'apiKey' gerada.
    // Certifique-se de que o JavaScript do seu front-end pega este retorno e o grava no Firebase como 'asaasApiKey'
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
