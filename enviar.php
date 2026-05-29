<?php
// Recebe o formulário da landing e envia os leads por e-mail.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Remove quebras de linha para evitar injeção de cabeçalhos
function limpa($v) {
    return trim(str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', (string) $v));
}

$nome    = limpa($_POST['name']    ?? '');
$email   = limpa($_POST['email']   ?? '');
$cargo   = limpa($_POST['role']    ?? '');
$receita = limpa($_POST['revenue'] ?? '');
$goal    = trim($_POST['goal']     ?? '');

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$destino = 'carloscosta.int@gmail.com';
$assunto = 'Nova aplicação — Mentoria AI FIRST';

$corpo  = "Nova aplicação recebida pela landing page AI FIRST\n";
$corpo .= "--------------------------------------------------\n\n";
$corpo .= "Nome:           $nome\n";
$corpo .= "E-mail:         $email\n";
$corpo .= "Cargo:          $cargo\n";
$corpo .= "Receita anual:  $receita\n\n";
$corpo .= "Objetivo (próximos 4 meses):\n$goal\n\n";
$corpo .= "--------------------------------------------------\n";
$corpo .= "Enviado por iafirst.3ads.com.br em " . date('d/m/Y H:i') . "\n";

$remetente = 'noreply@iafirst.3ads.com.br';
$headers   = [];
$headers[] = 'From: Landing AI FIRST <' . $remetente . '>';
$headers[] = 'Reply-To: ' . $nome . ' <' . $email . '>';
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=utf-8';

$assuntoEnc = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

if (mail($destino, $assuntoEnc, $corpo, implode("\r\n", $headers))) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao enviar o e-mail']);
}
