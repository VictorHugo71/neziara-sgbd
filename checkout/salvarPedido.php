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

    $dados = json_decode(file_get_contents("php://:input"));

    if(!isset($dados->enderecoEnvio->idEndereco) || !isset($dados->enderecoEnvio->idCliente) || 
        !isset($dados->usuarioMP->idCliente) || !isset($dados->usuarioMP->emailCliente) || 
        !isset($dados->itens) || !is_array($dados->itens) || count($dados->itens) === 0 ||
        !isset($dados->pagamentoMP->metodoPagamento) || !isset($dados->pagamentoMP->valorTotal)
    ) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos']);
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

    $idCliente = $dados->usuarioMP->idCliente;
    $emailCliente = $dados->usuarioMP->emailCliente;
    $telefoneCliente = $dados->usuarioMP->telefoneCliente;

    $metodoPagamento = $dados->pagamentoMP->metodoPagamento;
    $valorTotal = $dados->pagamentoMP->valorTotal;

    try{
        $stmtPedido = $conn->prepare("INSERT INTO Pedidos (Id_Cliente, Email_Pedido, Telefone_Pedido, Estado_Pedido, Cidade_Pedido, Bairro_Pedido, Logradouro_Pedido, Complemento_Pedido, Cep_Pedido, Numero_Pedido, Metodo_Pagamento, Valor_Total, Status_Pedido) 
        VALUES ()");
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao processar o pedido', $e->getMessage()]);
        exit;
    }


    foreach($dados->itens as $item) {
        if(!isset($item->idProduto) || !isset($item->quantidade) || !isset($item->precoUnitario)) {
            http_response_code(400);
            echo json_encode(['mensagem' => 'Dados do item incompletos']);
            exit;
        }
    }
?>