<?php
// Permite requisições de qualquer origem
header("Access-Control-Allow-Origin: *");
// Permite os métodos HTTP
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Permite os cabeçalhos de conteúdo, como o Content-Type
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

// Acessa a chave do arquivo .env
$key = $_ENV['JWT_SECRET'];

// Área de Conexão com o Banco de Dados //
$servername = "localhost";
$username = "root";
$password = "";
$database = "E-commerce";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["mensagem" => "Erro na conexão com o banco de dados: " . $e->getMessage()]);
    exit;
}

$dados = json_decode(file_get_contents("php://input"), true);

if (!isset($dados['email'], $dados['senha']) || empty($dados['email']) || empty($dados['senha'])) {
    http_response_code(400);
    echo json_encode(['mensagem' => 'E-mail e senha são obrigatórios']);
    exit;
}

$email = trim($dados['email']);
$senha = $dados['senha'];

try {
    $stmt = $conn->prepare("SELECT Id_Adm, Nome, Senha FROM Usuarios_Admin WHERE Email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($senha, $admin['Senha'])) {
        $payload = [
            'iss' => 'http://localhost', // Emissor (sua URL)
            'aud' => 'http://localhost', // Audiência (sua URL)
            'iat' => time(), // Hora em que o JWT foi emitido
            'exp' => time() + (3600 * 24), // Tempo de expiração (24 horas)
            'data' => [
                'id' => $admin['Id_Adm'],
                'nome' => $admin['Nome'],
                'papel' => 'admin' // Definindo o papel do usuário
            ]
        ];

        // Geração do token
        $jwt = JWT::encode($payload, $key, 'HS256');

        // Retorna o token e a mensagem de sucesso
        http_response_code(200);
        echo json_encode([
            'mensagem' => 'Login realizado com sucesso',
            'token' => $jwt // O token é enviado aqui
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['mensagem' => 'E-mail ou senha incorretos']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['mensagem' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>