<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json"); 

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

    if(
        !isset($dados['Cliente_Id']) || empty(trim($dados['Cliente_Id']))|| 
        !isset($dados['endereco']) || 
        !is_array($dados['endereco']) //verifica se é um array, mesmo que seja um vazio é ok
    ){
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados dados de usuário incompleto']);
        exit;
    }

    $idCliente = (int)$dados['Cliente_Id'];
    $enderecosDados = $dados['endereco'];

    $sucessoGeral = true;
    $mensagemGeral = "Endereços sincronizados com sucesso.";

    $idsMantidos = [];

    foreach($enderecosDados as $end) {
        $idEndereco = isset($end['Id_Endereco']) ? trim($end['Id_Endereco']) : null;
        $estado = trim($end['Estado']);
        $cidade = trim($end['Cidade']);
        $bairro = trim($end['Bairro']);
        $logradouro = trim($end['Logradouro']);
        $complemento = isset($end['Complemento']) ? trim($end['Complemento']) : null;
        $numero = (int)$end['Numero'];
        $cep = $end['Cep'];
        $principal = empty($end['Principal']) ? 0 : 1;
        $principalInsert = 0;

        if($idEndereco == null) {
            try {
                $stmtInsert = $conn->prepare("INSERT INTO Enderecos (Cliente_Id, Estado, Cidade, Bairro, Logradouro, Complemento, Numero, Cep, Principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([
                    $idCliente, $estado, $cidade, $bairro, 
                    $logradouro, $complemento, $numero, $cep,
                    $principalInsert
                ]);

                $idsMantidos[] = $conn->lastInsertId();

            } catch(PDOException $e) {
                http_response_code(500);
                echo json_encode(["mensagem" => "Erro ao adicionar novo Endereço: ". $e->getMessage()]);
                exit;
            }
        } else {
            try {
                $stmtUpdate = $conn->prepare("UPDATE Enderecos SET Estado = ?, Cidade = ?, Bairro = ?, Logradouro = ?, Complemento = ?, Numero = ?, Cep = ?, Principal = ? WHERE Cliente_Id = ? AND Id_Endereco = ?");
                $stmtUpdate->execute([
                    $estado, $cidade, $bairro, 
                    $logradouro, $complemento, $numero, $cep,
                    $principal, $idCliente, $idEndereco
                ]);

                $idsMantidos[] = $idEndereco;
            } catch(PDOException $e) {
                http_response_code(500);
                echo json_encode(["mensagem" => "Erro ao atualizar Endereço: ". $e->getMessage()]);
                exit;
            }
        }
    }

    $enderecoPrincipalEnviado = false;
    foreach ($enderecosDados as $end) {
        if (isset($end['Principal']) && (int)$end['Principal'] === 1) {
            $enderecoPrincipalEnviado = true;
            // Capturamos o ID do endereço que DEVE ser o principal
            $idEnderecoPrincipal = isset($end['Id_Endereco']) ? (int)trim($end['Id_Endereco']) : $conn->lastInsertId();
            break; 
        }
    }

    if($enderecoPrincipalEnviado) {
        try {
            $stmtUnicidade = $conn->prepare("UPDATE Enderecos SET Pricipal = 0 WHERE Cliente_Id = ? AND Id_Endereco != ?");
            $stmtUnicidade->execute([$idCliente, $idEnderecoPrincipal]);
        } catch(PDOException $e) {
            error_log("Falha na unicidade Principal: " . $e->getMessage());
        }
    }

    $idsMantidosString = empty($idsMantidos) ? '-1' : implode(',', $idsMantidos);

    try {
        $stmtDelete = $conn->prepare("DELETE FROM Enderecos WHERE Cliente_Id = ? AND Id_Endereco NOT IN ($idsMantidosString)");
        $stmtDelete->execute([$idCliente]);

        $linhasDeletadas = $stmtDelete->rowCount();
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["mensagem" => "Erro ao deletar Endereços: ". $e->getMessage()]);
        exit;
    }

    http_response_code(200);
    echo json_encode(["mensagem" => "Endereços sincronizados com sucesso. Foram deletados $linhasDeletadas registros obsoletos."]);
?>