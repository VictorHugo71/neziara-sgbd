<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

    $dados = json_decode(file_get_contents('php://input'), true);

    $idCliente = $dados['Id_Cliente'];
    $produtos = $dados['produtoFormatado'];
    
    // Define uma variável para a mensagem final de sucesso
    $mensagemSucesso = '';

    try {
        foreach ($produtos as $produto) {
            $idProduto = $produto['Id_Produto'];
            $quantidade = $produto['Quantidade'];

            // Validação de dados de forma mais robusta
            if (!is_numeric($idCliente) || !is_numeric($idProduto) || !is_numeric($quantidade)) {
                http_response_code(400);
                echo json_encode(['mensagem' => 'Dados inválidos.']);
                exit;
            }

            $stmtConsulta = $conn->prepare("SELECT Id_Produto FROM Carrinho WHERE Id_Produto = ? AND Id_Cliente = ?");
            $stmtConsulta->execute([$idProduto, $idCliente]);
            $itemNoCarrinho = $stmtConsulta->fetch(PDO::FETCH_ASSOC);

            if (!$itemNoCarrinho) {
                $stmtInsert = $conn->prepare("INSERT INTO Carrinho (Id_Cliente, Id_Produto, Quantidade) VALUES (?, ?, ?)");
                $stmtInsert->execute([$idCliente, $idProduto, $quantidade]);
                // Se o primeiro produto inserido, define a mensagem de sucesso
                if (empty($mensagemSucesso)) {
                    $mensagemSucesso = 'Produtos adicionados ao Carrinho com sucesso!';
                }
            } else {
                $stmtUpdate = $conn->prepare("UPDATE Carrinho SET Quantidade = Quantidade + ? WHERE Id_Produto = ? AND Id_Cliente = ?");
                $stmtUpdate->execute([$quantidade, $idProduto, $idCliente]);
                // Se o primeiro produto atualizado, define a mensagem de sucesso
                if (empty($mensagemSucesso)) {
                    $mensagemSucesso = 'Quantidade dos produtos atualizada com sucesso!';
                }
            }
        }
        
        // Define uma única resposta de sucesso após o loop
        http_response_code(200);
        // Se nenhum produto foi processado, a mensagem padrão é definida
        if (empty($mensagemSucesso)) {
             echo json_encode(['mensagem' => 'Nenhum produto foi processado.']);
        } else {
             echo json_encode(['mensagem' => 'Produtos processados com sucesso!']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Falha ao processar produtos no carrinho.']);
        exit;
    }
?>