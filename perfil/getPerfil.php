<?php
    // Permite requisições de qualquer origem
    header("Access-Control-Allow-Origin: *");
    // Permite os métodos HTTP
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    // Permite os cabeçalhos de conteúdo0 específicos, como o Content-Type
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json"); 

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

        if(!isset($dados['email'])) {
            http_response_code(400);
            echo json_encode(["erro" => "Email não fornecido"]);
            exit;
        }
        

        //Buscar Cliente
        $stmtCliente = $conn -> prepare("SELECT Id_Cliente, Nome, Email, Cpf, Telefone, Avatar_Url FROM Clientes WHERE Email = :email");
        $stmtCliente -> bindParam(':email', $dados['email']);
        $stmtCliente -> execute();
        $cliente = $stmtCliente -> fetch(PDO::FETCH_ASSOC);

        if(!$cliente) {
            http_response_code(404);
            echo json_encode(["erro" => "Usuário não encontraado"]);
            exit;
        }
        //Fim do Buscar Cliente


        //Buscar endereço com base no id do cliente
        $stmtEndereco = $conn -> prepare("SELECT * FROM Enderecos WHERE Cliente_Id = :clienteid");
        $stmtEndereco -> bindParam(':clienteid', $cliente['Id_Cliente']);
        $stmtEndereco -> execute();
        $enderecosBanco = $stmtEndereco -> fetchAll(PDO::FETCH_ASSOC);

        $enderecosNormalizados = [];
        if($enderecosBanco) {
            foreach($enderecosBanco as $endereco) {
                $enderecosNormalizados[] = [
                    'id_endereco' => $endereco['Id_Endereco'],
                    'cliente_id' => $endereco['Cliente_Id'],
                    'rua' => $endereco['Rua'],
                    'numero' => $endereco['Numero'],
                    'cidade' => $endereco['Cidade'],
                    'estado' => $endereco['Estado'],
                    'cep' => $endereco['Cep'],
                    'complemento' => $endereco['Complemento'],
                    'bairro' => $endereco['Bairro'],
                    'principal' => (bool)$endereco['Principal'],
                    'logradouro' => $endereco['Logradouro'],
                ];
            }
        }
        //Fim do Buscar Endereço


        //Deletar endereço com base no id do cliente

        //Fim do Deletar Endereço


        $usuarioFormatado = [
            "id" => $cliente['Id_Cliente'],
            "nome" => $cliente['Nome'],
            "email" => $cliente['Email'],
            "telefone" => $cliente['Telefone'],
            "cpf" => $cliente['Cpf'],
            "avatar" => $cliente['Avatar_Url'],
            "endereco" => $enderecosNormalizados
        ];

        echo json_encode([
            "usuario" => $usuarioFormatado,
            "enderecos" => $enderecosNormalizados   
        ]);
        
    } catch(PDOexception $e) {
        http_response_code(500);
        echo json_encode(["erro" => "Erro ao buscar dados: ". $e -> getMessage()]);
    }
?>