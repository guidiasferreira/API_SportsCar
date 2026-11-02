<?php
    declare(strict_types = 1);

    require "./database.php";
    require "./auxiliar.php";

    ini_set("display_errors", "0");

    $metodo  = $_SERVER["REQUEST_METHOD"];
    $caminho = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $caminho = substr($caminho, strlen(dirname($_SERVER["SCRIPT_NAME"])));
    $partes  = explode("/", trim($caminho, "/"));

    $versao  = $partes[0] ?? "";
    $recurso = $partes[1] ?? "";
    $acao = $partes[3] ?? null;
    $id = null;                 

    if (isset($partes[2])) {
        if (!ctype_digit($partes[2])) {
            erro(404, "Rota não encontrada.");
        }

        $id = (int) $partes[2];
    }


    try {
        if ($versao !== "v1") {
            erro(404, "Rota não encontrada.");
        }

        switch ($recurso) {
            case "veiculos":
                require "./controllers/veiculos.php";
                break;
            
            case "gastos":
                require "./controllers/gastos.php";
                break;
                
            default:
                erro(404, "Rota não encontrada.");
        }

    } catch (PDOException $erro) {
        error_log("Erro no banco: ". $erro -> getMessage());
        erro(500, "Erro ao acessar o banco de dados.");

    } catch (Throwable $erro) {
        error_log("Erro interno: ". $erro -> getMessage());
        erro(500, "Erro interno do servidor.");
    }

?>