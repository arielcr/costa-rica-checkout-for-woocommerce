# Test fixtures

| File | Origin |
|---|---|
| `hacienda/tc-2026-10-31.json` | **Real** response of `GET https://api.hacienda.go.cr/indicadores/tc` on 2026-10-31. |
| `bccr/cuadro-1-2026-10-07.json` | **Real** response of `GET …/cuadro/1/series` (trimmed), verified in production by the project owner in October 2026 (docs/SPEC.md §3B). |
| `bccr/error-500-invalid-token.json` | **Real** body returned by `apim.bccr.fi.cr` on 2026-10-31 for a request with an invalid bearer token (HTTP 500). |
| `bccr/cuadro-1-with-tomorrow-and-nulls.json` | Synthetic, same shape as the real one: includes tomorrow's rate (published in the afternoon) and a `null` value, to test date filtering. |
| `bccr/error-estado-false.json` | Synthetic: `estado: false` envelope. |

A request without a token to `apim.bccr.fi.cr` returns **HTTP 401 with an empty body** (verified 2026-10-31), so there is no 401 fixture body.
