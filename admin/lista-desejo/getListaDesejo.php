<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
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

    $url = $_SERVER['REQUEST_URI'];
    $parte_url = explode('/', $url);
    $id_parte = array_pop($parte_url);

    if(is_numeric($id_parte) && $id_parte > 0) {
        try {
            $stmt = $conn->prepare("SELECT Produtos.Id_Produto, Nome_Produto, Preco, Imagem_Url FROM Produtos INNER JOIN Lista_Desejo ON Produtos.Id_Produto = Lista_Desejo.Id_Produto WHERE Id_Cliente = ?");
            $stmt->execute([$id_parte]);

            $carrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($carrinho);
            exit;
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao buscar Lista de Desejo: '. $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'ID do produto inválido']);
        exit;
    }
?>