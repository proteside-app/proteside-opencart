<?php
// Heading
$_['heading_title']           = 'Proteção de checkout Proteside';

// Text
$_['text_extension']          = 'Extensões';
$_['text_description']        = 'Carrega a proteção client-side do Proteside (PCI DSS 4.0, requisitos 6.4.3 e 11.6.1) nas suas páginas de pagamento usando a sua chave pública do SDK.';
$_['text_edit']               = 'Configurações';
$_['text_success']            = 'Configurações salvas.';
$_['text_success_validated']  = 'Configurações salvas e status atualizado.';
$_['text_status_refreshed']   = 'Status atualizado.';
$_['text_section_activation'] = 'Ativação';
$_['text_section_status']     = 'Status da conexão';
$_['text_section_pages']      = 'Onde carregar a proteção';
$_['text_section_options']    = 'Opções';
$_['text_section_advanced']   = 'Avançado';
$_['text_section_diagnostics'] = 'Diagnóstico';
$_['text_default_store']      = 'Loja padrão';
$_['text_store_settings']     = 'Configurações de';
$_['text_store_inherits']     = 'Esta loja ainda não tem configurações próprias e usa as da loja padrão. Salvar aqui cria configurações só para esta loja.';
$_['text_yes']                = 'Sim';
$_['text_no']                 = 'Não';
$_['text_every_hours']        = 'A cada %d horas';
$_['text_channel_auto']       = 'Seguir o dashboard (recomendado)';
$_['text_channel_stable']     = 'stable';
$_['text_channel_latest']     = 'latest';
$_['text_no_account']         = 'Ainda não tem conta?';
$_['text_docs']               = 'Documentação';
$_['text_copied']             = 'Copiado';
$_['text_checking']           = 'Verificando…';
$_['text_never']              = 'nunca';
$_['text_less_minute']        = 'menos de um minuto';
$_['text_minutes']            = '%d min';
$_['text_hours']              = '%d h';
$_['text_days']               = '%d d';

// Status
$_['status_active']           = 'Ativo';
$_['status_grace']            = 'Período de carência';
$_['status_suspended']        = 'Suspenso';
$_['status_inactive']         = 'Inativo';
$_['status_invalid']          = 'Chave inválida';
$_['status_connection_error'] = 'Erro de conexão';
$_['status_unknown']          = 'Ainda não verificado';
$_['text_plan']               = 'Plano: %s';
$_['text_checked_ago']        = 'Verificado há %s';
$_['text_decision']           = 'Proteção nesta loja:';
$_['text_domain_mismatch']    = 'Esta chave pertence a uma loja cadastrada em outro domínio. A proteção continua carregando, mas confira a loja no seu dashboard.';
$_['text_notice_grace']       = 'Sua assinatura Proteside está no período de carência. Regularize a cobrança para manter a proteção ativa.';
$_['text_notice_invalid']     = 'Sua chave do SDK Proteside é inválida, então a proteção não está sendo carregada.';
$_['text_notice_connection']  = 'Não foi possível contatar o Proteside (%s). O último status conhecido é mantido por até 48 horas.';
$_['text_notice_disabled']    = 'A proteção está desativada nas configurações abaixo.';
$_['text_notice_no_key']      = 'Informe a sua chave do SDK para ativar a proteção.';
$_['text_notice_suspended']   = 'Sua assinatura Proteside não está ativa, então a proteção não está sendo carregada. Abra o dashboard para revisar o seu plano.';

// Entry
$_['entry_store']             = 'Loja';
$_['entry_status']            = 'Ativar proteção';
$_['entry_sdk_key']           = 'Chave do SDK';
$_['entry_checkout']          = 'Checkout (todas as etapas)';
$_['entry_order_confirmation'] = 'Confirmação do pedido (obrigado)';
$_['entry_cart']              = 'Carrinho';
$_['entry_shop']              = 'Loja e páginas de produto';
$_['entry_account']           = 'Minha conta';
$_['entry_login']             = 'Página de login';
$_['entry_all_store']         = 'Loja inteira';
$_['entry_custom_urls']       = 'Padrões de URL personalizados (um por linha, * como curinga)';
$_['entry_channel']           = 'Canal de release';
$_['entry_calibration']       = 'Calibração (segundos)';
$_['entry_inline_rules']      = 'Embutir regras de bloqueio na página (config inline)';
$_['entry_interval']          = 'Verificar status a cada';
$_['entry_deferred']          = 'Reverificar após visitas à loja (sem cron)';

