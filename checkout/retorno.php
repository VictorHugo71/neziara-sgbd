<?php
// Obtém todos os parâmetros (status, collection_id, external_reference, etc.)
$query_params = http_build_query($_GET);

// Endereço do seu servidor Angular
$angular_url = 'http://localhost:4200/checkout/retorno';

// Constrói a URL final de redirecionamento para o Angular
$final_url = $angular_url . '?' . $query_params;

// Redireciona o navegador do cliente para o Angular
header("Location: " . $final_url);
exit();
?>

