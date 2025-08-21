<?php
    // Permite requisições de qualquer origem
    header("Access-Control-Allow-Origin: *");
    // Permite os métodos HTTP
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    // Permite os cabeçalhos de conteúdo0 específicos, como o Content-Type
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Área de Conexão com o Banco de Dados //
    $servername = "localhost"; //Nome do Servidor onde está o Banco de Dados
    $username = "root"; //Usuário para conectar no Banco de Dados
    $password = ""; //Senha para conectar no Banco de dado(se necessário)
    $database = "E-commerce"; //Nome do Banco de Dados que quer conectar no servidor

    try{ //try de conexão do banco de dados
        $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //Pegar os dados do JSON vindo do Angular

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["mensagem" => "Erro na conexão com o banco de dados: ". $e -> getMessage()]);
    }

    $dados = json_decode(file_get_contents("php://input"), true);

    if(!isset ($dados['email'], $dados['senha']) || empty($dados['email']) || empty($dados['senha'])) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'E-mail e senha são obrigatórios']);
        exit;
    }

    $email = trim($dados['email']);
    $senha = $dados['senha'];

    try {
        $stmt = $conn -> prepare("SELECT Id_Adm, Nome, Senha FROM Usuarios_Admin WHERE Email = ?");
        $stmt -> execute([$email]);
        $admin = $stmt -> fetch(PDO::FETCH_ASSOC);

        if($admin && password_verify($senha, $admin['Senha'])) {
            http_response_code(200);
            echo json_encode(['mensagem' => 'Login realizado com sucesso']);
        } else {
            http_response_code(401);
            echo json_encode(['mensagem' => 'E-mail ou senha incorretos']);
        }

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro no servidor'. $e -> getMessage()]);
    }
?>