// Help
$_['help_sdk_key']            = 'Cole a chave pública do seu dashboard Proteside (Configurações → Páginas e Domínios). Formato: pk_live_ seguido de 32 caracteres hexadecimais. Ela é pública por design.';
$_['help_pages']              = 'Checkout inclui todas as etapas do pedido, as extensões de checkout em uma página e as páginas das suas extensões de pagamento. Confirmação do pedido é a página de sucesso, que o SDK usa para confirmar pagamentos com cartão.';
$_['help_custom_urls']        = 'Comparados com o caminho e a URL da requisição, ex.: /finalizar-compra ou *route=extension/payment/meupagamento*.';
$_['help_calibration']        = 'Só é usado quando a configuração remota não pode ser carregada. Normalmente vale o valor do dashboard.';
$_['help_inline_rules']       = 'Copia as suas regras ativas e os containers GTM permitidos do dashboard para o bootstrapper, para que o bloqueio comece desde o primeiro byte. Atualizado em cada verificação de status.';
$_['help_deferred']           = 'Quando o intervalo termina, a próxima visita à loja agenda uma verificação de status que roda depois que a página foi enviada ao visitante. Desative se você chama a URL de cron pelo seu servidor.';

// Diagnostics
$_['diag_extension_version']  = 'Versão da extensão';
$_['diag_bootstrapper_version'] = 'Versão do bootstrapper embutido';
$_['diag_method']             = 'Método de injeção';
$_['diag_method_value']       = 'evento da view do header, logo após a tag head de abertura';
$_['diag_channel']            = 'Canal em uso';
$_['diag_inline_rules_count'] = 'Regras inline / containers GTM';
$_['diag_csp_hash']           = 'Hash CSP do script inline';
$_['help_csp_hash']           = 'Só é necessário se a sua loja envia um header Content-Security-Policy. Adicione-o ao script-src junto com https://cdn.proteside.com e libere https://app.proteside.com e https://cdn.proteside.com no connect-src. Ele muda sempre que as suas regras ou containers GTM mudam.';
$_['diag_cron_url']           = 'URL de cron (opcional)';
$_['help_cron_url']           = 'Chame esta URL pelo agendador do seu servidor (ex.: a cada hora) para atualizar o status de todas as lojas sem depender de visitas. Mantenha-a privada.';
$_['diag_cron_native']        = 'O cron job "proteside" do OpenCart (Extensões → Cron Jobs) também atualiza o status a cada hora quando o servidor executa o cron.php.';
$_['diag_verify']             = 'Verificar a instalação';
$_['diag_verify_hint']        = 'Abra o seu checkout e rode Proteside.getStatus() no console do navegador. installationOrderValid precisa ser true.';
$_['diag_probe_help']         = 'Renderiza o header da loja selecionada e confere se o snippet do Proteside é o primeiro script depois da tag head de abertura.';

// Probe results
$_['probe_ok']                = 'O snippet do Proteside é o primeiro script depois da tag head de abertura desta loja.';
$_['probe_not_first']         = 'O snippet foi injetado, mas %d script(s) vêm antes dele no head. Outra extensão ou o tema os imprime antes do template do header; o dashboard mostrará um aviso de ordem de instalação.';
$_['probe_no_head']           = 'O template do header do tema ativo não tem a tag head de abertura, então o snippet não pode ser injetado. Fale com o suporte.';
$_['probe_not_injected']      = 'O snippet não foi injetado. Confira se o evento "proteside" está ativo em Extensões → Eventos.';
$_['probe_error']             = 'Não foi possível acessar a loja (%s).';
$_['probe_no_key']            = 'Salve uma chave do SDK válida primeiro.';

// Button
$_['button_save']             = 'Salvar alterações';
$_['button_check_status']     = 'Verificar status';
$_['button_open_dashboard']   = 'Abrir dashboard Proteside';
$_['button_get_proteside']    = 'Contratar Proteside';
$_['button_copy']             = 'Copiar';
$_['button_probe']            = 'Testar a instalação';

// Error
$_['error_permission']        = 'Você não tem permissão para fazer isso.';
$_['error_sdk_key']           = 'O formato da chave do SDK é inválido. Esperado pk_live_ ou pk_test_ seguido de 32 caracteres hexadecimais.';
$_['error_calibration']       = 'A calibração deve ficar vazia ou entre 10 e 120 segundos.';
$_['error_form']              = 'Confira os erros no formulário.';
$_['error_library']           = 'Os arquivos da biblioteca Proteside não foram encontrados. Reinstale a extensão.';

// Decision
$_['diag_decision_active']    = 'ativa — a proteção carrega nas páginas selecionadas.';
$_['diag_decision_inactive']  = 'inativa — a proteção NÃO carrega.';
