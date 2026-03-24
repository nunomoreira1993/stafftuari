<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/rp.obj.php');
$dbrp = new rp($db, $_SESSION['id_rp']);

require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/privados/privados.obj.php');
$dbprivados = new privados($db);

require_once($_SERVER['DOCUMENT_ROOT'] . '/multibanco-e-ou-payshop-by-lusopay/class-lusopay-api-client.php');

$permissao = $dbrp->permissao();

$rps = $dbrp->listaRps();
$mesa = $dbprivados->devolveMesa($_GET['id_mesa']);

if (empty($permissao)) {
    $_SESSION['erro'] = "Não tem permissão para aceder a esta página.";
    header('Location: /rp/index.php?pg=disponibilidade_de_mesas&data_evento=' . $_GET['data_evento'] . '#sala_' . $mesa['id_sala']);
    exit;
}

if (empty($mesa)) {
    $_SESSION['erro'] = "A mesa que está a tentar reserva não existe.";
    header('Location: /rp/index.php?pg=disponibilidade_de_mesas&data_evento=' . $_GET['data_evento'] . '#sala_' . $mesa['id_sala']);
    exit;
} else {
    $sala = $dbprivados->devolveSala($mesa['id_sala']);
}

if ($_GET['id_mesa']) {

    $cartao = $dbprivados->verificaMesaDisponivel(intval($_GET['id_mesa']), $_GET['data_evento']);

    if (empty($cartao)) {
        $_SESSION['erro'] = "A mesa que está a tentar reserva já não se encontra com disponibilidade.";
        header('Location: /rp/index.php?pg=disponibilidade_de_mesas&data_evento=' . $_GET['data_evento'] . '#sala_' . $mesa['id_sala']);
        exit;
    }
}
$mensagem_default = $dbprivados->getMensagemDefault();

$campos = array();

$reserva_com_valor_antecipado = 'sim';
$valor_caucao_reserva = '';
$mbway_numero = '351';

