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

    $dados = json_decode(file_get_contents("php://input"),true);

    if(!isset($dados['id']) || empty(trim($dados['id']))) {
        http_response_code(400);
        echo json_encode(["mensagem" => "Id não encontrado."]);
        exit;
    }

    $idCliente = $dados['id'];

    try{
        $stmtEndereco = $conn->prepare("SELECT Id_Endereco, Cliente_Id, Estado, Cidade, Bairro, Logradouro, Complemento, Numero, Cep, Principal FROM Enderecos WHERE Cliente_Id = ?");
        $stmtEndereco->execute([$idCliente]);
        $enderecosDB = $stmtEndereco->fetchAll(PDO::FETCH_ASSOC); 

        if(empty($enderecosDB)) {
            http_response_code(200); 
            echo json_encode([
                "mensagem" => "Nenhum endereço encontrado. Lista vazia retornada.",
                "enderecos" => [] 
            ]);
            exit;
        }

        $enderecosMapeados = [];

        foreach($enderecosDB as $end) {
            $enderecosMapeados[] = [
                "idEndereco" => $end['Id_Endereco'],
                "idCliente" => $end['Cliente_Id'],
                "estado" => $end['Estado'],
                "cidade" => $end['Cidade'],
                "bairro" => $end['Bairro'],
                "logradouro" => $end['Logradouro'],
                "complemento" => $end['Complemento'],

                "numero" => (int)$end['Numero'],
                "cep" => $end['Cep'],
                "principal" => (bool)$end['Principal'],
            ];
        }

        http_response_code(200);
        echo json_encode(["mensagem" => "Dados de endereco do usuário carregados com sucesso.",
            "enderecos" => $enderecosMapeados
        ]);

    } catch(PDOexception $e) {
        http_response_code(500);
        echo json_encode(["mensagem" => "Erro ao buscar dados: ". $e->getMessage()]);
        exit;
    }
?>