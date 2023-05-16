<!DOCTYPE html>
<html>

<head>
    <title>Supervisor</title>
</head>

<body>
    <h1>Supervisor</h1>

    <form action="demon_supervisor_cron_linux.php" method="get">
        <button type="submit" name="action" value="start">Spustit server</button>
        <button type="submit" name="action" value="stop">Vypnout server</button>
        <button type="submit" name="action" value="vypsat">Vypsat server procesy</button>
        <button type="submit" name="action" value="vypsat2">Vypsat všechny procesy</button>
    </form>
</body>


<?php
echo "<pre>";
$osName = php_uname('s');

// Kontrola, zda je spuštěn Linux
$isLinux = (strtolower(substr($osName, 0, 5)) === 'linux');

// Výpis výsledku
echo "Operační systém: " . $osName . "\n";
echo "Linux: " . ($isLinux ? "Ano" : "Ne") . "\n";
$psOutput = shell_exec("ps 2>&1");
if (strpos($psOutput, 'PID') !== false) {
    $psSupported = true;
}

// Kontrola kompatibility pro shell_exec("php")
$phpSupported = false;
$phpOutput = shell_exec("php -v");
if (strpos($phpOutput, 'PHP') !== false) {
    $phpSupported = true;
}

// Výpis výsledků kompatibility
echo "Kompatibilita pro spuštění příkazů podporujících PHP:\n";
echo "----------------------------------------------\n";
echo "shell_exec(\"ps\"): " . ($psSupported ? "Podporováno" : "Nepodporováno") . "\n";
echo "shell_exec(\"php\"): " . ($phpSupported ? "Podporováno" : "Nepodporováno") . "\n";

// Kontrola a výpis nepodporovaných příkazů
if (!$psSupported) {
    echo "Nepodporovaný příkaz: shell_exec(\"ps\")\n";
}
if (!$phpSupported) {
    echo "Nepodporovaný příkaz: shell_exec(\"php\")\n";
}

$psSupported = false;
$psOutput = exec("ps -A");
if (!empty($psOutput)) {
    $psSupported = true;
}

// Kontrola kompatibility pro exec("php")
$phpSupported = false;
$phpOutput = exec("php -v");
if (!empty($phpOutput)) {
    $phpSupported = true;
}
echo "exec(\"ps\"): " . ($psSupported ? "Podporováno" : "Nepodporováno") . "\n";
echo "exec(\"php\"): " . ($phpSupported ? "Podporováno" : "Nepodporováno") . "\n";

// Kontrola a výpis nepodporovaných příkazů





// Kontrola a výpis nepodporovaného prostředí
if (!$isLinux) {
    die("Tento skript podporuje pouze operační systém Linux.");
}
echo "</pre>";

// Cesta k demonickému skriptu
$scriptPath = "./server2.php";
$processName = "server2.php";

// Akce získaná z GET parametru
$action = isset($_GET["action"]) ? $_GET["action"]  : "";

// Procházení procesů a jejich informací
$processes = [];
$allProcesses = [];

if ($action === "vypsat") {
    // Výpis běžících procesů serveru
    $output = shell_exec("ps -ef | grep $scriptPath");
    echo "Seznam běžících procesů server:<br>";
    echo $output;
    echo "---------------------------------<br>";
} elseif ($action === "vypsat2") {
    // Výpis všech běžících procesů
    $output = shell_exec("ps -ef");
    echo "Seznam všech běžících procesů:<br>";
    echo $output;
    echo "---------------------------------<br>";
} elseif ($action === "start") {
    // Spuštění demonického skriptu
    echo "Demonický skript byl spuštěn.";
    $outputDirectory = "/server/output/";
    $baseFileName = "output";
    $fileExtension = ".txt";

    // Vyhledání neexistujícího názvu souboru
    $counter = 1;
    $filePath = $outputDirectory . $baseFileName . $counter . $fileExtension;
    while (file_exists($filePath)) {
        $counter++;
        $filePath = $outputDirectory . $baseFileName . $counter . $fileExtension;
    }

    // Vytvoření a zapamatování souboru
    // Vytvoření a zapamatování souboru
    $fileContent = "Toto je obsah vytvořeného souboru.";
    file_put_contents($filePath, $fileContent);

    shell_exec("php $scriptPath > \"$filePath\" &");
}
?>

</html>