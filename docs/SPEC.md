# SPEC: Costa Rica Kit for WooCommerce

> Documento de especificación para Claude Code. Guardalo en la raíz del repo como `docs/SPEC.md`.
> Estado del ecosistema verificado al 31 de octubre de 2026.

---

## 0. Cómo trabajar con este documento (léelo primero)

Sos el único implementador de este plugin. El dueño del proyecto (Ariel Orozco, dev full-stack senior, 18 años de PHP) revisa cada fase con un segundo revisor. Reglas de trabajo:

1. **Trabajá por fases** (sección 9). Al terminar cada fase, **detenete** y entregá el reporte de fase (sección 11). No empieces la siguiente fase sin aprobación explícita.
2. **Antes de escribir código en la Fase 0**, creá un `CLAUDE.md` en la raíz con las reglas permanentes de las secciones 4, 5 y 6 (resumidas) para que persistan entre sesiones.
3. **No inventes formas de APIs externas.** Si una respuesta de API no está confirmada en este documento, hacé una petición real para verificarla. Si no tenés red, escribí el adaptador contra un fixture, marcá la duda en el reporte y seguí.
4. **No agregues dependencias de runtime** (Composer o npm) sin preguntar. Las dependencias de desarrollo (phpcs, phpstan, phpunit, @wordpress/scripts, etc.) están permitidas.
5. **Commits pequeños y frecuentes**, formato Conventional Commits (`feat(sinpe): ...`, `test(rates): ...`, `chore(ci): ...`).
6. Si algo en este documento contradice la documentación oficial vigente de WordPress o WooCommerce, **gana la documentación oficial**: aplicala y reportá la discrepancia.
7. Ante una decisión de diseño no cubierta aquí, elegí la opción más simple y estándar del ecosistema WP/WC, documentala en `docs/DECISIONS.md` (formato ADR corto) y mencionala en el reporte.

### Skills recomendadas
Si están disponibles, instalá las skills oficiales de WordPress para agentes antes de empezar:

```bash
npx skills add WordPress/agent-skills --list
npx skills add WordPress/agent-skills --agent claude-code \
  --skill wp-plugin-development \
  --skill wp-plugin-directory-guidelines
```
(Agregá otras relevantes de la lista, por ejemplo una de PHPStan o Playground, si existen.)

---

## 1. Contexto y objetivo

Plugin gratuito para el directorio de WordPress.org que reúne en un solo paquete bien mantenido las necesidades locales de una tienda WooCommerce en Costa Rica. Hoy esas necesidades están cubiertas por plugins dispersos, varios desactualizados o abandonados:

- **SINPE Móvil:** el plugin más usado no se ha probado con las últimas versiones mayores de WordPress. Sus usuarios piden en reseñas: subir comprobante de pago y mostrar el monto correcto en colones cuando la tienda está en dólares.
- **Provincia / cantón / distrito:** el plugin de referencia tiene un solo mantenedor y figura como desactualizado.
- **Tipo de cambio BCCR:** el plugin existente está prácticamente abandonado.
- **Factura electrónica:** cada proveedor (GTI, Facturador Virtual, etc.) tiene su propio plugin atado a su servicio. **Fuera de alcance.**

Objetivos del proyecto, en orden:
1. Calidad de portafolio: código que un revisor senior de WooCommerce apruebe sin reparos. Esto pesa más que la cantidad de features.
2. Aprobación en el directorio de WordPress.org al primer intento.
3. Arquitectura que permita una versión Pro futura **sin** incluir upsells, bloqueos ni código Pro ahora.

---

## 2. Identidad del plugin

Nombre provisional (Ariel puede cambiarlo antes de la Fase 0):

