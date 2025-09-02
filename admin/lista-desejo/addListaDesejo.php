<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");

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
    $Id_Cliente = trim($dados['Id_Cliente']);
    $Id_Produto = trim($dados['Id_Produto']);

    if(is_numeric($Id_Cliente) && is_numeric($Id_Produto) && $Id_Cliente > 0 && $Id_Produto > 0) {
        try {
            $stmtVerify = $conn->prepare("SELECT Id_Produto FROM Lista_Desejo WHERE Id_Produto = ? AND Id_Cliente = ?");
            $stmtVerify->execute([$Id_Produto, $Id_Cliente]);
            $produtoCarrinho = $stmtVerify->rowCount();
            
            if(!$produtoCarrinho) {
                try {
                    $stmtInsert = $conn->prepare("INSERT INTO Lista_Desejo (Id_Cliente, Id_Produto) VALUES (?, ?)");
                    $stmtInsert->execute([$Id_Cliente, $Id_Produto]);
                    http_response_code(201);
                    echo json_encode(['mensagem' => 'Produto adicionado à lista de desejo com sucesso!']);
                    exit;
                } catch(PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['mensagem' => 'Erro ao adicionar produto à lista de desejo: '. $e->getMessage()]);
                    exit;
                }
            } else {
                http_response_code(409);
                echo json_encode(['mensagem' => 'Produto já adicionado à lista de desejo']);
                exit;
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao buscar produto: '. $e->getMessage()]);
            exit;
        }

            
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados do Produto ou Cliente inválidos ou ausentes.']);
        exit;
    }
?>