if ($_POST) {
    $mbway_timeout = 0;
    $idReservaDisponibilidade = isset($_GET['id']) ? intval($_GET['id']) : 0;

    $id_gerente = $_SESSION['id_rp'];
    $id_rp = $_POST['id_rp'];
    $nome_cliente = $_POST['nome'];
    $nome_array = explode(" ", $nome_cliente);
    $data_evento = $_GET['data_evento'];
    $id_mesa = $_GET['id_mesa'];
    $garrafas = $_POST['garrafas'];
    $cartoes = $_POST['cartoes'];
    $valor = $_POST['valor'];
    $reserva_com_valor_antecipado = isset($_POST['reserva_com_valor_antecipado']) && $_POST['reserva_com_valor_antecipado'] === 'nao' ? 'nao' : 'sim';
    $valor_caucao_reserva = isset($_POST['valor_caucao_reserva']) ? (float) str_replace(',', '.', $_POST['valor_caucao_reserva']) : 0;
    $mbway_numero = isset($_POST['mbway_numero']) ? preg_replace('/\D+/', '', $_POST['mbway_numero']) : '';

    if (strpos($mbway_numero, '351') === 0 && strlen($mbway_numero) === 12) {
        $mbway_numero = substr($mbway_numero, 3);
    }

    $mensagem = trim(str_replace("{VALOR}", ($valor / 2) , $_POST['mensagem']));
    $mensagem = trim(str_replace("{NOME}", ($nome_array[0]) , $mensagem));
    $mensagem = trim(str_replace("{DATA}", (date('d/m/Y', strtotime($data_evento))), $mensagem));
    $telemovel = $_POST['telemovel'];


    if (empty($data_evento)) {
        $_SESSION['erro'] = "Por favor introduza a data do evento.";
    }
    if (empty($id_rp)) {
        $_SESSION['erro'] = "Por favor escolha um elemento do Staff para associar á reserva.";
    }
    if (empty($nome_cliente)) {
        $_SESSION['erro'] = "Por favor introduza o nome do cliente.";
    }
    if (empty($id_mesa)) {
        $_SESSION['erro'] = "Não foi possivel inserir a reserva porque deve escolher uma mesa.";
    }
    if (empty($id_gerente)) {
        $_SESSION['erro'] = "Não foi possivel inserir a reserva porque existe agente.";
    }

    if ($reserva_com_valor_antecipado === 'sim') {
        if ($valor_caucao_reserva <= 0) {
            $_SESSION['erro'] = "Por favor introduza um Valor Caução de Reserva superior a 0.";
        }

        if (!preg_match('/^9[1236]\d{7}$/', $mbway_numero)) {
            $_SESSION['erro'] = "Por favor introduza um Nº MB Way português válido.";
        }
    }

    if (strlen($telemovel) > 8 && $mensagem && empty($sms_enviada)) {
        $sms = $dbprivados->smsto(array('telemovel' => str_replace("+", "", $telemovel), 'mensagem' => $mensagem));

        if($sms->error){
            $campos['sms_erro'] = 1;
            $campos['sms_erro_mensagem'] = $sms->error->code." - ". $sms->error->description;
            $campos['sms_data_erro'] = date('Y-m-d H:i:s');
        }
        else if($sms[0]->accepted){
            $campos['sms_enviada'] = 1;
            $campos['sms_id'] = $sms[0]->id;
            $campos['sms_data_envio'] = date('Y-m-d H:i:s');
        }
        else{
            $campos['sms_erro'] = 1;
            $campos['sms_erro_mensagem'] = "O Serviço de mensagens não se encontra disponivel. Mandar mensagem manualmente.";
        }
    }

    if (empty($_SESSION['erro'])) {
        $campos['data'] = date('Y-m-d H:i:s');
        $campos['data_evento'] = $data_evento;
        $campos['id_mesa'] = $id_mesa;
        $campos['id_rp'] = $id_rp;
        $campos['id_gerente'] = $id_gerente;
        $campos['nome'] = $nome_cliente;
        $campos['garrafas'] = $garrafas;
        $campos['cartoes'] = $cartoes;
        $campos['valor'] = $valor;
        $campos['reserva_com_valor_antecipado'] = $reserva_com_valor_antecipado === 'sim' ? 1 : 0;
        $campos['valor_caucao_reserva'] = $reserva_com_valor_antecipado === 'sim' ? number_format($valor_caucao_reserva, 2, '.', '') : null;
        $campos['mbway_numero'] = $reserva_com_valor_antecipado === 'sim' ? $mbway_numero : null;
        $campos['mensagem'] = $mensagem;
        $campos['telemovel'] = $telemovel;

        if ($reserva_com_valor_antecipado === 'sim' && $idReservaDisponibilidade <= 0) {
            $idReservaDisponibilidade = intval($db->Insert('privados_salas_mesas_disponibilidade', $campos));
            if ($idReservaDisponibilidade <= 0) {
                $_SESSION['erro'] = "Não foi possível criar a reserva para enviar o pedido MB Way.";
            }
        }

        if ($reserva_com_valor_antecipado === 'sim' && empty($_SESSION['erro'])) {
            try {
                $lusopayClientGuid = isset($cfg_lusopay['client_guid']) ? $cfg_lusopay['client_guid'] : 'BA3FB8CE-1F40-4F4C-AF84-3F55C2D7C1CB';
                $lusopayVatNumber = isset($cfg_lusopay['vat_number']) ? $cfg_lusopay['vat_number'] : '514791535';

                $lusopayClient = new LusopayApiClient(
                    $lusopayClientGuid,
                    $lusopayVatNumber,
                    array(
                        'timeout' => 30,
                        'verify_ssl' => true,
                    )
                );

                $mbwayOrderId = $dbprivados->geraCodigoPagamentoMbway($data_evento, $sala['id'], $mesa['codigo_mesa'], $idReservaDisponibilidade);
                $mbwayResponse = $lusopayClient->sendMbWayRequest(
                    $mbwayOrderId,
                    $valor_caucao_reserva,
                    $mbway_numero,
                    false
                );

                $mbwayStatusCode = (string) ($mbwayResponse['statusCode'] ?? '');
                if ($mbwayStatusCode !== '000') {
                    $erroMbway = trim((string) ($mbwayResponse['statusMessage'] ?? $mbwayResponse['message'] ?? 'Erro desconhecido.'));
                    $_SESSION['erro'] = "Não foi possível enviar o pedido MB Way da caução: " . $erroMbway;
                } else {
                    $campos['mbway_order_id'] = $mbwayOrderId;
                    $campos['mbway_status_code'] = $mbwayStatusCode;
                    $campos['mbway_token'] = isset($mbwayResponse['token']) ? (string) $mbwayResponse['token'] : null;
                    $campos['mbway_status_mensagem'] = isset($mbwayResponse['statusMessage']) ? (string) $mbwayResponse['statusMessage'] : null;
                    $campos['mbway_data_pedido'] = date('Y-m-d H:i:s');
                }
            } catch (Throwable $e) {
                $mensagemErroMbway = (string) $e->getMessage();
                $erroTimeoutMbway = stripos($mensagemErroMbway, 'Timeout na chamada Lusopay') !== false || stripos($mensagemErroMbway, 'timed out') !== false;

                if ($erroTimeoutMbway) {
                    $mbway_timeout = 1;
                    $campos['mbway_order_id'] = isset($mbwayOrderId) ? $mbwayOrderId : $dbprivados->geraCodigoPagamentoMbway($data_evento, $sala['id'], $mesa['codigo_mesa'], $idReservaDisponibilidade);
                    $campos['mbway_status_code'] = 'TIMEOUT';
                    $campos['mbway_status_mensagem'] = 'Pedido MB Way sem resposta imediata (timeout). A aguardar pagamento até 15 minutos.';
                    $campos['mbway_data_pedido'] = date('Y-m-d H:i:s');
                } else {
                    $_SESSION['erro'] = "Erro ao comunicar com Lusopay MB Way: " . $mensagemErroMbway;
                }
            }
        }

        if (!empty($_SESSION['erro'])) {
            if ($reserva_com_valor_antecipado === 'sim' && empty($_GET['id']) && $idReservaDisponibilidade > 0) {
                $db->query('DELETE FROM privados_salas_mesas_disponibilidade WHERE id = ' . intval($idReservaDisponibilidade));
                $idReservaDisponibilidade = 0;
            }
            $campos = array();
        }

        if (empty($_SESSION['erro'])) {
        if ($_GET['id']) {
            $db->Update('privados_salas_mesas_disponibilidade', $campos, 'id=' . intval($_GET['id']));

            if ($campos['sms_erro'] == 1) {
                $_SESSION['erro'] = "Reserva criada mas houve um erro a enviar a SMS: ". $campos['sms_erro_mensagem'];
            }
            else if ($mbway_timeout == 1) {
                $_SESSION['sucesso'] = "A reserva foi alterada. Pedido MB Way em processamento; aguarde até 15 minutos.";
            }
            else{
                $_SESSION['sucesso'] = "A reserva foi alterada.";
            }

        } else if ($reserva_com_valor_antecipado === 'sim' && $idReservaDisponibilidade > 0) {
            $db->Update('privados_salas_mesas_disponibilidade', $campos, 'id=' . $idReservaDisponibilidade);

            if ($campos['sms_erro'] == 1) {
                $_SESSION['erro'] = "Reserva criada mas houve um erro a enviar a SMS: ". $campos['sms_erro_mensagem'];
            }
            else if ($mbway_timeout == 1) {
                $_SESSION['sucesso'] = "A reserva foi criada. Pedido MB Way em processamento; aguarde até 15 minutos.";
            }
            else{
                $_SESSION['sucesso'] = "A reserva foi criada";
            }

        } else {
            $id = $db->Insert('privados_salas_mesas_disponibilidade', $campos);
            if ($campos['sms_erro'] == 1) {
                $_SESSION['erro'] = "Reserva criada mas houve um erro a enviar a SMS: ". $campos['sms_erro_mensagem'];
            }
            else if ($mbway_timeout == 1) {
                $_SESSION['sucesso'] = "A reserva foi criada. Pedido MB Way em processamento; aguarde até 15 minutos.";
            }
            else{
                $_SESSION['sucesso'] = "A reserva foi criada";
            }
        }
        header('Location: /rp/index.php?pg=disponibilidade_de_mesas&data_evento=' . $_GET['data_evento'] . '#sala_' . $mesa['id_sala']);
        exit;
        }
    }
}
?>


