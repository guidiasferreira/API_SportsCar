<?php
    declare(strict_types = 1);

    function responder(int $status, $corpo = null): void {
        http_response_code($status);

        if ($corpo !== null) {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        exit;
    }

    function erro(int $status, string $mensagem, array $campos = []): void {
        $erro = [
            "codigo" => $status, 
            "mensagem" => $mensagem
        ];

        if ($campos) {
            $erro["campos"] = $campos;
        }

        responder($status, ["erro" => $erro]);
    }

    function ler_json(): array {
        $dados_bruto = file_get_contents("php://input");

        if ($dados_bruto === false || trim($dados_bruto) === "") {
            erro(400, "Corpo da requisição vazio. Envie um JSON.");
        }

        $dados = json_decode($dados_bruto, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
            erro(400, "JSON inválido.");
        }

        return $dados;
    }

    function metodo_nao_permitido(string $permitidos): void {
        header("Allow: ". $permitidos);
        erro(405, "Método não permitido nesta rota.");
    }

    function parametro(string $nome): ?string {
        $valor = $_GET[$nome] ?? null;

        if ($valor === null || $valor === "") {
            return null;
        }

        if (!is_string($valor)) {
            erro(422, "Parâmetro inválido.", [$nome => "valor inválido"]);
        }

        return $valor;
    }

    function data_valida($texto): bool {
        if (!is_string($texto)) {
            return false;
        }

        $data = DateTime::createFromFormat("!Y-m-d", $texto);

        return $data !== false && $data->format("Y-m-d") === $texto;
    }
?>