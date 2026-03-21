<?php
/**
 * Página de Configuração LUSOPAY
 * 
 * @package  WC_LUSOPAY
 * @category Admin
 * @author   LUSOPAY
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza a página de configurações da LusoPay
 */
function lusopay_render_config_page() {
    
    if (!class_exists('WooCommerce')) {
        echo '<div class="lusopay-notice lusopay-notice-error"><p><strong>O WooCommerce precisa estar ativo para configurar o LusoPay.</strong></p></div>';
        return;
    }

    $integration_file = plugin_dir_path(__FILE__) . 'class-wc-lusopay-integration.php';
    if (file_exists($integration_file)) {
        require_once $integration_file;
    }

    if (!class_exists('WC_LusoPay_Integration')) {
        echo '<div class="lusopay-notice lusopay-notice-error"><p>Classe <code>WC_LusoPay_Integration</code> não encontrada.</p></div>';
        return;
    }

    // Instancia a classe de integração
    $integration = new WC_LusoPay_Integration();

    // Processa o salvamento se o botão for clicado
    if (isset($_POST['lusopay_save_settings'])) {
        check_admin_referer('lusopay-settings-save');
        
        // Processa as opções
        $integration->process_admin_options();
        
        echo '<div class="lusopay-notice lusopay-notice-success"><p><strong>Configurações guardadas com sucesso!</strong></p></div>';
    }

    // Caminho da imagem
     $imgapplepay = plugin_dir_url(__FILE__) . '../imagens/applepay.svg';
      $imggoglepay = plugin_dir_url(__FILE__) . '../imagens/googlepay.png';
       $imgcard = plugin_dir_url(__FILE__) . '../imagens/card.png';
    $img_url = plugin_dir_url(__FILE__) . '../imagens/logo.png';
    
    // Obtém os valores atuais
    $chave = $integration->get_option('chave');
    $nif = $integration->get_option('nif');
    $secret_key = $integration->get_option('secret_key');
    $debug = $integration->get_option('debug');
    $custom_field = $integration->get_option('custom_field');
    $custom_field_2_value = get_option('lusopaygateway_custom_field_2_value');
    			$email_sent = get_option( 'email_sent' );


$teste = "MBWay: " .home_url('/wc-api/WC_Lusopay_MBWAY/?descricao=«descricao»&statuscode=«statuscode»&data=«data»&valor=«valor»&chave=' .$secret_key);





if (isset($_POST['lusopay_callback'])) {

if($integration->get_option('chave')!='9CE4639A-5125-4B5E-8160-0C2DFD98AD8A'){
           		 	$PayShop = home_url('/wc-api/WC_Lusopay_PS/?entidade=«entidade»&referencia=«referencia»&valor=«valor»&chave=' . $secret_key) ;
                   $MBWay = home_url('/wc-api/WC_Lusopay_MBWAY/?descricao=«descricao»&statuscode=«statuscode»&data=«data»&valor=«valor»&chave=' .$secret_key);
              $Multibanco =   home_url('/wc-api/WC_Lusopaygateway/?entidade=«entidade»&referencia=«referencia»&valor=«valor»&chave=' . $secret_key );

$payload = [
    'guid'     => $integration->get_option('chave'),
    'nif' => $integration->get_option('nif'),
     'callback_p2' => $Multibanco,
     'callback_p3' => $Multibanco,
     'callback_p4' => $MBWay,       
    'callback_p5' => $PayShop
];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://services.lusopay.com/callback/callback.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
]);




$response = curl_exec($curl);
curl_close($curl);

// Tenta decodificar o JSON retornado
$data = json_decode($response, true);

// Verifica se veio um erro
if (isset($data['status']) && strtoupper($data['status']) === 'ERROR') {
    // Aviso em vermelho com instruções adicionais
 echo '<div class="lusopay-notice lusopay-notice-error">
        <p><strong>Erro ao atualizar callbacks:</strong> ' . esc_html($data['message']) . '</p>
        <p>Os dados fornecidos parecem estar incorretos. Verifique no seu perfil na área de clientes LUSOPAY os dados corretos antes de tentar novamente. 
        <a href="https://app.lusopay.com:8443/web" target="_blank">Clique aqui para aceder à área de clientes</a>.</p>
      </div>';
} elseif (isset($data['status']) && strtoupper($data['status']) === 'OK') {
    // Mensagem de sucesso
    echo '<div class="lusopay-notice lusopay-notice-success">
            <p><strong>Callbacks atualizados com sucesso!</strong></p>
          </div>';
} else {
    // Caso inesperado
   echo '<div class="lusopay-notice lusopay-notice-error">
        <p><strong>Erro ao tentar atualizar callbacks:</strong></p>
        <p>Tente novamente mais tarde ou configure manualmente os callbacks no menu pessoal da área de clientes da LUSOPAY. 
        <a href="https://app.lusopay.com:8443/web" target="_blank">Clique aqui para aceder à área de clientes</a>.</p>
        <p>URLs de callback:</p>
        <ul>
            <li>Multibanco: <code>' . esc_html(home_url('/wc-api/WC_Lusopaygateway/?entidade=«entidade»&referencia=«referencia»&valor=«valor»&chave=' . $secret_key)) . '</code></li>
            <li>MBWay: <code>' . esc_html(home_url('/wc-api/WC_Lusopay_MBWAY/?descricao=«descricao»&statuscode=«statuscode»&data=«data»&valor=«valor»&chave=' . $secret_key)) . '</code></li>
            <li>PayShop: <code>' . esc_html(home_url('/wc-api/WC_Lusopay_PS/?entidade=«entidade»&referencia=«referencia»&valor=«valor»&chave=' . $secret_key)) . '</code></li>
        </ul>
      </div>';
}






        $dt = new DateTime("now", new DateTimeZone('Europe/Lisbon'));
        update_option('lusopaygateway_custom_field_2_value', $dt->format('d-m-Y, H:i:s'));
        }else{

         echo '<div class="lusopay-notice lusopay-notice-error">
        <p><strong>Erro ao tentar atualizar callbacks:</strong></p>
        <p>Não existe callbacks para dados teste</p>
        
      </div>';
      }
		}



    ?>
    
   <div class="lusopay-wrap">
    <div class="lusopay-header">
        <h1><?php esc_html_e('Configurações LUSOPAY', 'lusopaygateway'); ?></h1>
    </div>

    <div class="lusopay-content-container">

        <div class="lusopay-content">

            <!-- Sidebar Esquerda: Novidades & Informações -->
