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
        $conn->setAttribute(PDO::ERRMODE_EXCEPTION, PDO::ATTR_ERRMODE);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao conectar ao banco de dados']);
        exit;
    }

    $dados = json_decode(file_get_contents("php://input"));

    $chaves_superiores = [
        'idCliente', 
        'emailCliente',
        'telefoneCliente',
        'valorTotal',
        'metodoPagamento',
        'itens',
        'nomeCliente'
    ];

    $endereco = null;

    $chaves_endereco = [
        'estado',
        'cidade',
        'bairro',
        'logradouro',
        'cep',
        'complemento',
        'numero'
    ];

    $erro_validacao = false;

    foreach($chaves_superiores as $chave) {
        if(!isset($dados->{$chave})) {
            $erro_validacao = true;
            break;
        }
    }

    if(!$erro_validacao && (!is_array($dados->itens) || count($dados->itens) === 0)) {
        $erro_validacao = true;
    }

    if(!$erro_validacao && isset($dados->enderecoEnvio)) {
        $endereco = $dados->enderecoEnvio;
        foreach($chaves_endereco as $chave) {
            if(!isset($endereco->{$chave})) {
                $erro_validacao = true;
                break;
            }
        }
    }

    if($erro_validacao) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos ou inválidos']);
        exit;
    }

    $idEndereco = $dados->enderecoEnvio->idEndereco;
    $estado = $dados->enderecoEnvio->estado;
    $cidade = $dados->enderecoEnvio->cidade;
    $bairro = $dados->enderecoEnvio->bairro;
    $logradouro = $dados->enderecoEnvio->logradouro;
    $complemento = $dados->enderecoEnvio->complemento;
    $numero = $dados->enderecoEnvio->numero;
    $cep = $dados->enderecoEnvio->cep;

    $idCliente = $dados->idCliente;
    $emailCliente = $dados->emailCliente;
    $telefoneCliente = $dados->telefoneCliente;
    $nomePedido = $dados->nomeCliente;

    $metodoPagamento = $dados->metodoPagamento;
    $valorTotal = $dados->valorTotal;

    try{
        $conn->beginTransaction();

        $stmtPedido = $conn->prepare("INSERT INTO Pedidos (Id_Cliente, Email_Pedido, Telefone_Pedido, Estado_Pedido, Cidade_Pedido, Bairro_Pedido, Logradouro_Pedido, Complemento_Pedido, Cep_Pedido, Numero_Pedido, Metodo_Pagamento, Valor_Total, Data_Pedido, Status_Pedido, Nome_Pedido)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pendente', ?)");
        $stmtPedido->execute([
            $idCliente, $emailCliente, $telefoneCliente, $estado, $cidade, $bairro, $logradouro, 
            $complemento, $cep, $numero, $metodoPagamento, $valorTotal, $nomePedido
        ]);

        $idPedido = $conn->lastInsertId();

        foreach($dados->itens as $item) {
            if(!isset($item->Id_Produto) || !isset($item->Quantidade) || !isset($item->Preco_Unitario)) {
                http_response_code(400);
                echo json_encode(['mensagem' => 'Dados do item incompletos']);
                exit;
            }

            $idProduto = $item->Id_Produto;
            $quantidade = $item->Quantidade;
            $precoUnitario = $item->Preco_Unitario;

            $stmtItem = $conn->prepare("INSERT INTO Itens_Pedido (Id_Pedido, Id_Produto, Quantidade, Preco_Unitario) VALUES (?, ?, ?, ?)");
            $stmtItem->execute([$idPedido, $idProduto, $quantidade, $precoUnitario]);
        }
        $conn->commit();
        
        http_response_code(201);
        echo json_encode(['mensagem' => 'Pedido realizado com sucesso', 'Id_Pedido' => (int)$idPedido]);
    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack(); 
        }
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao processar o pedido', $e->getMessage()]);
        exit;
    }
?>