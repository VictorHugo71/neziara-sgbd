<?php
    ini_set('log_errors', 'On');
    ini_set('error_reporting', E_ALL);
    ini_set('error_log', __DIR__ . '/../php_errors.log');

    require_once __DIR__ . '/../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    
    function validarJWT(): array {
        // Acessa a chave do arquivo .env
        $key = $_ENV['JWT_SECRET'];
        try{
            $authorizationHeader = getallheaders()['Authorization'] ?? '';
        
            if (!$authorizationHeader) {
                http_response_code(401);
                echo  json_encode(["mensagem" => "Token de autorização ausente"]);
                exit;
            }

            $token = str_replace('Bearer ', '', $authorizationHeader);

            if (empty($token)) {
                http_response_code(400);
                echo json_encode(["mensagem" => "Token de autorização vazio"]);
                exit;
            }

            $decoded = JWT::decode($token, new Key ($key, 'HS256'));

            return [
                "id" => $decoded->data->id,
                "nome" => $decoded->data->nome,
                "email" => $decoded->data->email,
                "papel" => $decoded->data->papel,
             ];

        } catch (ExpiredException $e) {
            // Handle expired token: inform the client to refresh or re-login
            http_response_code(401);
            if($_ENV['APP_ENV'] === 'development') {
                //Trocar o "X" da linha abaixo para o nome do cliente ou sistema específico, para facilitar a identificação do problema durante o desenvolvimento.
                echo json_encode(['mensagem' => 'Token expirado para o Cliente X: ' . $e->getMessage()]);
                exit;
            } else {
                echo json_encode(['mensagem' => 'Token expirado. Atualize seu token ou faça login novamente.']);
                error_log($e->getMessage()); // Log interno, organizar as mensagens dos logs depois
                exit;
            } 
        } catch (Exception $e) {
            // Handle other errors (invalid signature, etc.)
            http_response_code(401);
            if($_ENV['APP_ENV'] === 'development') {
                echo json_encode(['mensagem' => 'Tentativa de acesso com token inválido ' . $e->getMessage()]);
                exit;
            } else {
                echo json_encode(['mensagem' => 'Acesso negado. Tente novamente mais tarde.']);
                error_log($e->getMessage()); // Log interno, Organizar as mensagens dos logs depois
                exit;
            }
        }

        /*
            400 Bad Request Requisição malformada Erros de sintaxe, JSON inválido ou falta de campos obrigatórios.
            401 Unauthorized Autenticação necessária Usuário não logado, token expirado ou senha incorreta.
            403 Forbidden Acesso proibido  Usuário logado, mas sem permissão para acessar aquele recurso específico.
        */


        /*
            Adicionar uma forma de atualizar o token (refresh token) para 
            evitar que o usuário tenha que fazer login novamente a cada 
            15 minutos, ou seja, quando o token expirar, o usuário pode 
            usar um refresh token para obter um novo token sem precisar 
            inserir suas credenciais novamente.

            **IDÉIA **
            Criar uma tabela de refresh tokens no banco de dados, 
            onde cada token é associado a um usuário e tem uma 
            data de expiração. Quando o usuário fizer login, 
            além de gerar o JWT, também gera um refresh token e 
            armazena ambos no banco de dados. O refresh token 
            pode ser enviado ao cliente junto com o JWT. Quando o JWT 
            expirar, o cliente pode enviar o refresh token para um 
            endpoint específico (por exemplo, /refresh-token) para 
            obter um novo JWT. O servidor verifica se o refresh token é 
            válido e não expirou, e se for válido, gera um novo JWT e 
            retorna para o cliente. Se o refresh token for inválido ou 
            expirado, o servidor pode solicitar que o usuário faça 
            login novamente.




            Refresh Token Flow (Recommended): The server issues a short-lived Access Token (e.g., 15 mins) and a long-lived Refresh Token (e.g., 7 days).
            The client receives a 401 Unauthorized (Token Expired) response.
            The client sends the Refresh Token to a dedicated /refresh endpoint.
            The server validates the Refresh Token (usually against a database) and issues a new Access Token.
        */
    }


?>