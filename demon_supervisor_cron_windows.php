<!DOCTYPE html>
<html>

<head>
    <title>Supervisor</title>
</head>

<body>
    <h1>Supervisor</h1>

    <form action="demon_supervisor_cron_windows.php" method="get">
        <button type="submit" name="action" value="start">Spustit server</button>
        <button type="submit" name="action" value="stop">Vypnout server</button>
        <button type="submit" name="action" value="vypsat">Vypsat server procesy</button>
        <button type="submit" name="action" value="vypsat2">Vypsat všechny procesy</button>
    </form>
</body>


<?php
echo "<pre>";
$osName = php_uname('s');

// Kontrola, zda je spuštěn Windows
$isWindows = (strtolower(substr($osName, 0, 3)) === 'win');

// Výpis výsledku
echo "Operační systém: " . $osName . "\n";
echo "Windows: " . ($isWindows ? "Ano" : "Ne") . "\n";
// Kontrola kompatibility pro shell_exec("wmic")
$wmicSupported = false;
$wmicOutput = shell_exec("wmic 2>&1");
if (strpos($wmicOutput, 'wmic') !== false) {
    $wmicSupported = true;
}

// Kontrola kompatibility pro shell_exec("taskkill")
$taskkillSupported = false;
$taskkillOutput = shell_exec("taskkill /?");
if (strpos($taskkillOutput, 'TASKKILL') !== false) {
    $taskkillSupported = true;
}

// Výpis výsledků kompatibility
echo "Kompatibilita pro spuštění příkazů podporujících PHP:\n";
echo "----------------------------------------------\n";
echo "shell_exec(\"wmic\"): " . ($wmicSupported ? "Podporováno" : "Nepodporováno") . "\n";
echo "shell_exec(\"taskkill\"): " . ($taskkillSupported ? "Podporováno" : "Nepodporováno") . "\n";

// Kontrola a výpis nepodporovaných příkazů
if (!$wmicSupported) {
    echo "Nepodporovaný příkaz: shell_exec(\"wmic\")\n";
}
if (!$taskkillSupported) {
    echo "Nepodporovaný příkaz: shell_exec(\"taskkill\")\n";
}

// Vypnutí skriptu, pokud některý příkaz není podporován
if (!$wmicSupported || !$taskkillSupported) {
    die("Některý z příkazů není podporován v aktuálním prostředí.");
}
// Vypnutí skriptu, pokud není spuštěn Windows
if (!$isWindows) {
    die("Tento skript podporuje pouze operační systém Windows.");
}
echo "</pre>";
// Cesta k demonickému skriptu
$scriptPath = "./server2.php";
// Zvolit příkaz pro kontrolu běžícího procesu

// Akce získaná z GET parametru
$action = isset($_GET["action"]) ? $_GET["action"]  : "";
// Získání seznamu všech procesů a jejich informací
$output = shell_exec("wmic process get ProcessId,Name,CommandLine /FORMAT:CSV");
$lines = explode("\n", trim($output));

// Načtení hlavičky
$header = str_getcsv(array_shift($lines));

// Procházení procesů a jejich informací
$scriptPath = "./server2.php";
$action = isset($_GET["action"]) ? $_GET["action"] : "";

$processes = [];
$allProcesses = [];
foreach ($lines as $line) {
    $data = str_getcsv($line);

    if (count($data) === count($header)) {
        $processInfo = array_combine($header, $data);

        $allProcesses[] = $processInfo;
        if (isset($processInfo["CommandLine"]) && strpos($processInfo["CommandLine"], $scriptPath) !== false) {
            $processes[] = $processInfo;

            if ($action === "stop") {
                // Zastavení procesu
                $processId = $processInfo["ProcessId"];
                shell_exec("taskkill /PID $processId /F");
                echo "Proces s ID $processId byl zastaven.\n";
            }
        } else {
        }
    }

    // echo "---------------------------------<br>";
}

if ($action === "start" && empty($processes)) {
    // Spuštění demonického skriptu
    echo "Demonický skript byl spuštěn.";
    $outputDirectory = "C:/server/output/";
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
    $fileContent = "Toto je obsah vytvořeného souboru.";
    file_put_contents($filePath, $fileContent);

    shell_exec("start /B php $scriptPath > \"$filePath\"");
}

if ($action === "vypsat") {
    echo "Seznam běžících procesů server:<br>";
    foreach ($processes as $process) {
        echo "Process ID: " . $process['ProcessId'] . "<br>";
        echo "Name: " . $process['Name'] . "<br>";
        echo "Command Line: " . $process['CommandLine'] . "<br>";
        echo "---------------------------------<br>";
    }
}
if ($action === "vypsat2") {
    echo "Seznam všech běžících procesů:<br>";
    foreach ($allProcesses as $process) {
        echo "Process ID: " . $process['ProcessId'] . "<br>";
        echo "Name: " . $process['Name'] . "<br>";
        echo "Command Line: " . $process['CommandLine'] . "<br>";
        echo "---------------------------------<br>";
    }
}
?>

</html>