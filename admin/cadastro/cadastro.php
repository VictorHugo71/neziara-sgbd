<?php

    // Permite os métodos HTTP
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    // Permite os cabeçalhos de conteúdo0 específicos, como o Content-Type
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

    require_once __DIR__ . '/../../vendor/autoload.php';
    use Firebase\JWT\JWT;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    $servername = $_ENV['DB_HOST'];
    $username = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASS'];
    $database = $_ENV['DB_NAME'];

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $database)) {
        die('Nome de banco inválido');
    }

    try{ //try de conexão do banco de dados
        $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //Pegar os dados do JSON vindo do Angular

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["mensagem" => "Erro na conexão com o banco de dados: ". $e -> getMessage()]);
        exit;
    }


    $dados = json_decode(file_get_contents("php://input"),true);

    if(!isset($dados['nome'], $dados['email'], $dados['senha']) || 
        empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos.']);
        exit; // ← Importante!
    }

    $nome = trim($dados['nome']);
    $email = trim($dados['email']);
    $senha = $dados['senha'];

    if(empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados inválidos']);
        exit;
    }

    try { //try de verificação de email
        $stmt = $conn -> prepare("SELECT Id_Adm FROM Usuarios_Admin WHERE Email = ?");
        $stmt -> execute([$email]);
        if($stmt -> fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode(['mensagem' => 'Este email já esta cadastrado']);
            exit;
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao verificar email']);
        exit;
    }

    try { //try de inserção no banco de dados
        $senhaSegura = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $conn -> prepare("INSERT INTO Usuarios_Admin (Nome, Email, Senha) VALUES (?, ?, ?)");
        if($stmt -> execute([$nome, $email, $senhaSegura])) {
            http_response_code(201);
            echo json_encode(['mensagem' => 'Cadastro de Administrador realizado com sucesso']);
        } else {
            http_response_code(500);
            echo json_encode(['mensagem' => 'Erro ao inserir dados']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro no  servidor'. $e -> getMessage()]);
    }
?>