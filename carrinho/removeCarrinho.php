<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Methods: OPTIONS, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF8");

    // Enquanto estiver desenvolvendo em localhost
    $allowed_origins = ['http://localhost:4200']; // Porta do Angular

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "E-commerce";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao conectar ao banco de dados']);
        exit;
    }

    $idCliente = $_GET['Id_Cliente'];
    $idProduto = $_GET['Id_Produto'];

    if(isset($idCliente) && is_numeric($idCliente) && isset($idProduto) && is_numeric($idProduto)) {
        try {
            $stmtDelete = $conn->prepare("DELETE FROM Carrinho WHERE Id_Cliente = ? AND Id_Produto = ?");
            $stmtDelete->execute([$idCliente, $idProduto]);

            http_response_code(200);
            echo json_encode(['mensagem' => 'Produto removido com sucesso']);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao remover item do Carrinho'. $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos']);
        exit;
    }
?>