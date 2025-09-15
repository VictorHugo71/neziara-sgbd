<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: PUT, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF8");

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

    $dados = json_decode(file_get_contents('php://input'), true);
    
    $idCliente = $dados['Id_Cliente'];
    $idProduto = $dados['Id_Produto'];
    $quantidade = $dados['Quantidade'];

    if(isset($idCliente) && isset($idProduto) && isset($quantidade) && is_numeric($idCliente) && is_numeric($idProduto) && is_numeric($quantidade)) {
        try {
            $stmtUpdate = $conn->prepare("UPDATE Carrinho SET Quantidade = ? WHERE Id_Cliente = ? AND Id_Produto = ? ");
            $stmtUpdate->execute([$quantidade, $idCliente, $idProduto]);

            http_response_code(200);
            echo json_encode(['mensagem' => 'Quantidade do produto atualizada']);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao atualizar Carrinho'. $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados inválidos']);
        exit;
    }
?>