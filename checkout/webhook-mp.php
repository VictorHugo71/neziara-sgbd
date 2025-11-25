<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    require '../vendor/autoload.php';

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "E-commerce";

    function gravar_log($mensagem) {
        $caminho_log = 'webhook_erros.log'; // Nome do arquivo de log
        $data_hora = date('[Y-m-d H:i:s]');
        
        // Formata a linha de log: [DATA/HORA] MENSAGEM
        $linha_log = $data_hora . " " . $mensagem . "\n";
        
        // Adiciona a linha ao final do arquivo (FILE_APPEND)
        file_put_contents($caminho_log, $linha_log, FILE_APPEND);
    }

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ERRMODE_EXCEPTION, PDO::ATTR_ERRMODE);
    } catch(PDOException $e) {
        //Conexão Falhou - Logue isto
        gravar_log("ERRO ao conectar ao Banco de Dados " . $e->getMessage());
        http_response_code(500);
        exit;
    }

    use MercadoPago\MercadoPagoConfig;
    use MercadoPago\Client\Payment\PaymentClient;

    $ACCESS_TOKEN = $_ENV['ACESS_TOKEN_MP'];
    MercadoPagoConfig::setAccessToken($ACCESS_TOKEN);

    $post_data = file_get_contents('php://input');
    $data = json_decode($post_data, true);

    if(empty($data) || $data['type'] !== 'payment' || empty($data['data']['id'])) {
        http_response_code(200);
        exit;
    }

    $payment_id = $data['data']['id'];

    $client = new  PaymentClient();

    try {
        $payment = $client->get($payment_id);

        $status  =  $payment->status;
        $status_detail = $payment->status_detail;

        $idPedidoInterno = $payment->external_reference;

        if(!empty($idPedidoInterno)) {
            $status_mapeado = $status;

            try {
                $stmt = $conn->prepare("UPDATE Pedidos SET Status_Pedido = ?, Status_Detalhe_Mp = ? WHERE Id_Pedido = ?");
                $stmt->execute([$status_mapeado, $status_detail, $idPedidoInterno]);

            } catch (PDOException $e) {
                //Erro ao atualizar o DB - Logue isto
                gravar_log("ERRO DB na atualização do Webhook: " . $e->getMessage() . " - Pedido ID: " . $idPedidoInterno);
                http_response_code(500);
                exit;
            }
        }

        http_response_code(200);
    
    } catch(\Exception $e) {
        //Logue se falhar
        gravar_log("ERRO API MP no Webhook: " . $e->getMessage() . " - Pagamento ID: " . $payment_id);
        http_response_code(500);
        exit;
    }
?>