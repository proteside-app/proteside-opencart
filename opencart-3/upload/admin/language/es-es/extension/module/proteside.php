<?php
// Heading
$_['heading_title']           = 'Protección de checkout Proteside';

// Text
$_['text_extension']          = 'Extensiones';
$_['text_description']        = 'Carga la protección client-side de Proteside (PCI DSS 4.0, requisitos 6.4.3 y 11.6.1) en tus páginas de pago usando tu clave pública del SDK.';
$_['text_edit']               = 'Configuración';
$_['text_success']            = 'Configuración guardada.';
$_['text_success_validated']  = 'Configuración guardada y estado actualizado.';
$_['text_status_refreshed']   = 'Estado actualizado.';
$_['text_section_activation'] = 'Activación';
$_['text_section_status']     = 'Estado de la conexión';
$_['text_section_pages']      = 'Dónde cargar la protección';
$_['text_section_options']    = 'Opciones';
$_['text_section_advanced']   = 'Avanzado';
$_['text_section_diagnostics'] = 'Diagnóstico';
$_['text_default_store']      = 'Tienda predeterminada';
$_['text_store_settings']     = 'Configuración de';
$_['text_store_inherits']     = 'Esta tienda aún no tiene configuración propia y usa la de la tienda predeterminada. Guardar aquí crea una configuración solo para esta tienda.';
$_['text_yes']                = 'Sí';
$_['text_no']                 = 'No';
$_['text_every_hours']        = 'Cada %d horas';
$_['text_channel_auto']       = 'Seguir el dashboard (recomendado)';
$_['text_channel_stable']     = 'stable';
$_['text_channel_latest']     = 'latest';
$_['text_no_account']         = '¿Aún no tienes cuenta?';
$_['text_docs']               = 'Documentación';
$_['text_copied']             = 'Copiado';
$_['text_checking']           = 'Verificando…';
$_['text_never']              = 'nunca';
$_['text_less_minute']        = 'menos de un minuto';
$_['text_minutes']            = '%d min';
$_['text_hours']              = '%d h';
$_['text_days']               = '%d d';

// Status
$_['status_active']           = 'Activo';
$_['status_grace']            = 'Período de gracia';
$_['status_suspended']        = 'Suspendido';
$_['status_inactive']         = 'Inactivo';
$_['status_invalid']          = 'Clave inválida';
$_['status_connection_error'] = 'Error de conexión';
$_['status_unknown']          = 'Aún no verificado';
$_['text_plan']               = 'Plan: %s';
$_['text_checked_ago']        = 'Verificado hace %s';
$_['text_decision']           = 'Protección en esta tienda:';
$_['text_domain_mismatch']    = 'Esta clave pertenece a una tienda registrada en otro dominio. La protección sigue cargando, pero revisa la tienda en tu dashboard.';
$_['text_notice_grace']       = 'Tu suscripción de Proteside está en el período de gracia. Regulariza el pago para mantener la protección activa.';
$_['text_notice_invalid']     = 'Tu clave del SDK de Proteside no es válida, por lo que la protección no se está cargando.';
$_['text_notice_connection']  = 'No se pudo contactar con Proteside (%s). El último estado conocido se mantiene hasta 48 horas.';
$_['text_notice_disabled']    = 'La protección está desactivada en la configuración de abajo.';
$_['text_notice_no_key']      = 'Introduce tu clave del SDK para activar la protección.';
$_['text_notice_suspended']   = 'Tu suscripción de Proteside no está activa, por lo que la protección no se está cargando. Abre el dashboard para revisar tu plan.';

// Entry
$_['entry_store']             = 'Tienda';
$_['entry_status']            = 'Activar protección';
$_['entry_sdk_key']           = 'Clave del SDK';
$_['entry_checkout']          = 'Checkout (todos los pasos)';
$_['entry_order_confirmation'] = 'Confirmación del pedido (gracias)';
$_['entry_cart']              = 'Carrito';
$_['entry_shop']              = 'Tienda y páginas de producto';
$_['entry_account']           = 'Mi cuenta';
$_['entry_login']             = 'Página de inicio de sesión';
$_['entry_all_store']         = 'Toda la tienda';
$_['entry_custom_urls']       = 'Patrones de URL personalizados (uno por línea, * como comodín)';
$_['entry_channel']           = 'Canal de release';
$_['entry_calibration']       = 'Calibración (segundos)';
$_['entry_inline_rules']      = 'Incluir reglas de bloqueo en la página (config inline)';
$_['entry_interval']          = 'Verificar estado cada';
$_['entry_deferred']          = 'Reverificar después de visitas a la tienda (sin cron)';

