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
                ipe.Preco_Unitario, ipe.Quantidade, prod.Nome_Produto
            FROM  
                Pedidos ped
            JOIN
                Clientes cli ON ped.Id_Cliente = cli.Id_Cliente
            JOIN
                Itens_Pedido ipe ON ped.Id_Pedido = ipe.Id_Pedido
            WHERE
                ped.Id_Pedido = ?");

        $stmt->execute([$idPedido]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['mensagem' => 'Erro ao consultar o pedido']);
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
                'Telefone' => $row['Telefone']
            ];
        }

        $itensMP[] = [
            'id' => (string)$row['Id_Produto'],
            'title' => $row['Nome_Produto'],
            'description' => $row['Nome_Produto'],
            'quantity' => (int)$row['Quantidade'],
            'unit_price' => (float)$row['Preco_Unitario'],
            'currency_id' => 'BRL'
        ];
    }

    use MercadoPago\Client\Preference\PreferenceClient;
    use MercadoPago\MercadoPagoConfig;

    $ACCESS_TOKEN = $_ENV['ACESS_TOKEN_MP'];

    MercadoPagoConfig::setAccessToken($ACCESS_TOKEN);

    $preferenceClient = new PreferenceClient();

    $preferenceData = [
        'items' => $itensMP,
        'payer' => [
            'email' => $dadosCliente['email'],
            'name' => $dadosCliente['nome'],
            'phone' => [
                'area_code' => substr($dadosCliente['Telefone'], 0, 2),
                'number' => substr($dadosCliente['Telefone'], 2)
            ]
        ],
        'back_urls' => [
            'success' => 'http://localhost:4200/checkout/sucesso',
            'pending' => 'http://localhost:4200/checkout/pendente',
            'failure' => 'http://localhost:4200/checkout/falha',
        ],

        'auto_return' => 'approved',

        'external_reference' => (string)$idPedido,

        'notification_url' => '',
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
        echo json_encode([
            'mensagem' => 'Erro interno ao comunicaar com o Mercadoo Pago.',
            'detalhes' => $e->getMessage()
        ]);
    }
?>