<?php
$host = 'localhost';
$dbname = 'bd_cliente';
$user = 'usuario';
$pass = 'senha';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass); //PDO interface orientada a objetos
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //setattribute configura o compartemento da conexão; attr_errmode define modo de tratamento de erros, ERRMODE_EXECPTION orienta a, em havendo erro, criar uma exceção
} catch (PDOException $e) { //se houver exceção guarda ela na variável $e
    die("Erro na conexão: " . $e->getMessage()); // -> signifca acesse o método
}