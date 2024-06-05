<?php
if ($tipo != 1 && $tipo != 3 && $tipo != 4 && $tipo != 8) {
    header('Location:/administrador/index.php');
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/privados/privados.obj.php');
$dbprivados = new privados($db);


require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/rp.obj.php');
$dbrp = new rp($db, $_SESSION['id_rp']);
$id_cargo = $dbrp->devolveCargo();

if ($_GET['data_evento']) {
    $data_evento = $_GET['data_evento'];
} else {
    if (date('H') < 14) {
        $data_evento = date('Y-m-d', strtotime('-1 day'));
    } else {
        $data_evento = date('Y-m-d');
    }
}

$salas_pesquisa = array();
if ($_POST['nome']) {
    $nome = $_POST['nome'];
    $mesas = $dbprivados->listaMesas(false, false, $nome, $data_evento);

    if ($mesas) {
        foreach ($mesas as $mesa) {
            $salas_pesquisa[] = $mesa['id_sala'];
            $mesas_pesquisa[] = $mesa['id_mesa'];
        }
    }
    if (empty($mesas)) {
        $_SESSION['erro'] = "Não foi encontrado nenhuma mesa com o nome do cliente indicado.";
        header('Location: /administrador/index.php?pg=escolher_mesa_privados');
        exit;
    }
}

$salas = $dbprivados->listaSalas($salas_pesquisa);

if ($_GET['cancelar']) {
    $reserva = $dbprivados->devolveReservaMesa($_GET['id_mesa'], $data_evento);
    if ($reserva) {
        $mesa = $dbprivados->devolveMesa($_GET['id_mesa']);
        if ($mesa) {
            $query = 'DELETE from privados_salas_mesas_disponibilidade WHERE data_evento= "' . $data_evento . '" AND id_mesa="' . $_GET['id_mesa'] . '"';
            $db->query($query);
            $estado = $db->Insert('logs', array('descricao' => "Apagou uma reserva de privados para a mesa ID " . $_GET['id_mesa'], 'arr' => json_encode($campos), 'id_admin' => $_SESSION['id_utilizador'], 'tipo' => "Apagar", 'user_agent' => $_SERVER['HTTP_USER_AGENT'], 'ip' => $_SERVER['REMOTE_ADDR']));
            $_SESSION['sucesso'] = "Reserva cancelada com sucesso.";
        } else {
            $_SESSION['erro'] = "Mesa não encontrada.";
        }
    } else {
        $_SESSION['erro'] = "Reserva não encontrada.";
    }
    header('Location: ?pg=escolher_mesa_privados#sala_' . $mesa['id_sala']);
    exit;
}
?>

<div class="content" <?php echo escreveErroSucesso(); ?>>

    <form class="filtros" name="filtros" action="" method="POST">
        <div class="filtro">
            <span class="nome">Nome Cliente:</span>
            <span class="input"><input type="text" name="nome" value="<?php echo $_POST['nome']; ?>" /></span>
        </div>
        <input type="submit" value="Filtrar" />
        <a href="/administrador/index.php?pg=escolher_mesa_privados" class="clean"> Limpar filtros </a>
    </form>
    <div class="conteudo disponibilidade">
        <?php
        if (empty($salas)) {
            ?>
            <span class="sem_registos">
                Não foram encontrados registos.
            </span>
        <?php
        } else {
            ?>
            <div class="swiper-container">
                <div class="swiper-wrapper">
                    <?php
                    foreach ($salas as $sala) {
                        $mesas = $dbprivados->listaMesas($sala['id']);
                        ?>
                        <div class="swiper-slide" id="sala_<?php echo $sala['id']; ?>" data-hash="sala_<?php echo $sala['id']; ?>">
                            <?php
                            if ($sala['foto'] && file_exists($_SERVER['DOCUMENT_ROOT'] . "/fotos/privados/" . $sala['foto'])) {
                                ?>
                                <div class="hotspot">
                                    <img src="/fotos/privados/<?php echo $sala['foto']; ?>">
                                    <?php
                                    foreach ($mesas as $mesa) {

                                        if ($mesas_pesquisa) {
                                            if (!in_array($mesa['id'], $mesas_pesquisa)) {
                                                continue;
                                            }
                                        }

                                        $disponivel = $dbprivados->verificaMesaDisponivel($mesa['id'], $data_evento, 1);
                                        $vendida = $dbprivados->verificaMesaVendida($mesa['id'], $data_evento, 1);
                                        ?>
                                        <a class="mesa <?php if ($vendida) { ?> vendida <?php } else if ($disponivel == 1) { ?> livre <?php } else { ?> ocupado <?php } ?> " id="mesa_<?php echo $sala['id'] . "_" . $mesa['codigo_mesa']; ?>" href="?pg=inserir_venda_privados&id_mesa=<?php echo $mesa['id']; ?>&id=0">
                                            <?php echo $mesa['codigo_mesa']; ?>
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
                                    if ($mesas_pesquisa) {
                                        if (!in_array($mesa['id'], $mesas_pesquisa)) {
                                            continue;
                                        }
                                    }

                                    $disponivel = $dbprivados->verificaMesaDisponivel($mesa['id'], $data_evento, 1);

                                    ?>
                                    <div class="mesa">
                                        <a class="topo" href="?pg=inserir_venda_privados&id_mesa=<?php echo $mesa['id']; ?>&id=0">
                                            <span class="codigo">
                                                <?php echo $mesa['codigo_mesa']; ?>
                                            </span>
                                            <?php
                                            if (!$disponivel) {
                                            ?>
                                                <span class="estado ocupado">
                                                    Ocupada
                                                </span>
                                                <?php
                                            } else {
                                                ?>
                                                <span class="estado livre">
                                                    Disponivel
                                                </span>
                                            <?php
                                            }
                                            ?>
                                        </a>
                                        <?php
                                        if (!$disponivel) {
                                            $reserva = $dbprivados->devolveReservaMesa($mesa['id'], $data_evento);

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
												<?php
												if($tipo == 1){
												?>
                                                <div class="bloco">
                                                    <span class="titulo">
                                                        Valor (€)
                                                    </span>
                                                    <span class="valor">
                                                        <?php echo euro($reserva['valor']); ?>
                                                    </span>
                                                </div>
												<?php
												}
												?>
                                                <div class="botoes">
                                                    <a href="?pg=escolher_mesa_privados&cancelar=1&id_mesa=<?php echo $reserva['id_mesa']; ?>" class="cancelar">
                                                        Cancelar
                                                    </a>
                                                    <a href="?pg=inserir_venda_privados&id_mesa=<?php echo $mesa['id']; ?>&id=0" class="cancelar confirmar">
                                                        Confirmar
                                                    </a>
                                                </div>
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