<?php
    // Permite os métodos HTTP
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    // Permite os cabeçalhos de conteúdo, como o Content-Type
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // Enquanto estiver desenvolvendo em localhost
    $allowed_origins = ['http://localhost:4200']; // Porta do Angular

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }

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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'E-mail inválido']);
        exit;
    }

    try {
        //Adicionar verificação de Ip no futuro
        $stmtBloqueado = $conn->prepare("SELECT 1 FROM Tentativas_Login WHERE Email = ? AND Bloqueado_Ate > NOW()");
        $stmtBloqueado->execute([$email]);
        $tentativa = $stmtBloqueado->fetch(PDO::FETCH_ASSOC);

        if($tentativa) {
            http_response_code(429);
            echo json_encode(['mensagem' => 'Usuário bloqueado temporariamente. Tente novamente em 15 minutos.']);
            //Adicionar uma mensagem de quantos minutos faltam para o desbloqueio no futuro???
            exit;
        }
    } catch(PDOException $e) {
        http_response_code(500);
         if($_ENV['APP_ENV'] === 'development') {
            echo json_encode(['mensagem' => 'Erro no servidor: ' . $e->getMessage()]);
        } else {
            echo json_encode(['mensagem' => 'Erro no servidor. Tente novamente mais tarde.']);
            error_log($e->getMessage()); // Log interno
        }
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT Id_Adm, Nome, Senha FROM Usuarios_Admin WHERE Email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($senha, $admin['Senha'])) {
            $payload = [
                'iss' => 'http://localhost', // Emissor (sua URL)
                'aud' => 'http://localhost', // Audiência (sua URL)
                'iat' => time(), // Hora em que o JWT foi emitido
                'exp' => time() + (60 * 15), // Tempo de expiração (24 horas)
                'data' => [
                    'id' => $admin['Id_Adm'],
                    'nome' => $admin['Nome'],
                    'email' => $email,
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
            $stmtVerify = $conn->prepare("SELECT Email, Tentativas FROM Tentativas_Login WHERE Email = ?");
            $stmtVerify->execute([$email]);
            $verificacao = $stmtVerify->fetch(PDO::FETCH_ASSOC); //PESQUISA isso melhor - Ja entendi isto

            if ($verificacao && $verificacao['Tentativas'] >= 5) {
                $stmtBlock = $conn->prepare("UPDATE Tentativas_Login SET Bloqueado_Ate = NOW() + INTERVAL 1 MINUTE, Tentativas = 0 WHERE Email = ?");
                $stmtBlock->execute([$email]);

                http_response_code(429); //Bloqueio por muitas tentativas
                echo json_encode(['mensagem' => 'Usuário bloqueado temporariamente. Tente novamente em 15 minutos.']);
                exit;
            } else {
                $stmtVerify = $conn->prepare(
                    "INSERT INTO Tentativas_Login (Email) VALUES (?)
                    ON DUPLICATE KEY 
                    UPDATE Tentativas = Tentativas + 1, Ultima_Tentativa = NOW()"
                );
                $stmtVerify->execute([$email]);

                /*Fazer verificação para encontrar as tentativas restantes*/

                $stmtTentativas = $conn->prepare("SELECT Tentativas FROM Tentativas_Login WHERE Email =  ?");
                $stmtTentativas->execute([$email]);
                $tentativas = $stmtTentativas->fetch(PDO::FETCH_ASSOC); 
                $tentativasRestantes = 5 - $tentativas['Tentativas'];

                http_response_code(401); //Não autorizado/crendenciais erradas
                echo json_encode(['mensagem' => 'Email ou Senha incorretos. Tentativas restantes: '. $tentativasRestantes]);
                exit;
            }
        }

    } catch (PDOException $e) {
        http_response_code(500);
        if ($_ENV['APP_ENV'] === 'development') {
            echo json_encode(['mensagem' => 'Erro no servidor: ' . $e->getMessage()]);
            //Em desenvolvimento exponha detalhes para facilitar a depuração
        } else {
            echo json_encode(['mensagem' => 'Erro no servidor. Por favor, tente novamente mais tarde.']);
            //Erro no log para análise posterior, sem expor detalhes ao usuário
            error_log($e->getMessage());
        }
    }
?>