<?php

declare(strict_types=1);

require_once __DIR__ . '/class-lusopay-api-client.php';

/*
 * Exemplo end-to-end (sem WooCommerce):
 * 1) Mostrar formulário para criar pagamento
 * 2) Gerar referência MB/PayShop ou pedido MBWay na Lusopay
 * 3) Guardar estado local do pedido
 * 4) Receber callback e marcar como pago
 *
 * Como testar localmente:
 * - Coloca este ficheiro num servidor PHP (Apache/Nginx ou php -S)
 * - Define os dados abaixo (GUID, NIF, chave anti-phishing)
 * - Abre no browser: /includes/example-lusopay-end-to-end.php
 */

$config = [
    // Credenciais Lusopay
    'client_guid' => '55800498-0256-4F6E-B478-FCBC9FE23018',
    'vat_number' => '514791535', // 999999999 = ambiente de teste no plugin

    // Chave anti-phishing usada para validar callbacks
    'anti_phishing_key' => 'db14ce5792be619fe44f5d05552f3a9a',

    // Caminho para persistência simples (demo)
    'storage_file' => __DIR__ . '/lusopay-demo-orders.json',

    // URL pública base deste ficheiro (sem query string)
    // Exemplo: https://teudominio.com/includes/example-lusopay-end-to-end.php
    'base_url' => detectBaseUrl(),
];

$action = $_GET['action'] ?? 'form';

