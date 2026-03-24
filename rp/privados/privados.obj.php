<?php
class privados {
	function __construct($db) {
		$this->db = $db;
	}
	function devolveIDPrivado() {

		$query = "SELECT * FROM administradores WHERE tipo = 3 ORDER BY id ASC";
		$res = $this->db->query($query);

		return $res[0]['id'];
	}
	function listaSalas($salas = array()) {
		if (!empty($salas)) {
			$where = "AND id IN ('" . implode("','", $salas) . "') ";
		}

		$query = "SELECT * FROM privados_salas WHERE activo = 1 $where ORDER BY id ASC";
		$res = $this->db->query($query);

		return $res;
	}

	function listaGarrafas($ids = array()) {
		if (!empty($ids)) {
			$where = "WHERE id IN (" . implode(",", $ids) . ")";
		}
		$query = "SELECT * FROM garrafas $where ORDER BY id ASC";
		$res = $this->db->query($query);
		return $res;
	}

	function devolveSala($id) {

		$query = "SELECT * FROM privados_salas WHERE id = " . $id . " ORDER BY id ASC";
		$res = $this->db->query($query);
		return $res[0];
	}
	function devolveListaEspera($data_evento, $id = false) {
		if ($id > 0) {
			$where = "AND id = '" . $id . "'";
		}
		$query = "SELECT * FROM venda_privados_lista_espera WHERE data_evento = '" . $data_evento . "' $where ORDER BY id ASC";
		$res = $this->db->query($query);

		return $res;
	}
	function devolveListaEsperaGarrafas($data_evento, $id = false) {
		if ($id > 0) {
			$where = "AND id = '" . $id . "'";
		}

		$query = "SELECT * FROM reserva_garrafas WHERE data_evento = '" . $data_evento . "' $where ORDER BY id ASC";
		$res = $this->db->query($query);
		foreach ($res as $k => $rs) {

			$query = "SELECT * FROM reserva_garrafas_garrafas WHERE id_reserva = '" . $rs['id'] . "'";
			$res_garrafas = $this->db->query($query);
			$res[$k]['garrafas'] = $res_garrafas;
		}
		return $res;
	}
	function devolveMesa($id) {
		$query = "SELECT * FROM privados_salas_mesas WHERE id = " . $id . " ORDER BY id ASC";
		$res = $this->db->query($query);
		return $res[0];
	}
	function listaMesas($id_sala = false, $mesas = array(), $nome = false, $data_evento = false) {
		if ($id_sala) {
			$where = " AND id_sala = " . $id_sala;
		}

		if (!empty($mesas)) {
			$where = " AND id IN ('" . implode( "','", $mesas) . "') ";
		}

        if($nome != ""){
            $inner = " INNER JOIN privados_salas_mesas_disponibilidade ON privados_salas_mesas_disponibilidade.id_mesa = privados_salas_mesas.id ";
            $where = " AND privados_salas_mesas_disponibilidade.nome like '%" . $nome. "%' AND privados_salas_mesas_disponibilidade.data_evento = '".$data_evento."'";
        }

		$query = "SELECT * FROM privados_salas_mesas  $inner  WHERE 1=1 $where
		ORDER BY  CASE
			WHEN privados_salas_mesas.codigo_mesa REGEXP '^[0-9]+$' THEN 1
			ELSE 2
		END,
		CASE
			WHEN privados_salas_mesas.codigo_mesa REGEXP '^[0-9]+$' THEN CAST(privados_salas_mesas.codigo_mesa AS UNSIGNED)
			ELSE CAST(SUBSTRING(privados_salas_mesas.codigo_mesa, 2) AS UNSIGNED)
		END";

		$res = $this->db->query($query);


		return $res;
	}
	function verificaMesaDisponivel($id_mesa, $data_evento) {

		$query = "SELECT * FROM privados_salas_mesas_disponibilidade WHERE id_mesa = $id_mesa AND data_evento = '" . $data_evento . "' AND saiu = 0  ORDER BY id ASC";
		$res = $this->db->query($query);
		if (empty($res)) {
			return 1;
		}
	}
	function verificaMesaVendida($id_mesa, $data_evento) {

		$query = "SELECT * FROM venda_privados LEFT JOIN privados_salas_mesas_disponibilidade ON  venda_privados.id_reserva = privados_salas_mesas_disponibilidade.id  WHERE venda_privados.id_mesa = $id_mesa AND venda_privados.data_evento = '" . $data_evento . "' AND privados_salas_mesas_disponibilidade.saiu != 1  ORDER BY venda_privados.id ASC";
		$res = $this->db->query($query);
		if (!empty($res)) {
			return $res;
		}
	}
	function devolveVenda($id_venda) {

		$query = "SELECT * FROM venda_privados WHERE id = '" . (int) $id_venda . "'";
		$res = $this->db->query($query);
		if (!empty($res)) {
			return $res[0];
		}
	}
	function devolveReservaMesa($id_mesa, $data_evento) {
		$query = "SELECT * FROM privados_salas_mesas_disponibilidade  WHERE id_mesa = $id_mesa AND data_evento = '" . $data_evento . "' AND saiu = 0 ORDER BY id ASC";
		$res = $this->db->query($query);
		return $res[0];
	}

