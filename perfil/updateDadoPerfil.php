<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json"); 

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
    $dbname = "E-commerce";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao conectar ao banco de dados']);
        exit;
    }
   
    $dados = json_decode(file_get_contents("php://input"), true);

    if(
        !isset($dados['id']) || empty(trim($dados['id'])) || 
        !isset($dados['email']) || empty(trim($dados['email'])) || 
        !isset($dados['nome']) || empty(trim($dados['nome']))
    ){
        http_response_code(400);
        echo json_encode(["mensagem" => "Dados necessários não fornecidos não fornecido."]);
        exit;
    }

    $idCliente = (int)$dados['id'];
    $nome = trim($dados['nome']);
    $email = trim($dados['email']);

    $telefone = isset($dados['telefone']) ? trim($dados['telefone']) : null;
    $cpf = isset($dados['cpf']) ? trim($dados['cpf']) : null;
    $avatar = isset($dados['avatar'])? trim($dados['avatar']): null;

    try {
        $stmtUnico = $conn->prepare("SELECT COUNT(Id_Cliente) FROM Clientes WHERE (Email = ? OR Cpf = ?) AND Id_Cliente != ?");
        $stmtUnico->execute([$email, $cpf, $idCliente]);
        $count = $stmtUnico->fetchColumn();

        if($count > 0) {
            http_response_code(409);
            echo json_encode(['mensagem' => 'Email ou cpf já estão sendo utilizados por outro cliente']);
            exit;
        }
            $stmtUpdate = $conn->prepare("UPDATE Clientes SET Nome = ?, Email = ?, Telefone = ?, Cpf = ?, Avatar_Url = ? WHERE Id_Cliente = ?");
            $stmtUpdate->execute([$nome, $email, $telefone, $cpf, $avatar, $idCliente]);

            if($stmtUpdate->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(['mensagem' => 'Dados atualizados com sucesso']);
            } else {
                http_response_code(200);
                echo json_encode(['mensagem' => 'Nenhuma alteração detectada']);
            }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["mensagem" => "Erro ao atualizar dados: ". $e->getMessage()]);
        exit;
    }
?>