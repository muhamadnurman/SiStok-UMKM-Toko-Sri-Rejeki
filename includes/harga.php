<?php
function parseHarga($nilai): float
{
    $v = preg_replace('/[^\d.,]/', '', trim((string) $nilai));
    if ($v === '' || $v === null) {
        return 0.0;
    }
    if (strpos($v, ',') !== false) {
        // 1.234,50 -> titik = ribuan, koma = desimal
        $v = str_replace(',', '.', str_replace('.', '', $v));
    } elseif (!preg_match('/^\d+\.\d{1,2}$/', $v)) {
        // 72.000 / 1.234.567 -> titik = ribuan
        // (72000.00 dibiarkan: itu format desimal dari database)
        $v = str_replace('.', '', $v);
    }
    return (float) $v;
}

function formatSatuan($nilai): string
{
    $v = trim(preg_replace('/\s+/', ' ', (string) $nilai));
    if ($v === '') {
        return 'Pcs';
    }
    return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
}