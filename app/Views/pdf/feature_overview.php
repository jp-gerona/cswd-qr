<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSWD QR Batch Generator — Feature Overview</title>
    <style>
        @page { margin: 0.75in; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #212529;
            line-height: 1.7;
            margin: 0;
        }
        h1 {
            font-size: 20px;
            color: #6f42c1;
            border-bottom: 2px solid #6f42c1;
            padding-bottom: 6px;
            margin-bottom: 4px;
        }
        h2 {
            font-size: 14px;
            color: #6f42c1;
            margin-top: 18px;
            margin-bottom: 4px;
            border-bottom: 1px solid #d8c8f4;
            padding-bottom: 2px;
        }
        h3 {
            font-size: 12px;
            color: #495057;
            margin-top: 12px;
            margin-bottom: 2px;
        }
        p { margin: 4px 0 8px 0; }
        .subtitle {
            font-size: 10px;
            color: #6c757d;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin: 8px 0 12px 0;
        }
        th {
            background-color: #6f42c1;
            color: #ffffff;
            padding: 5px 8px;
            text-align: left;
        }
        td {
            border: 1px solid #dee2e6;
            padding: 4px 8px;
            vertical-align: top;
        }
        tr:nth-child(even) td { background-color: #f8f5ff; }
        code {
            background-color: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 2px;
            padding: 1px 3px;
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 9px;
        }
        ul { margin: 4px 0 8px 0; padding-left: 18px; }
        li { margin-bottom: 3px; }
        .note {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 6px 10px;
            font-size: 10px;
            margin: 8px 0;
        }
    </style>
</head>
<body>

<h1>CSWD QR Batch Generator</h1>
<p class="subtitle">Feature Overview — City Social Welfare and Development Office &middot; Generated <?= date('F j, Y') ?></p>

<h2>1. Purpose</h2>

<p>
The CSWD QR Batch Generator is a web-based tool built for the City Social Welfare and Development Office
to produce large sets of printable QR-code identification cards. Each card carries a unique six-digit
control number that is encoded as the QR payload. Staff can request any quantity up to 10,000 cards from
a single browser form; the system renders those cards into a US Letter PDF (or a ZIP of multiple PDFs for
large batches) and initiates an immediate file download — no database write, no server-side storage of the
generated file, and no page reload.
</p>

<p>
The primary use case is welfare-programme beneficiary registration, where each physical card is handed to
one beneficiary and later scanned to confirm identity. Because the QR content is a plain control number,
the cards carry no personally identifiable information at the point of printing; linking a card to a
person happens downstream in a separate registry (see Section 8, Extension Points).
</p>

<h2>2. End-to-End Request Flow</h2>

<h3>2.1 Browser Form</h3>
<p>
The user visits the home page, which is served by <code>App\Controllers\Home</code> and rendered by
<code>app/Views/home.php</code>. The form contains a single numeric input for the requested quantity
and a submit button. Client-side validation (enforced by jQuery) prevents the form from submitting a
value outside the range 1–10,000 before it even reaches the server.
</p>

<h3>2.2 AJAX Submission</h3>
<p>
Rather than a standard HTML form post, jQuery intercepts the submit event and sends the quantity to
<code>POST /generate</code> as an XMLHttpRequest with <code>responseType</code> set to
<code>'blob'</code>. This means the browser treats the response body as raw binary data, which is
necessary because the server returns either a PDF or a ZIP file directly — there is no intermediate
JSON envelope. When the response arrives, jQuery synthesises a temporary anchor element, sets its
<code>href</code> to an object URL wrapping the blob, and programmatically clicks it to trigger the
browser's native file-save dialog. The object URL is then revoked to release memory.
</p>

<h3>2.3 Controller Dispatch</h3>
<p>
The route resolves to <code>App\Controllers\Batch::generate()</code>. The controller reads the
<code>quantity</code> field from the POST body, validates that it is an integer in the permitted range,
and delegates all generation work to <code>App\Libraries\QrBatchPdfGenerator::generate()</code>. The
controller is intentionally thin: it converts the library's return value into an HTTP response with the
appropriate <code>Content-Type</code> header (<code>application/pdf</code> or
<code>application/zip</code>), sets <code>Content-Disposition: attachment</code> with the correct
filename, and returns.
</p>

<h3>2.4 QrBatchPdfGenerator — Chunked Rendering</h3>
<p>
<code>QrBatchPdfGenerator::generate()</code> first consults <code>QrBatchPlanner</code> to determine
how many chunks the quantity requires. If the entire batch fits in one chunk (at most 50 pages, or 600
cards), it renders a single PDF and returns it directly. If the batch spans more than one chunk, it opens
a temporary ZIP archive via PHP's built-in <code>ZipArchive</code> extension, renders each chunk
sequentially, appends the resulting PDF bytes as a named entry
(<code>batch-001.pdf</code>, <code>batch-002.pdf</code>, …), and closes the archive. The temporary ZIP
file lives in <code>sys_get_temp_dir()</code> and is unconditionally deleted in a
<code>finally</code> block, even if a chunk render throws an exception mid-way.
</p>

<p>
Before rendering each chunk, the method raises the PHP memory limit to 512 MB if the current limit is
lower. A full 50-page chunk with embedded SVG QR codes requires roughly 180 MB inside dompdf; the
default web request limit of 128 MB would exhaust available memory before the render completes. The
limit is raised only when necessary so as never to inadvertently shrink a higher pre-configured value.
After each chunk, the local references to the chunk's PDF bytes are explicitly unset to release memory
before the next chunk begins.
</p>

<h3>2.5 QR Image Generation</h3>
<p>
Individual QR codes are produced by <code>App\Libraries\QrImageGenerator::svgDataUri()</code>, which
uses the <code>chillerlan/php-qrcode</code> library's <code>QRMarkupSVG</code> output driver.
The driver encodes the SVG markup as a Base64 data URI suitable for embedding directly in an HTML
<code>img</code> tag's <code>src</code> attribute. A fresh <code>QRCode</code> instance is created for
every control number because reusing one instance across multiple <code>render()</code> calls causes the
library to accumulate data segments internally, eventually exceeding the QR version's capacity.
</p>

<p>
The SVG format was chosen specifically because dompdf can render inline SVG without any native PHP
image extensions. A PNG-based approach was originally planned but requires <code>ext-gd</code> to
compose and encode the bitmap; the deployment environment does not have <code>ext-gd</code> available,
so SVG was adopted instead. SVG QR codes also scale perfectly for print, producing sharp output at any
physical size without resampling artifacts.
</p>

<h3>2.6 PDF Composition</h3>
<p>
Each page of a chunk is rendered from <code>app/Views/pdf/batch_page.php</code>, which receives an
array of up to 12 cells. A cell is an associative array containing the formatted control number string
and the SVG data URI. The view emits a fixed-layout HTML table that dompdf interprets as the print grid.
Shared CSS for the grid is kept in <code>app/Views/pdf/_styles.php</code>, which is prepended once per
chunk rather than once per page so that dompdf processes the style rules a single time. The final HTML
string passed to dompdf is therefore the stylesheet followed by all page HTML concatenated in order.
</p>

<h2>3. Control-Number Scheme</h2>

<p>
Control numbers are sequential integers formatted as six-digit zero-padded decimal strings (for example,
<code>000001</code>, <code>000042</code>, <code>001500</code>). The formatting is handled by
<code>QrBatchPlanner::formatControlNumber()</code>, which uses PHP's <code>str_pad()</code> with a
field width of 6 and a left-padding character of <code>'0'</code>.
</p>

<p>
For a given batch request the sequence always starts at 1 and increments by 1 for each card. Within a
multi-chunk batch the <code>startNumber</code> argument passed to each chunk's render call advances by
the number of cards already produced, so control numbers are globally unique and contiguous across all
PDFs in the ZIP.
</p>

<p>
The string that is encoded into the QR code is exactly the formatted control number — plain ASCII text,
no URL prefix, no metadata. Scanners therefore read back the bare number string, which downstream
systems can match against a registry. This keeps the QR module count small (version 1 or 2) and
maximises scan reliability.
</p>

<h2>4. Print and Layout Specifications</h2>

<table>
    <thead>
        <tr>
            <th>Property</th>
            <th>Value</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Paper size</td>
            <td>US Letter (8.5 &times; 11 in)</td>
            <td>Landscape not used; portrait only</td>
        </tr>
        <tr>
            <td>Page margin</td>
            <td>None (0)</td>
            <td><code>@page { margin: 0; }</code></td>
        </tr>
        <tr>
            <td>Grid layout</td>
            <td>3 columns &times; 4 rows = 12 cells per page</td>
            <td>Rendered as an HTML table; each column is 33.33% width, each row 25% page height</td>
        </tr>
        <tr>
            <td>Cell borders</td>
            <td>1 px dashed, color <code>#adb5bd</code></td>
            <td>Serve as cut guides; dashed pattern reduces toner usage</td>
        </tr>
        <tr>
            <td>Cell padding</td>
            <td>8 px top/bottom, 10 px left/right</td>
            <td></td>
        </tr>
        <tr>
            <td>Header text</td>
            <td>Purple (<code>#6f42c1</code>), bold, 10 px, centered</td>
            <td>Programme name or office identifier printed at top of each cell</td>
        </tr>
        <tr>
            <td>Field labels</td>
            <td>8 px, dark (<code>#212529</code>), left-aligned</td>
            <td>Name and other fields shown as label + blank underline for hand-writing</td>
        </tr>
        <tr>
            <td>Blank underlines</td>
            <td>70% cell width, <code>border-bottom: 1px solid</code></td>
            <td>Space for staff to write beneficiary details after printing</td>
        </tr>
        <tr>
            <td>QR image size</td>
            <td>1.1 &times; 1.1 in</td>
            <td>Embedded as SVG data URI; vector, no raster scaling artifacts</td>
        </tr>
        <tr>
            <td>Control-number label</td>
            <td>8 px, dark</td>
            <td>Static label "Control No." above the number</td>
        </tr>
        <tr>
            <td>Control-number value</td>
            <td>14 px, DejaVu Sans Mono (monospace)</td>
            <td>Large, fixed-width for easy visual verification</td>
        </tr>
        <tr>
            <td>Body font</td>
            <td>Roboto (embedded TTF)</td>
            <td>Registered per-render via dompdf's <code>FontMetrics::registerFont()</code></td>
        </tr>
    </tbody>
</table>

<h2>5. Library Choices and Rationale</h2>

<h3>5.1 dompdf/dompdf</h3>
<p>
Dompdf converts an HTML+CSS string to a PDF byte stream entirely in pure PHP, with no external
binary dependency. It was selected because the CI4 application is already PHP-only and the print layout
is naturally expressible as a CSS table grid. Dompdf's support for <code>@page</code> rules, table-based
layout, and embedded fonts is sufficient for the fixed-grid card design. The library writes font
metrics to a writable cache directory (<code>writable/fonts/</code>) to avoid repeating font-parsing
work across requests.
</p>

<h3>5.2 chillerlan/php-qrcode</h3>
<p>
This library generates QR codes entirely in PHP without requiring any image or graphics extension. It
supports multiple output formats through a driver interface; the <code>QRMarkupSVG</code> driver
produces an inline XML SVG string that dompdf can embed directly. Error correction level M (roughly
15% data restoration capacity) is used — a reasonable balance between data density and scan tolerance
for codes that will be printed and potentially handled physically. The library is well-maintained,
follows semantic versioning, and is available through Composer.
</p>

<h3>5.3 ZipArchive (PHP built-in)</h3>
<p>
PHP's bundled <code>ZipArchive</code> extension handles multi-chunk batch assembly without any
additional Composer dependency. Chunk PDFs are added to the archive as in-memory strings via
<code>addFromString()</code>, which avoids writing individual chunk files to disk. Only the final
assembled ZIP is written to a temporary file, read back as a byte string, and then deleted.
</p>

<h2>6. Scaling Notes</h2>

<p>
The system is designed to handle up to 10,000 cards per request. The key scaling constants, all
defined in <code>QrBatchPlanner</code>, are:
</p>
<ul>
    <li><strong>12 cells per page</strong> — a 3&times;4 grid on US Letter with zero margin.</li>
    <li><strong>50 pages per chunk</strong> — each chunk is rendered into one PDF. 50 pages &times; 12 cards = 600 cards per PDF.</li>
    <li><strong>10,000 maximum quantity</strong> — enforced at the controller. 10,000 cards require 834 pages, which is 17 chunks (PDFs in a ZIP).</li>
</ul>

<p>
Rendering is strictly sequential rather than parallel. PHP's single-threaded execution model and the
shared memory space of a web worker process make parallel chunk rendering impractical without a queue
or background-process architecture. For the expected workload (welfare programme batches of a few
hundred to a few thousand), sequential rendering completes well within typical HTTP timeout limits.
</p>

<p>
The 512 MB memory ceiling is set per-render invocation and is released when the PHP process ends or
is reused for the next request. If the deployment environment's <code>php.ini</code> already allows
more than 512 MB, the code will never lower that limit.
</p>

<h2>7. Key Source Files</h2>

<table>
    <thead>
        <tr>
            <th>File</th>
            <th>Role</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>app/Controllers/Batch.php</code></td>
            <td>Validates quantity, calls generator, returns HTTP binary response</td>
        </tr>
        <tr>
            <td><code>app/Libraries/QrBatchPdfGenerator.php</code></td>
            <td>Orchestrates chunked PDF rendering and ZIP assembly</td>
        </tr>
        <tr>
            <td><code>app/Libraries/QrBatchPlanner.php</code></td>
            <td>Pure-value class: constants, control-number formatting, chunk-count arithmetic</td>
        </tr>
        <tr>
            <td><code>app/Libraries/QrImageGenerator.php</code></td>
            <td>Wraps chillerlan to produce SVG data URIs per control number</td>
        </tr>
        <tr>
            <td><code>app/Views/pdf/_styles.php</code></td>
            <td>Shared CSS for the print grid; prepended once per chunk</td>
        </tr>
        <tr>
            <td><code>app/Views/pdf/batch_page.php</code></td>
            <td>Renders one page (up to 12 cells) of the QR grid</td>
        </tr>
        <tr>
            <td><code>app/Views/home.php</code></td>
            <td>Quantity form; jQuery AJAX wires up the blob download</td>
        </tr>
    </tbody>
</table>

<h2>8. Extension Points</h2>

<h3>8.1 Database-Backed Records</h3>
<p>
The current implementation is stateless: control numbers are generated on-the-fly and not persisted.
A natural extension is to insert one row per generated control number into a beneficiary registry table
at generation time, recording the number, batch ID, timestamp, and initially-null beneficiary fields.
This would allow the office to track which numbers have been issued, detect duplicates, and pre-fill
beneficiary data before printing.
</p>

<h3>8.2 URL-Encoded QR Payload</h3>
<p>
Instead of encoding a bare control number, the QR payload could be a URL pointing to a beneficiary
lookup endpoint (for example, <code>https://cswd.example.gov.ph/verify/000042</code>). Scanning the
code with any smartphone camera would then open a web page rather than displaying raw text. This
requires no change to the PDF layout — only the string passed to <code>QrImageGenerator::svgDataUri()</code>
changes. Note that URL payloads are longer and will push the QR to a higher version, making the
module grid denser; error correction level M remains appropriate.
</p>

<h3>8.3 CSV Upload</h3>
<p>
For batches that must correspond to a pre-existing list of names or IDs, the form could accept a CSV
file upload. The controller would parse the CSV rows, assign one control number per row, and pass the
name or ID alongside the control number to the cell view so that the name field is pre-filled rather
than left blank for hand-writing. The chunking and PDF rendering pipeline would be unchanged.
</p>

</body>
</html>