// Help
$_['help_sdk_key']            = 'Pega la clave pública de tu dashboard de Proteside (Configuración → Páginas y Dominios). Formato: pk_live_ seguido de 32 caracteres hexadecimales. Es pública por diseño.';
$_['help_pages']              = 'Checkout incluye todos los pasos del pedido, las extensiones de checkout en una página y las páginas de tus extensiones de pago. Confirmación del pedido es la página de éxito, que el SDK usa para confirmar pagos con tarjeta.';
$_['help_custom_urls']        = 'Se comparan con la ruta y la URL de la solicitud, p. ej.: /finalizar-compra o *route=extension/payment/mipago*.';
$_['help_calibration']        = 'Solo se usa cuando la configuración remota no puede cargarse. Normalmente se aplica el valor del dashboard.';
$_['help_inline_rules']       = 'Copia tus reglas activas y los contenedores GTM permitidos del dashboard al bootstrapper para que el bloqueo empiece desde el primer byte. Se actualiza en cada verificación de estado.';
$_['help_deferred']           = 'Cuando termina el intervalo, la siguiente visita a la tienda programa una verificación de estado que se ejecuta después de enviar la página al visitante. Desactívalo si llamas a la URL de cron desde tu servidor.';

// Diagnostics
$_['diag_extension_version']  = 'Versión de la extensión';
$_['diag_bootstrapper_version'] = 'Versión del bootstrapper incluido';
$_['diag_method']             = 'Método de inyección';
$_['diag_method_value']       = 'evento de la vista del header, justo después de la etiqueta head de apertura';
$_['diag_channel']            = 'Canal en uso';
$_['diag_inline_rules_count'] = 'Reglas inline / contenedores GTM';
$_['diag_csp_hash']           = 'Hash CSP del script inline';
$_['help_csp_hash']           = 'Solo es necesario si tu tienda envía un header Content-Security-Policy. Añádelo a script-src junto con https://cdn.proteside.com y permite https://app.proteside.com y https://cdn.proteside.com en connect-src. Cambia cada vez que cambian tus reglas o contenedores GTM.';
$_['diag_cron_url']           = 'URL de cron (opcional)';
$_['help_cron_url']           = 'Llama a esta URL desde el programador de tu servidor (p. ej. cada hora) para actualizar el estado de todas las tiendas sin depender de las visitas. Mantenla privada.';
$_['diag_cron_native']        = 'El cron job "proteside" de OpenCart (Extensiones → Cron Jobs) también actualiza el estado cada hora cuando el servidor ejecuta cron.php.';
$_['diag_verify']             = 'Verificar la instalación';
$_['diag_verify_hint']        = 'Abre tu checkout y ejecuta Proteside.getStatus() en la consola del navegador. installationOrderValid debe ser true.';
$_['diag_probe_help']         = 'Renderiza el header de la tienda seleccionada y comprueba que el snippet de Proteside es el primer script después de la etiqueta head de apertura.';

// Probe results
$_['probe_ok']                = 'El snippet de Proteside es el primer script después de la etiqueta head de apertura de esta tienda.';
$_['probe_not_first']         = 'El snippet está inyectado, pero %d script(s) aparecen antes en el head. Otra extensión o el tema los imprime antes de la plantilla del header; el dashboard mostrará un aviso de orden de instalación.';
$_['probe_no_head']           = 'La plantilla del header del tema activo no tiene la etiqueta head de apertura, así que el snippet no puede inyectarse. Contacta con soporte.';
$_['probe_not_injected']      = 'El snippet no se inyectó. Comprueba que el evento "proteside" esté activo en Extensiones → Eventos.';
$_['probe_error']             = 'No se pudo acceder a la tienda (%s).';
$_['probe_no_key']            = 'Guarda primero una clave del SDK válida.';

// Button
$_['button_save']             = 'Guardar cambios';
$_['button_check_status']     = 'Verificar estado';
$_['button_open_dashboard']   = 'Abrir dashboard de Proteside';
$_['button_get_proteside']    = 'Contratar Proteside';
$_['button_copy']             = 'Copiar';
$_['button_probe']            = 'Probar la instalación';

// Error
$_['error_permission']        = 'No tienes permiso para hacer esto.';
$_['error_sdk_key']           = 'El formato de la clave del SDK no es válido. Se espera pk_live_ o pk_test_ seguido de 32 caracteres hexadecimales.';
$_['error_calibration']       = 'La calibración debe quedar vacía o entre 10 y 120 segundos.';
$_['error_form']              = 'Revisa los errores del formulario.';
$_['error_library']           = 'No se encontraron los archivos de la biblioteca de Proteside. Reinstala la extensión.';

// Decision
$_['diag_decision_active']    = 'activa — la protección se carga en las páginas seleccionadas.';
$_['diag_decision_inactive']  = 'inactiva — la protección NO se carga.';
