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
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao conectar ao banco de dados']);
        exit;
    }

    $dados = json_decode(file_get_contents('php://input'), true);
    $idPedido = $dados['idPedido'];
    $numero_cartao = $dados['numero_cartao'];
    $cvv = $dados['cvv'];
    $data_validade = $dados['data_validade'];
    $email_cliente = $dados['email'];

    
    if(!isset($idPedido) || !isset($numero_cartao) || !isset($cvv) || !isset($data_validade) || !isset($email_cliente)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Dados incompletos']);
        exit;
    } else if(empty($idPedido) || empty($numero_cartao) || empty($cvv) || empty($data_validade) || empty($email_cliente)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Todos os campos são obrigatórios']);
        exit;
    } else if(!filter_var($email_cliente, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Email inválido']);
        exit;
    } else if(!preg_match('/^[0-9]{16}$/', $numero_cartao) || !is_numeric($numero_cartao)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Número do cartão inválido']);
        exit;
    } else if(!preg_match('/^[0-9]{3,4}$/', $cvv) || !is_numeric($cvv)) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'CVV inválido']);
        exit;
    }

    $dataExplode = explode('/', $data_validade);
    $mesCliente = intval($dataExplode[0]);
    $anoCliente = intval($dataExplode[1]);

    $date = new DateTime();
    $dateForm = $date->format('m/Y');
    $dateFormExplode = explode('/', $dateForm);
    $mesServer = intval($dateFormExplode[0]);
    $anoServer = intval($dateFormExplode[1]);
    
    if(!preg_match('#^[0-9]{2}/[0-9]{4}$#', $data_validade) || $anoCliente < $anoServer || $anoCliente == $anoServer && $mesCliente < $mesServer || $mesCliente > 12) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'Data de validade inválida ou cartão expirado']);
        exit;
    }

    $statusFinal = 'pendente';
    $mensagemFinal =  'Pagamento pendente de aprovação';

    $cartoesAprovados = explode(',', $_ENV['CARTOES_TESTE_APROVADOS']);
    $cartoesRecusados = explode(',', $_ENV['CARTOES_TESTE_CANCELADOS']);
    $cartoesSemSaldo = explode(',', $_ENV['CARTOES_TESTE_SALDO_INSUFICIENTE']);

    try {
        $conn->beginTransaction();
        if(in_array($numero_cartao, $cartoesAprovados)) {
            $statusFinal = 'aprovado';
            $mensagemFinal =  'Pagamento aprovado';
        } else if(in_array($numero_cartao, $cartoesRecusados)) {
            $statusFinal = 'cancelado';
            $mensagemFinal =  'Pagamento recusado/cancelado';
        } else if(in_array($numero_cartao, $cartoesSemSaldo)) {
            $statusFinal = 'cancelado';
            $mensagemFinal =  'Cartão Não Possui Saldo Disponível';
        } else {
            $statusFinal = 'pendente';
            $mensagemFinal =  'Cartão não cadastrado na base de testes';
        }

        $stmt = $conn->prepare("UPDATE Pedidos SET Status_Pedido = ? WHERE Id_Pedido = ?");
        $stmt->execute([$statusFinal, $idPedido]);

        $conn->commit();
        echo json_encode(['mensagem' => $mensagemFinal, 'detalhe' => $statusFinal]);
    } catch(PDOException $e) {
        http_response_code(500);
        $conn->rollBack();
        echo json_encode(['mensagem' => 'Erro ao processar o pagamento', 'detalhe' => $e->getMessage()]);
        exit;
    }
    //Status_Pedido = 'aprovado','pendente','cancelado'
    //Ajustar redirescionamento no Angular conforme status
?>
