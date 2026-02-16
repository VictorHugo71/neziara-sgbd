<?php
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8"); // Adicionado para garantir o Content-Type

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

    // Área de Conexão com o Banco de Dados //
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "E-commerce";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$database;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro na conexão com o banco de dados']);
        exit;
    }

    // Lógica para GET (LISTAR) das categorias
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $stmt = $conn->prepare("SELECT Id_Categoria, Nome_Categoria AS Nome FROM Categorias ORDER BY Nome_Categoria ASC");
            $stmt->execute();
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode($categorias);
            exit; // Adicionado para encerrar o script
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao buscar categorias: '. $e->getMessage()]);
            exit;
        }
    }

    // Método para POST (INSERIR) uma nova categoria
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents("php://input"), true);

        if(!isset($dados['nome']) || empty(trim($dados['nome']))) {
            http_response_code(400);
            echo json_encode(['mensagem' => 'O nome da categoria é obrigatório.']);
            exit;
        }

        $nome = trim($dados['nome']);

        $stmt = $conn->prepare("SELECT Nome_Categoria FROM Categorias WHERE Nome_Categoria = ?");
        $stmt->execute([$nome]);
        $res = $stmt->rowCount();

        // If para verificar se a categoria já existe
        if($res == 0) {
            try {
                $stmt = $conn->prepare("INSERT INTO Categorias (Nome_Categoria) VALUES (?)");
                $stmt->execute([$nome]);

                http_response_code(201);
                echo json_encode(['mensagem' => 'Categoria cadastrada com sucesso.']);
                exit; // Adicionado para encerrar o script
            } catch(PDOException $e) {
                http_response_code(500);
                echo json_encode(['mensagem' => 'Erro ao cadastrar categoria: '. $e->getMessage()]);
                exit;
            }
        } else {
            http_response_code(409);
            echo json_encode(['mensagem' => 'Categoria já existente.']);
            exit; // Adicionado para encerrar o script
        }
    }
?>