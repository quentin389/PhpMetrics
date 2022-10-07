<?php

$filename = 'phpmetrics/phpmetric_report.json';
//$filename = 'phpmetrics/test.json';
$data = json_decode(file_get_contents($filename), true);
$components = [];
$componentStats = [];

foreach ($data as $row) {
    $componentName = $row['@component'] ?? '';

    if (!$componentName || !isset($row['externals'])) {
        continue;
    }

    if (!isset($components[$componentName])) {
        $components[$componentName] = [];
        $componentStats[$componentName] = [
            'abstraction' => 0,
            'instability' => 0,
            'normalizedDistance' => 0,
            'classCount' => [],
            'count' => 0,
            'incomingClassDependencies' => [],
            'outputClassDependencies' => [],
        ];
    }

    if (!isset($componentStats[$componentName]['count'])) {
        $componentStats[$componentName]['abstraction'] = 0;
        $componentStats[$componentName]['instability'] = 0;
        $componentStats[$componentName]['normalizedDistance'] = 0;
        $componentStats[$componentName]['classCount'] = [];
        $componentStats[$componentName]['count'] = 0;
    }

    $componentStats[$componentName]['count']++;
    if (isset($row['abstraction'])) {
        $componentStats[$componentName]['abstraction'] += $row['abstraction'];
        $componentStats[$componentName]['instability'] += $row['instability'];
        $componentStats[$componentName]['normalizedDistance'] += $row['normalized_distance'];
        $componentStats[$componentName]['classCount'] = array_unique($componentStats[$componentName]['classCount'] + $row['classes']);
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

        $componentStats[$componentName]['outputClassDependencies'][$externalComponentName] = true;
        $componentStats[$externalComponentName]['incomingClassDependencies'][$componentName] = true;
    }
}

// RELATIONS
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

// PACKAGES

$packageTable = '';
$packageTemplate = file_get_contents('generate/packages.html');

$spots = [];
foreach ($componentStats as $component => $stats) {
    if (empty($stats['count'])) {
        continue;
    }

    $abstraction = ($stats['abstraction'] ?? 0) / $stats['count'];
    $instability = ($stats['instability'] ?? 0) / $stats['count'];
    $distance = ($stats['distance'] ?? 0) / $stats['count'];
    $normalizedDistance = ($stats['normalizedDistance'] ?? 0) / $stats['count'];
    $outgoingComponentDep = count($stats['outputClassDependencies']);
    $incomingComponentDep = count($stats['incomingClassDependencies']);
    $classCount = count($stats['classCount'] ?? []);

    $packageTable .= "
    <tr>
        <td><span class=\"path\">{$component}</span></td>
        <td>{$classCount}</td>
        <td>".number_format($abstraction)."</td>
        <td>".number_format($instability, 3)."</td>
        <td>".number_format($distance, 3)."</td>
        <td>{$incomingComponentDep}</td>
        <td>{$outgoingComponentDep}</td>
    </tr>
";

    $spots[] = [
        'name' => $component,
        'abstraction' => $abstraction,
        'instability' => $instability,
        'distance' => $distance,
        'normalizedDistance' => $normalizedDistance,
        'classCount' => $classCount,
    ];
}

$packageTemplate = str_replace('%PACKAGE_TABLE%', $packageTable, $packageTemplate);
$packageTemplate = str_replace('%PACKAGE_SPOTS%', 'var spots = ' . json_encode($spots, JSON_PRETTY_PRINT) . ';', $packageTemplate);

file_put_contents('output/packages.html', $packageTemplate);
