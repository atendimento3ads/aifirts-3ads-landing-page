<?php
// Recebe o formulário da landing e envia os leads por e-mail (3ADS — AI FIRST).
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'Método não permitido');
}

// Anti-abuso: só aceita envios originados do próprio site
$origem = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origem !== '' && stripos($origem, 'iafirst.3ads.com.br') === false) {
    fail(403, 'Origem não autorizada');
}

// Lê os dados (urlencoded/multipart; fallback para JSON ou corpo bruto)
$in = $_POST;
if (empty($in)) {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j)) {
        $in = $j;
    } else {
        parse_str($raw, $tmp);
        if (!empty($tmp)) $in = $tmp;
    }
}

// Honeypot: bots preenchem o campo oculto "website" → finge sucesso e descarta
if (!empty($in['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Remove quebras de linha (evita injeção de cabeçalhos de e-mail)
function limpa($v) {
    return trim(str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', (string) $v));
}

$nome    = limpa($in['name']    ?? '');
$email   = limpa($in['email']   ?? '');
$cargo   = limpa($in['role']    ?? '');
$receita = limpa($in['revenue'] ?? '');
$goal    = trim($in['goal']     ?? '');

if (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail(422, 'Preencha nome e e-mail válidos.');
}

// Rate limit simples por IP (anti-flood)
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$lock = sys_get_temp_dir() . '/lpaifirst_' . md5($ip) . '.lock';
if (is_file($lock) && (time() - filemtime($lock)) < 15) {
    fail(429, 'Aguarde alguns segundos antes de enviar novamente.');
}
@touch($lock);

$destino   = 'carloscosta.int@gmail.com';
$remetente = 'noreply@iafirst.3ads.com.br';
$assunto   = 'Nova aplicação — Mentoria AI FIRST';

$corpo  = "Nova aplicação recebida pela landing page AI FIRST\n";
$corpo .= "--------------------------------------------------\n\n";
$corpo .= "Nome:           $nome\n";
$corpo .= "E-mail:         $email\n";
$corpo .= "Cargo:          $cargo\n";
$corpo .= "Receita anual:  $receita\n\n";
$corpo .= "Objetivo (próximos 4 meses):\n$goal\n\n";
$corpo .= "--------------------------------------------------\n";
$corpo .= "IP: $ip · " . date('d/m/Y H:i') . "\n";

$headers   = [];
$headers[] = 'From: Landing AI FIRST <' . $remetente . '>';
$headers[] = 'Reply-To: ' . $nome . ' <' . $email . '>';
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=utf-8';

$assuntoEnc = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

// 5º parâmetro = envelope sender (-f), exigido pelo Exim do cPanel
if (@mail($destino, $assuntoEnc, $corpo, implode("\r\n", $headers), '-f' . $remetente)) {
    echo json_encode(['success' => true]);
} else {
    fail(500, 'Não foi possível enviar agora. Tente novamente em instantes.');
}
