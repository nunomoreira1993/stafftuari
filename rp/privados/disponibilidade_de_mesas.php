<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/privados/privados.obj.php');
$dbprivados = new privados($db);
require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/rp.obj.php');
$dbrp = new rp($db, $_SESSION['id_rp']);
$id_cargo = $dbrp->devolveCargo();
$permissao = $dbrp->permissao();

$nome_cliente = $_GET['nome_cliente'];

if ($_GET['data_evento']) {
    $data_evento = $_GET['data_evento'];
} else {

    $proximo_privado = $dbprivados->devolveProximoPrivado();
    if (empty($proximo_privado)) {
        if (date('H') < 14) {
            $data_evento = date('Y-m-d', strtotime('-1 day'));
        } else {
            $data_evento = date('Y-m-d');
        }
    } else {
        $data_evento = $proximo_privado['data_evento'];
    }
}

$data_evento = date('Y-m-d', strtotime($data_evento));

$dbprivados->libertaReservasMbwayExpiradas($data_evento);

$salasMesasArr = $dbprivados->devolveSalasPesquisa($nome_cliente, $data_evento);
$mesasArr = $dbprivados->devolveMesasPesquisa($nome_cliente, $data_evento);

$devolveTempoLimiteMbwayMinutos = function ($reserva) {
    if ((string) ($reserva['mbway_status_code'] ?? '') === 'TIMEOUT') {
        return 15;
    }

    return 4;
};

$calculaEstadoReservaMesa = function ($reserva, $vendida, $disponivel) use ($devolveTempoLimiteMbwayMinutos) {
    if ($vendida) {
        return array('classe' => 'vendida', 'label' => 'Vendida');
    }

    if (empty($reserva)) {
        return array('classe' => 'livre', 'label' => 'Disponivel');
    }
    
    $reservaComAntecipado = !empty($reserva['reserva_com_valor_antecipado']) || (float) ($reserva['valor_caucao_reserva'] ?? 0) > 0;
    if ($reservaComAntecipado  && $reserva["mbway_response_status_code"] != "000") {
        if (!empty($reserva['mbway_data_pedido'])) {
            $tempoLimiteMbwayMinutos = $devolveTempoLimiteMbwayMinutos($reserva);
            $timestampExpira = strtotime($reserva['mbway_data_pedido'] . ' +' . $tempoLimiteMbwayMinutos . ' minutes');
            if ($timestampExpira && $timestampExpira <= time()) {
                return array('classe' => 'livre', 'label' => 'Disponivel');
            }
        }

        return array('classe' => 'aguardar_pagamento', 'label' => 'Aguardar Pagamento');
    }

    if (empty($disponivel)) {
         if (($reserva['valor_dinheiro_adiantado'] + $reserva['valor_multibanco_adiantado'] + $reserva['valor_mbway_adiantado']) == 0 && $reserva["mbway_response_status_code"] != "000") {
            return array('classe' => 'aguardar_adiantamento', 'label' => 'Ocupado (s/ adiantamento)');
         }
         else {
             return array('classe' => 'ocupado', 'label' => 'Ocupada');
         }
    }

    return array('classe' => 'livre', 'label' => 'Disponivel');
};

$salas = $dbprivados->listaSalas($salasMesasArr);

