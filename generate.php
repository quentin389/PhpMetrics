<?php

$filename = 'phpmetrics/phpmetric_report.json';
//$filename = 'phpmetrics/test.json';
$data = json_decode(file_get_contents($filename), true);
$components = [];

foreach ($data as $row) {
    $componentName = $row['@component'] ?? '';

    if (!$componentName || !isset($row['externals'])) {
        continue;
    }

    if (!isset($components[$componentName])) {
        $components[$componentName] = [];
    }

    foreach ($row['externals'] as $external) {
        if (!isset($data[$external])) {
            continue;
        }

        $externalComponentName = $data[$external]['@component'] ?? '';

        if (!$externalComponentName) {
            continue;
        }

        if (!isset($components[$externalComponentName])) {
            $components[$externalComponentName] = [];
        }
        $components[$componentName][$externalComponentName] = true;
    }
}
var_dump(count($components));
$relationTemplate = file_get_contents('generate/relations.html');

$variables = [];

foreach ($components as $component => $dependency) {
    $variables[] = [
        'name' => $component,
        'size' => 1,
        'relations' => array_keys($dependency),
    ];
}

file_put_contents('output/relations.html', str_replace('%RELATIONS%', 'var relations = ' . json_encode($variables, JSON_PRETTY_PRINT) . ';', $relationTemplate));