try {
    if ($action === 'callback') {
        handleCallback($config);
        exit;
    }
    var_dump($config);

    if ($action === 'status') {
        handleStatus($config);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
        handleCreatePayment($config);
        exit;
    }

    renderForm($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Erro</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}

function handleCreatePayment(array $config): void
{
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;
    $service = $_POST['service'] ?? 'MB'; // MB | PS | MBWAY
    $phoneNumber = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $orderId = generateOrderId();

    if ($amount < 1.00) {
        renderForm($config, 'Valor inválido. O mínimo recomendado é 1.00€');
        return;
    }

    if (!in_array($service, ['MB', 'PS', 'MBWAY'], true)) {
        renderForm($config, 'Serviço inválido.');
        return;
    }

    if ($service === 'MBWAY' && strlen($phoneNumber) < 9) {
        renderForm($config, 'Para MBWay indica um telemóvel válido (9 dígitos).');
        return;
    }

    $client = new LusopayApiClient(
        $config['client_guid'],
        $config['vat_number'],
        [
            'timeout' => 30,
            'verify_ssl' => true,
        ]
    );

    if ($service === 'MB') {
        $apiResponse = $client->generateMultibancoReference($orderId, $amount, false);
        $reference = $apiResponse['referenceMB'] ?? null;
        $entity = $apiResponse['entityMB'] ?? null;
    } elseif ($service === 'PS') {
        $apiResponse = $client->generatePayshopReference($orderId, $amount, false);
        $reference = $apiResponse['referencePS'] ?? null;
        $entity = null;
    } else {
        $apiResponse = $client->sendMbWayRequest($orderId, $amount, $phoneNumber, false);
        $reference = $apiResponse['merchantOperationID'] ?? $orderId;
        $entity = null;
    }

    if ($service !== 'MBWAY' && (empty($reference) || $reference === '-1')) {
        $message = $apiResponse['message'] ?? 'Não foi possível gerar referência na Lusopay.';
        renderForm($config, 'Erro Lusopay: ' . $message);
        return;
    }

    if ($service === 'MBWAY' && empty($apiResponse['statusCode'])) {
        renderForm($config, 'Erro Lusopay MBWay: resposta sem statusCode.');
        return;
    }

    $orders = loadOrders($config['storage_file']);
    $initialStatus = 'pending';
    if ($service === 'MBWAY') {
        $initialStatus = ((string) ($apiResponse['statusCode'] ?? '') === '000') ? 'requested' : 'failed';
    }

    $orders[$orderId] = [
        'order_id' => $orderId,
        'service' => $service,
        'amount' => number_format($amount, 2, '.', ''),
        'entity' => $entity,
        'reference' => $reference,
        'phone' => $service === 'MBWAY' ? $phoneNumber : null,
        'mbway_status_code' => $service === 'MBWAY' ? ($apiResponse['statusCode'] ?? null) : null,
        'mbway_token' => $service === 'MBWAY' ? ($apiResponse['token'] ?? null) : null,
        'status' => $initialStatus,
        'created_at' => date('c'),
        'callback_at' => null,
        'callback_payload' => null,
    ];
    saveOrders($config['storage_file'], $orders);

    renderOrderPage($config, $orders[$orderId], $apiResponse);
}

function handleCallback(array $config): void
{
    // Callback típico MB/PS:
    // ?entidade=...&referencia=...&valor=...&chave=...
    // Callback típico MBWay:
    // ?descricao=...&statuscode=...&data=...&valor=...&chave=...

    $antiPhishing = trim((string) ($_GET['chave'] ?? ''));
    $description = trim((string) ($_GET['descricao'] ?? ''));
    $statusCode = trim((string) ($_GET['statuscode'] ?? ''));
    $callbackDate = trim((string) ($_GET['data'] ?? ''));
    $entity = trim((string) ($_GET['entidade'] ?? ''));
    $reference = trim((string) ($_GET['referencia'] ?? ''));
    $value = trim((string) ($_GET['valor'] ?? ''));

    if ($antiPhishing === '' || !hash_equals($config['anti_phishing_key'], $antiPhishing)) {
        http_response_code(403);
        echo 'Callback inválido (chave anti-phishing incorreta).';
        return;
    }

    if ($value === '') {
        http_response_code(400);
        echo 'Callback inválido (parâmetros em falta).';
        return;
    }

    $isMbWayCallback = ($description !== '' && $statusCode !== '');
    if (!$isMbWayCallback && $reference === '') {
        http_response_code(400);
        echo 'Callback inválido (parâmetros em falta).';
        return;
    }

    $orders = loadOrders($config['storage_file']);
    $matchedOrderId = null;

    $valueNormalized = number_format((float) str_replace(',', '.', $value), 2, '.', '');

    if ($isMbWayCallback) {
        foreach ($orders as $orderId => $order) {
            $sameOrderId = (string) ($order['order_id'] ?? '') === $description;
            $sameAmount = number_format((float) ($order['amount'] ?? 0), 2, '.', '') === $valueNormalized;
            $isMbWay = (string) ($order['service'] ?? '') === 'MBWAY';
            if ($isMbWay && $sameOrderId && $sameAmount) {
                $matchedOrderId = $orderId;
                break;
            }
        }
    } else {
        foreach ($orders as $orderId => $order) {
            $sameReference = (string) ($order['reference'] ?? '') === $reference;
            $sameAmount = number_format((float) ($order['amount'] ?? 0), 2, '.', '') === $valueNormalized;

            $service = (string) ($order['service'] ?? '');
            if ($service === 'MB') {
                $sameEntity = (string) ($order['entity'] ?? '') === $entity;
                if ($sameReference && $sameAmount && $sameEntity) {
                    $matchedOrderId = $orderId;
                    break;
                }
            } else {
                if ($sameReference && $sameAmount) {
                    $matchedOrderId = $orderId;
                    break;
                }
            }
        }
    }

    if ($matchedOrderId === null) {
        http_response_code(404);
        echo 'Pedido não encontrado para este callback.';
        return;
    }

    if ($isMbWayCallback) {
        $orders[$matchedOrderId]['status'] = $statusCode === '000' ? 'paid' : 'failed';
    } else {
        $orders[$matchedOrderId]['status'] = 'paid';
    }
    $orders[$matchedOrderId]['callback_at'] = date('c');
    $orders[$matchedOrderId]['callback_payload'] = [
        'descricao' => $description,
        'statuscode' => $statusCode,
        'data' => $callbackDate,
        'entidade' => $entity,
        'referencia' => $reference,
        'valor' => $valueNormalized,
        'chave' => '***',
        'raw_get' => $_GET,
    ];

    saveOrders($config['storage_file'], $orders);

    echo 'OK';
}

function handleStatus(array $config): void
{
    $orderId = trim((string) ($_GET['order_id'] ?? ''));
    if ($orderId === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Parâmetro obrigatório em falta: order_id',
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $orders = loadOrders($config['storage_file']);
    if (!isset($orders[$orderId])) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Pedido não encontrado.',
            'order_id' => $orderId,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $order = $orders[$orderId];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'order_id' => $order['order_id'] ?? $orderId,
        'service' => $order['service'] ?? null,
        'status' => $order['status'] ?? null,
        'amount' => $order['amount'] ?? null,
        'entity' => $order['entity'] ?? null,
        'reference' => $order['reference'] ?? null,
        'created_at' => $order['created_at'] ?? null,
        'callback_at' => $order['callback_at'] ?? null,
        'mbway_status_code' => $order['mbway_status_code'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
}

function renderForm(array $config, ?string $error = null): void
{
    $orders = loadOrders($config['storage_file']);
    $lastOrders = array_slice(array_reverse($orders, true), 0, 5, true);

    echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><title>Demo Lusopay End-to-End</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;max-width:920px;margin:30px auto;padding:0 16px}';
    echo 'input,select,button{padding:8px;margin:4px 0}';
    echo '.card{border:1px solid #ddd;padding:16px;border-radius:8px;margin-bottom:16px}';
    echo '.error{color:#b00020}.muted{color:#666} table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px}</style>';
    echo '</head><body>';

    echo '<h1>Pagamento Lusopay (End-to-End)</h1>';
    echo '<p class="muted">Este exemplo cria pagamento MB/PayShop/MBWay e processa callback no mesmo ficheiro.</p>';

    if ($error !== null) {
        echo '<p class="error"><strong>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    }

    echo '<div class="card">';
    echo '<form method="post" action="?action=create">';
    echo '<label>Serviço</label><br>';
    echo '<select name="service"><option value="MB">Multibanco</option><option value="PS">PayShop</option><option value="MBWAY">MBWay</option></select><br>';
    echo '<label>Telemóvel (MBWay)</label><br>';
    echo '<input type="text" name="phone" placeholder="912345678"><br>';
    echo '<label>Valor (€)</label><br>';
    echo '<input type="number" step="0.01" min="1" name="amount" value="10.00" required><br>';
    echo '<button type="submit">Gerar Pagamento</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>Endpoint de callback</h3>';
    $callbackUrl = $config['base_url'] . '?action=callback';
    echo '<p><strong>' . htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p class="muted">Configura este URL no painel Lusopay. A chave validada é a anti-phishing.</p>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>Últimos pedidos</h3>';
    echo '<table><thead><tr><th>Order</th><th>Serviço</th><th>Valor</th><th>Ref</th><th>Entidade</th><th>Estado</th></tr></thead><tbody>';
    if (empty($lastOrders)) {
        echo '<tr><td colspan="6">Sem pedidos.</td></tr>';
    } else {
        foreach ($lastOrders as $order) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string) $order['order_id'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $order['service'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $order['amount'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $order['reference'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) ($order['entity'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><strong>' . htmlspecialchars((string) $order['status'], ENT_QUOTES, 'UTF-8') . '</strong></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '</body></html>';
}

function renderOrderPage(array $config, array $order, array $apiResponse): void
{
    echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><title>Pagamento Gerado</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;max-width:900px;margin:30px auto;padding:0 16px}.card{border:1px solid #ddd;padding:16px;border-radius:8px;margin-bottom:16px}.ok{color:#1b5e20}.muted{color:#666}.warn{color:#8a6d3b}.bad{color:#b00020}pre{white-space:pre-wrap;background:#f8f8f8;padding:12px;border-radius:8px}</style>';
    echo '</head><body>';

    echo '<h1 class="ok">Pagamento criado com sucesso</h1>';
    echo '<div class="card">';
    echo '<p><strong>Order ID:</strong> ' . htmlspecialchars((string) $order['order_id'], ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><strong>Serviço:</strong> ' . htmlspecialchars((string) $order['service'], ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><strong>Valor:</strong> ' . htmlspecialchars((string) $order['amount'], ENT_QUOTES, 'UTF-8') . ' €</p>';

    if ($order['service'] === 'MB') {
        echo '<p><strong>Entidade:</strong> ' . htmlspecialchars((string) $order['entity'], ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if ($order['service'] === 'MBWAY') {
        echo '<p><strong>Telemóvel:</strong> ' . htmlspecialchars((string) ($order['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><strong>StatusCode inicial:</strong> ' . htmlspecialchars((string) ($order['mbway_status_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $currentStatus = (string) ($order['status'] ?? 'pending');
    echo '<p><strong>Referência:</strong> ' . htmlspecialchars((string) $order['reference'], ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><strong>Estado atual:</strong> <span id="order-status-value">' . htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') . '</span></p>';
    $statusUrl = $config['base_url'] . '?action=status&order_id=' . rawurlencode((string) $order['order_id']);
    echo '<p><strong>Consultar estado:</strong> <a href="' . htmlspecialchars($statusUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">JSON status</a></p>';
    if ($order['service'] === 'MBWAY') {
        echo '<p id="mbway-live-hint" class="muted">A aguardar confirmação MBWay... (atualiza automaticamente durante 4 minutos)</p>';
    }
    echo '</div>';

    if ($order['service'] === 'MBWAY') {
        $callbackExample = $config['base_url']
            . '?action=callback'
            . '&descricao=' . rawurlencode((string) $order['order_id'])
            . '&statuscode=000'
            . '&data=' . rawurlencode(date('Y-m-d H:i:s'))
            . '&valor=' . rawurlencode((string) $order['amount'])
            . '&chave=' . rawurlencode((string) $config['anti_phishing_key']);
    } else {
        $callbackExample = $config['base_url']
            . '?action=callback'
            . '&entidade=' . rawurlencode((string) ($order['entity'] ?? ''))
            . '&referencia=' . rawurlencode((string) $order['reference'])
            . '&valor=' . rawurlencode((string) $order['amount'])
            . '&chave=' . rawurlencode((string) $config['anti_phishing_key']);
    }

    echo '<div class="card">';
    echo '<h3>Teste manual de callback</h3>';
    echo '<p class="muted">Para simular confirmação, abre este URL no browser:</p>';
    echo '<pre>' . htmlspecialchars($callbackExample, ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h3>Resposta bruta da API</h3>';
    echo '<pre>' . htmlspecialchars(json_encode($apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</div>';

    if ($order['service'] === 'MBWAY') {
        echo '<script>';
        echo '(function(){';
        echo 'var statusUrl = ' . json_encode($statusUrl) . ';';
        echo 'var startedAt = Date.now();';
        echo 'var maxMs = 4 * 60 * 1000;';
        echo 'var intervalMs = 5000;';
        echo 'var statusEl = document.getElementById("order-status-value");';
        echo 'var hintEl = document.getElementById("mbway-live-hint");';
        echo 'var done = false;';
        echo 'function setHint(text, cssClass){ if(!hintEl){ return; } hintEl.className = cssClass || "muted"; hintEl.textContent = text; }';
        echo 'function finish(text, cssClass){ done = true; setHint(text, cssClass); }';
        echo 'function poll(){';
        echo 'if(done){ return; }';
        echo 'if(Date.now() - startedAt >= maxMs){ finish("Tempo de espera de 4 minutos atingido. Podes atualizar a página ou consultar o JSON status.", "warn"); return; }';
        echo 'fetch(statusUrl, {cache:"no-store"})';
        echo '.then(function(r){ return r.json(); })';
        echo '.then(function(data){';
        echo 'if(!data || data.ok !== true){ return; }';
        echo 'var status = String(data.status || "").toLowerCase();';
        echo 'if(statusEl){ statusEl.textContent = status || "-"; }';
        echo 'if(status === "paid"){ finish("Pagamento confirmado com sucesso.", "ok"); return; }';
        echo 'if(status === "failed"){ finish("Pagamento falhou ou foi rejeitado.", "bad"); return; }';
        echo 'if(status === "requested" || status === "pending"){ setHint("Pedido MBWay enviado. A aguardar confirmação no telemóvel...", "muted"); }';
        echo '})';
        echo '.catch(function(){ setHint("Não foi possível atualizar agora. Nova tentativa em instantes...", "warn"); });';
        echo 'setTimeout(poll, intervalMs);';
        echo '}';
        echo 'poll();';
        echo '})();';
        echo '</script>';
    }

    echo '<p><a href="' . htmlspecialchars($config['base_url'], ENT_QUOTES, 'UTF-8') . '">← Voltar</a></p>';
    echo '</body></html>';
}

function loadOrders(string $storageFile): array
{
    if (!file_exists($storageFile)) {
        return [];
    }

    $content = file_get_contents($storageFile);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function saveOrders(string $storageFile, array $orders): void
{
    $json = json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Erro ao serializar pedidos em JSON.');
    }

    $result = file_put_contents($storageFile, $json, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException('Não foi possível guardar o ficheiro de pedidos: ' . $storageFile);
    }
}

function generateOrderId(): string
{
    return 'LP' . date('YmdHis') . random_int(100, 999);
}

function detectBaseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/includes/example-lusopay-end-to-end.php', '?');
    return $scheme . '://' . $host . $path;
}