if($_GET["libertar"] == 1) {
    $reserva = $dbprivados->devolveReservaMesa($_GET['id_mesa'], $_GET['data_evento']);
    $mesa = $dbprivados->devolveMesa($_GET['id_mesa']);
    if ($permissao) {
        if ($mesa) {
            $query = 'DELETE from privados_salas_mesas_ocupacao WHERE data_evento= "' . $data_evento . '" AND id_mesa="' . $_GET['id_mesa'] . '"';
            $db->query($query);

            $query = 'UPDATE privados_salas_mesas_disponibilidade SET saiu = 1  WHERE id = ' . $reserva["id"];
            $db->query($query);

            $estado = $db->Insert('logs', array('descricao' => "Libertou a mesa ID " . $_GET['id_mesa'], 'arr' => json_encode($campos), 'id_admin' => $_SESSION['id_utilizador'], 'tipo' => "Apagar", 'user_agent' => $_SERVER['HTTP_USER_AGENT'], 'ip' => $_SERVER['REMOTE_ADDR']));


            $_SESSION['sucesso'] = "Reserva libertada com sucesso.";
        } else {
            $_SESSION['erro'] = "Mesa não encontrada.";
        }
    }else {
        $_SESSION['erro'] = "Não tem permissões para libertar esta mesa.";
    }
    header('Location: /rp/index.php?pg=disponibilidade_de_mesas&data_evento=' . $_GET['data_evento'] . '#sala_' . $mesa['id_sala']);
    exit;
}
if ($_GET['cancelar']) {
    $reserva = $dbprivados->devolveReservaMesa($_GET['id_mesa'], $_GET['data_evento']);
    $mesa = $dbprivados->devolveMesa($_GET['id_mesa']);
    if ($permissao) {
        $query = 'DELETE from privados_salas_mesas_disponibilidade WHERE data_evento= "' . $_GET['data_evento'] . '" AND id_mesa="' . $_GET['id_mesa'] . '"';
        $db->query($query);
        $db->Insert('logs_rp', array('descricao' => "Cancelou a reserva de uma mesa.", 'arr' => json_encode($reserva), 'id_rp' => $_SESSION['id_rp'], 'tipo' => "Apagar", 'user_agent' => $_SERVER['HTTP_USER_AGENT'], 'ip' => $_SERVER['REMOTE_ADDR']));
        $_SESSION['sucesso'] = "Reserva cancelada com sucesso.";
    } else {
        $_SESSION['erro'] = "Não tem permissões para cancelar esta reserva.";
    }
    header('Location: /rp/index.php?pg=disponibilidade_de_mesas&data_evento=' . $_GET['data_evento'] . '#sala_' . $mesa['id_sala']);
    exit;
}

if ($_GET['auto_libertar_expirada'] == 1 && $_GET['id_reserva']) {
    header('Content-Type: application/json; charset=utf-8');

    $idReserva = intval($_GET['id_reserva']);
    if (!$permissao || $idReserva <= 0) {
        echo json_encode(array('ok' => false, 'mensagem' => 'Sem permissão ou reserva inválida.'));
        exit;
    }

    $libertou = $dbprivados->libertaReservaMbwayExpiradaPorId($idReserva);
    if ($libertou) {
        echo json_encode(array('ok' => true));
    } else {
        echo json_encode(array('ok' => false, 'mensagem' => 'Reserva não está elegível para libertação automática.'));
    }
    exit;
}
?>
<div class="header">
    <h2>Disponibilidade de mesas</h2>
</div>

<style>
.mesa .estado.aguardar_pagamento {
    background-color: #007BFF;
    color: #fff;
}

.hotspot a.mesa.aguardar_pagamento {
    background-color: #007BFF;
    color: #fff;
}

.hotspot a.mesa {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    line-height: 1.1;
}

.hotspot a.mesa .hotspot-timer {
    font-size: 10px;
    margin-top: 2px;
    color: #fff;
}

.mbway-timer {
    font-weight: bold;
    color: #007BFF;
}

.mbway-timer.expirado {
    color: #d9534f;
}
</style>

