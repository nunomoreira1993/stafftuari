<?php
if (empty($_SESSION['id_utilizador'])) {
    header('Location:/index.php');
    exit;
}

$data = $_GET['data'];
if (empty($data)) {
    header('Location:/index.php');
    exit;
}
require_once($_SERVER['DOCUMENT_ROOT'] . '/administrador/rps/rps.obj.php');
$dbrps = new rps($db);
$gerir_entradas = $dbrps->listaEntradasData($data);

if ($_GET['apagar'] == 1 && $_GET['id'] > 0) {

    $entrada = $dbrps->devolveEntrada($_GET['id']);
    if ($entrada) {
        $query = 'DELETE from rps_entradas WHERE id=' . $_GET['id'];
        $db->query($query);
        $db->Insert('logs', array('descricao' => "Apagou uma entrada para o RP", 'arr' => json_encode($entrada), 'id_admin' => $_SESSION['id_utilizador'], 'tipo' => "apagar", 'user_agent' => $_SERVER['HTTP_USER_AGENT'], 'ip' => $_SERVER['REMOTE_ADDR']));
        $_SESSION['sucesso'] = "A entrada foi apagada.";
        header('Location: /administrador/index.php?pg=entradas_evento_data&data=' . $_GET["data"]);
        exit;
    }
}
?>
<h1 class="titulo"> Entradas em <?php echo $data; ?> </h1>
<div class="content" <?php echo escreveErroSucesso(); ?>>
    <div class="table-responsive">
        <table cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Nome RP</th>
                    <th>Data</th>
                    <th>Quantidade</th>
                    <?php
                    if($tipo == 1) {
                    ?>
                        <th class="text-nowrap"></th>
                    <?php
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($gerir_entradas)) {
                    ?>
                    <td colspan="5">
                        Sem registos inseridos.
                    </td>
                <?php
                }
                foreach ($gerir_entradas as $entradas) {
                ?>
                    <tr>
                        <td><?php echo $entradas['nome']; ?></td>
                        <td><?php echo $entradas['data']; ?></td>
                        <td><?php echo $entradas['quantidade']; ?></td>
                        <?php
                        if($tipo == 1) {
                            ?>
                            <td class="text-nowrap">
                                <a href="?pg=entradas_evento_data&data=<?php echo $_GET["data"]; ?>&&apagar=1&id=<?php echo $entradas['id']; ?>" class="apagar"> <i class="fa fa-close text-danger"></i> </a>
                            </td>
                            <?php
                         }
                         ?>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>
</div>