| Campo | Valor |
|---|---|
| Plugin Name | Costa Rica Kit for WooCommerce |
| Slug / carpeta / text domain | `costa-rica-kit-for-woocommerce` |
| Prefijo global (funciones, opciones, hooks, meta, handles) | `crkit_` / `crkit-` |
| Namespace PHP | `CRKit\` |
| Autor | Ariel Orozco |
| Licencia | GPL-2.0-or-later |

Nota de marca: el nombre no puede **empezar** con "WooCommerce" ni "Woo"; el sufijo "for WooCommerce" sí es aceptable.

### Cabecera del archivo principal

```
Plugin Name:          Costa Rica Kit for WooCommerce
Description:          SINPE Móvil, tipo de cambio, provincias/cantones/distritos y cédula para tiendas WooCommerce en Costa Rica.
Version:              0.1.0
Requires at least:    6.8
Requires PHP:         8.1
Requires Plugins:     woocommerce
Author:               Ariel Orozco
License:              GPL-2.0-or-later
License URI:          https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:          costa-rica-kit-for-woocommerce
Domain Path:          /languages
WC requires at least: (definir en Fase 0, mínimo 9.0)
WC tested up to:      (la última estable al momento de la release)
```

Justificación de versiones:
- WordPress actual: **7.1** (publicado el 19 de agosto de 2026). WordPress 7.0 subió el mínimo de PHP a 7.4.
- WooCommerce actual: **11.2.x**, con 11.3 por salir. WooCommerce **11.6 exigirá PHP 8.1+**, por eso este plugin arranca directamente en PHP 8.1. La recomendación oficial de PHP es 8.3+.
- La página de WooCommerce en WordPress.org exige WordPress 6.8+, por eso `Requires at least: 6.8`.
- `Version`, la constante `CRKIT_VERSION` y `Stable tag` en `readme.txt` deben coincidir siempre.

---

## 3. Alcance funcional

Cuatro módulos que el comerciante puede activar o desactivar por separado en **WooCommerce → Ajustes → Costa Rica** (una pestaña propia con secciones, usando la Settings API de WooCommerce).

### Módulo A: Pasarela SINPE Móvil

Método de pago manual (estilo BACS). El cliente paga desde su app bancaria y la tienda confirma a mano.

**Ajustes de la pasarela** (`WC_Payment_Gateway`, id `crkit_sinpe_movil`):
- Título, descripción, instrucciones extra (texto libre).
- Número SINPE Móvil del comercio: validar 8 dígitos de Costa Rica, aceptar con o sin guion o espacios y guardar normalizado.
- Nombre del titular (se muestra al cliente para que confirme a quién paga).
- Exigir comprobante (sí/no, default sí).
- Monto máximo por pedido en CRC (vacío = sin límite). Si el total en colones lo supera, el método no se ofrece. En la ayuda del campo, indicar que el BCCR limita SINPE por SMS a ₡100.000 diarios y que el tope en canales autenticados depende de cada banco (en general hasta ₡500.000).
- Tipo de cambio a usar si la tienda no está en CRC: venta (default) o compra, más un recargo % opcional.

**Flujo del pedido:**
1. `process_payment()`: estado `on-hold`, `wc_reduce_stock_levels()`, vaciar el carrito y nota en el pedido.
2. Calcular y **congelar en el pedido**: monto en CRC (redondeado hacia arriba a colones enteros), tipo de cambio usado, fuente y fecha del tipo de cambio, y una referencia corta (por ejemplo, el número de pedido) para que el cliente la ponga en la descripción del SINPE.
3. Página de "pedido recibido" y correo `customer_on_hold_order`: número SINPE, titular, **monto exacto en colones**, referencia y pasos claros. Si se exige comprobante, mostrar un formulario de carga.
4. **Carga de comprobante** en la página de pedido recibido **y** en Mi cuenta → Ver pedido, mientras el pedido esté `on-hold`. Debe funcionar igual con el checkout clásico y el de bloques, porque ocurre **después** del checkout.
   - Tipos permitidos: JPG, PNG, WEBP y PDF. Validar con `wp_check_filetype_and_ext()` y comprobar el MIME real. Tamaño máximo configurable (default 5 MB).
   - Guardar fuera de la Biblioteca de Medios, en `wp-content/uploads/crkit-receipts/` con nombre aleatorio no adivinable, más `index.php` vacío y `.htaccess` con deny. **No** depender del `.htaccess`, porque Nginx lo ignora: los archivos se sirven **solo** a través de un endpoint PHP autenticado (`admin-post.php` o REST) que verifica la capability `edit_shop_orders` o que el usuario sea el dueño del pedido, más un nonce.
   - Verificar que quien sube el archivo sea el dueño del pedido: usuario logueado dueño, o invitado con la `order_key` válida.
   - Al subir: nota en el pedido y correo al administrador (registrar un email propio de WooCommerce que extienda `WC_Email`, para que sea configurable en WooCommerce → Ajustes → Correos).
5. **Admin del pedido:** metabox compatible con HPOS que muestre monto CRC, tipo de cambio congelado, referencia, vista o descarga del comprobante, y botones "Confirmar pago" (pasa a `processing` y llama a `payment_complete()`) y "Rechazar pago" (pasa a `failed` o `cancelled`, configurable). Ambos con nonce y comprobación de capability.
6. Reembolsos: no soportados por la pasarela (son manuales); no declarar `refunds` en `supports`.

**Checkout de bloques:**
- Clase que extienda `Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType`, registrada en `woocommerce_blocks_payment_method_type_registration`.
- Script cliente con `registerPaymentMethod()` (desde `wc.wcBlocksRegistry`), compilado con `@wordpress/scripts`. Usar `@woocommerce/dependency-extraction-webpack-plugin` si aplica, para no empaquetar dependencias de WooCommerce.
- `get_payment_method_data()` envía título, descripción, ícono y el **monto estimado en CRC** cuando la tienda no está en colones.
- `canMakePayment` respeta el monto máximo y la disponibilidad del tipo de cambio.
- Declarar `cart_checkout_blocks` como compatible.

### Módulo B: Tipo de cambio

Servicio interno que usan los otros módulos. Además expone un shortcode en la Fase 2.

**Proveedores** (interfaz `RateProviderInterface` con implementaciones intercambiables):

1. **Ministerio de Hacienda (default, sin credenciales):** `GET https://api.hacienda.go.cr/indicadores/tc`. Devuelve compra y venta del dólar (y del euro). Forma de respuesta esperada: `dolar.compra.valor`, `dolar.venta.valor`, cada uno con `fecha`. **Verificá la forma real con una petición** antes de escribir el parser y guardá esa respuesta como fixture de test.

