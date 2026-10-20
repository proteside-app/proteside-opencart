# Protección de checkout Proteside para OpenCart

[English](README.md) · [Português (Brasil)](README.pt-BR.md)

**Descarga:** el paquete para instalar está adjunto a la [versión más reciente](https://github.com/proteside-app/proteside-opencart/releases/latest). Este repositorio lo publica el equipo de Proteside solo para descarga y no acepta contribuciones. Dudas: [team@proteside.com](mailto:team@proteside.com).

Carga la protección client-side de Proteside (PCI DSS 4.0, requisitos 6.4.3 y 11.6.1) en las páginas de pago de una
tienda OpenCart usando tu **clave pública del SDK**. La extensión reproduce la instalación canónica de Proteside: un
bootstrapper inline y síncrono seguido del `shield.js` asíncrono, colocados como **primer contenido del `<head>`**
para que todos los demás scripts de la página se observen desde el primer byte.

Código de la extensión: `proteside` · Versión: 1.0.0 · Bootstrapper incluido: 1.1.3 · Licencia: GPL-3.0-or-later

## Requisitos

| | Paquete OpenCart 3.0 | Paquete OpenCart 4 |
| --- | --- | --- |
| OpenCart | 3.0.2.0 a 3.0.5.1 | 4.0.2.0 o posterior, incluida la 4.1 |
| Archivo | `dist/proteside-opencart-3.ocmod.zip` | `dist/proteside.ocmod.zip` (conserva exactamente este nombre) |
| PHP | 7.0 o posterior (el que use tu OpenCart) | 8.0 o posterior |

Los dos paquetes necesitan HTTPS saliente del servidor a `app.proteside.com` (cURL o `allow_url_fopen`), solo para las
verificaciones de estado, y una cuenta de Proteside con su clave del SDK (`pk_live_…` o `pk_test_…`), que está en el
dashboard en **Configuración → Páginas y Dominios**. ¿Aún no tienes cuenta? Consulta <https://app.proteside.com/plans>.

OpenCart 4.0.0.0 y 4.0.1.x no son compatibles (usan otro separador de ruta y otra API de eventos).

## Instalación

### OpenCart 4.0.2+ / 4.1

1. **Extensiones → Instalador → Subir** y elige `proteside.ocmod.zip`. El nombre del archivo debe seguir siendo
   `proteside.ocmod.zip`: OpenCart 4 lo usa como carpeta de la extensión.
2. En la lista de instalados, pulsa el botón verde **Instalar** de "Protección de checkout Proteside".
3. **Extensiones → Extensiones**, elige el tipo **Módulos**, busca "Protección de checkout Proteside" y pulsa
   **Instalar** (+) y luego **Editar** (lápiz).
4. Pega tu clave del SDK y pulsa **Guardar**. La página se recarga con el estado devuelto por Proteside.

### OpenCart 3.0.x

1. **Extensiones → Instalador → Subir** y elige `proteside-opencart-3.ocmod.zip`.
2. **Extensiones → Extensiones**, elige **Módulos**, busca "Protección de checkout Proteside", pulsa **Instalar** (+)
   y luego **Editar** (lápiz).
3. Pega tu clave del SDK y pulsa **Guardar**.

Sin el instalador (en las dos versiones), copia los archivos por FTP: en 3.0, el contenido de `upload/` a la raíz de
la tienda; en 4.x, el contenido de `proteside.ocmod.zip` a `extension/proteside/`. Después sigue desde **Extensiones
→ Extensiones → Módulos**.

Instalar el módulo registra el evento `proteside` (**Extensiones → Eventos**) y, en OpenCart 4, el cron job
`proteside` (**Extensiones → Cron Jobs**). No desactives el evento: es el que inserta la protección.

**No** hace falta añadir el módulo a un diseño (**Diseño → Diseños**). Si lo añades, no imprime nada.

## Configuración

| Campo | Predeterminado | Qué hace |
| --- | --- | --- |
| Activar protección | activado | Interruptor general. |
| Clave del SDK | — | Clave pública `pk_live_`/`pk_test_` + 32 caracteres hexadecimales. Solo se guarda si el formato es válido. |
| Páginas | checkout, confirmación del pedido | Dónde se insertan las etiquetas (ver abajo). |
| Patrones de URL personalizados | — | Uno por línea, `*` como comodín, comparados con la ruta y la URL de la solicitud. |
| Canal de release | Seguir el dashboard | `auto` usa el canal informado por `/api/sdk/config`; `stable`/`latest` lo fuerzan. |
| Incluir reglas de bloqueo (config inline) | activado | Escribe tus reglas activas y los contenedores GTM permitidos en el bootstrapper (`window.__PROTE_INLINE__`) para que el bloqueo empiece antes de que llegue la configuración remota. |
| Calibración (segundos) | vacío | `data-calibration` opcional (10 a 120). Solo se usa si el SDK no puede cargar su configuración remota. |
| Verificar estado cada | 12 h | 6, 12 o 24 horas. |
| Reverificar después de visitas a la tienda | activado | Revalidación diferida (ver "Revalidación"). Desactívalo si llamas a la URL de cron. |

Botones: **Guardar** guarda la configuración y actualiza el estado; **Verificar estado** lo actualiza sin guardar.

### Páginas

Las páginas se detectan por la ruta de OpenCart (`route`, también definida cuando las URL amigables están activas),
nunca por la URL, salvo en tus patrones personalizados:

| Opción | Rutas |
| --- | --- |
| Checkout (todos los pasos) | `checkout/checkout` y todas las demás rutas `checkout/…` salvo carrito, éxito y fallo; páginas de las extensiones de pago (`extension/payment/…` en 3.0, `extension/<proveedor>/payment/…` en 4.x); extensiones de checkout en una página cuya ruta contiene `checkout` (por ejemplo `checkout/simplecheckout`, `extension/quickcheckout/checkout`, `journal3/checkout`) |
| Confirmación del pedido (gracias) | `checkout/success`, `checkout/failure` (el SDK usa la página de éxito para confirmar pagos con tarjeta) |
| Carrito | `checkout/cart` |
| Tienda y páginas de producto | `common/home`, `product/…` |
| Mi cuenta | `account/…` (salvo las páginas de inicio de sesión), `affiliate/…` |
| Página de inicio de sesión | `account/login`, `account/forgotten`, `account/reset` |
| Toda la tienda | todas las páginas |
| Patrones de URL personalizados | tus patrones, p. ej.: `/finalizar-compra` o `*route=extension/payment/mipago*` |

Los pasos del checkout cargados por AJAX dentro de la página de checkout quedan cubiertos por las etiquetas de esa
página.

### Multitienda

Con más de una tienda aparece un selector **Configuración de** arriba. Cada tienda puede tener su propia clave,
páginas y opciones; una tienda sin configuración propia usa la de la tienda predeterminada. El estado se verifica y
se guarda por tienda, usando la URL de la tienda.

### Tarjeta de estado

Muestra **Activo**, **Período de gracia**, **Suspendido**, **Inactivo**, **Clave inválida**, **Error de conexión** o
**Aún no verificado**, el plan, cuándo fue la última verificación, avisos (cobro en período de gracia, clave inválida,
suscripción no activa, error de conexión, clave registrada en otro dominio) y la decisión: "activa — la protección se
carga en las páginas seleccionadas" o "inactiva — la protección NO se carga".

Reglas de decisión:

| Última respuesta definitiva | API ahora | ¿Inserta la protección? |
| --- | --- | --- |
| activo / gracia | OK | sí (gracia muestra un aviso de cobro) |
| suspendido / inactivo / inválido | OK | no |
| nunca validado | no disponible | no (fail-closed) |
| activo / gracia, último éxito ≤ 48 h | no disponible | sí (tolerancia) |
| activo / gracia, último éxito > 48 h | no disponible | no |

Solo HTTP 200 y 401 cambian la decisión; 429, 5xx y timeouts mantienen la anterior y muestran "Error de conexión".
Una clave registrada en otro dominio es solo un aviso; la protección sigue cargando.

## Revalidación

La extensión nunca llama a Proteside mientras construye una página. El estado se actualiza:

1. **Al guardar** o al pulsar **Verificar estado**.
2. **Después de visitas a la tienda (predeterminado).** Cuando termina el intervalo, la siguiente página de la tienda
   programa una verificación que se ejecuta después de entregar la página. Con PHP-FPM o LiteSpeed la conexión con el
   visitante se cierra antes de empezar la verificación; con otras SAPI (p. ej. Apache mod_php) la página ya se envió
   completa, pero la conexión puede quedar abierta hasta 10 s mientras Proteside responde. Un bloqueo evita
   verificaciones en paralelo.
3. **URL de cron (opcional).** Aparece en **Diagnóstico** con un botón de copiar:

   ```
   https://tu-tienda.example/index.php?route=extension/proteside/module/proteside.cron&token=<token>   (OpenCart 4)
   https://tu-tienda.example/index.php?route=extension/module/proteside/cron&token=<token>             (OpenCart 3.0)
   ```

   Llámala desde el programador del servidor, por ejemplo cada hora: `0 * * * * curl -fsS '<url>' >/dev/null`.
   Verifica todas las tiendas cuyo intervalo terminó y devuelve JSON; añade `&force=1` para verificarlas todas ahora.
   Mantén el token en privado; se genera en la instalación.
4. **Cron job de OpenCart 4.** El módulo registra el job horario `proteside` en **Extensiones → Cron Jobs**. Se ejecuta
   cuando el servidor llama al cron de OpenCart (`index.php?route=cron/cron` o `php cron.php`) y cuando pulsas
   **Ejecutar** en esa página.

## Orden de instalación (por qué importa la posición)

El bootstrapper registra cuántos elementos `<script>` hay antes que él. Si algún script se ejecuta antes, el SDK
informa `INSTALLATION_ORDER_WARNING` y `Proteside.getStatus().installationOrderValid` es `false`; la protección
continúa, con cobertura parcial.

La extensión escucha el evento que renderiza la plantilla del header (`catalog/view/common/header/after`) con orden
9999, así que se ejecuta después de las demás extensiones que modifican el header, e inserta las dos etiquetas justo
después de la etiqueta `<head>` de apertura (después de un `<meta charset>` que venga justo detrás). En los temas
predeterminados de OpenCart 3.0 y 4 eso queda antes de jQuery, de Bootstrap y de cualquier otro script.

**Cómo verificarlo:**

- En el admin, **Diagnóstico → Probar la instalación** renderiza el header de la tienda seleccionada e indica si el
  snippet es el primer script después del `<head>`.
- En la tienda, abre la página de confirmación del pedido o el checkout, abre la consola del navegador y ejecuta
  `Proteside.getStatus()`. `installationOrderValid` debe ser `true`.
- En el código fuente de la página, `<!-- Proteside Start -->` debe aparecer justo después de `<head>` (y de su
  `<meta charset>`), luego el script inline y luego `<script async src="https://cdn.proteside.com/v1/shield.js" …>`.

Nunca instales Proteside a través de Google Tag Manager. Si la tienda usa GTM, Proteside debe ejecutarse antes, y esta
extensión lo garantiza.

## Content Security Policy (CSP)

OpenCart no envía CSP de forma predeterminada. Si tu tienda (o su proxy o CDN) lo hace, permite:

- `script-src`: el bootstrapper inline (hash, nonce o `'unsafe-inline'`) **y** `https://cdn.proteside.com`
- `connect-src`: `https://app.proteside.com` y `https://cdn.proteside.com` (más `'self'`); `shield.js` calcula el hash de los scripts externos, incluido el suyo, con un fetch CORS

Hash del bootstrapper 1.1.3 incluido **sin** reglas inline: `'sha256-t2ypUgwBPt6N82k2gXq5D5ZHIdxOXxhj6ZISJatVbg8='`. Con
reglas inline el script cambia; el hash actual aparece siempre en **Diagnóstico → Hash CSP del script inline** y
cambia cuando cambian tus reglas o contenedores GTM en el dashboard. Si mantener el hash al día no es práctico,
desactiva "Incluir reglas de bloqueo" para conservar el hash publicado.

## Servicios externos / datos enviados

La extensión solo habla con Proteside, solo desde el servidor y solo para verificar el estado (guardar, "Verificar
estado", revalidación diferida, cron). Nunca llama a Proteside mientras construye una página.

| Solicitud | Cuándo | Datos enviados |
| --- | --- | --- |
| `GET https://app.proteside.com/api/sdk/status` | verificaciones de estado | clave del SDK, URL de la tienda (`site`), `platform=opencart`, versión de la extensión; `User-Agent: Proteside-OpenCart/1.0.0; <URL de la tienda>` |
| `GET https://app.proteside.com/api/sdk/config` | después de una verificación positiva | clave del SDK |

En el navegador del visitante, `shield.js` (cargado desde `https://cdn.proteside.com`) envía la telemetría del SDK
(scripts observados, scripts bloqueados, eventos de la página) a `https://app.proteside.com/api/sdk/events`; lo hace
el SDK, no la extensión. La clave del SDK es pública por diseño. La extensión no guarda ningún secreto aparte del
token de su URL de cron.

Términos: <https://proteside.com/termos> · Privacidad: <https://proteside.com/privacidade> · Documentación: <https://docs.proteside.com>

## Desinstalación

**Extensiones → Extensiones → Módulos → Desinstalar** (–) elimina el evento, el cron job (OpenCart 4) y toda la
configuración y el estado guardados por la extensión; la tienda deja de cargar la protección al momento (salvo
páginas en caché). Para eliminar también los archivos, desinstala el paquete en **Extensiones → Instalador**.

## Limitaciones

- Las etiquetas se insertan en la salida de la plantilla del header. Un tema cuya plantilla `common/header` no tenga
  la etiqueta `<head>` de apertura no puede cubrirse; **Probar la instalación** lo detecta.
- Otra extensión que imprima scripts antes de la plantilla del header, o que reescriba la página entera después de
  OpenCart (algunas extensiones de caché o minificación), puede colocar scripts antes de Proteside. **Probar la
  instalación** y `Proteside.getStatus()` lo muestran.
- Las cachés de página completa sirven el HTML que tenían; un cambio de estado se ve cuando la caché expira.
- Los checkouts alojados fuera de OpenCart (páginas de pago en el dominio del proveedor, iframes) no quedan
  cubiertos. En checkouts de una sola página que cambian la URL sin recargar, llama a `Proteside.pageChanged(path)` en
  cada cambio de ruta.
- La extensión no muestra sello. El sello es un `<a>` estático copiado del dashboard (Configuración → Tienda).
- El bootstrapper viene con la extensión y se actualiza con cada versión.
- En OpenCart 4.1.0.4 el ejecutor de línea de comandos `php cron.php` falla antes de llegar a cualquier extensión (no
  carga Twig); usa `index.php?route=cron/cron`, el botón **Ejecutar** o la URL de cron de esta extensión.

## Preguntas frecuentes

**El estado dice "Aún no verificado" después de guardar.** No se pudo verificar la clave (red). Pulsa **Verificar
estado**. La tienda no carga la protección hasta recibir una respuesta positiva (fail-closed).

**El dashboard muestra un aviso de orden de instalación.** Ejecuta **Diagnóstico → Probar la instalación**. Si otro
script va antes, busca una extensión que imprima scripts antes del header o reescriba la página, y comprueba que el
evento `proteside` esté activo en **Extensiones → Eventos**.

**¿La clave tiene que ser secreta?** No. Las claves `pk_` son públicas y aparecen en el HTML de cada página
protegida.

**¿Funciona con URL amigables (SEO)?** Sí. Las páginas se detectan por la ruta, que OpenCart define a partir de la
palabra clave SEO.

**¿Qué versiones se probaron?** OpenCart 3.0.5.0, 4.0.2.3 y 4.1.0.4 en PHP 8.5 con MariaDB; ver `VERIFICATION.md`.

## Soporte

<team@proteside.com> · <https://docs.proteside.com> · Dashboard: <https://app.proteside.com>

## Desarrollo

```sh
php tests/run.php                 # pruebas unitarias (PHP puro, sin OpenCart)
php tools/build.php               # sincroniza lib/, bootstrapper/ y lang/ en los dos paquetes y genera dist/*.ocmod.zip
php tools/build.php --check       # falla si alguna copia de los paquetes está desactualizada
php tools/php70-compat-check.php  # el paquete de OpenCart 3.0 debe ser válido en PHP 7.0
```

`lib/`, `bootstrapper/` y `lang/` son las fuentes; las copias dentro de `opencart-3/` y `opencart-4/` se generan.
`tools/mock-api.php` y `tools/inspect-head.php` sirven para pruebas en staging (ver `VERIFICATION.md`).

## Licencia

GNU General Public License v3.0 o posterior — ver `LICENSE`.
