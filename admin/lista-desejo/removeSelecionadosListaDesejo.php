<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Methods: POST, OPTIONS");
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

    $dados = json_decode(file_get_contents('php://input'), true);

    $idCliente = $dados['Id_Cliente'];
    $produtos = $dados['produtoFormatado'];

    try {
        foreach ($produtos as $produto) {
            $idProduto = $produto['Id_Produto'];
            
            // Validação de dados
            if (!is_numeric($idCliente) || !is_numeric($idProduto)) {
                http_response_code(400);
                echo json_encode(['mensagem' => 'Dados inválidos.']);
                exit;
            }
            
            // AQUI ESTÁ A CORREÇÃO: Usando a tabela correta da lista de desejos
            $stmtDelete = $conn->prepare("DELETE FROM Lista_Desejo WHERE Id_Cliente = ? AND Id_Produto = ?");
            $stmtDelete->execute([$idCliente, $idProduto]);
        }
        
        // Resposta única de sucesso após o loop
        http_response_code(200);
        echo json_encode(['mensagem' => 'Produtos removidos da Lista de Desejo com sucesso!']);
        
    } catch(PDOException $e) {
        // Mensagem de erro corrigida
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao remover item da Lista de Desejo.']);
        exit;
    }
?>