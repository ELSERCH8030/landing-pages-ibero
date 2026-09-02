<?php
// Archivo temporal de diagnóstico. Borrar cuando el conector esté funcionando.
header("Content-Type: text/plain; charset=utf-8");
echo "PHP OK - version " . PHP_VERSION . "\n";
echo "curl: " . (function_exists("curl_init") ? "si" : "no") . "\n";
echo "allow_url_fopen: " . (ini_get("allow_url_fopen") ? "si" : "no") . "\n";
echo "openssl: " . (extension_loaded("openssl") ? "si" : "no") . "\n";
echo "json: " . (function_exists("json_encode") ? "si" : "no") . "\n";
echo "docroot: " . $_SERVER["DOCUMENT_ROOT"] . "\n";
echo "script: " . __FILE__ . "\n";
$fuera = dirname($_SERVER["DOCUMENT_ROOT"]) . "/prospecty-config.php";
echo "config fuera de docroot ($fuera): " . (is_readable($fuera) ? "existe" : "no existe") . "\n";
echo "env GHL_TOKEN: " . (getenv("GHL_TOKEN") ? "definida" : "no definida") . "\n";
$ctx = stream_context_create(["http" => ["method" => "GET", "timeout" => 10, "ignore_errors" => true]]);
$r = @file_get_contents("https://services.leadconnectorhq.com/contacts/?limit=1", false, $ctx);
echo "salida https a HighLevel: " . ($r === false ? "FALLO" : "ok (" . substr($r, 0, 60) . ")") . "\n";
