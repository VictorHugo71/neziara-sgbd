<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // 1. O Autoload do Composer é o que carrega o PHPMailer automaticamente
    require '../vendor/autoload.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    // 2. Importa a classe do Dotenv
    use Dotenv\Dotenv;

    // 3. Indica onde está o arquivo .env (neste caso, na mesma pasta do script)
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    $mail = new PHPMailer(true); // O 'true' habilita as exceções (erros detalhados)

    $mensagemFinal = 'Cartão não cadastrado para teste';
    $idPedido = 12345;
    $statusFinal = 'Pendente'; // ou 'cancelado'

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
            $mail->addAddress('freitavitor71@gmail.com');   // Envie para o cliente
        
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
            echo 'email enviado com sucesso';
        } catch (Exception $e) {
            echo "A mensagem não pôde ser enviada. Erro do Mailer: {$mail->ErrorInfo}";
        }

?>