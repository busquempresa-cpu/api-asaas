<?php
// index.php

// Habilita o CORS para que seu app Firebase se conecte sem bloqueios
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// Responde requisições de preflight do navegador (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 SUA CHAVE DE SANDBOX DO ASAAS
$apiKey = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmIyN2IxZGVkLWE4YzItNDgxMS04MTc2LWIwMzJhNGFkOTk2NTo6JGFhY2hfZjBjNWI1MmUtNWYzMi00NzM5LWFkNzgtMjExNjk0YzYwM2Jl'; 

// URL do Asaas em Sandbox (Ambiente de Testes)
$asaasUrl = 'https://sandbox.asaas.com/api/v3/payments';

// Pega os dados enviados pelo seu app do Firebase (Valor e ID do Cliente)
$inputJson = file_get_contents('php://input');
$input = json_decode($inputJson, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['value'])) {
    
    // Dados da cobrança do PIX exigidos pelo Asaas
    $data = [
        "customer" => "cus_000005728491", // ID do cliente de teste (use o ID do cliente criado no seu Sandbox)
        "billingType" => "PIX",
        "value" => floatval($input['value']),
        "dueDate" => date('Y-m-d', strtotime('+1 day')), // Vence amanhã
        "description" => "Recarga Saldo Cashback - Busque Empresa"
    ];

    // Configuração da chamada cURL para o Asaas
    $ch = curl_init($asaasUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'access_token: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    // Devolve a resposta do Asaas (incluindo o QR Code e o Copia e Cola) para o Firebase
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Mensagem simples caso acesse o link direto pelo navegador
header('Content-Type: application/json');
echo json_encode([
    "status" => "Servidor Backend do Busque Empresa Rodando na Koyeb!",
    "ambiente" => "Sandbox (Testes)"
]);
