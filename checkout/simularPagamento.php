<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);


    // 1. O Autoload do Composer é o que carrega o PHPMailer automaticamente
    require '../vendor/autoload.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    // 2. Importa a classe do Dotenv
    use Dotenv\Dotenv;

    // 3. Indica onde está o arquivo .env (neste caso, na mesma pasta do script)
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    $mail = new PHPMailer(true);

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
    $numero_cartao = $dados['cartao']['numeroCartao'];
    $cvv = $dados['cartao']['cvv'];
    $data_validade = $dados['cartao']['dataValidade'];
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
        $stmtSelect = $conn->prepare("
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

        $stmtSelect->execute([$idPedido]);
        $resultados = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

        if(!$resultados) {
            http_response_code(404);
            echo json_encode(['mensagem' => 'Pedido não encontrado ou sem itens']);
            exit;
        } else {
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
        }
        unset($numero_cartao);
        unset($cvv);
        unset($data_validade);

        $stmt = $conn->prepare("UPDATE Pedidos SET Status_Pedido = ? WHERE Id_Pedido = ?");
        $stmt->execute([$statusFinal, $idPedido]);

        $conn->commit();

        //Envio de email (simulado)
        $infoEmail = 'Não enviado';
        try {
            // Define uma cor para o título baseada no status
            $corTitulo = '#333333'; // Cor padrão (Cinza escuro)

            if ($statusFinal === 'aprovado') {
                $corTitulo = '#27ae60'; // Verde Sucesso
            } elseif ($statusFinal === 'cancelado') {
                $corTitulo = '#c0392b'; // Vermelho Erro
            }

            // Configurações do Servidor
            $mail->isSMTP();                                      // Define que vai usar SMTP
            $mail->Host       = $_ENV['SMTP_HOST'];               // Endereço do servidor (ex: smtp.gmail.com)
            $mail->SMTPAuth   = true;                             // Habilita autenticação SMTP
            $mail->Username   = $_ENV['SMTP_USER'];          // Seu e-mail
            $mail->Password   = $_ENV['SMTP_PASS'];      // Sua senha
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // Tipo de criptografia
            $mail->Port       = $_ENV['SMTP_PORT'];                              // Porta TCP (geralmente 587 ou 465)
        
            // Destinatários
            $mail->setFrom($mail->Username, 'Sistema Pagamento Neziara');
            $mail->addAddress($email_cliente);   // Envie para o cliente
        
            // Conteúdo do E-mail
            $mail->isHTML(true);                                  // Define que o e-mail aceita HTML
            $mail->Subject = 'Resultado do Pagamento do Pedido #' . $idPedido ;
            $mail->Body = <<<HTML
            <div style="background-color: #f4f4f4; padding: 20px; font-family: Arial, sans-serif;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    
                    <h2 style="color: {$corTitulo}; text-align: center; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                        Status do Pedido #{$idPedido}
                    </h2>

                    <p style="font-size: 16px; color: #555; line-height: 1.5;">
                        Olá! Temos uma atualização sobre o seu pedido.
                    </p>

                    <div style="background-color: #f9f9f9; padding: 15px; border-left: 5px solid {$corTitulo}; margin: 20px 0;">
                        <p style="margin: 0; font-weight: bold; color: #333;">Resultado da Análise:</p>
                        <p style="margin: 5px 0 0 0; font-size: 18px; color: {$corTitulo};">
                            {$mensagemFinal}
                        </p>
                    </div>

                    <p style="font-size: 14px; color: #777;">
                        Se houver dúvidas, entre em contato com nosso suporte.
                    </p>

                    <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">
                    <p style="text-align: center; font-size: 12px; color: #aaa;">
                        © 2024 Neziara Store. Este é um e-mail automático, não responda.
                    </p>
                </div>
            </div>
            HTML;
        
            $mail->send();
            $infoEmail = 'Mensagem enviada com sucesso!';
        } catch (Exception $e) {
            $infoEmail = "A mensagem não pôde ser enviada. Erro no Servidor: {$mail->ErrorInfo}";
        }

        echo json_encode([
            'mensagem' => $mensagemFinal,
            'status' => $statusFinal,
            'info_email' => $infoEmail,
            'idPedido' => $idPedido,
            'numero_cartao' => $numero_cartao ?? 'dados apagado',
            'cvv' => $cvv ?? 'dados apagado',
            'data_validade' => $data_validade ?? 'dados apagado'

        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        $conn->rollBack();
        echo json_encode(['mensagem' => 'Erro ao processar o pagamento', 'detalhe' => $e->getMessage()]);
        exit;
    }
    //Status_Pedido = 'aprovado','pendente','cancelado'
    //Ajustar redirescionamento no Angular conforme status
?>
