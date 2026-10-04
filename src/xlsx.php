<?php
// Génération d'un classeur Excel (.xlsx) d'une seule feuille, sans dépendance :
// le fichier est une archive ZIP de quelques fichiers XML, assemblée ici à la main.

/**
 * Envoie un classeur au navigateur et termine la requête.
 * @param array<int, array<int, string|int|float|array|null>> $rows première ligne = en-tête (gras, figée) ;
 *        une cellule ['f' => formule sans « = », 'v' => valeur pré-calculée] est une formule (affichée avec 1 décimale)
 * @param array<int, float> $widths largeur de chaque colonne (en caractères)
 */
function xlsx_download(string $filename, string $sheetName, array $rows, array $widths = []): void
{
    $data = xlsx_build($sheetName, $rows, $widths);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: no-store');
    echo $data;
    exit;
}

function xlsx_build(string $sheetName, array $rows, array $widths = []): string
{
    $x = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sheetRows = '';
    foreach (array_values($rows) as $r => $row) {
        $cells = '';
        $style = $r === 0 ? ' s="1"' : '';
        foreach (array_values($row) as $c => $value) {
            $ref = xlsx_col($c) . ($r + 1);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $cached = isset($value['v']) ? "<v>{$value['v']}</v>" : '';
                $cells .= "<c r=\"{$ref}\" s=\"2\"><f>{$x($value['f'])}</f>{$cached}</c>";
                continue;
            }
            $cells .= is_int($value) || is_float($value)
                ? "<c r=\"{$ref}\"{$style}><v>{$value}</v></c>"
                : "<c r=\"{$ref}\"{$style} t=\"inlineStr\"><is><t xml:space=\"preserve\">{$x((string) $value)}</t></is></c>";
        }
        $sheetRows .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
    }
    $cols = '';
    foreach (array_values($widths) as $i => $w) {
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    }

    $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    return zip_build([
        '[Content_Types].xml' => $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $rel . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $rel . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="' . $rel . '/styles" Target="styles.xml"/></Relationships>',
        'xl/workbook.xml' => $head . "<workbook {$ns} xmlns:r=\"{$rel}\"><sheets>"
            . '<sheet name="' . $x(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
        // Style 0 : normal ; style 1 : gras (en-tête) ; style 2 : nombre à 1 décimale (formules)
        'xl/styles.xml' => $head . "<styleSheet {$ns}>"
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $head . "<worksheet {$ns}>"
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols ? "<cols>{$cols}</cols>" : '')
            . "<sheetData>{$sheetRows}</sheetData></worksheet>",
    ]);
}

/** Lettre(s) de colonne Excel à partir d'un index commençant à 0 (0 → A, 26 → AA). */
function xlsx_col(int $i): string
{
    $name = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
        $name = chr(65 + ($i - 1) % 26) . $name;
    }
    return $name;
}

/** Archive ZIP (méthode deflate) à partir de [chemin => contenu]. */
function zip_build(array $files): string
{
    $zip = '';
    $central = '';
    [$time, $date] = (function (): array {
        $t = getdate();
        return [($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
                (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday']];
    })();
    foreach ($files as $name => $content) {
        $deflated = gzdeflate($content);
        $common = pack('vvvvvVVV', 20, 0x0800, 8, $time, $date, crc32($content), strlen($deflated), strlen($content))
            . pack('v', strlen($name));
        $offset = strlen($zip);
        $zip .= "PK\x03\x04" . $common . pack('v', 0) . $name . $deflated;
        $central .= "PK\x01\x02" . pack('v', 20) . $common . pack('vvvvVV', 0, 0, 0, 0, 0, $offset) . $name;
    }
    return $zip . $central
        . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($central), strlen($zip), 0);
}
