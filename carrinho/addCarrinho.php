<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: OPTIONS, POST");
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
    $Quantidade = trim($dados['Quantidade']);

    if(isset($Id_Cliente) && isset($Id_Produto) && isset($Quantidade) && is_numeric($Id_Cliente) && is_numeric($Id_Produto) && is_numeric($Quantidade) && $Id_Cliente > 0 && $Id_Produto > 0 && $Quantidade > 0) {
        try {
            $stmtConsulta = $conn->prepare("SELECT Id_Produto FROM Carrinho WHERE Id_Produto = ? AND Id_Cliente = ?");
            //fazer logica de consulta/comparação de estoque com a quantidade de produtos no carrinho para saber o que fazer
            $stmtConsulta->execute([$Id_Produto, $Id_Cliente]);
            $itemNoCarrinho = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
            
            if(!$itemNoCarrinho) {
                $stmtInsert = $conn->prepare("INSERT INTO Carrinho (Id_Cliente, Id_Produto, Quantidade) VALUES (?, ?, ?)");
                $stmtInsert->execute([$Id_Cliente, $Id_Produto, $Quantidade]);

                http_response_code(201);
                echo json_encode(['mensagem' => 'Produto adicionado ao Carrinho com sucesso!']);
            } else {
                $stmtUpdate = $conn->prepare("UPDATE Carrinho SET Quantidade = Quantidade + ? WHERE Id_Produto = ? AND Id_Cliente = ?");
                $stmtUpdate->execute([$Quantidade, $Id_Produto, $Id_Cliente]);

                http_response_code(200);
                echo json_encode(['mensagem' => 'Quantidade do produto atualizada com sucesso!']);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao adicionar produto ao Carrinho: '. $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos']);
        exit;
    }
?>