<div class="lusopay-sidebar lusopay-sidebar-left">
    <h2><?php esc_html_e('Novidades & Informações', 'lusopaygateway'); ?></h2>
    <ul class="lusopay-info-list">
        <li>Última atualização do plugin: <?php echo esc_html(WC_Lusopay::VERSION); ?></li>
        <li>Agora totalmente compatível com Blocos (WooCommerce Blocks).</li>
        <li>Foram corrigidas algumas incompatibilidades, tornando o plugin mais eficiente. Agora é possível enviar ou atualizar os URLs de callback para notificações em tempo real.</li>
        
        <!-- Novos métodos de pagamento em destaque sem fundo, imagens abaixo -->
        <li style="font-weight: bold; margin-top: 10px;">
            Novos métodos de pagamento disponíveis em breve.
        </li>
        <li style="margin-top: 5px;">
            Pode solicitar a ativação antecipada destes métodos através da sua Área de Clientes da LusoPay.
        </li>
        <li style="margin-top: 10px;">
            <img src="<?php echo esc_url($imgapplepay); ?>" alt="Apple Pay" style="height:32px; vertical-align:middle; margin-right:10px;">
            <img src="<?php echo esc_url($imggoglepay); ?>" alt="Google Pay" style="height:32px; vertical-align:middle; margin-right:10px;">
            <img src="<?php echo esc_url($imgcard); ?>" alt="Cartões de crédito/débito" style="height:32px; vertical-align:middle;">
        </li>

        <!-- Link para Área de Clientes -->
        <li style="margin-top: 10px; font-weight: bold;">
            Acesse a sua <a href="https://app.lusopay.com:8443/web" target="_blank" style="color:#0073aa;">Área de Clientes LusoPay</a> para solicitar a ativação dos novos métodos.
        </li>
    </ul>

    <div class="lusopay-divider"></div>

    <p><strong>Nota:</strong> Estes métodos serão oficialmente integrados numa atualização futura do plugin.</p>
