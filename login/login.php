<?php
    // Permite requisições de qualquer origem
    header("Access-Control-Allow-Origin: *");
    // Permite os métodos HTTP
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
    
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        //Pegar os dados do JSON vindo do Angular
        $dados = json_decode(file_get_contents("php://input"),true);

        if(!isset($dados['email'], $dados['senha'])) {
            http_response_code(400);
            echo json_encode(["erro" => "Dados Incompletos"]);
            exit;
        } 

        $stmt = $conn -> prepare("SELECT * FROM Clientes WHERE Email = :email");
        $stmt -> bindParam(':email', $dados['email']);
        $stmt -> execute();

        $usuario = $stmt -> fetch(PDO::FETCH_ASSOC);

        if($usuario && password_verify($dados['senha'], $usuario['Senha'])) {
            echo json_encode(["mensagem" => "Login realizado com sucesso", 
                "usuario" => [
                    "id" => $usuario['Id_Cliente'],
                    "nome" => $usuario['Nome'],
                    'email' => $usuario['Email']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["Erro" => "Email ou senha inválidos"]);
        }
    } catch (PDOException $e){
        http_response_code(500);
        echo json_encode(["erro" => "Erro ao conectar: ". $e -> getMessage()]);
    }
?>