<?php
    declare(strict_types = 1);

    const COLUNAS_VEICULO = "ID_VEICULO, MARCA, MODELO, ANO_FABRICACAO, POTENCIA_CV, PLACA, CRIADO_EM";

    $pdo = conectar();

    if ($acao !== null) {
        erro(404, "Rota não encontrada.");
    }

    if ($id === null) {
        switch ($metodo) {
            case "GET": listar_veiculos($pdo); break;
            case "POST": criar_veiculo($pdo); break;
            default: metodo_nao_permitido("GET, POST");
        }

    } else {
        switch ($metodo) {
            case "GET": responder(200, ["veiculos" => obter_veiculo($pdo, $id)]); break;
            case "PUT": atualizar_veiculo($pdo, $id); break;
            case "DELETE": excluir_veiculo($pdo, $id); break;
            default: metodo_nao_permitido("GET, PUT, DELETE");
        }
    }

    function listar_veiculos(PDO $pdo): void {
        $sql = "SELECT ". COLUNAS_VEICULO ." FROM VEICULOS";
        $params = [];

        $marca = $_GET["marca"] ?? null;
        if (is_string($marca) && $marca !== "") {
            $sql .= " WHERE MARCA = :marca";
            $params["marca"] = $marca;
        }

        $stmt = $pdo -> prepare($sql ." ORDER BY ID_VEICULO");
        $stmt -> execute($params);
        $veiculos = $stmt -> fetchAll();

        responder(200, ["veiculos" => $veiculos, "total" => count($veiculos)]);
    }

    function obter_veiculo(PDO $pdo, int $id): array {
        $stmt = $pdo -> prepare("SELECT ". COLUNAS_VEICULO ." FROM VEICULOS WHERE ID_VEICULO = :id");
        $stmt -> execute(["id" => $id]);
        $veiculo = $stmt->fetch();

        if ($veiculo === false) {
            erro(404, "Veículo não encontrado.");
        }

        return $veiculo;
    }

    function criar_veiculo(PDO $pdo): void {
        [$veiculo, $erros] = validar_veiculo(ler_json());
        if ($erros) {
            erro(422, "Dados inválidos.", $erros);
        }

        $sql = "INSERT INTO VEICULOS (MARCA, MODELO, ANO_FABRICACAO, POTENCIA_CV, PLACA) VALUES (:marca, :modelo, :ano_fabricacao, :potencia_cv, :placa)";

        try {
            $pdo -> prepare($sql) -> execute($veiculo);

        } catch (PDOException $e) {
            tratar_erro_veiculo($e);
        }

        $novo_id = (int) $pdo -> lastInsertId();

        header("Location: ". dirname($_SERVER["SCRIPT_NAME"]) ."/v1/veiculos/". $novo_id);
        responder(201, ["veiculos" => obter_veiculo($pdo, $novo_id)]);
    }

    function atualizar_veiculo(PDO $pdo, int $id): void {
        obter_veiculo($pdo, $id); 

        [$veiculo, $erros] = validar_veiculo(ler_json());
        if ($erros) {
            erro(422, "Dados inválidos.", $erros);
        }

        $sql = "UPDATE VEICULOS SET MARCA = :marca, MODELO = :modelo, ANO_FABRICACAO = :ano_fabricacao, POTENCIA_CV = :potencia_cv, PLACA = :placa WHERE ID_VEICULO = :id";

        try {
            $pdo -> prepare($sql) -> execute($veiculo + ["id" => $id]);

        } catch (PDOException $e) {
            tratar_erro_veiculo($e);
        }

        responder(200, ["veiculos" => obter_veiculo($pdo, $id)]);
    }

    function excluir_veiculo(PDO $pdo, int $id): void {
        obter_veiculo($pdo, $id); 

        $pdo -> prepare("DELETE FROM VEICULOS WHERE ID_VEICULO = :id") -> execute(["id" => $id]);

        responder(204);
    }


    function validar_veiculo(array $d): array {
        $erros = [];

        $marca = is_string($d["marca"] ?? null) ? trim($d["marca"]) : "";
        if ($marca === "" || mb_strlen($marca) > 50) {
            $erros["marca"] = "obrigatório, texto de até 50 caracteres";
        }

        $modelo = is_string($d["modelo"] ?? null) ? trim($d["modelo"]) : "";
        if ($modelo === "" || mb_strlen($modelo) > 80) {
            $erros["modelo"] = "obrigatório, texto de até 80 caracteres";
        }

        $anoMaximo = (int) date("Y") + 1;
        $ano = $d["ano_fabricacao"] ?? null;
        if (!is_int($ano) || $ano < 1950 || $ano > $anoMaximo) {
            $erros["ano_fabricacao"] = "obrigatório, número inteiro entre 1950 e $anoMaximo";
        }

        $potencia = $d["potencia_cv"] ?? null;
        if (!is_int($potencia) || $potencia < 1 || $potencia > 3000) {
            $erros["potencia_cv"] = "obrigatório, número inteiro entre 1 e 3000";
        }

        $placa = $d["placa"] ?? null;

        if ($placa !== null) {
            if (!is_string($placa)) {
                $erros["placa"] = "deve ser um texto";

            } else {
                $placa = strtoupper(trim($placa));

                if ($placa === "") {
                    $placa = null;

                } elseif (mb_strlen($placa) > 8) {
                    $erros["placa"] = "texto de até 8 caracteres";
                }
            }
        }

        $veiculos = [
            "marca" => $marca,
            "modelo" => $modelo,
            "ano_fabricacao" => $ano,
            "potencia_cv" => $potencia,
            "placa" => $placa,
        ];

        return [$veiculos, $erros];
    }

    function tratar_erro_veiculo(PDOException $e): void {
        if ((int) ($e -> errorInfo[1] ?? 0) === 1062) {
            erro(409, "Já existe um veículo com esta placa.", ["placa" => "já cadastrada"]);
        }

        throw $e; 
    }
?>