<?php
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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
    $database = "E-commerce";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$database;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro na conexão com o banco de dados.']);
        exit;
    }

    if(empty(trim($_POST['nome'])) || empty(trim($_POST['descricao'])) || empty(trim($_POST['preco'])) || empty(trim($_POST['estoque'])) || empty(trim($_POST['categoria'])) || empty(trim($_POST['status']))){
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos']);
        exit;
    }

    if(!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Erro no upload da imagem.']);
        exit;
    }

    $caminho_temp = $_FILES['imagem']['tmp_name'];
    $mime_type = mime_content_type($caminho_temp);
    $extension = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);

    $permitidos = [
        "jpeg" => "image/jpeg",
        "png" => "image/png",
        "jpg" => "image/jpeg"];

    if(!array_key_exists($extension, $permitidos) || !in_array($mime_type, $permitidos)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Tipo de arquivo não permitido.']);
        exit;
    }

    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = trim($_POST['preco']);
    $estoque = trim($_POST['estoque']);
    $categoria = trim($_POST['categoria']);
    $status = intval($_POST['status']);
    
    $nome_imagem_unico = uniqid() . '.' . $extension;
    $pasta_destino = '../uploads/';
    $caminho_final = $pasta_destino . $nome_imagem_unico;

    try {
        $stmt = $conn->prepare("SELECT Id_Categoria FROM Categorias WHERE Id_Categoria = ?");
        $stmt->execute([$categoria]);
        $res = $stmt->rowCount();

        if($res == 0) {
            http_response_code(400);
            echo json_encode(['mensagem' => 'Categoria inexistente.']);
            exit;
        }

        // Move o arquivo e insere no banco de dados
        if(move_uploaded_file($caminho_temp, $caminho_final)) {
            $stmt = $conn->prepare("INSERT INTO Produtos (Nome_Produto, Preco, Descricao, Estoque, Id_Categoria, Status, Imagem_Url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $preco, $descricao, $estoque, $categoria, $status, $nome_imagem_unico]);
            
            http_response_code(201);
            echo json_encode(['mensagem' => 'Produto cadastrado com sucesso.']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao mover a imagem.']);
            exit;
        }

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro no servidor: ' . $e->getMessage()]);
        exit;
    }
?>