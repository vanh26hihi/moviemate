<style>
    *{box-sizing:border-box}
    html,body{margin:0;background:#e8edf3;color:#111827;font-family:"Arial Narrow",Arial,sans-serif}
    .paper{position:relative;width:80mm;min-height:118mm;margin:16px auto;padding:5mm 5mm 6mm;overflow:hidden;background:#fff;font-size:10.5px;line-height:1.35;page-break-after:always;break-after:page}
    .paper:last-of-type{page-break-after:auto;break-after:auto}
    .paper::before,.paper::after{position:absolute;top:28mm;width:5mm;height:5mm;border-radius:50%;background:#e8edf3;content:""}
    .paper::before{left:-2.5mm}.paper::after{right:-2.5mm}
    .paper-brand{text-align:center;font-size:22px;font-weight:900;letter-spacing:2px}
    .paper-title{margin:1mm 0 0;text-align:center;font-size:12px;font-weight:900;letter-spacing:1.5px}
    .paper-subtitle{margin:.5mm 0 0;text-align:center;color:#475569;font-size:8.5px;font-weight:700;letter-spacing:1.3px}
    .paper-rule{height:0;margin:3mm 0;border:0;border-top:1px dashed #64748b}
    .paper-code{margin:1.5mm 0;text-align:center;font-family:ui-monospace,Consolas,monospace;font-size:11px;font-weight:800;overflow-wrap:anywhere}
    .paper-movie{margin:0;text-align:center;font-size:17px;font-weight:900;line-height:1.2;text-transform:uppercase;overflow-wrap:anywhere}
    .paper-facts{display:grid;gap:1.4mm;margin:0}
    .paper-fact{display:grid;grid-template-columns:21mm minmax(0,1fr);gap:2mm;align-items:start}
    .paper-fact dt{color:#64748b;font-size:9px;font-weight:700;text-transform:uppercase}
    .paper-fact dd{min-width:0;margin:0;text-align:right;font-weight:800;overflow-wrap:anywhere}
    .paper-seat{display:grid;grid-template-columns:1fr 1fr;gap:3mm;align-items:stretch}
    .paper-seat-box{padding:2.5mm;border:1px solid #0f172a;text-align:center}
    .paper-seat-box span{display:block;color:#64748b;font-size:8.5px;font-weight:800;text-transform:uppercase}
    .paper-seat-box strong{display:block;margin-top:.5mm;font-size:25px;line-height:1}
    .paper-price{display:flex;justify-content:space-between;gap:3mm;align-items:baseline;padding:2.5mm 0;border-top:2px solid #111827;border-bottom:2px solid #111827;font-weight:900}
    .paper-price span{font-size:9px;text-transform:uppercase}.paper-price strong{font-size:17px;white-space:nowrap}
    .paper-items{display:grid;gap:1.5mm;margin:0;padding:0;list-style:none}
    .paper-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:3mm;padding-bottom:1.5mm;border-bottom:1px dashed #cbd5e1}
    .paper-item span{font-weight:800;overflow-wrap:anywhere}.paper-item strong{white-space:nowrap}
    .paper-note{margin:3mm 0 0;padding:2.5mm;border:1px dashed #64748b;text-align:center;font-size:9px;font-weight:700}
    .paper-footer{margin-top:3mm;text-align:center;color:#475569;font-size:8.5px}
    .print-controls{width:min(80mm,calc(100% - 24px));margin:20px auto;padding:18px;border-radius:14px;background:#fff}
    .print-controls form{margin-top:14px}
    @page{size:80mm auto;margin:0}
    @media(max-width:360px){.paper{width:58mm;min-height:100mm;padding:4mm;font-size:9px}.paper-brand{font-size:18px}.paper-title{font-size:10px}.paper-movie{font-size:14px}.paper-fact{grid-template-columns:16mm minmax(0,1fr)}.paper-seat-box strong{font-size:21px}.paper-price strong{font-size:14px}}
    @media print{html,body{width:80mm;background:#fff}.paper{width:80mm;margin:0;padding:4mm 5mm 5mm}.paper::before,.paper::after{background:#fff}.print-controls{display:none!important}}
</style>
