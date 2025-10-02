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
    $database = "E-commerce";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao conectar ao banco de dados']);
        exit;
    }

try {
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $dados = json_decode(file_get_contents("php://input"), true);

    // Para depuração: registra os dados recebidos em um arquivo de log
    file_put_contents("log_debug.txt", "Dados recebidos: " . print_r($dados, true) . "\n", FILE_APPEND);

    if (!isset($dados['id']) || !isset($dados['email'])) { // Removi 'nome' pois pode não vir na atualização parcial
        http_response_code(400);
        echo json_encode(["erro" => "Dados de usuário incompletos (ID ou Email)."]);
        exit;
    }

    $id_cliente = filter_var($dados['id'], FILTER_VALIDATE_INT);
    if (!$id_cliente) {
        http_response_code(400);
        echo json_encode(["erro" => "ID de cliente inválido."]);
        exit;
    }

    // --- INÍCIO DA TRANSAÇÃO ---
    // É crucial iniciar a transação aqui para que todas as operações sejam atômicas
    $conn->beginTransaction();

    // 1. ATUALIZAR DADOS DO CLIENTE
    // Note que a sua query de update cliente espera todos os campos. 
    // Se o Angular não envia todos eles em cada chamada de salvarPerfil, você pode precisar ajustar esta query
    $stmtUpdateCliente = $conn->prepare("UPDATE Clientes SET Nome = :nome, Email = :email, Cpf = :cpf, Telefone = :telefone, Avatar_Url = :avatar WHERE Id_Cliente = :id");
    $stmtUpdateCliente->bindParam(':nome', $dados['nome']);
    $stmtUpdateCliente->bindParam(':email', $dados['email']);
    $stmtUpdateCliente->bindParam(':cpf', $dados['cpf']);
    $stmtUpdateCliente->bindParam(':telefone', $dados['telefone']);
    $stmtUpdateCliente->bindParam(':avatar', $dados['avatar']);
    $stmtUpdateCliente->bindParam(':id', $id_cliente, PDO::PARAM_INT); // Usar PDO::PARAM_INT para IDs
    $stmtUpdateCliente->execute();

    // 2. OBTER ENDEREÇOS ATUAIS NO BANCO DE DADOS PARA ESTE CLIENTE
    $stmtEnderecosAtuais = $conn->prepare("SELECT Id_Endereco FROM Enderecos WHERE Cliente_Id = :cliente_id");
    $stmtEnderecosAtuais->bindParam(':cliente_id', $id_cliente, PDO::PARAM_INT);
    $stmtEnderecosAtuais->execute();
    $enderecosAtuaisIds = $stmtEnderecosAtuais->fetchAll(PDO::FETCH_COLUMN, 0); // Pega apenas os IDs como um array simples

    $enderecosEnviadosIds = []; // Para armazenar os IDs dos endereços que vieram do frontend

    // 3. PROCESSAR ENDEREÇOS ENVIADOS PELO FRONTEND (INSERIR OU ATUALIZAR)
    $enderecosRecebidos = $dados['endereco'] ?? []; // Garante que é um array, mesmo que vazio
    foreach ($enderecosRecebidos as $endereco) {
        // Converte booleano para 0 ou 1 para o banco de dados
        // Usa uma verificação mais robusta para booleano ou 0/1
        $principal_db = ($endereco['principal'] === true || $endereco['principal'] === 1) ? 1 : 0;

        if (isset($endereco['id_endereco']) && !empty($endereco['id_endereco'])) {
            // É UMA ATUALIZAÇÃO DE ENDEREÇO EXISTENTE
            $id_endereco_atual = filter_var($endereco['id_endereco'], FILTER_VALIDATE_INT);
            if (!$id_endereco_atual) {
                // Se o ID é inválido, lança uma exceção para o catch block
                throw new Exception("ID de endereço inválido para atualização: " . $endereco['id_endereco']);
            }
            $enderecosEnviadosIds[] = $id_endereco_atual; // Adiciona o ID para controle de remoção

            $stmtEndereco = $conn->prepare("UPDATE Enderecos SET Rua = :rua, Numero = :numero, Cidade = :cidade, Estado = :estado, Cep = :cep, Complemento = :complemento, Bairro = :bairro, Principal = :principal, Logradouro = :logradouro WHERE Id_Endereco = :id_endereco AND Cliente_Id = :idCliente");
            
            $stmtEndereco->bindParam(':id_endereco', $id_endereco_atual, PDO::PARAM_INT);
        } else {
            // É UMA INSERÇÃO DE NOVO ENDEREÇO
            $stmtEndereco = $conn->prepare("INSERT INTO Enderecos (Cliente_Id, Rua, Numero, Cidade, Estado, Cep, Complemento, Bairro, Principal, Logradouro) VALUES (:idCliente, :rua, :numero, :cidade, :estado, :cep, :complemento, :bairro, :principal, :logradouro)");
        }

        // BIND PARAMETERS COMUNS PARA INSERT/UPDATE
        $stmtEndereco->bindParam(':idCliente', $id_cliente, PDO::PARAM_INT);
        $stmtEndereco->bindParam(':rua', $endereco['rua']);
        $stmtEndereco->bindParam(':numero', $endereco['numero']);
        $stmtEndereco->bindParam(':cidade', $endereco['cidade']);
        $stmtEndereco->bindParam(':estado', $endereco['estado']);
        $stmtEndereco->bindParam(':cep', $endereco['cep']);
        $stmtEndereco->bindParam(':complemento', $endereco['complemento']);
        $stmtEndereco->bindParam(':bairro', $endereco['bairro']);
        $stmtEndereco->bindParam(':principal', $principal_db, PDO::PARAM_INT); // PDO::PARAM_INT para 0 ou 1
        $stmtEndereco->bindParam(':logradouro', $endereco['logradouro']);
        
        $stmtEndereco->execute();

        // Se for uma inserção, obtenha o novo ID e adicione-o à lista de IDs enviados
        if (!isset($endereco['id_endereco']) || empty($endereco['id_endereco'])) {
            $enderecosEnviadosIds[] = $conn->lastInsertId();
        }
    }

    // 4. DELETAR ENDEREÇOS QUE NÃO FORAM ENVIADOS PELO FRONTEND
    // Encontra os IDs que estão no banco mas não foram enviados pelo frontend
    $idsParaDeletar = array_diff($enderecosAtuaisIds, $enderecosEnviadosIds);

    // Debugging: Registrar os IDs para deletar
    file_put_contents("log_debug.txt", "IDs no banco: " . print_r($enderecosAtuaisIds, true) . "\n", FILE_APPEND);
    file_put_contents("log_debug.txt", "IDs enviados: " . print_r($enderecosEnviadosIds, true) . "\n", FILE_APPEND);
    file_put_contents("log_debug.txt", "IDs para deletar: " . print_r($idsParaDeletar, true) . "\n", FILE_APPEND);


    if (!empty($idsParaDeletar)) {
        // Cria uma string de placeholders para a cláusula IN (ex: ?, ?, ?)
        $placeholders = implode(',', array_fill(0, count($idsParaDeletar), '?'));
        // Adiciona uma condição para garantir que só delete endereços do cliente correto
        $stmtDelete = $conn->prepare("DELETE FROM Enderecos WHERE Id_Endereco IN ($placeholders) AND Cliente_Id = ?");
        
        // Combina os IDs para deletar com o ID do cliente para o bind
        $bindParams = array_merge($idsParaDeletar, [$id_cliente]);
        
        // Binda cada valor individualmente
        foreach ($bindParams as $k => $id) {
            $stmtDelete->bindValue(($k+1), $id, PDO::PARAM_INT);
        }
        $stmtDelete->execute();
        // Debugging: Registrar quantos endereços foram deletados
        file_put_contents("log_debug.txt", "Deletados " . $stmtDelete->rowCount() . " endereços.\n", FILE_APPEND);
    }

    // Se tudo ocorreu bem, comita a transação
    $conn->commit();

    http_response_code(200);
    echo json_encode(["mensagem" => "Perfil e endereços atualizados com sucesso!"]);
    
} catch (PDOException $e) {
    // Se algo deu errado com o banco de dados, faz rollback da transação
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao atualizar o perfil: " . $e->getMessage()]);
    // Registra o erro completo no log do PHP
    error_log("PDOException no atualiza_perfil.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
} catch (Exception $e) {
    // Para outras exceções que não sejam PDO (ex: validação de ID inválido)
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["erro" => "Erro inesperado: ". $e->getMessage()]);
    // Registra o erro completo no log do PHP
    error_log("Exception no atualiza_perfil.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
}
?>