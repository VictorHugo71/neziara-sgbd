<?php
    header("Access-Control-Allow-Origin:*");
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
    $database = "E-commerce";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$database;charset=utf8", $username, $password);
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
            $stmt = $conn->prepare("SELECT Id_Produto, Nome_Produto, Preco, Descricao, Estoque, Id_Categoria, Status, Imagem_Url FROM Produtos WHERE Id_Produto = ?");
            $stmt-> execute([$id_parte]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if($produto) {
                http_response_code(200);
                echo json_encode($produto);
                exit;
            } else {
                http_response_code(404);
                echo json_encode(['mensagem' => 'Produto não encontrado']);
                exit;
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao buscar produtos: '. $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'ID do produto inválido']);
        exit;
    }
?>