2. **BCCR, API SDDE (opcional, requiere token del comerciante):**
   - En 2026 el BCCR **discontinuó el viejo servicio web SOAP** (`wsindicadoreseconomicos.asmx`) a favor del nuevo sistema SDDE. **No implementes el SOAP viejo.**
   - Base: `https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API`
   - Autenticación: `Authorization: Bearer <token>`. El token se genera desde el sitio de Indicadores Económicos del BCCR (Mi perfil → Generar token). Solo HTTPS, TLS 1.2+.
   - Serie: `GET /indicadoresEconomicos/{codigo}/series?fechaInicio=yyyy/mm/dd&fechaFin=yyyy/mm/dd&idioma=ES` (las barras de las fechas van URL-encoded).
   - Respuesta: `{ "estado": true, "mensaje": "...", "datos": [ { "codigoIndicador": "317", "nombreIndicador": "...", "series": [ { "fecha": "2026-10-30", "valorDatoPorPeriodo": 462.1 } ] } ] }`. `valorDatoPorPeriodo` puede venir `null`.
   - Códigos: **317 = compra**. La venta tradicionalmente es **318**, pero la documentación de SDDE no lo menciona: verificalo con el endpoint de metadata antes de usarlo y reportá el resultado.
   - Errores: 400, 401 (token inválido o vencido), 403 (sin suscripción), 404, 429 (exceso de solicitudes, bajar la frecuencia), 500. Mapear cada uno a un mensaje claro en el admin.
   - Hay inconsistencias de mayúsculas en la doc oficial (`Bccr.Ge` vs `Bccr.GE`) y un host alterno de Azure en un ejemplo. Usá `apim.bccr.fi.cr`, centralizá la URL en una constante filtrable y reportá qué variante funcionó.

