<?php
    // Permite os métodos HTTP
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

    require_once __DIR__ . '/../vendor/autoload.php';
    use Firebase\JWT\JWT;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    
    $servername = $_ENV['DB_HOST'];
    $username = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASS'];
    $database = $_ENV['DB_NAME'];
    
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $database)) {
        die('Nome de banco inválido');
    }
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        //Pegar os dados do JSON vindo do Angular
        $dados = json_decode(file_get_contents("php://input"),true);

        if(!isset($dados['nome'], $dados['email'], $dados['senha'])) {
            http_response_code(400);
            echo json_encode(["erro" => "dados incompletos"]);
            exit;
        }

        //Processo de fazer hashcode na senha
        $senhaSegura = password_hash($dados['senha'], PASSWORD_DEFAULT);

        //Inserção no Banco
        $stmt = $conn -> prepare("INSERT INTO Clientes (Nome, Email, Senha) VALUES (:nome, :email, :senha)");
        $stmt -> bindParam(':nome', $dados['nome']);
        $stmt -> bindParam(':email', $dados['email']);
        $stmt -> bindParam(':senha', $senhaSegura);
        $stmt -> execute();

        echo json_encode(["mensagem" => "Cadastro Realizado com Sucesso"]);

    } catch (PDOException $e) { //Captura de erro
        if ($e->errorInfo[1] == 1062) {
            http_response_code(409);
            echo json_encode(["erro" => "Este e-mail já está cadastrado"]);
        } else {
            http_response_code(500);
            echo json_encode(["erro" => "Erro ao cadastrar: " . $e->getMessage()]);
        }
    }
?>