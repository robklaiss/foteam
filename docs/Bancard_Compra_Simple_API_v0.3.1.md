
# Integración con **eCommerce Bancard – Compra Simple** (API v0.3.1)

> **Fuente:** `ecommerce-bancard-compra-simple-version-0-3-1.pdf`  
> Última revisión pública accesible — 23 páginas.

---

## 1 · Introducción
El documento describe la integración técnica entre un **Portal/Comercio** y el **servicio VPOS** de Bancard para realizar cobros con tarjetas.

- Formato de mensajería: **JSON**  
- Flujo de compra: `Cliente → Portal → VPOS → Portal → Cliente`  
- Ambientes disponibles:  
  | Ambiente | URL Base | Puerto | Notas |
  |----------|----------|--------|-------|
  | **Staging** | `https://vpos.infonet.com.py:8888` | 8888 | Pruebas |
  | **Producción** | `https://vpos.infonet.com.py` | 443 | Operaciones reales |

El comercio obtiene en el *Portal de Comercios* (<https://comercios.bancard.com.py>) su **public_key** y **private_key** para firmar peticiones.

---

## 2 · Autenticación

### 2.1 Token
El **token** es un `MD5` generado concatenando la `private_key` con campos de la operación (orden exacto y sin separadores).

| Operación | Fórmula (antes de MD5) |
|-----------|------------------------|
| `single_buy` | `private_key + shop_process_id + amount + currency` |
| `single_buy_confirm` | `private_key + shop_process_id + "confirm" + amount + currency` |
| `get_single_buy_confirmation` | `private_key + shop_process_id + "get_confirmation"` |
| `single_buy_rollback` | `private_key + shop_process_id + "rollback" + "0.00"` |

- `amount` debe tener **dos decimales** y usar `.` como separador (ej.: `130.00`).  
- El token resultante es una cadena de **32 caracteres hex**.

---

## 3 · Operaciones REST

### 3.1 single_buy  
`POST {environment}/vpos/api/0.3/single_buy`

```json
{
  "public_key": "YOUR_PUBLIC_KEY",
  "operation": {
    "token": "MD5_HASH",
    "shop_process_id": 54322,
    "currency": "PYG",
    "amount": "10330.00",
    "additional_data": "017VSORO000045001",
    "description": "Ejemplo de pago",
    "return_url": "https://tu‑sitio.com/ok",
    "cancel_url": "https://tu‑sitio.com/cancel"
  }
}
```

**Respuesta**  
```json
{ "status": "success", "process_id": "i5fn*lx6niQel0QzWK1g" }
```

### 3.1.2 single_buy/rollback  
`POST {environment}/vpos/api/0.3/single_buy/rollback`

```json
{
  "public_key": "YOUR_PUBLIC_KEY",
  "operation": {
    "token": "MD5_HASH",
    "shop_process_id": "12313"
  }
}
```

Posibles `messages[*].key` de error/success:

`InvalidJsonError`, `UnauthorizedOperationError`, `ApplicationNotFoundError`, `InvalidPublicKeyError`,  
`InvalidTokenError`, `InvalidOperationError`, `BuyNotFoundError`, `PaymentNotFoundError`,  
`AlreadyRollbackedError`, `PosCommunicationError`, `RollbackSuccessful`, `TransactionAlreadyConfirmed`.

### 3.2 buy_single_confirm (callback de VPOS)  
Invocado por VPOS al finalizar el pago. **El comercio debe responder HTTP 200**.

Campos destacados enviados por VPOS:

| Campo | Tipo | Ejemplo / Valores |
|-------|------|-------------------|
| `response` | `S`/`N` | `S`=procesado |
| `amount` | Decimal(15,2) | `"10100.00"` |
| `currency` | `PYG` | |
| `authorization_number` | `String(6)` | `"123456"` (si aprobada) |
| `response_code` | `00`, `05`, `12`, `15`, `51` | |
| `security_information.card_source` | `L` / `I` | Local / Internacional |

### 3.3 get_single_buy_confirmation  
`POST {environment}/vpos/api/0.3/single_buy/confirmations`

```json
{
  "public_key": "YOUR_PUBLIC_KEY",
  "operation": {
    "token": "MD5_HASH",
    "shop_process_id": "12313"
  }
}
```

**Respuesta exitosa** anidará el objeto `confirmation` con la misma estructura de `buy_single_confirm`.

---

## 4 · Redirección a VPOS

Una vez recibido `process_id` el usuario se redirige a:  
```
{environment}/payment/single_buy?process_id=PROCESS_ID
```

---

## 5 · Página de pago del usuario
El formulario es alojado por VPOS. El comercio **no** debe capturar datos sensibles de la tarjeta.

---

## 6 · Restricciones del comercio

- La página de resultado **debe mostrar**: fecha/hora, `shop_process_id`, `amount`, `response_description`, `authorization_number` (si aplica).  
- **No** mostrar `response_code`, `extended_response_description`, ni `security_information`.
- Incluir sección **Contacto** para consultas.
- No almacenar datos de tarjeta (PAN, CVV, vencimiento, etc.).
- Logo ideal: `173 × 55 px` (mín. `85 px` de ancho).

---

## 7 · Formato JSON & Monitoreo

- Todas las peticiones/respuestas son **POST** con JSON en el *body*.  
- VPOS puede enviar pings de monitoreo (JSON vacío) cada **5 min** a la URL de confirmación.

---

## Anexo · Campo `additional_data`
Estructura de 18 caracteres fijos:

| Posiciones | Longitud | Descripción |
|------------|----------|-------------|
| 1‑3 | 3 | **Entidad** (ej. `017`) |
| 4‑6 | 3 | **Marca** (`VS`, `MC`, `AX`) |
| 7‑9 | 3 | **Producto** (`ORO`, `PLA`, `NEG`) |
| 10‑15 | 6 | **Afinidad** (`000045`) |
| 16‑18 | 3 | **Agrupador** (`001`) |

Ejemplo completo: `017VSORO000045001`

---

### Códigos de respuesta (`response_code`)
| Código | Significado |
|--------|-------------|
| `00` | Transacción aprobada |
| `05` | Tarjeta inhabilitada |
| `12` | Transacción inválida |
| `15` | Tarjeta inválida |
| `51` | Fondos insuficientes |

---

> **Nota de versión**  
> Bancard publica ocasionalmente PDFs internos con números de versión como “1.18”, pero la ruta pública suele exponer la **misma API v0.3**. Este resumen refleja el contenido íntegro técnico de la versión PDF *0‑3‑1* (23 páginas) que es la referencia más reciente accesible públicamente. Si cuentas con la 1.18 en local, súbela aquí y la convertiré.

---

## Ejemplo end‑to‑end (código PHP)

```php
<?php
$private = getenv('BANCARD_PRIVATE');
$public  = getenv('BANCARD_PUBLIC');

$shop_process_id = 1234;
$amount          = number_format(10100, 2, '.', '');
$currency        = 'PYG';
$token_raw       = $private . $shop_process_id . $amount . $currency;
$token           = md5($token_raw);

$payload = [
    'public_key' => $public,
    'operation'  => [
        'token'           => $token,
        'shop_process_id' => $shop_process_id,
        'amount'          => $amount,
        'currency'        => $currency,
        'description'     => 'Compra de prueba',
        'return_url'      => 'https://tu‑sitio.com/ok',
        'cancel_url'      => 'https://tu‑sitio.com/cancel'
    ]
];

$response = json_decode(
    file_get_contents(
        'https://vpos.infonet.com.py:8888/vpos/api/0.3/single_buy',
        false,
        stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json
",
                'content' => json_encode($payload)
            ]
        ])
    ),
    true
);
var_dump($response);
```

---

¡Listo! Copia/pega o abre este `.md` en tu proyecto Windsurf 🚀
