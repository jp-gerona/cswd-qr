# CSWD QR Batch Generator — Feature Overview

A web tool for the **City of Biñan** CSWD office that produces large sets of
printable QR-code identification cards. Each card carries a unique six-digit
control number encoded as the QR payload. Staff request a quantity (up to
10,000) from a single browser form; the system renders the cards into a
print-ready US-Letter PDF — or a ZIP of PDFs for large batches — and triggers
an immediate download. No database write, no server-side storage of the
generated file, no page reload.

The QR content is a plain control number, so cards carry no personally
identifiable information at print time; linking a card to a person happens
downstream in a separate registry (see *Extension Points*).

## End-to-End Flow

1. **Browser form** — `/` is served by `App\Controllers\Home` (`app/Views/home.php`).
   A single numeric input (1–10,000, default 12) with HTML `min`/`max` bounds.
2. **AJAX submission** — jQuery intercepts submit and `POST`s `quantity` to
   `/generate` with `responseType: 'blob'`. The response is binary (PDF or ZIP);
   jQuery builds an object URL, clicks a synthetic anchor to save it, then
   revokes the URL. On error it reads the JSON `{"error": ...}` and shows it.
3. **Controller** — `App\Controllers\Batch::generate()` validates
   `quantity` (`required|is_natural_no_zero|less_than_equal_to[10000]`),
   returns `400` JSON on failure, and otherwise delegates to the library and
   streams the bytes with the right `Content-Type`
   (`application/pdf` / `application/zip`) and `Content-Disposition: attachment`.
4. **Generation** — `App\Libraries\QrBatchPdfGenerator::generate()` consults
   `QrBatchPlanner` for chunking. One chunk → a single PDF
   (`cswd-qr-batch.pdf`). Multiple chunks → a ZIP (`cswd-qr-batch.zip`) with
   entries `batch-001.pdf`, `batch-002.pdf`, …, assembled via PHP's
   `ZipArchive` in a temp file that is always cleaned up in a `finally` block.

## Control Numbers

- Six digits, zero-padded, sequential from `1` (`000001`, `000002`, …).
- `QrBatchPlanner::formatControlNumber()` / `controlNumbers()`.
- Encoded as the **plain control-number string** in the QR — no URL, no metadata.

## Print / Layout (per card)

| Property | Value |
|---|---|
| Paper | US Letter, portrait, zero margin |
| Grid | 3 columns × 4 rows = **12 cards per page** |
| Cut guide | 1px dashed gray border per cell |
| Header | `CITY OF BIÑAN`, purple `#6f42c1`, bold, 9px, centered |
| Fields | `Barangay:` / `Name:` labels left, blank underlines ending flush right |
| QR | 1.4in × 1.4in, centered |
| Control number | 15px **Roboto Mono**, centered |
| Fonts embedded | Roboto (body/header), Roboto Mono (control number) |

The grid is a fixed-height CSS table (`display: table`, `.grid` height
distributed across 4 rows) — dompdf's float layout silently drops rows, so a
table is used. Page breaks are emitted **before** each page after the first
(via a `.page-break` class set by the renderer); using `break-after` would
append a trailing blank page.

## Library Choices

- **dompdf/dompdf** — HTML/CSS → PDF; renders the grid and inline SVG without
  native image extensions.
- **chillerlan/php-qrcode** (v6) — pure-PHP QR generation. QR codes are
  embedded as **SVG** data URIs (`QRMarkupSVG`), not PNG: dompdf's PNG path
  needs `ext-gd`, which the environment lacks. SVG is vector — sharp at any
  print size — and needs no image extension. (A custom pure-PHP PNG encoder,
  `QrPngOutput`, remains in the tree but is currently unused.)
- **ZipArchive** (built-in) — multi-chunk packaging.

A fresh `QRCode` instance is created per control number; reusing one instance
makes chillerlan accumulate data segments across `render()` calls and
eventually overflow the QR's capacity.

## Scaling Notes

- 12 cells/page, 50 pages/chunk (600 cards), 10,000 max.
- Per-chunk rendering with explicit memory release (`unset`) between chunks.
- The renderer raises `memory_limit` to 512MB when lower (a 50-page chunk needs
  ~180MB in dompdf) and resets `set_time_limit(300)` per chunk, since the
  default 128MB / 30s web limits are exceeded by large batches.

## Extension Points

- **DB-backed records** — persist issued control numbers / link to beneficiaries.
- **URL-encoded QR** — encode a scan URL instead of the bare number.
- **CSV upload** — drive names/barangays from an uploaded roster, pre-filling
  the currently blank fields.

## Environment Notes

- Tests run under a PHP with `ext-intl` (CodeIgniter requirement),
  `ext-zip`, `ext-gd`-independent SVG, and `ext-sqlite3` (for the framework's
  example database tests). `composer test` covers the planner, QR generation,
  PDF rendering, pagination (exactly 12/page), chunk orchestration, and the
  HTTP endpoint.
