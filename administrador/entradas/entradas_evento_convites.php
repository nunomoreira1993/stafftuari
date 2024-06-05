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
$gerir_entradas = $dbrps->listaCartoesData($data);
?>
<h1 class="titulo"> Entradas em <?php echo $data; ?> </h1>
<div class="content" <?php echo escreveErroSucesso(); ?>>
    <div class="table-responsive">
        <table cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Nome Cliente</th>
                    <th>Nome RP</th>
                    <th>Tipo de Cartão</th>
                    <th>Entrou</th>
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
                        <td><?php echo $entradas['nome_rp']; ?></td>
                        <td><?php echo $entradas['tipo_cartao'] == 1 ? "Cartão sem consumo" : "Cartão com 2/bebidas"; ?></td>
                        <td><?php echo $entradas['entrou'] == 1 ? "Cartão sem consumo" : "Cartão com 2/bebidas"; ?></td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>
</div>