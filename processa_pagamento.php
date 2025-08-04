<?php
require 'conecta.php';

$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOjI2ODQsInN0b3JlSWQiOjE5NzksImlhdCI6MTc1Mzk2MjIwOCwiZXhwIjoxNzU2Njg0Nzk5fQ.WlLjEihOHihKoznQkQLvVGIvYjJ4WmpoikSZmuTZ7oUs'; // token fornecido
$urlBase = 'https://apiinterna.ecompleto.com.br/exams/processTransaction?accessToken=' . $token;

$sql = "
SELECT p.id AS pedido_id, p.valor_total, pp.num_cartao, pp.codigo_verificacao, pp.vencimento,
       pp.nome_portador, c.id AS cliente_id, c.nome, c.cpf_cnpj, c.email, c.data_nasc
FROM pedidos p
JOIN pedidos_pagamentos pp ON pp.id_pedido = p.id
JOIN clientes c ON c.id = p.id_cliente
WHERE p.id_situacao = 1
  AND pp.id_formapagto = 3
  AND p.id_loja IN (
    SELECT id_loja FROM lojas_gateway WHERE id_gateway = 1
  );
";

$pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); //fetchall coleta os resultados retornados

foreach ($pedidos as $pedido) {
    echo "Processando pedido #{$pedido['pedido_id']}...\n";

    $body = [
        "external_order_id" => (int)$pedido["pedido_id"],
        "amount" => (float)$pedido["valor_total"],
        "card_number" => $pedido["num_cartao"],
        "card_cvv" => $pedido["codigo_verificacao"],
        "card_expiration_date" => str_replace("-", "", $pedido["vencimento"]),
        "card_holder_name" => $pedido["nome_portador"],
        "customer" => [
            "external_id" => (string)$pedido["cliente_id"],
            "name" => $pedido["nome"],
            "type" => "individual",
            "email" => $pedido["email"],
            "documents" => [
                [
                    "type" => "cpf",
                    "number" => $pedido["cpf_cnpj"]
                ]
            ],
            "birthday" => $pedido["data_nasc"]
        ]
    ];

    // Enviar requisição
    $ch = curl_init($urlBase);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($erro) {
        echo "Erro ao processar pedido {$pedido['pedido_id']}: $erro\n";
        continue;
    }

    $retorno = json_decode($resposta, true);
    $nova_situacao = ($retorno && isset($retorno['Error']) && $retorno['Error'] === false) ? 2 : 3;

    // Atualiza situação do pedido
    $stmt1 = $pdo->prepare("UPDATE pedidos SET id_situacao = ? WHERE id = ?");
    $stmt1->execute([$nova_situacao, $pedido['pedido_id']]);

    // Salva retorno da API
    $stmt2 = $pdo->prepare("UPDATE pedidos_pagamentos SET retorno_intermediador = ? WHERE id_pedido = ?");
    $stmt2->execute([json_encode($retorno), $pedido['pedido_id']]);

    echo "Pedido #{$pedido['pedido_id']} atualizado para situação $nova_situacao.\n";
}
