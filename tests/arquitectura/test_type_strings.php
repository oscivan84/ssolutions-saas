<?php
/**
 * Test: Verifica que los type strings coinciden con el número de parámetros
 * en TODAS las llamadas a ejecutarConsulta()
 *
 * Ejecutar: php tests/arquitectura/test_type_strings.php
 */

$errores = 0;
$total = 0;

$archivos = array_merge(
    glob(__DIR__ . '/../../model/*.php'),
    glob(__DIR__ . '/../../services/*.php'),
    glob(__DIR__ . '/../../ajax/*.php'),
    glob(__DIR__ . '/../../api/*.php')
);

echo "=== TEST: Type String Validation ===\n\n";

foreach ($archivos as $archivo) {
    $contenido = file_get_contents($archivo);
    $nombre = basename($archivo);

    // Buscar todas las llamadas a ejecutarConsulta con type string
    // Patrón: ejecutarConsulta($sql, 'tipos', [params])
    if (preg_match_all("/ejecutarConsulta\s*\(\s*\\\$\w+\s*,\s*'([^']+)'\s*,\s*\[([^\]]*)\]/s", $contenido, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($matches as $match) {
            $types = $match[1][0];
            $paramsStr = $match[2][0];
            $offset = $match[0][1];

            // Contar types
            $typeCount = strlen($types);

            // Contar params (contar comas + 1, considerando arrays anidados)
            $paramCount = 0;
            $depth = 0;
            $inString = false;
            $chars = str_split($paramsStr);

            if (trim($paramsStr) === '') {
                $paramCount = 0;
            } else {
                $paramCount = 1;
                foreach ($chars as $c) {
                    if ($c === '(' || $c === '[') $depth++;
                    if ($c === ')' || $c === ']') $depth--;
                    if ($c === "'" || $c === '"') $inString = !$inString;
                    if ($c === ',' && $depth === 0 && !$inString) $paramCount++;
                }
            }

            // Calcular línea
            $lineNum = substr_count(substr($contenido, 0, $offset), "\n") + 1;

            $total++;
            if ($typeCount !== $paramCount) {
                echo "  [FAIL] {$nombre}:L{$lineNum} — types='{$types}' ({$typeCount}) vs params ({$paramCount})\n";
                $errores++;
            }
        }
    }
}

echo "\n--- Resultado ---\n";
echo "Queries analizadas: {$total}\n";
echo "Mismatches: {$errores}\n";
echo $errores === 0 ? "PASS: Type strings OK\n" : "FAIL: {$errores} type string mismatches\n";
exit($errores > 0 ? 1 : 0);
