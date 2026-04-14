<?php
    ini_set('log_errors', 'On');
    ini_set('error_reporting', E_ALL);
    ini_set('error_log', __DIR__ . '/../php_errors.log');

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    require_once 'validar-jwt.php';

    $resultado = validarJWT();
    var_dump($resultado);
?>