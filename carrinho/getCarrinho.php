<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Methods: OPTIONS, GET");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");

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

    if(isset($idCliente) && is_numeric($idCliente) && $idCliente > 0) {
        try {
            $stmt = $conn->prepare("SELECT c.Id_Carrinho, c.Quantidade, p.Id_Produto, p.Nome_Produto, p.Preco, p.Estoque, p.Imagem_Url FROM Carrinho c INNER JOIN Produtos p ON c.Id_Produto = p.Id_Produto WHERE c.Id_Cliente = ?");
            $stmt->execute([$idCliente]);

            http_response_code(200);
            $carrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($carrinho);
            exit;
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao buscar Carrinho.'. $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos']);
        exit;
    }
?>