</div>




            
            <!-- Coluna Central: Formulário -->
            <div class="lusopay-form-column">
                <form method="post" action="">
                    <?php wp_nonce_field('lusopay-settings-save'); ?>
                    
                    <table class="lusopay-form-table">
                        <tbody>
                            <!-- ClientGuid -->
                            <tr>
                                <th>
                                    <label for="lusopay_chave">
                                        <?php esc_html_e('ClientGuid', 'lusopaygateway'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input 
                                        type="text" 
                                        id="lusopay_chave"
                                        name="woocommerce_multibanco-e-ou-payshop-by-lusopay_chave" 
                                        class="lusopay-input" 
                                        value="<?php echo esc_attr($chave); ?>"
                                    />
                                    <span class="lusopay-description">
    <?php esc_html_e('O ClientGuid é fornecida pela LUSOPAY por email após aprovação da conta.', 'lusopaygateway'); ?>
</span>
                                </td>
                            </tr>

                            <!-- VatNumber -->
                            <tr>
                                <th>
                                    <label for="lusopay_nif">
                                        <?php esc_html_e('VatNumber', 'lusopaygateway'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input 
                                        type="text" 
                                        id="lusopay_nif"
                                        name="woocommerce_multibanco-e-ou-payshop-by-lusopay_nif" 
                                        class="lusopay-input" 
                                        value="<?php echo esc_attr($nif); ?>"
                                    />
                                    <span class="lusopay-description">
                                        <?php esc_html_e('Está no email que recebe quando ativa o serviço.', 'lusopaygateway'); ?>
                                    </span>
                                </td>
                            </tr>

                            <!-- Anti-phishing Key -->
                            <tr>
                                <th>
                                    <label>
                                        <?php esc_html_e('Chave Anti-phishing', 'lusopaygateway'); ?>
                                    </label>
                                </th>
                                <td>
                                    <span class="lusopay-secret-key" id="lusopaygateway_secret_key_label">
                                        <?php echo esc_html($secret_key); ?>
                                    </span>
                                    <input 
                                        type="hidden" 
                                        name="woocommerce_multibanco-e-ou-payshop-by-lusopay_secret_key" 
                                        value="<?php echo esc_attr($secret_key); ?>"
                                    />
                                    <span class="lusopay-description">
                                        <?php esc_html_e('Esta chave é gerada automaticamente e deve ser usada nos callbacks.', 'lusopaygateway'); ?>
                                    </span>
                                </td>
                            </tr>

                            <!-- Debug Log -->
                            <tr>
                                <th>
                                    <label for="lusopay_debug">
                                        <?php esc_html_e('Debug Log', 'lusopaygateway'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="lusopay-checkbox-wrapper">
                                        <input 
                                            type="checkbox" 
                                            id="lusopay_debug"
                                            name="woocommerce_multibanco-e-ou-payshop-by-lusopay_debug" 
                                            class="lusopay-checkbox"
                                            value="yes"
                                            <?php checked($debug, 'yes'); ?>
                                        />
                                        <label for="lusopay_debug" class="lusopay-checkbox-label">
                                            <?php esc_html_e('Ativar logging', 'lusopaygateway'); ?>
                                        </label>
                                    </div>
                                    <span class="lusopay-description">
                                        <?php 
                                        printf(
                                            esc_html__('Registar eventos do plugin, como pedidos de callback, dentro de %s', 'lusopaygateway'),
                                            '<code>wc-logs/multibanco-e-ou-payshop-by-lusopay</code>'
                                        ); 
                                        ?>
                                    </span>
                                </td>
                            </tr>

                            <!-- Send Email -->
                           

                        </tbody>
                    </table>

                    <div class="lusopay-divider"></div>

                    <!-- Botões lado a lado -->
                    <table class="lusopay-form-table">
    <tbody>
        <tr>
            <!-- Botão Guardar Alterações (lado esquerdo) -->
            <td style="text-align: left;">
                <button type="submit" name="lusopay_save_settings" class="lusopay-button-primary">
                    <?php esc_html_e('Guardar Alterações', 'lusopaygateway'); ?>
                </button>
            </td>

            <!-- Botão Atualizar Callbacks (lado direito) -->
            <td style="text-align: right;">
                <?php if($email_sent==true){ ?>
                    <button type="submit" name="lusopay_callback" class="lusopay-button-primary">
                        <?php esc_html_e('Atualizar callbacks', 'lusopaygateway'); ?>
                    </button>
                    <span class="lusopay-description" style="display:block; margin-top:5px;">
                        <strong>Data do último envio:</strong> <?php echo esc_html($custom_field_2_value); ?>
                    </span>
                <?php } ?>
            </td>
        </tr>
    </tbody>
</table>



                </form>
            </div>

            <!-- Sidebar Direita -->
            <div class="lusopay-sidebar lusopay-sidebar-right">
                <img src="<?php echo esc_url($img_url); ?>" alt="Lusopay" class="lusopay-logo">
                
                <p class="lusopay-sidebar-text">
                    Integração oficial da <strong>LUSOPAY</strong> para WooCommerce.
                </p>
                
                <p class="lusopay-sidebar-text">
                    <a href="https://app.lusopay.com:8443/web/#system.wizards.run!customWizardId=-1347720241550422566" target="_blank" class="lusopay-sidebar-link">
                        Abrir conta na LUSOPAY
                    </a>
                </p>
                
    <p class="lusopay-sidebar-text">
    Para dúvidas técnicas, envie um e-mail para 
    <a href="mailto:it1@lusopay.com,it2@lusopay.com,dev@lusopay.com" class="lusopay-sidebar-link">
        it1@lusopay.com, it2@lusopay.com, dev@lusopay.com
    </a><br>
    Para assuntos comerciais, envie para 
    <a href="mailto:comercial@lusopay.com" class="lusopay-sidebar-link">comercial@lusopay.com</a><br>
    Para questões gerais, envie para <br>
    <a href="mailto:geral@lusopay.com" class="lusopay-sidebar-link">geral@lusopay.com</a>
</p>
                
                <p class="lusopay-sidebar-text" style="font-size: 12px; color: #999;">
                    Versão <?php echo esc_html(WC_Lusopay::VERSION); ?>
                </p>
            </div>

        </div>
    </div>
</div>

    
    <?php



	

}

/**
 * Adiciona submenu no WooCommerce
 */
function lusopay_add_woocommerce_submenu() {
    add_submenu_page(
        'woocommerce',
        'Configurações Lusopay',
        'Lusopay',
        'manage_woocommerce',
        'lusopay-config',
        'lusopay_render_config_page'
    );
}
add_action('admin_menu', 'lusopay_add_woocommerce_submenu', 99);

/**
 * Carrega os estilos CSS personalizados
 */
function lusopay_load_admin_styles($hook) {
    // Apenas carrega na página de configuração do Lusopay
    if ($hook !== 'woocommerce_page_lusopay-config') {
        return;
    }
    
    wp_enqueue_style(
        'lusopay-admin-styles',
        plugin_dir_url(__FILE__) . '../assets/css/lusopay-admin-styles.css',
        array(),
        WC_Lusopay::VERSION
    );
}
add_action('admin_enqueue_scripts', 'lusopay_load_admin_styles');


	