3. **Manual:** el comerciante fija el valor. Tiene prioridad si está activo.

**Comportamiento:**
- Caché en una opción (`crkit_exchange_rate`, sin autoload) con valor de compra, valor de venta, fecha del dato, fuente y fecha de consulta.
- Refresco programado con **Action Scheduler** (viene incluido en WooCommerce) dos veces al día, más un botón "Actualizar ahora" en ajustes (con nonce).
- **Nunca** hacer llamadas externas durante una petición del checkout o del frontend. Siempre se lee la caché.
- `wp_remote_get()` con timeout corto (por ejemplo 10 s), sin desactivar la verificación SSL, y con un user-agent que identifique al plugin y su versión.
- Fallback: si falla el proveedor, se conserva el último valor bueno. Si el valor tiene más de N horas (configurable, default 48) y no hay tipo manual, se muestra un aviso en el admin y SINPE deja de ofrecerse **solo** en tiendas que no están en CRC.
- API interna: `crkit_get_exchange_rate( 'sell' | 'buy' )` y `crkit_convert_to_crc( float $amount, string $currency )`, con filtros para extender.

### Módulo C: Provincia, cantón y distrito (Fase 2)

- Revisá primero qué trae WooCommerce para Costa Rica (`WC()->countries->get_states( 'CR' )` y el formato de dirección del país). Si las provincias ya vienen en el core, reutilizalas y no las dupliques.
- Datos: JSON en `data/cr-locations.json` con códigos oficiales (provincia 1 dígito, cantón 2, distrito 2; el código postal de 5 dígitos se arma igual). La **fuente oficial** es la División Territorial Administrativa 2025 (Decreto Ejecutivo 44882-MGP, Alcance 12 a La Gaceta 17 del 28 de enero de 2025, publicado por el SNIT). Costa Rica tiene **7 provincias y 84 cantones**. Escribí tests que verifiquen esos conteos y el conteo de distritos contra la fuente. Si no podés obtener la fuente oficial desde tu entorno, generá el dataset, marcalo como "pendiente de validación" en el reporte y Ariel lo valida a mano contra el PDF.
- **Checkout clásico:** cantón y distrito como selects dependientes (selectWoo, que ya trae WooCommerce), autocompletar el código postal y guardar en los campos estándar de dirección cuando corresponda (por ejemplo, cantón en `city`) para que otros plugins de envío lo entiendan.
- **Checkout de bloques:** investigá si se puede implementar limpiamente con la Additional Checkout Fields API (location `address`, tipo `select`) más filtrado del lado del cliente. Si los selects dependientes no se pueden hacer de forma soportada, **no hagas hacks frágiles**: entregá la versión de bloques con la mejor alternativa soportada (por ejemplo, un select de distrito con búsqueda) o limitá el módulo al checkout clásico, y documentá la limitación en el readme. Presentá las opciones en el reporte de fase antes de implementar.
- Mostrar los datos en el admin del pedido, en los correos y en Mi cuenta.

### Módulo D: Cédula / identificación (Fase 3)

- Dos campos: tipo de identificación (Física, Jurídica, DIMEX, NITE) y número.
- Normalizar quitando guiones y espacios. Validar:
  - **Física:** 9 dígitos.
  - **Jurídica:** 10 dígitos.
  - **NITE:** 10 dígitos.
  - **DIMEX:** 11 o 12 dígitos. Confirmá esta regla con una fuente oficial y reportala.
  - No existe dígito verificador público: la validación es solo de formato.
