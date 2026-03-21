<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/lib/config.php";

header('Content-Type: text/plain; charset=utf-8');

$logDir = $_SERVER['DOCUMENT_ROOT'] . '/fotos/log';
$logFile = $logDir . '/callback_mbway_lusopay.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

$escreveLog = function ($nivel, $mensagem, $dados = array()) use ($logFile) {
    $payload = array(
        'data' => date('Y-m-d H:i:s'),
        'nivel' => $nivel,
        'mensagem' => $mensagem,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'dados' => $dados,
    );

    $linha = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($linha !== false) {
        @file_put_contents($logFile, $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
};

$antiPhishing = trim((string) ($_REQUEST['chave'] ?? ''));
$descricao = trim((string) ($_REQUEST['descricao'] ?? ''));
$statusCode = trim((string) ($_REQUEST['statuscode'] ?? ''));
$dataCallback = trim((string) ($_REQUEST['data'] ?? ''));
$valor = trim((string) ($_REQUEST['valor'] ?? ''));

$requestLog = $_REQUEST;
if (isset($requestLog['chave']) && $requestLog['chave'] !== '') {
    $requestLog['chave'] = '***';
}

$escreveLog('INFO', 'Callback MBWay recebido', array('request' => $requestLog));

$chaveEsperada = isset($cfg_lusopay['anti_phishing_key']) ? (string) $cfg_lusopay['anti_phishing_key'] : '';
if ($chaveEsperada === '' || $antiPhishing === '' || !hash_equals($chaveEsperada, $antiPhishing)) {
    $escreveLog('WARN', 'Callback rejeitado por chave anti-phishing inválida.', array(
        'descricao' => $descricao,
        'statuscode' => $statusCode,
    ));
    http_response_code(403);
    echo 'Callback inválido (chave anti-phishing incorreta).';
    exit;
}

if ($descricao === '' || $statusCode === '' || $valor === '') {
    $escreveLog('WARN', 'Callback rejeitado por parâmetros em falta.', array(
        'descricao' => $descricao,
        'statuscode' => $statusCode,
        'valor' => $valor,
    ));
    http_response_code(400);
    echo 'Callback inválido (parâmetros em falta).';
    exit;
}

$descricaoEsc = addslashes($descricao);
$query = "SELECT * FROM privados_salas_mesas_disponibilidade WHERE mbway_order_id = '" . $descricaoEsc . "' AND saiu = 0 ORDER BY id DESC LIMIT 1";
$reservaArr = $db->query($query);

if (empty($reservaArr)) {
    $escreveLog('WARN', 'Reserva não encontrada para callback.', array(
        'descricao' => $descricao,
        'statuscode' => $statusCode,
        'valor' => $valor,
    ));
    http_response_code(404);
    echo 'Reserva não encontrada para este callback.';
    exit;
}

$reserva = $reservaArr[0];
$valorNormalizado = number_format((float) str_replace(',', '.', $valor), 2, '.', '');
$mensagemStatus = 'Status MB Way recebido por callback em ' . date('Y-m-d H:i:s');
if ($dataCallback !== '') {
    $mensagemStatus .= ' (data gateway: ' . $dataCallback . ')';
}

$campos = array(
    'mbway_status_code' => $statusCode,
    'mbway_status_mensagem' => $mensagemStatus,
);

if ($statusCode === '000') {
    $valorMbwayAdiantadoAtual = (float) ($reserva['valor_mbway_adiantado'] ?? 0);
    if ($valorMbwayAdiantadoAtual <= 0) {
        $campos['valor_mbway_adiantado'] = $valorNormalizado;
    }

    $db->update('privados_salas_mesas_disponibilidade', $campos, 'id = ' . intval($reserva['id']));
    $escreveLog('INFO', 'Callback aceite: pagamento MBWay confirmado.', array(
        'id_reserva' => intval($reserva['id']),
        'id_mesa' => intval($reserva['id_mesa']),
        'descricao' => $descricao,
        'statuscode' => $statusCode,
        'valor_normalizado' => $valorNormalizado,
        'campos_update' => $campos,
    ));
    echo 'OK';
    exit;
}

$campos['saiu'] = 1;
$db->update('privados_salas_mesas_disponibilidade', $campos, 'id = ' . intval($reserva['id']));
$db->query('DELETE from privados_salas_mesas_ocupacao WHERE data_evento = "' . $reserva['data_evento'] . '" AND id_mesa = "' . intval($reserva['id_mesa']) . '"');

$escreveLog('INFO', 'Callback recusado/cancelado: reserva libertada automaticamente.', array(
    'id_reserva' => intval($reserva['id']),
    'id_mesa' => intval($reserva['id_mesa']),
    'descricao' => $descricao,
    'statuscode' => $statusCode,
    'valor_normalizado' => $valorNormalizado,
    'campos_update' => $campos,
));

echo 'OK';