<div class="conteudo disponibilidade" <?php echo escreveErroSucesso(); ?>>
    <?php
    if (empty($salas)) {
    ?>
    <span class="sem_registos">
        Não foram encontrados registos.
    </span>
    <?php
    } else {
    ?>
    <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" class="data formulario" method="get">
        <div class="inputs">
            <div class="label">
                Data do evento
            </div>
            <div class="input">
                <input type="hidden" name="pg" value="disponibilidade_de_mesas" />
                <input name="data_evento" value="<?php echo $data_evento; ?>" required="required" type="date"
                    min="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" />
            </div>
        </div>
        <div class="inputs">
            <div class="label">
                Nome do cliente
            </div>
            <div class="input">
                <input name="nome_cliente" value="<?php echo $nome_cliente; ?>" type="text" />
            </div>
        </div>
        <div class="inputs">
            <input type="submit" value="Pesquisar" class="adicionar">
        </div>
    </form>
    <?php
		if($cargo == $dbrp->getIDGerente()) {
			?>
    <a href="/administrador/exportar/exportar_reservas.php?data=<?php echo $data_evento; ?>" class="exportar-excell">
        Exportar para Excell </a>
    <?php
		}
		?>
    <div class="swiper-container">
        <div class="swiper-wrapper">
            <?php
                foreach ($salas as $sala) {
                    $mesas = $dbprivados->listaMesas($sala['id'], $mesasArr);
                ?>
            <div class="swiper-slide" id="sala_<?php echo $sala['id']; ?>" data-hash="sala_<?php echo $sala['id']; ?>">
                <?php
                        if ($sala['foto'] && file_exists($_SERVER['DOCUMENT_ROOT'] . "/fotos/privados/" . $sala['foto'])) {
                        ?>
                <div class="hotspot">
                    <img src="/fotos/privados/<?php echo $sala['foto']; ?>">
                    <?php
                                foreach ($mesas as $mesa) {
                                        $disponivel = $dbprivados->verificaMesaDisponivel($mesa['id'], $data_evento, 1);
                                        $vendida = $dbprivados->verificaMesaVendida($mesa['id'], $data_evento, 1);
                                        $reservaMesa = $dbprivados->devolveReservaMesa($mesa['id'], $data_evento);
                                        $estadoMesa = $calculaEstadoReservaMesa($reservaMesa, $vendida, $disponivel);
                                        $timestampExpiraMsHotspot = 0;
                                        if ($estadoMesa['classe'] === 'aguardar_pagamento' && !empty($reservaMesa['mbway_data_pedido'])) {
                                            $tempoLimiteMbwayMinutosHotspot = $devolveTempoLimiteMbwayMinutos($reservaMesa);
                                            $timestampExpiraHotspot = strtotime($reservaMesa['mbway_data_pedido'] . ' +' . $tempoLimiteMbwayMinutosHotspot . ' minutes');
                                            if ($timestampExpiraHotspot) {
                                                $timestampExpiraMsHotspot = $timestampExpiraHotspot * 1000;
                                            }
                                        }
                                ?>
                    <a class="mesa <?php echo $estadoMesa['classe']; ?>" data-mesa-id="<?php echo $mesa['id']; ?>"
                        id="mesa_<?php echo $sala['id'] . "_" . str_replace(".", "_", $mesa['codigo_mesa']); ?>"
                        <?php if ($permissao) { ?>
                        href="/rp/index.php?pg=inserir_reserva&id=0&id_mesa=<?php echo $mesa['id']; ?>&data_evento=<?php echo $data_evento; ?>"
                        <?php } ?>>
                        <span><?php echo $mesa['codigo_mesa']; ?></span>
                        <?php
                                        if ($timestampExpiraMsHotspot > 0) {
                                        ?>
                        <span class="hotspot-timer mbway-timer" data-mesa-id="<?php echo $mesa['id']; ?>"
                            data-reserva-id="<?php echo !empty($reservaMesa['id']) ? intval($reservaMesa['id']) : 0; ?>"
                            data-expira-ms="<?php echo $timestampExpiraMsHotspot; ?>">--:--</span>
                        <?php
                                        }
                                        ?>
                    </a>
                    <?php
                                }
                                ?>
                </div>
                <?php
                        }
                        ?>
                <div class="mesas">
                    <?php
                            foreach ($mesas as $mesa) {
                                $disponivel = $dbprivados->verificaMesaDisponivel($mesa['id'], $data_evento, 1);
                                $vendida = $dbprivados->verificaMesaVendida($mesa['id'], $data_evento, 1);
                                $reserva = $dbprivados->devolveReservaMesa($mesa['id'], $data_evento);
                                $estadoMesa = $calculaEstadoReservaMesa($reserva, $vendida, $disponivel);
                            ?>
                    <div class="mesa">
                        <a class="topo" <?php if ($permissao) { ?>
                            href="/rp/index.php?pg=inserir_reserva&id_mesa=<?php echo $mesa['id']; ?>&id=0&data_evento=<?php echo $data_evento; ?>"
                            <?php } ?>>
                            <span class="codigo">
                                <?php echo $mesa['codigo_mesa']; ?>
                            </span>

                            <?php
                                        ?>
                            <span class="estado <?php echo $estadoMesa['classe']; ?>"
                                data-mesa-id="<?php echo $mesa['id']; ?>">
                                <?php echo $estadoMesa['label']; ?>
                            </span>
                            <?php
                                        ?>
                        </a>
                        <?php
                                    if (!empty($reserva) && $cargo == $dbrp->getIDGerente()) {
                                    ?>
                        <div class="info_reserva">
                            <div class="bloco">
                                <span class="titulo">
                                    Gerente
                                </span>
                                <span class="valor">
                                    <?php echo $dbrp->devolveNomeRp($reserva['id_gerente']); ?>
                                </span>
                            </div>
                            <div class="bloco">
                                <span class="titulo">
                                    Staff
                                </span>
                                <span class="valor">
                                    <?php echo $dbrp->devolveNomeRp($reserva['id_rp']); ?>
                                </span>
                            </div>
                            <div class="bloco">
                                <span class="titulo">
                                    Cliente
                                </span>
                                <span class="valor">
                                    <?php echo $reserva['nome']; ?>
                                </span>
                            </div>
                            <div class="bloco">
                                <span class="titulo">
                                    Data da reserva
                                </span>
                                <span class="valor">
                                    <?php echo $reserva['data']; ?>
                                </span>
                            </div>

                            <div class="bloco">
                                <span class="titulo">
                                    Nr. de garrafas
                                </span>
                                <span class="valor">
                                    <?php echo $reserva['garrafas']; ?>
                                </span>
                            </div>
                            <div class="bloco">
                                <span class="titulo">
                                    Nr. de Cartões
                                </span>
                                <span class="valor">
                                    <?php echo $reserva['cartoes']; ?>
                                </span>
                            </div>
                            <div class="bloco">
                                <span class="titulo">
                                    Valor (€)
                                </span>
                                <span class="valor">
                                    <?php echo euro($reserva['valor']); ?>
                                </span>
                            </div>
                            <div class="bloco">
                                <span class="titulo">
                                    SMS Enviada
                                </span>
                                <span class="valor">
                                    <?php echo ($reserva['sms_enviada']) ? "Sim" : "Não"; ?>
                                </span>
                            </div>
                            <?php
                                            if ($estadoMesa['classe'] === 'aguardar_pagamento' && !empty($reserva['mbway_data_pedido'])) {
                                                $tempoLimiteMbwayMinutosReserva = $devolveTempoLimiteMbwayMinutos($reserva);
                                                $timestampExpira = strtotime($reserva['mbway_data_pedido'] . ' +' . $tempoLimiteMbwayMinutosReserva . ' minutes');
                                                if ($timestampExpira) {
                                                    $timestampExpiraMs = $timestampExpira * 1000;
                                            ?>
                            <div class="bloco">
                                <span class="titulo">
                                    Tempo MB Way
                                </span>
                                <span class="valor mbway-timer" data-mesa-id="<?php echo $mesa['id']; ?>"
                                    data-reserva-id="<?php echo intval($reserva['id']); ?>"
                                    data-expira-ms="<?php echo $timestampExpiraMs; ?>">
                                    <span class="mbway-timer-count">--:--</span>
                                    <?php
                                                        if (!empty($reserva['valor_caucao_reserva']) && floatval($reserva['valor_caucao_reserva']) > 0) {
                                                        ?>
                                    <span class="mbway-caucao"> • Caução:
                                        <?php echo euro($reserva['valor_caucao_reserva']); ?></span>
                                    <?php
                                                        }
                                                        ?>
                                </span>
                            </div>
                            <?php
                                                }
                                            }
                                            ?>
                            <?php
                                            if ($reserva['valor_multibanco_adiantado']) {
                                            ?>
                            <div class="bloco">
                                <span class="titulo">
                                    Adiantado Multibanco (€)
                                </span>
                                <span class="valor">
                                    <?php echo euro($reserva['valor_multibanco_adiantado']); ?>
                                </span>
                            </div>
                            <?php
                            }
                            if ($reserva['valor_dinheiro_adiantado']) {
                            ?>
                            <div class="bloco">
                                <span class="titulo">
                                    Adiantado Dinheiro (€)
                                </span>
                                <span class="valor">
                                    <?php echo euro($reserva['valor_dinheiro_adiantado']); ?>
                                </span>
                            </div>
                            <?php
                            }
                            if ($reserva['valor_mbway_adiantado']) {
                            ?>
                            <div class="bloco">
                                <span class="titulo">
                                    Adiantado MBWAY (€)
                                </span>
                                <span class="valor">
                                    <?php echo euro($reserva['valor_mbway_adiantado']); ?>
                                </span>
                            </div>
                            <?php
                            }
                            ?>
                            <a href="/rp/index.php?pg=disponibilidade_de_mesas&data_evento=<?php echo $data_evento; ?>&cancelar=1&id_mesa=<?php echo $reserva['id_mesa']; ?>"
                                class="cancelar aviso-popup"
                                data-texto="Deseja cancelar a reserva para a mesa <?php echo $sala['nome']; ?> - <?php echo $mesa['codigo_mesa']; ?> no dia <?php echo $data_evento; ?> ">
                                Cancelar reserva
                            </a>
                            <?php 
                            /*
                            if ($reserva['valor_mbway_adiantado'] == 0) {
                            ?>
                            <a href="/rp/index.php?pg=pagamento_adiantado&id_mesa=<?php echo $mesa['id']; ?>&id=<?php echo $reserva['id']; ?>&data_evento=<?php echo $data_evento; ?>"
                                class="pagamento">
                                Pagamento Adiantado
                            </a>
                            <?php
                            }
                            */
                            if (!empty($reserva)) {
                            ?>
                            <a href="/rp/index.php?pg=disponibilidade_de_mesas&data_evento=<?php echo $data_evento; ?>&libertar=1&id_mesa=<?php echo $reserva['id_mesa']; ?>"
                                class="cancelar"
                                onclick="return confirm('Tem a certeza que pretende libertar a mesa <?php echo $mesa['codigo_mesa']; ?> reservada a <?php echo $reserva['nome']; ?>?')">
                                Libertar mesa
                            </a>
                            <?php
                            }
                            ?>
                        </div>
                        <?php
                                    }
                                    ?>
                    </div>
                    <?php
                            }
                            ?>
                </div>
            </div>
            <?php
                }
                ?>
        </div>

        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
    <?php

    }
    ?>