<div class="header">
    <h2>Reservar mesa - <?php echo $mesa['codigo_mesa']; ?> - <?php echo $sala['nome']; ?> </h2>
</div>

<div class="conteudo" <?php echo escreveErroSucesso(); ?>>
    <a href="/rp/index.php?pg=disponibilidade_de_mesas&data_evento=<?php echo $_GET['data_evento']; ?>" class="voltar">
        <span class="icon"> <img src="/temas/rps/imagens/back.svg" /> </span>
        <span class="label"> Voltar </span>
    </a>

    <form name="formulario" class="consumo-obrigatorio" action="" method="post">
        <div class="inputs">
            <div class="label">
                Data do evento
            </div>
            <div class="input">
                <?php echo $_GET['data_evento']; ?>
            </div>
        </div>

        <div class="inputs">
            <div class="label">
                Nome do cliente
            </div>
            <div class="input">
                <input name="nome" value="<?php echo $nome; ?>" type="text" required="required" />
            </div>
        </div>

        <div class="inputs">
            <div class="label">
                Staff
            </div>
            <div class="input">
                <select name="id_rp" required="required">
                    <option value="">
                        Selecione um Staff
                    </option>
                    <?php
                    foreach ($rps as $rp) {
                    ?>
                    <option value="<?php echo $rp['id']; ?>" <?php if ($id_rp == $rp['id']) { ?> selected="selected"
                        <?php } ?>><?php echo $rp['nome']; ?> </option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="inputs">
            <div class="label">
                Número de garrafas
            </div>
            <div class="input">
                <input name="garrafas" value="<?php echo $garrafas; ?>" type="number" />
            </div>
        </div>

        <div class="inputs">
            <div class="label">
                Número de cartões
            </div>
            <div class="input">
                <input name="cartoes" value="<?php echo $cartoes; ?>" type="number" />
            </div>
        </div>

        <div class="inputs">
            <div class="label">
                Valor Total Privado
            </div>
            <div class="input">
                <input name="valor" value="<?php echo $valor; ?>" type="number" step="0.01" />
            </div>
        </div>

        <div class="inputs">
            <div class="label">
                Reserva com valor antecipado?
            </div>
            <div class="input">
                <select name="reserva_com_valor_antecipado" id="reserva_com_valor_antecipado">
                    <option value="sim" <?php if ($reserva_com_valor_antecipado === 'sim') { ?> selected="selected"
                        <?php } ?>>Sim</option>
                    <option value="nao" <?php if ($reserva_com_valor_antecipado === 'nao') { ?> selected="selected"
                        <?php } ?>>Não</option>
                </select>
            </div>
        </div>

        <div id="campos-antecipado">
            <br>
            <div class="inputs">
                <div class="label">
                    Valor Caução de Reserva
                </div>
                <div class="input">
                    <input name="valor_caucao_reserva" id="valor_caucao_reserva"
                        value="<?php echo $valor_caucao_reserva; ?>" type="number" step="0.01" min="0" />
                </div>
            </div>

            <div class="inputs">
                <div class="label">
                    Nº MB Way
                </div>
                <div class="input">
                    <input name="mbway_numero" id="mbway_numero" value="<?php echo $mbway_numero; ?>" type="tel" />
                </div>
            </div>
        </div>
        <!--
        <div class="inputs">
            <div class="label">
                Nº de Telemovel
            </div>
            <div class="input">
                <input name="telemovel" value="<?php echo $telemovel?$telemovel:"+351"; ?>" type="tel" step="0.01" />
            </div>
        </div>
        -->

        <div class="inputs">
            <input type="submit" value="Enviar" />
        </div>


        <div class="inputs">
            <div class="label">
                Pre-visalização de mensagem
            </div>
            <div class="input">
                <textarea name="mensagem"><?php echo $mensagem_default;?></textarea>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    var selectAntecipado = document.getElementById('reserva_com_valor_antecipado');
    var blocoAntecipado = document.getElementById('campos-antecipado');
    var valorCaucaoInput = document.getElementById('valor_caucao_reserva');
    var mbwayInput = document.getElementById('mbway_numero');

    function toggleCamposAntecipado() {
        var comAntecipado = selectAntecipado && selectAntecipado.value === 'sim';

        if (blocoAntecipado) {
            blocoAntecipado.style.display = comAntecipado ? '' : 'none';
        }

        if (valorCaucaoInput) {
            valorCaucaoInput.required = comAntecipado;
            if (!comAntecipado) {
                valorCaucaoInput.value = '';
            }
        }

        if (mbwayInput) {
            mbwayInput.required = comAntecipado;
            if (!comAntecipado) {
                mbwayInput.value = '';
            }
        }
    }

    if (selectAntecipado) {
        selectAntecipado.addEventListener('change', toggleCamposAntecipado);
        toggleCamposAntecipado();
    }
})();
</script>