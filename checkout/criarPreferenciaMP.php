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

    require '../vendor/autoload.php';

    $dados = json_decode(file_get_contents('php://input'), true);
    $idPedido = $dados['idPedido'];

    if(!$idPedido) {
        http_response_code(400);
        echo json_encode(['mensagem' => 'ID do Pedido não fornecido']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            SELECT 
                ped.Valor_Total, cli.Email, cli.Nome, cli.Telefone, ipe.Id_Produto, 
                ipe.Preco_Unitario, ipe.Quantidade, prod.Nome_Produto,
                ped.Cep_Pedido, ped.Logradouro_Pedido, ped.Numero_Pedido, ped.Complemento_Pedido
            FROM  
                Pedidos ped
            JOIN
                Clientes cli ON ped.Id_Cliente = cli.Id_Cliente
            JOIN
                Itens_Pedido ipe ON ped.Id_Pedido = ipe.Id_Pedido
            JOIN
                Produtos prod ON ipe.Id_Produto = prod.Id_Produto
            WHERE
                ped.Id_Pedido = ?");

        $stmt->execute([$idPedido]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao consultar o pedido'. $e->getMessage()]);
        exit;
    }

    if(empty($resultados)) {
        http_response_code(404);
        echo json_encode(['mensagem' => 'Pedido não encontrado ou sem itens']);
        exit;
    }

    $itensMP = [];
    $dadosCliente = [];

    foreach($resultados as $row) {
        if(empty($dadosCliente)) {
            $dadosCliente = [
                'email' => $row['Email'],
                'nome' => $row['Nome'],
                'valor_total' => $row['Valor_Total'],
                'Telefone' => $row['Telefone'],

                'Cep_Pedido' => $row['Cep_Pedido'], 
                'Logradouro_Pedido' => $row['Logradouro_Pedido'],
                'Numero_Pedido' => $row['Numero_Pedido'],
                'Complemento_Pedido' => $row['Complemento_Pedido']
            ];
        }


        // 1. OBTÉM O PREÇO DO DB (GARANTINDO QUE É UMA STRING)
        $preco_string_db = (string)$row['Preco_Unitario'];

        // 3. TRATAMENTO DO PREÇO: Garante que o separador decimal é PONTO
        $precoUnitarioTratado = str_replace(',', '.', $preco_string_db);

        //Converte para float para garantir que é um número válido
        $precoUnitarioFloat = (float)$precoUnitarioTratado;

        // **Aviso**: Se o preço final for 0, o MP rejeitará.
        if ($precoUnitarioFloat <= 0) {
            http_response_code(400);
            echo json_encode(['mensagem' => 'Erro de dados: O preço do item ' . $row['Nome_Produto'] . ' não pode ser zero ou negativo.']);
            exit;
        }

        $itensMP[] = [
            'id' => (string)$row['Id_Produto'],
            'title' => iconv('UTF-8', 'ASCII//TRANSLIT', $row['Nome_Produto']),
            'description' => iconv('UTF-8', 'ASCII//TRANSLIT', $row['Nome_Produto']),
            'quantity' => (int)$row['Quantidade'],
            'unit_price' => $precoUnitarioFloat,
            'currency_id' => 'BRL'
        ];
    }

    use MercadoPago\Client\Preference\PreferenceClient;
    use MercadoPago\MercadoPagoConfig;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    $ACCESS_TOKEN = $_ENV['ACESS_TOKEN_MP'] ?? '';

    if (empty($ACCESS_TOKEN)) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro de Configuração: ACCESS_TOKEN não definido.']);
        exit;
    }

    MercadoPagoConfig::setAccessToken($ACCESS_TOKEN);

    $preferenceClient = new PreferenceClient();

    // 1. Limpeza garantida (Remove todos os caracteres que não são dígitos)
    $telefoneLimpo = preg_replace('/[^0-9]/', '', $dadosCliente['Telefone'] ?? '');

    if (!empty($telefoneLimpo) && strlen($telefoneLimpo) >= 10) {
        // Se o telefone for válido, injete no payer:
        $payer_phone_data = [
            'area_code' => substr($telefoneLimpo, 0, 2),
            'number' => substr($telefoneLimpo, 2)
        ];
    } else {
        $payer_phone_data = null;
    }

    // 2. Garante que o total é float (Apesar de não ser injetado, é boa prática)
    $valorTotalFloat = (float)$dadosCliente['valor_total'];

    // NOVO: 2. Limpeza garantida do CEP (REMOVE TRAÇOS E CARACTERES)
    $cepLimpo = preg_replace('/[^0-9]/', '', $dadosCliente['Cep_Pedido']);

    $preferenceData = [
        'items' => $itensMP,
        'payer' => [
            'email' => 'TESTUSER2585998055306311260@testuser.com', // USE UM EMAIL DE TESTE SEGURO
            //'email' => $dadosCliente['email'],
            //'name' => iconv('UTF-8', 'ASCII//TRANSLIT', $dadosCliente['nome']),
            'name' => 'Comprador Teste',
            //'phone' => $payer_phone_data,
            //'address' => [
            //    'zip_code' => $cepLimpo,
            //    'street_name' => $dadosCliente['Logradouro_Pedido'],
            //    'street_number' => $dadosCliente['Numero_Pedido'],
            //    'complement' => $dadosCliente['Complemento_Pedido'] // Opcional
            //]
        ],
        'back_urls' => [
            'success' => 'http://localhost:4200/checkout/sucesso',
            'pending' => 'http://localhost:4200/checkout/pendente',
            'failure' => 'http://localhost:4200/checkout/falha',
        ],

        'auto_return' => 'approved',

        'external_reference' => (string)$idPedido,

        //'notification_url' => 'https://intervascular-uncombining-rikki.ngrok-free.dev/neziara-sgbd/checkout/webhook_mp.php',
    ];

    try {
        $preference = $preferenceClient->create($preferenceData);

        http_response_code(200);
        echo json_encode([
            'mensagem' => 'Preferência criada com sucesso',
            'idPedidoInterno' => $idPedido,
            'init_point' => $preference->init_point
        ]);
    } catch(\Exception $e) {
        http_response_code(500);
        
        $mensagemDetalhada = $e->getMessage();

        // Tenta capturar o corpo da resposta HTTP (que é onde o MP coloca o JSON de erro)
        if (method_exists($e, 'getResponse')) {
            $responseBody = (string)$e->getResponse()->getBody();
            $responseJson = json_decode($responseBody, true);
            
            if (isset($responseJson['message'])) {
                $mensagemDetalhada = $responseJson['message'];
            } else if (isset($responseJson['cause'][0]['description'])) {
                 $mensagemDetalhada = $responseJson['cause'][0]['description'];
            } else if (!empty($responseBody)) {
                 $mensagemDetalhada = $responseBody; // Retorna o corpo completo (pode ser JSON ou HTML)
            }
        }

        echo json_encode([
            'mensagem' => 'Erro ao criar preferência no Mercado Pago. Causa Oculta.',
            'detalhes' => $mensagemDetalhada 
        ]);
    }
?>