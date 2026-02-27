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

    require_once __DIR__ . '/../vendor/autoload.php';
    use Firebase\JWT\JWT;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

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
        $stmtLogin = $conn->prepare("SELECT Id_Cliente, Nome, Senha FROM Clientes WHERE Email = ?");
        $stmtLogin->execute([$email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente && password_verify($senha, $cliente['Senha'])) {
            $payload = [
                'iss' => 'http://localhost',
                'aud' => 'http://localhost',
                'iat' => time(),
                'exp' => time() + (60 * 15),
                'data' => [
                    'id' => $cliente['Id_Cliente'],
                    'nome' => $cliente['Nome'],
                    'email' => $email,
                    'papel' => 'cliente' // Definindo o papel do usuário
                ]
            ];

            $jwt = JWT::encode($payload, $key, 'HS256');

            http_response_code(200);
            echo json_encode([
                'mensagem' => 'Login realizado com sucesso',
                'token' => $jwt // O token é enviado aqui!
            ]);
        } else {
            $stmtVerify = $conn->prepare("SELECT Email, Tentativas FROM Tentativas_Login WHERE Email = ?");
            $stmtVerify->execute([$email]);
            $verificacao = $stmtVerify->fetch(PDO::FETCH_ASSOC); //PESQUISA isso melhor - Ja entendi isto

            if ($verificacao && $verificacao['Tentativas'] >= 5) {
                $stmtBlock = $conn->prepare("UPDATE Tentativas_Login SET Bloqueado_Ate = NOW() + INTERVAL 15 MINUTE, Tentativas = 0 WHERE Email = ?");
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

                http_response_code(401); //Não autorizado/crendenciais erradas
                echo json_encode(['mensagem' => 'Email ou Senha incorretos. Tentativas restantes: ']);
                exit;
            }

            /*if($verificacao && $verificacao['Tentativas'] < 5) {
                $stmtUpdate = $conn->prepare("UPDATE Tentativas_Login SET Tentativas = Tentativas + 1, Ultima_Tentativa = NOW() WHERE Email = ?");
                $stmtUpdate->execute([$email]);

                echo json_encode(['mensagem' => 'E-mail ou senha incorretos. Tentativas Restante: ' . (5 - ($verificacao['Tentativas'] + 1))]);
                //Adicionar o http code aqui ou no else principal???
            } else {
                $stmtInsert = $conn->prepare("INSERT INTO Tentativas_Login (Email) VALUES (?) ");
                $stmtInsert->execute([$email]);

                //$verificacao['Tentativas'] não exite no banco, só depois deste INSERT!!!
                //Como faz a conta com o $verificacao['Tentativas'] se ela não existe?????? 
                //Fazer outro select depois do insert para pegar o número de tentativas atualizado e mostrar a mensagem correta para o usuário??????
                echo json_encode(['mensagem' => 'E-mail ou senha incorretos. Tentativas Restante: ' . (5 - $verificacao['Tentativas'])]);

                //Adicionar o http code aqui ou no else principal???

                //Pesquisar e talvez adicionar um ON DUPLICATE KEY UPDATE para esta parte
                //e no else fazer o bloqueio do usuário por 15 minutos
            }*/

            
            /*Implementação do Rate Limiting para prevenir ataques de força bruta
            e melhorar a segurança do sistema.
            Se login falhar atualize o contador de tentativas e 
            bloqueie com 5 erros por 15min ou mais
            AQUI!!!!*/
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