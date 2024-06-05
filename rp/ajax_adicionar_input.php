<?php 
$incremento = $_GET['i'];
?>
<div class="bloco">
    <a href="#" class="remover">
        <img src="/temas/rps/imagens/remover.svg"/> 
    </a>
    <div class="inputs">
        <div class="label">
            Nome do cliente
        </div>
        <div class="input">
            <input name="input[<?php echo $incremento; ?>][nome]" value="" type="text" required="required" />
        </div>
    </div>
	<div class="inputs">
		<div class="label">
			Tipo de cartão
		</div>
		<div class="input">
			<select name="input[<?php echo $incremento; ?>][tipo_cartao]">
				<option value="1" <?php echo ($campo['tipo_cartao'] == 1) ? 'selected="selected"' : ""; ?>>Cartão sem consumo</option>
				<option value="2" <?php echo ($campo['tipo_cartao'] == 2) ? 'selected="selected"' : ""; ?>>Cartão com 2/bebidas</option>
			</select>
		</div>
	</div>
</div>