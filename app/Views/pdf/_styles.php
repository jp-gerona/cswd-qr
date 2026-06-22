<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Roboto", sans-serif; line-height: 1.5; }
    .page { width: 8.5in; height: 11in; page-break-after: always; }
    .grid { display: table; width: 100%; height: 100%; }
    .row { display: table-row; }
    .cell {
        display: table-cell;
        width: 33.3333%;
        height: 25%;
        border: 1px dashed #adb5bd; /* gray cut guide */
        padding: 8px 10px;
        vertical-align: top;
        text-align: center;
    }
    .cell .header { color: #6f42c1; font-weight: 700; font-size: 10px; text-align: center; }
    .cell .field  { font-size: 8px; color: #212529; text-align: left; }
    .cell .field .line { display: inline-block; border-bottom: 1px solid #212529; width: 70%; }
    .cell .qr img { width: 1.1in; height: 1.1in; }
    .cell .control-label { font-size: 8px; color: #212529; }
    .cell .control-number { font-size: 14px; font-family: "DejaVu Sans Mono", monospace; }
</style>