<?php

// run_tests.php

// Definir la ubicación del archivo log
$logFile = 'test_results_'.date('Ymd_His').'.log';

// Función para ejecutar comandos y registrar la salida
function runCommand($command, $logFile)
{
    echo "Ejecutando: $command\n";
    $output = [];
    $returnVar = 0;
    exec("$command 2>&1", $output, $returnVar);

    // Guardar en el archivo log
    file_put_contents($logFile, "Ejecutando: $command\n", FILE_APPEND);
    file_put_contents($logFile, implode("\n", $output)."\n", FILE_APPEND);
    file_put_contents($logFile, "===============================================\n", FILE_APPEND);

    // Mostrar en la consola
    echo implode("\n", $output)."\n";
    echo "===============================================\n";

    return $returnVar;
}

// Iniciar el archivo log
file_put_contents($logFile, 'Iniciando pruebas del sistema BCommerce a '.date('Y-m-d H:i:s')."\n");
file_put_contents($logFile, "===============================================\n", FILE_APPEND);

echo "Iniciando pruebas del sistema BCommerce...\n";
echo "===============================================\n";

// Ejecutar todas las pruebas
$tests = [
    'tests/Unit' => 'Ejecutando todas las pruebas unitarias',
    'tests/Unit/Services' => 'Ejecutando pruebas de servicios',
    'tests/Unit/Services/RecommendationServiceTest.php' => 'Ejecutando prueba del servicio de recomendación',
    'tests/Unit/UseCases/Recommendation' => 'Ejecutando pruebas de casos de uso de recomendación',
    'tests/Unit/Middleware' => 'Ejecutando pruebas de middleware',
    'tests/Feature' => 'Ejecutando pruebas de característica',
    'tests/Feature/ProductRepositoryTest.php' => 'Ejecutando prueba del repositorio de productos',
    'tests/Feature/ChatRepositoryTest.php' => 'Ejecutando prueba del repositorio de chat',
    'tests/Feature/Auth' => 'Ejecutando pruebas de autenticación',
];

foreach ($tests as $testPath => $description) {
    file_put_contents($logFile, "$description...\n", FILE_APPEND);
    echo "$description...\n";

    $result = runCommand("php artisan test $testPath", $logFile);

    if ($result !== 0) {
        file_put_contents($logFile, "⚠️ WARNING: El comando anterior falló con código de salida $result\n", FILE_APPEND);
        echo "⚠️ WARNING: El comando anterior falló con código de salida $result\n";
    }
}

// Mostrar un resumen
$message = "Todas las pruebas han sido ejecutadas y los resultados se han guardado en $logFile";
file_put_contents($logFile, "$message\n", FILE_APPEND);
file_put_contents($logFile, "Para ver un informe detallado, ejecuta: php artisan test --coverage\n", FILE_APPEND);

echo "$message\n";
echo "Para ver un informe detallado, ejecuta: php artisan test --coverage\n";

echo "Contenido del log:\n";
echo file_get_contents($logFile);
