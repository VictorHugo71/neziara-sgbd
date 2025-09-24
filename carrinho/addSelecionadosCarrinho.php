<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");

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

    $dados = json_decode(file_get_contents('php://input'), true);

    $idCliente = $dados['Id_Cliente'];
    $produtos = $dados['produtoFormatado'];
    
    try {
        foreach ($produtos as $produto) {
            $idProduto = $produto['Id_Produto'];
            $quantidade = $produto['Quantidade'];

            // Validação de dados
            if (!is_numeric($idCliente) || !is_numeric($idProduto) || !is_numeric($quantidade)) {
                http_response_code(400);
                echo json_encode(['mensagem' => 'Dados inválidos.']);
                exit;
            }

            $stmtConsulta = $conn->prepare("SELECT p.Estoque, p.Nome_Produto, c.Quantidade AS QuantidadeNoCarrinho FROM Produtos AS p LEFT JOIN Carrinho AS c ON p.Id_Produto = c.Id_Produto AND c.Id_Cliente = ? WHERE p.Id_Produto = ?");
            $stmtConsulta->execute([$idCliente, $idProduto]);
            $dadosProduto = $stmtConsulta->fetch(PDO::FETCH_ASSOC);

            $nomeProduto = $dadosProduto['Nome_Produto'];
            $estoqueDisponivel = $dadosProduto['Estoque'] ?? 0;
            $quantidadeNoCarrinho = $dadosProduto['QuantidadeNoCarrinho'] ?? 0;
            $quantidadeFinal = $quantidadeNoCarrinho + $quantidade;

            // Verificação de estoque antes de qualquer operação no banco
            if ($quantidadeFinal > $estoqueDisponivel) {
                http_response_code(400);
                echo json_encode(['mensagem' => "A quantidade solicitada do produto '{$nomeProduto}' excede o estoque disponível."]);
                exit;
            }

            if ($quantidadeNoCarrinho == 0) {
                $stmtInsert = $conn->prepare("INSERT INTO Carrinho (Id_Cliente, Id_Produto, Quantidade) VALUES (?, ?, ?)");
                $stmtInsert->execute([$idCliente, $idProduto, $quantidade]);
            } else {
                $stmtUpdate = $conn->prepare("UPDATE Carrinho SET Quantidade = ? WHERE Id_Produto = ? AND Id_Cliente = ?");
                $stmtUpdate->execute([$quantidadeFinal, $idProduto, $idCliente]);
            }
        }
        
        http_response_code(200);
        echo json_encode(['mensagem' => 'Produtos processados com sucesso!']);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Falha ao processar produtos no carrinho.']);
        exit;
    }
?>