<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    require_once 'validar-jwt.php';

    $resultado = validarJWT();
    var_dump($resultado);
?>