- Opción del comerciante: campo opcional, obligatorio, u obligatorio solo si el cliente marca "Necesito factura electrónica".
- **Bloques:** `woocommerce_register_additional_checkout_field()` en `woocommerce_init` (requiere WooCommerce 8.9+), con validación del lado del servidor. **Clásico:** filtro de campos de checkout más validación en `woocommerce_after_checkout_validation`.
- Mostrar el dato en el admin, en los correos y en Mi cuenta. Exponer un filtro o acción para que plugins de factura electrónica de terceros lo consuman. **No** generar facturas.

---

## 4. Requisitos técnicos obligatorios

### Compatibilidad
- Declarar compatibilidad en `before_woocommerce_init` con `Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility()` para **`custom_order_tables`** (HPOS) y **`cart_checkout_blocks`**. Ojo: si el plugin tiene la cabecera "WC tested up to" y no declara HPOS, WooCommerce lo trata como incompatible.
- **Prohibido** leer o escribir datos de pedidos con `get_post_meta` / `update_post_meta` / `WP_Query` sobre pedidos. Usar siempre el CRUD de WooCommerce: `wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()` y `$order->save()`.
- Metaboxes del pedido registrados sobre la pantalla que devuelve `wc_get_page_screen_id( 'shop-order' )`, para que funcionen con HPOS y con el almacenamiento clásico.
- Debe funcionar con HPOS activo, con HPOS inactivo y en modo de compatibilidad (sincronización).
- No romper si WooCommerce está desactivado (aunque `Requires Plugins` lo evita): cargar todo en `plugins_loaded` o `woocommerce_loaded` y salir limpiamente.

### Seguridad (estándar del equipo de revisión de WordPress.org)
- `defined( 'ABSPATH' ) || exit;` en todo archivo PHP.
- Sanitizar toda entrada (`sanitize_text_field`, `absint`, `wc_clean`, etc.), validar, y **escapar tarde** toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Nonce y `current_user_can()` en toda acción que cambie estado (ajustes, confirmar o rechazar pago, refrescar tipo de cambio, subir y descargar comprobante).
- **Nunca** imprimir `<script>` ni `<style>` directamente: usar `wp_enqueue_script`/`wp_enqueue_style` y `wp_add_inline_script` cuando haga falta. Es una de las causas de rechazo más comunes.
- Encolar assets **solo** en las páginas donde se usan.
- Sin código ofuscado ni minificado sin su fuente: el código fuente de JS debe ir en el plugin (`assets/src/`) junto al build.
- Sin llamadas externas que no sean imprescindibles, sin tracking ni telemetría, y sin cargar assets desde CDNs.
- Datos personales: registrar texto sugerido de privacidad con `wp_add_privacy_policy_content()` e integrar exportador y borrador de datos personales para la cédula y los comprobantes.

### Calidad de código
- PHP 8.1+, `declare( strict_types=1 );` en los archivos de `src/`, tipos en parámetros y retornos.
- Autoload PSR-4 con Composer **solo para la estructura propia**. El zip final incluye `vendor/autoload.php` generado con `--no-dev --optimize-autoloader`. Sin librerías de terceros en runtime; si alguna resulta inevitable, preguntar y prefijarla con Strauss o PHP-Scoper.
- Coding standards: **WPCS 3.x** (WordPress-Extra + WordPress-Docs) más **PHPCompatibilityWP** con `testVersion 8.1-`. Cero errores; warnings justificados.
- **PHPStan** nivel 6 como mínimo, con `szepeviktor/phpstan-wordpress` y stubs de WooCommerce.
- Todo string de UI internacionalizado, con text domain **literal**, comentarios `/* translators: */` en los placeholders y `_n()` para plurales.
- Idioma: código, comentarios, nombres y strings fuente **en inglés**. Incluir traducción **es_CR** (`.po`/`.mo` en `/languages`) mientras llega la traducción comunitaria en translate.wordpress.org.
- Desinstalación: `uninstall.php` borra opciones y acciones programadas. Los comprobantes y la metadata de pedidos **se conservan** salvo que el comerciante active "Borrar todos los datos al desinstalar".
- Activación y desactivación: programar y desprogramar las acciones de Action Scheduler.
- Extensibilidad para una futura versión Pro: acciones y filtros con prefijo en los puntos clave (por ejemplo `crkit_sinpe_payment_confirmed`, `crkit_sinpe_receipt_uploaded`, `crkit_exchange_rate_updated`, `crkit_rate_providers`). **Sin** upsells, sin "Go Pro", sin features bloqueadas. Las reglas del directorio no permiten trialware.