</div>

<script>
(function() {
    var timers = document.querySelectorAll('.mbway-timer[data-expira-ms]');
    if (!timers.length) {
        return;
    }

    function pad(valor) {
        return valor < 10 ? '0' + valor : String(valor);
    }

    function atualizaTimer(el) {
        var expiraMs = parseInt(el.getAttribute('data-expira-ms'), 10);
        var mesaId = el.getAttribute('data-mesa-id');
        var reservaId = parseInt(el.getAttribute('data-reserva-id'), 10);
        var elContador = el.querySelector('.mbway-timer-count') || el;
        if (!expiraMs) {
            elContador.textContent = '--:--';
            return;
        }

        var agora = Date.now();
        var restante = expiraMs - agora;

        if (restante <= 0) {
            if (el.classList.contains('hotspot-timer')) {
                el.style.display = 'none';
            } else {
                elContador.textContent = 'Expirado';
            }
            el.classList.add('expirado');
            if (mesaId) {
                var estados = document.querySelectorAll('.estado[data-mesa-id="' + mesaId + '"]');
                for (var j = 0; j < estados.length; j++) {
                    estados[j].classList.remove('aguardar_pagamento');
                    estados[j].classList.add('livre');
                    estados[j].textContent = 'Disponivel';
                }

                var hotspots = document.querySelectorAll('.hotspot a.mesa[data-mesa-id="' + mesaId + '"]');
                for (var k = 0; k < hotspots.length; k++) {
                    hotspots[k].classList.remove('aguardar_pagamento');
                    hotspots[k].classList.add('livre');
                }

                var infoReserva = document.querySelectorAll('.estado[data-mesa-id="' + mesaId + '"]');
                for (var m = 0; m < infoReserva.length; m++) {
                    var containerMesa = infoReserva[m].closest('.mesa');
                    if (containerMesa) {
                        var blocoInfo = containerMesa.querySelector('.info_reserva');
                        if (blocoInfo) {
                            blocoInfo.style.display = 'none';
                        }
                    }
                }
            }

            if (reservaId > 0 && !el.getAttribute('data-libertar-enviado')) {
                el.setAttribute('data-libertar-enviado', '1');
                fetch('/rp/index.php?pg=disponibilidade_de_mesas&data_evento=<?php echo $data_evento; ?>&auto_libertar_expirada=1&id_reserva=' +
                    reservaId, {
                        cache: 'no-store'
                    }).catch(function() {});
            }
            return;
        }

        var totalSegundos = Math.floor(restante / 1000);
        var minutos = Math.floor(totalSegundos / 60);
        var segundos = totalSegundos % 60;
        elContador.textContent = pad(minutos) + ':' + pad(segundos);
        el.classList.remove('expirado');
    }

    function atualizaTodos() {
        for (var i = 0; i < timers.length; i++) {
            atualizaTimer(timers[i]);
        }
    }

    atualizaTodos();
    setInterval(atualizaTodos, 1000);
})();
</script>