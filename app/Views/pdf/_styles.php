<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Roboto", sans-serif; }

    /* Break BEFORE every page except the first (the renderer marks them with
       .page-break). Using break-after:always instead emits a trailing blank
       page after the final sheet. */
    .page { width: 8.5in; height: 11in; overflow: hidden; }
    .page.page-break { page-break-before: always; }

    /*
     * A reliable 3x4 grid in dompdf uses a fixed table (its float layout drops
     * rows). Each cell is 2.7in tall, so four rows total 10.8in and never spill
     * onto an extra page; the cell content is sized to stay within that height.
     */
    .grid { display: table; table-layout: fixed; width: 100%; height: 10.8in; }
    .row  { display: table-row; }
    .cell {
        display: table-cell;
        width: 33.3333%;
        border: 1px dashed #adb5bd; /* gray cut guide */
        padding: 0.1in 0.16in;
        vertical-align: middle;
        text-align: center;
    }

    .cell .header {
        color: #6f42c1;
        font-weight: 700;
        font-size: 9px;
        line-height: 1.15;
        margin-bottom: 6px;
    }

    /* Barangay / Name: label sits left, the underline fills the remaining width
       so both lines end flush at the same right edge. */
    .cell .field-row { display: table; width: 100%; margin: 3px 0; }
    .cell .field-label {
        display: table-cell;
        width: 1px;            /* shrink-to-fit the label text */
        white-space: nowrap;
        text-align: left;
        font-size: 8px;
        color: #212529;
        padding-right: 3px;
    }
    .cell .field-line {
        display: table-cell;
        border-bottom: 1px solid #212529;
    }

    .cell .qr { margin: 7px 0 5px 0; }
    .cell .qr img { width: 1.4in; height: 1.4in; }

    .cell .control-label { font-size: 8px; color: #212529; margin-top: 2px; }
    .cell .control-number {
        font-size: 15px;
        font-family: "Roboto Mono", monospace;
        letter-spacing: 1px;
    }
</style>
