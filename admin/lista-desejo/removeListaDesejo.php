<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: DELETE, OPTIONS");
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

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parte_url = explode('/', $path);
    $ids_request = array_slice($parte_url, -2);
    $idCliente_parte = array_pop($ids_request);
    $idProduto_parte = array_pop($ids_request);

    if(is_numeric($idCliente_parte) && is_numeric($idProduto_parte) && $idCliente_parte > 0 && $idProduto_parte > 0) {
        try {
            $stmtDelete = $conn->prepare("DELETE FROM Lista_Desejo WHERE Id_Cliente = ? AND Id_Produto = ?");
            $stmtDelete->execute([$idCliente_parte, $idProduto_parte]);

            http_response_code(200);
            echo json_encode(['mensagem' => 'Produto removido com sucesso da Lista de Desejo']);
            exit;
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao remover produto da lista de desejo: '. $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Id de Cliente ou Produto inválido']);
        exit;
    }
?>