	function devolveSalasPesquisa($nome = false, $data_evento = false) {
		if ($nome && $data_evento) {
			$query = "SELECT privados_salas_mesas.id_sala FROM privados_salas_mesas_disponibilidade INNER JOIN privados_salas_mesas ON privados_salas_mesas.id = privados_salas_mesas_disponibilidade.id_mesa  WHERE privados_salas_mesas_disponibilidade.nome like '%" . $nome . "%' AND privados_salas_mesas_disponibilidade.data_evento = '" . $data_evento . "'  GROUP BY privados_salas_mesas.id_sala";
			$res = $this->db->query($query);

			return array_column($res, 'id_sala');
		}
	}

	function devolveMesasPesquisa($nome = false, $data_evento = false) {
		if ($nome && $data_evento) {
			$query = "SELECT id_mesa FROM privados_salas_mesas_disponibilidade  WHERE nome like '%" . $nome . "%' AND data_evento = '" . $data_evento . "'    GROUP BY id_mesa";
			$res = $this->db->query($query);
			return array_column($res, 'id_mesa');
		}
	}

	function devolveReservasMesas($data_evento) {
		$query = "SELECT privados_salas_mesas.codigo_mesa, privados_salas_mesas_disponibilidade.*, rp_staff.nome as rp_staff, rp_gerente.nome as rp_gerente FROM privados_salas_mesas_disponibilidade  INNER JOIN privados_salas_mesas ON privados_salas_mesas.id = privados_salas_mesas_disponibilidade.id_mesa LEFT JOIN rps  rp_staff ON privados_salas_mesas_disponibilidade.id_rp = rp_staff.id LEFT JOIN rps  rp_gerente ON privados_salas_mesas_disponibilidade.id_gerente = rp_gerente.id WHERE privados_salas_mesas_disponibilidade.data_evento = '" . $data_evento . "' AND privados_salas_mesas_disponibilidade.saiu = 0 ORDER BY privados_salas_mesas.id_sala ASC, privados_salas_mesas.id ASC";
		$res = $this->db->query($query);

		return $res;
	}
	function devolveReserva($id) {
		$query = "SELECT * FROM privados_salas_mesas_disponibilidade WHERE id = $id  LIMIT 1";
		$res = $this->db->query($query);
		return $res[0];
	}
	function devolveProximoPrivado() {
		if (date('H') < 14) {
			$data_evento = date('Y-m-d', strtotime('-1 day'));
		} else {
			$data_evento = date('Y-m-d');
		}
		$query = "SELECT * FROM privados_salas_mesas_disponibilidade WHERE data_evento >= '" . $data_evento . "'  ORDER BY data_evento ASC LIMIT 1";
		$res = $this->db->query($query);

		return $res[0];
	}
	function smsto($params) {
		$post['to'] = array($params['telemovel']);
		$post['text'] = $params['mensagem'];
		$post['from'] = "TUARI";
		$post['coding'] = "gsm-pt";
		$post['parts'] = 4;
		$user = "tuari";
		$password = "NUgm17?%";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://dashboard.wausms.com/Api/rest/message");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Accept: application/json",
			"Authorization: Basic " . base64_encode($user . ":" . $password)
		));
		$result = curl_exec($ch);
		curl_close($ch);

		if ($result) {
			return json_decode($result);
		}
	}
	function getMensagemDefault() {
		$mensagem = "Olá {NOME}!\nObrigado por reservares uma mesa connosco para o dia {DATA}, esperemos que te divirtas e aproveites o Sunset!\nDeixamos aqui os dados para efetuares um pagamento de {VALOR}€ (sinalização de 50% da tua reserva):\nIBAN: PT50 0000 0000 0000 0000 0000 0\nMBWAY: 900 000 000\nTens 2 formas de o fazer (sempre que possível através do MBWAY), pedimos apenas que na descrição coloques o nome da reserva em questão! Caso não faças a sinalização nas próximas 48h, a tua reserva irá ser cancelada.\nObrigado e até já!\TUARI";
		return $mensagem;
	}

	private function normalizaParteCodigoPagamento($valor, $fallback = 'X', $maxLen = 4)
	{
		$valor = strtoupper((string) $valor);
		$valor = preg_replace('/[^A-Z0-9]/', '', $valor);
		$valor = substr($valor, 0, $maxLen);

		if ($valor === '') {
			return $fallback;
		}

		return $valor;
	}

	private function mbwayOrderIdExiste($mbwayOrderId)
	{
		$mbwayOrderIdEsc = $this->db->escape_string($mbwayOrderId);
		$query = "SELECT id FROM privados_salas_mesas_disponibilidade WHERE mbway_order_id = '" . $mbwayOrderIdEsc . "' LIMIT 1";
		$res = $this->db->query($query);

		return !empty($res);
	}

	function geraCodigoPagamentoMbway($data_evento, $id_sala, $codigo_mesa, $id_reserva)
	{
		$dataCodigo = date('Ymd', strtotime($data_evento));
		if ($dataCodigo === '19700101') {
			$dataCodigo = date('Ymd');
		}

		$salaCodigo = max(0, intval($id_sala));
		$mesaCodigo = $this->normalizaParteCodigoPagamento($codigo_mesa, 'MESA', 10);
		$reservaCodigo = max(0, intval($id_reserva));

		$mbwayOrderId = $dataCodigo . '-' . $salaCodigo . '-' . $mesaCodigo . '-' . $reservaCodigo;

		return $mbwayOrderId;
	}

    function devolveOcupacaoMesa($id_mesa, $data_evento)
    {
        $query = "SELECT * FROM privados_salas_mesas_ocupacao WHERE id_mesa = $id_mesa AND data_evento = '" . $data_evento . "'  ORDER BY id ASC";
        $res = $this->db->query($query);
        return $res[0];
    }
    function verificaMesaOcupada($id_mesa, $data_evento){
        $query = "SELECT * FROM privados_salas_mesas_ocupacao WHERE id_mesa = $id_mesa AND data_evento = '" . $data_evento . "'  ORDER BY id ASC";
        $res = $this->db->query($query);
        if (!empty($res)) {
            return $res[0];
        }
    }

	function libertaReserva($id_reserva, $id_mesa, $data_evento)
	{
		$id_reserva = intval($id_reserva);
		$id_mesa = intval($id_mesa);
		$data_evento = date('Y-m-d', strtotime($data_evento));

		if ($id_reserva <= 0 || $id_mesa <= 0 || empty($data_evento)) {
			return false;
		}

		$this->db->update('privados_salas_mesas_disponibilidade', array('saiu' => 1), 'id = ' . $id_reserva);
		$this->db->query('DELETE from privados_salas_mesas_ocupacao WHERE data_evento = "' . $data_evento . '" AND id_mesa = "' . $id_mesa . '"');

		return true;
	}

	function libertaReservasMbwayExpiradas($data_evento = false)
	{
		$whereData = '';

		if ($data_evento) {
			$data_evento = date('Y-m-d', strtotime($data_evento));
			$whereData = " AND data_evento = '" . $data_evento . "' ";
		}

		$query = "SELECT id, id_mesa, data_evento FROM privados_salas_mesas_disponibilidade
		WHERE saiu = 0
		$whereData
		AND ((IFNULL(reserva_com_valor_antecipado, 0) = 1) OR (IFNULL(valor_caucao_reserva, 0) > 0))
		AND mbway_data_pedido IS NOT NULL
		AND mbway_response_status_code != '000'
		AND DATE_ADD(mbway_data_pedido, INTERVAL (CASE WHEN mbway_status_code = 'TIMEOUT' THEN 15 ELSE 5 END) MINUTE) <= NOW()";
		$reservas = $this->db->query($query);
		if (empty($reservas)) {
			return 0;
		}

		$total = 0;
		foreach ($reservas as $reserva) {
			if ($this->libertaReserva($reserva['id'], $reserva['id_mesa'], $reserva['data_evento'])) {
				$total++;
			}
		}

		return $total;
	}

	function libertaReservaMbwayExpiradaPorId($id_reserva)
	{
		$id_reserva = intval($id_reserva);
		if ($id_reserva <= 0) {
			return false;
		}

		$query = "SELECT id, id_mesa, data_evento FROM privados_salas_mesas_disponibilidade
		WHERE id = " . $id_reserva . "
		AND saiu = 0
		AND ((IFNULL(reserva_com_valor_antecipado, 0) = 1) OR (IFNULL(valor_caucao_reserva, 0) > 0))
		AND mbway_data_pedido IS NOT NULL
		AND mbway_response_status_code != '000'
		AND DATE_ADD(mbway_data_pedido, INTERVAL (CASE WHEN mbway_status_code = 'TIMEOUT' THEN 15 ELSE 5 END) MINUTE) <= NOW()
		LIMIT 1";

		$reserva = $this->db->query($query);
		if (empty($reserva)) {
			return false;
		}

		return $this->libertaReserva($reserva[0]['id'], $reserva[0]['id_mesa'], $reserva[0]['data_evento']);
	}
}