---

## 5. Estructura del repositorio

```
costa-rica-kit-for-woocommerce/
├── costa-rica-kit-for-woocommerce.php   # Solo cabecera, constantes y bootstrap
├── uninstall.php
├── readme.txt
├── composer.json / composer.lock
├── package.json / package-lock.json
├── phpcs.xml.dist
├── phpstan.neon.dist
├── phpunit.xml.dist
├── .wp-env.json                          # WP 7.1 + WooCommerce + Plugin Check
├── .distignore                           # Lo que NO va en el zip
├── CLAUDE.md
├── CHANGELOG.md                          # Keep a Changelog
├── docs/  SPEC.md  DECISIONS.md  TESTING.md
├── src/
│   ├── Plugin.php                        # Contenedor simple, registra módulos
│   ├── Admin/SettingsPage.php
│   ├── Modules/ModuleInterface.php
│   ├── Modules/SinpeMovil/ ...           # Gateway, BlocksSupport, Receipts, AdminMetabox, Emails
│   ├── Modules/ExchangeRate/ ...         # Providers, RateRepository, Scheduler
│   ├── Modules/Locations/ ...
│   ├── Modules/Identification/ ...
│   └── Support/ ...                      # Helpers compartidos (formato CRC, teléfono, etc.)
├── assets/src/   (JS/CSS fuente)
├── assets/build/ (generado por @wordpress/scripts)
├── data/cr-locations.json
├── languages/
├── templates/                            # Plantillas sobreescribibles desde el tema
├── tests/
│   ├── Unit/                             # Sin WordPress (Brain Monkey o equivalente)
│   ├── Integration/                      # Con WP + WC vía wp-env
│   └── fixtures/                         # Respuestas reales de Hacienda / BCCR
└── .github/workflows/ci.yml
```

---

## 6. Herramientas y entorno

- **wp-env** (Docker) con WordPress 7.1, la última WooCommerce estable y el plugin **Plugin Check (PCP)**. Scripts npm: `env:start`, `env:stop`, `test:php`, `lint:php`, `lint:js`, `build`, `plugin-check`, `zip`.
- Plugin Check por CLI dentro de wp-env: `wp plugin check costa-rica-kit-for-woocommerce`.
- **CI en GitHub Actions:** phpcs, phpstan, phpunit (matriz PHP 8.1, 8.3 y 8.4), build de JS y Plugin Check (existe la action oficial `wordpress/plugin-check-action`; verificá su versión vigente).
- Script `zip` que produzca un zip limpio respetando `.distignore`: sin tests, sin `node_modules`, sin configs de desarrollo, y con `assets/build`, `vendor/autoload` y `languages`.

---

## 7. readme.txt (WordPress.org)

- Formato estándar: `Contributors`, `Tags` (máximo 5), `Requires at least`, `Tested up to` (7.1), `Requires PHP` (8.1), `Stable tag`, `License`. Descripción corta de 150 caracteres o menos.
- Secciones: Description, Installation, FAQ (incluida la del token del BCCR y los límites de SINPE), Screenshots, Changelog, Upgrade Notice.
- **Sección "External services" obligatoria:** documentar cada servicio externo (API de Hacienda y API SDDE del BCCR): qué datos se envían (ninguno personal, solo la consulta y el token del comerciante en el caso del BCCR), cuándo se llama, y enlaces a los términos de uso y la política de privacidad de cada uno. Buscá esos enlaces y marcalos para que Ariel los verifique.
- Aclarar que el plugin **no** procesa pagos automáticamente: SINPE se confirma a mano.

---

## 8. Matriz de pruebas

