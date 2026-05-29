<?php
// Recebe o formulário da landing e envia os leads por e-mail.
header('Content-Type: application/json; charset=utf-8');

$destino   = 'carloscosta.int@gmail.com';
$remetente = 'noreply@iafirst.3ads.com.br';

// ── MODO DIAGNÓSTICO (GET ?debug=3ads) ───────────────────────────────
if (($_GET['debug'] ?? '') === '3ads') {
    $temFuncao = function_exists('mail');
    $sendmail  = ini_get('sendmail_path');
    $teste = false;
    $erro  = null;
    if ($temFuncao) {
        $h = "From: Landing AI FIRST <$remetente>\r\nContent-Type: text/plain; charset=utf-8";
        $teste = @mail($destino, '=?UTF-8?B?' . base64_encode('Teste diagnóstico AI FIRST') . '?=',
                       "Teste de envio do enviar.php em " . date('d/m/Y H:i'),
                       $h, '-f' . $remetente);
        $e = error_get_last();
        if ($e) { $erro = $e['message']; }
    }
    echo json_encode([
        'mail_existe'   => $temFuncao,
        'sendmail_path' => $sendmail,
        'teste_envio'   => $teste,
        'erro'          => $erro,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

$headers   = [];
$headers[] = 'From: Landing AI FIRST <' . $remetente . '>';
$headers[] = 'Reply-To: ' . $nome . ' <' . $email . '>';
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=utf-8';

$assuntoEnc = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

// 5º parâmetro define o envelope sender (-f) — evita rejeição do Exim no cPanel
$ok = @mail($destino, $assuntoEnc, $corpo, implode("\r\n", $headers), '-f' . $remetente);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    $e = error_get_last();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao enviar o e-mail',
        'erro'    => $e ? $e['message'] : null,
    ], JSON_UNESCAPED_UNICODE);
}
