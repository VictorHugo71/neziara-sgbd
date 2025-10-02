<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json"); 

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

    $dados = json_decode(file_get_contents("php://input"),true);
    
    if(!isset($dados['email']) || empty(trim($dados['email']))) {
        http_response_code(400);
        echo json_encode(["mensagem" => "Email não fornecido"]);
        exit;
    }

    $email = trim($dados['email']);
            
    try {
        //Buscar Cliente
        $stmtCliente = $conn -> prepare("SELECT Id_Cliente, Nome, Email, Cpf, Telefone, Avatar_Url FROM Clientes WHERE Email = ?");
        $stmtCliente -> execute([$email]);
        $cliente = $stmtCliente -> fetch(PDO::FETCH_ASSOC);

        if(!$cliente) {
            http_response_code(404);
            echo json_encode(["mensagem" => "Usuário não encontrado"]);
            exit;
        }

        http_response_code(200);
        echo json_encode(["mensagem" => "Dados do usuário carregados com sucesso.",
            "usuario" => [
                "id" => $cliente['Id_Cliente'],
                "nome" => $cliente['Nome'],
                "email" => $cliente['Email'],
                "telefone" => $cliente['Telefone'],
                "cpf" => $cliente['Cpf'],
                "avatar" => $cliente['Avatar_Url'],
            ]
        ]);
    } catch(PDOexception $e) {
        http_response_code(500);
        echo json_encode(["mensagem" => "Erro ao buscar dados: ". $e -> getMessage()]);
        exit;
    }
?>