Para cada fase que toque el checkout, verificar y dejar evidencia en `docs/TESTING.md`:

| Dimensión | Variantes |
|---|---|
| Checkout | Clásico (shortcode) y de bloques |
| Moneda de la tienda | CRC y USD |
| Almacenamiento de pedidos | HPOS activo, HPOS inactivo, compatibilidad activada |
| Usuario | Invitado y logueado |
| PHP | 8.1 y 8.3 o superior |

Además:
- **Tests unitarios:** normalización y validación de teléfono y cédula, conversión y redondeo CRC, parsers de proveedores contra fixtures (incluidos errores 401/429/500 y `null`), conteos del dataset de ubicaciones.
- **Tests de integración:** `process_payment()` deja el pedido en `on-hold` con la metadata congelada; la carga de comprobante rechaza tipos inválidos, archivos grandes y usuarios ajenos; la descarga del comprobante exige permiso; confirmar y rechazar cambian el estado correctamente.
- **Opcional (Fase 4):** smoke E2E con Playwright para ambos checkouts.

---

## 9. Fases

### Fase 0: Andamiaje (sin features)
Repo, `CLAUDE.md`, Composer, npm, wp-env con WooCommerce y Plugin Check, phpcs, phpstan, phpunit, CI, cabecera, bootstrap, declaraciones de compatibilidad HPOS y bloques, pestaña de ajustes vacía con toggles de módulos, `readme.txt` inicial, `uninstall.php`, script de zip.
**Hecho cuando:** el plugin activa sin notices en WP 7.1 + WC última, Plugin Check da 0 errores, CI en verde y el zip se genera.

### Fase 1: MVP (Módulo B + Módulo A)
Primero el tipo de cambio (Hacienda, BCCR SDDE, manual, caché, scheduler, fallback), después la pasarela SINPE completa: clásico y bloques, comprobantes, metabox, correos y la matriz de pruebas.
**Hecho cuando:** se cumple toda la sección 3A y 3B, la matriz de la sección 8 pasa, Plugin Check da 0 errores, phpstan y phpcs están limpios, y existe la traducción es_CR.

### Fase 2: Ubicaciones + shortcode del tipo de cambio
Módulo C, más el shortcode `[crkit_exchange_rate]` (y bloque opcional) para mostrar el tipo de cambio del día.

### Fase 3: Cédula
Módulo D.

### Fase 4: Pulido para el envío a WordPress.org
Revisión completa contra las 18 guías del directorio, accesibilidad básica de los formularios, capturas de pantalla, assets del directorio (banner 772×250 y 1544×500, ícono 128 y 256) como placeholders que Ariel reemplazará, smoke E2E opcional y versión 1.0.0.

---

## 10. Fuera de alcance

- Factura electrónica (generación o envío a Hacienda).
- Verificación automática de pagos SINPE (sería parte de una futura versión Pro y requiere integraciones bancarias).
- Envíos con Correos de Costa Rica u otros couriers.
- Cualquier código, menú o aviso de una versión Pro.

---

## 11. Plantilla del reporte de fase

Al cerrar cada fase, respondé **exactamente** con esta estructura (Ariel la va a pasar a un revisor):

```
## Reporte Fase N: <nombre>

### Resumen
(3-5 líneas: qué quedó hecho)

### Archivos creados / modificados
(lista con una línea de propósito por archivo)

### Decisiones tomadas
(cada una: decisión, alternativas consideradas, por qué; enlace a DECISIONS.md)

### Desviaciones del SPEC
(qué cambió respecto al documento y por qué; "ninguna" si aplica)

### Verificaciones de APIs externas
(endpoint, petición hecha, forma de respuesta real, o "no verificado: sin red")

### Resultados de calidad
- phpcs: (salida resumida)
- phpstan: (nivel y resultado)
- phpunit: (N tests, N assertions, resultado)
- Plugin Check: (errores / warnings)
- Matriz de pruebas: (tabla con estado por combinación)

### Pendientes y riesgos conocidos

### Preguntas para Ariel
```
