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

try {
    // Configurações do Servidor
    $mail->isSMTP();                                      // Define que vai usar SMTP
    $mail->Host       = $_ENV['SMTP_HOST'];               // Endereço do servidor (ex: smtp.gmail.com)
    $mail->SMTPAuth   = true;                             // Habilita autenticação SMTP
    $mail->Username   = $_ENV['SMTP_USER'];          // Seu e-mail
    $mail->Password   = $_ENV['SMTP_PASS'];      // Sua senha
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // Tipo de criptografia
    $mail->Port       = $_ENV['SMTP_PORT'];                              // Porta TCP (geralmente 587 ou 465)

    // Destinatários
    $mail->setFrom($mail->Username, 'Sistema de Checkout');
    $mail->addAddress('freitavitor71@gmail.com');   // Envie para você mesmo para testar

    // Conteúdo do E-mail
    $mail->isHTML(true);                                  // Define que o e-mail aceita HTML
    $mail->Subject = 'Teste de Instalacao PHPMailer';
    $mail->Body    = '<b>Se você está lendo isso, o PHPMailer está funcionando!</b>';

    $mail->send();
    echo 'Mensagem enviada com sucesso!';
} catch (Exception $e) {
    echo "A mensagem não pôde ser enviada. Erro do Mailer: {$mail->ErrorInfo}";
}

?>