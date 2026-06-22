# CSWD QR Batch Generator — Feature Overview

A web tool for the **City of Biñan** CSWD office that produces large sets of
printable QR-code identification cards. Each card carries a unique six-digit
control number; the QR payload is the configured URL prefix prepended to that
number (prefix `""` = the bare number). Staff enter a **start/end control-number
range** in a browser form; the system renders the cards into a print-ready
US-Letter PDF — or a ZIP of PDFs for large batches — and triggers an immediate
download. No database write, no server-side storage of the generated file, no
page reload.

Batch size and every other tunable (QR URL prefix, filenames, grid, chunking,
control-number width, max quantity) live in `app/Config/QrBatchSettings.php` and
are `.env`-overridable. The default `maxQuantity` is **25,000**.

With the default empty URL prefix the QR content is a plain control number, so
cards carry no personally identifiable information at print time; linking a card
to a person happens downstream in a separate registry (see *Extension Points*).

## End-to-End Flow

1. **Browser form** — `/` is served by `App\Controllers\Home` (`app/Views/home.php`).
   Two numeric inputs — starting and ending control number — with HTML
   `min`/`max` bounds. The helper text (max per batch, codes per sheet) is read
   from `QrBatchSettings`.
2. **AJAX submission** — jQuery intercepts submit and `POST`s `startNumber` /
   `endNumber` to `/generate` with `responseType: 'blob'`. The response is binary
   (PDF or ZIP); jQuery builds an object URL, clicks a synthetic anchor to save
   it, then revokes the URL. On error the body is also a Blob, so it is read as
   text first, then the JSON `{"error": ...}` is parsed and shown.
3. **Controller** — `App\Controllers\Batch::generate()` validates `startNumber`
   and `endNumber` (`required|is_natural_no_zero|less_than_equal_to[<maxControlNumber>]`),
   rejects `end < start`, rejects ranges whose size exceeds `maxQuantity`,
   returns `400` JSON on failure. On generation error it logs the detail and
   returns a generic client-safe message (no internal details leaked). Otherwise
   it delegates to the library and streams the bytes with the right
   `Content-Type` (`application/pdf` / `application/zip`) and
   `Content-Disposition: attachment`.
4. **Generation** — `App\Libraries\QrBatchPdfGenerator::generate()` consults
   `QrBatchPlanner` for chunking. One chunk → a single PDF
   (`singlePdfFileName`, default `cswd-qr-batch.pdf`). Multiple chunks → a ZIP
   (named from `zipFileNamePattern` with the batch's first/last control numbers)
   with entries named from `chunkPdfNamePattern` (first/last control number per
   chunk), assembled via PHP's `ZipArchive` in a temp file that is always cleaned
   up in a `finally` block. Temp-file creation and chunk writes are checked for
   failure.

## Control Numbers

- Six digits, zero-padded, sequential from `1` (`000001`, `000002`, …).
- `QrBatchPlanner::formatControlNumber()` / `controlNumbers()`.
- Width is config-driven (`controlNumberWidth`, default 6).
- The QR payload is `qrUrlPrefix . controlNumber`; with the default empty prefix
  this is the **plain control-number string** — no URL, no metadata.

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

- **dompdf/dompdf** — HTML/CSS → PDF; renders the 3×4 card grid.
- **chillerlan/php-qrcode** (v6) — pure-PHP QR generation. QR codes are
  embedded as **PNG** data URIs via the custom pure-PHP encoder `QrPngOutput`
  (`QrImageGenerator::dataUri()`). dompdf's PNG embedding requires `ext-gd`, so
  the renderer fails fast with a clear message when it is missing. An SVG output
  mode (`QrImageGenerator::svgDataUri()`, `QRMarkupSVG`) remains available as an
  ext-gd-free alternative but is not currently used.
- **ZipArchive** (built-in) — multi-chunk packaging.

A fresh `QRCode` instance is created per control number; reusing one instance
makes chillerlan accumulate data segments across `render()` calls and
eventually overflow the QR's capacity.

## Scaling Notes

- 12 cells/page, 50 pages/chunk (600 cards); max batch = `maxQuantity` (default
  25,000, `.env`-overridable). All four are config knobs in `QrBatchSettings`.
- Per-chunk rendering with explicit memory release (`unset`) between chunks.
- The renderer raises `memory_limit` to 512MB when lower (a 50-page chunk needs
  ~180MB in dompdf) and resets `set_time_limit(300)` per chunk, since the
  default 128MB / 30s web limits are exceeded by large batches.

## Extension Points

- **DB-backed records** — persist issued control numbers / link to beneficiaries.
- **URL-encoded QR** — already supported via `qrUrlPrefix`; set it to a scan URL
  base to encode a full URL instead of the bare number.
- **CSV upload** — drive names/barangays from an uploaded roster, pre-filling
  the currently blank fields.

## Environment Notes

- Tests run under a PHP with `ext-intl` (CodeIgniter requirement), `ext-zip`,
  `ext-gd` (PNG QR embedding), and `ext-sqlite3` (for the framework's example
  database tests). `composer test` covers the planner, QR generation,
  PDF rendering, pagination (exactly 12/page), chunk orchestration, and the
  HTTP endpoint.
