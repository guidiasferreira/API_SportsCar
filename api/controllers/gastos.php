<?php
    declare(strict_types = 1);

    const COLUNAS_GASTO = "ID_GASTO, ID_VEICULO, TIPO, DESCRICAO, VALOR, DATA_GASTO, KM, CRIADO_EM";
    const TIPOS_GASTO = ["manutencao", "abastecimento", "seguro", "ipva", "licenciamento", "estacionamento", "outros"];

    $pdo = conectar();

    if ($acao !== null) {
        erro(404, "Rota não encontrada.");
    }

    if ($id === null) {
        switch ($metodo) {
            case "GET": listar_gastos($pdo); break;
            case "POST": criar_gasto($pdo); break;
            default: metodo_nao_permitido("GET, POST");
        }

    } else {
        switch ($metodo) {
            case "GET": responder(200, ["gastos" => formatar_gasto(obter_gasto($pdo, $id))]); break;
            case "PUT": atualizar_gasto($pdo, $id); break;
            case "DELETE": excluir_gasto($pdo, $id); break;
            default: metodo_nao_permitido("GET, PUT, DELETE");
        }
    }

    function listar_gastos(PDO $pdo): void {
        $condicoes = [];
        $params = [];

        $idVeiculo = parametro("id_veiculo");
        if ($idVeiculo !== null) {
            if (!ctype_digit($idVeiculo)) {
                erro(422, "Parâmetro inválido.", ["id_veiculo" => "deve ser um número inteiro"]);
            }

            $condicoes[] = "ID_VEICULO = :id_veiculo";
            $params["id_veiculo"] = (int) $idVeiculo;
        }

        $tipo = parametro("tipo");
        if ($tipo !== null) {
            if (!in_array($tipo, TIPOS_GASTO, true)) {
                erro(422, "Parâmetro inválido.", ["tipo" => "use um de: ". implode(",", TIPOS_GASTO)]);
            }

            $condicoes[] = "TIPO = :tipo";
            $params["tipo"] = $tipo;
        }

        $inicio = parametro("inicio");
        if ($inicio !== null) {
            if (!data_valida($inicio)) {
                erro(422, "Parâmetro inválido.", ["inicio" => "use o formato AAAA-MM-DD com uma data existente"]);
            }

            $condicoes[] = "DATA_GASTO >= :inicio";
            $params["inicio"] = $inicio;
        }

        $fim = parametro("fim");
        if ($fim !== null) {
            if (!data_valida($fim)) {
                erro(422, "Parâmetro inválido.", ["fim" => "use o formato AAAA-MM-DD com uma data existente"]);
            }

            $condicoes[] = "DATA_GASTO <= :fim";
            $params["fim"] = $fim;
        }

        if ($inicio !== null && $fim !== null && $inicio > $fim) {
            erro(422, "Período inválido.", ["inicio" => "não pode ser depois de fim"]);
        }

        $sql = "SELECT ". COLUNAS_GASTO ." FROM GASTOS";
        if ($condicoes) {
            $sql .= " WHERE ". implode(" AND ", $condicoes);
        }
        
        $sql .= " ORDER BY ID_GASTO ASC, DATA_GASTO ASC";

        $stmt = $pdo -> prepare($sql);
        $stmt -> execute($params);
        $gastos = array_map("formatar_gasto", $stmt -> fetchAll());

        responder(200, ["gastos" => $gastos, "total" => count($gastos)]);
    }

    function obter_gasto(PDO $pdo, int $id): array {
        $stmt = $pdo -> prepare("SELECT ". COLUNAS_GASTO ." FROM GASTOS WHERE ID_GASTO = :id");
        $stmt -> execute(["id" => $id]);
        $gasto = $stmt -> fetch();

        if ($gasto === false) {
            erro(404, "Gasto não encontrado.");
        }

        return $gasto;
    }

    function criar_gasto(PDO $pdo): void {
        [$gasto, $erros] = validar_gasto(ler_json());
        if ($erros) {
            erro(422, "Dados inválidos.", $erros);
        }

        $sql = "INSERT INTO GASTOS (ID_VEICULO, TIPO, DESCRICAO, VALOR, DATA_GASTO, KM) VALUES (:id_veiculo, :tipo, :descricao, :valor, :data_gasto, :km)";

        try {
            $pdo -> prepare($sql) -> execute($gasto);

        } catch (PDOException $e) {
            tratar_erro_gasto($e);
        }

        $novo_id = (int) $pdo -> lastInsertId();

        header("Location: ". dirname($_SERVER["SCRIPT_NAME"]) ."/v1/gastos/". $novo_id);
        responder(201, ["gastos" => formatar_gasto(obter_gasto($pdo, $novo_id))]);
    }

    function atualizar_gasto(PDO $pdo, int $id): void {
        obter_gasto($pdo, $id);

        [$gasto, $erros] = validar_gasto(ler_json());
        if ($erros) {
            erro(422, "Dados inválidos.", $erros);
        }

        $sql = "UPDATE GASTOS SET ID_VEICULO = :id_veiculo, TIPO = :tipo, DESCRICAO = :descricao, VALOR = :valor, DATA_GASTO = :data_gasto, KM = :km WHERE ID_GASTO = :id";

        try {
            $pdo -> prepare($sql) -> execute($gasto + ["id" => $id]);

        } catch (PDOException $e) {
            tratar_erro_gasto($e);
        }

        responder(200, ["gastos" => formatar_gasto(obter_gasto($pdo, $id))]);
    }

    function excluir_gasto(PDO $pdo, int $id): void {
        obter_gasto($pdo, $id);

        $pdo -> prepare("DELETE FROM GASTOS WHERE ID_GASTO = :id") -> execute(["id" => $id]);

        responder(204);
    }

    function validar_gasto(array $d): array {
        $erros = [];

        $idVeiculo = $d["id_veiculo"] ?? null;
        if (!is_int($idVeiculo) || $idVeiculo < 1) {
            $erros["id_veiculo"] = "obrigatório, número inteiro positivo";
        }

        $tipo = $d["tipo"] ?? null;
        if (!is_string($tipo) || !in_array($tipo, TIPOS_GASTO, true)) {
            $erros["tipo"] = "obrigatório, um de: ". implode(", ", TIPOS_GASTO);
        }

        $descricao = $d["descricao"] ?? null;
        if ($descricao !== null) {
            if (!is_string($descricao) || mb_strlen(trim($descricao)) > 255) {
                $erros["descricao"] = "texto de até 255 caracteres";

            } else {
                $descricao = trim($descricao);

                if ($descricao === "") {
                    $descricao = null;
                }
            }
        }

        $valor = $d["valor"] ?? null;
        if ((!is_int($valor) && !is_float($valor)) || $valor <= 0 || $valor > 99999999.99 || abs($valor * 100 - round($valor * 100)) > 0.0001) {
            $erros["valor"] = "obrigatório, número maior que zero com no máximo 2 casas decimais";

        } else {
            $valor = number_format($valor, 2, ".", "");
        }

        $data = $d["data_gasto"] ?? null;
        if (!data_valida($data)) {
            $erros["data_gasto"] = "obrigatório, formato AAAA-MM-DD com uma data existente";
        }

        $km = $d["km"] ?? null;
        if ($km !== null && (!is_int($km) || $km < 0 || $km > 9999999)) {
            $erros["km"] = "número inteiro entre 0 e 9999999";
        }

        $gasto = [
            "id_veiculo" => $idVeiculo,
            "tipo" => $tipo,
            "descricao" => $descricao,
            "valor" => $valor,
            "data_gasto" => $data,
            "km" => $km,
        ];

        return [$gasto, $erros];
    }

    function formatar_gasto(array $gasto): array {
        $gasto["valor"] = (float) $gasto["valor"];

        return $gasto;
    }

    function tratar_erro_gasto(PDOException $e): void {
        if ((int) ($e -> errorInfo[1] ?? 0) === 1452) {
            erro(422, "Dados inválidos.", ["id_veiculo" => "veículo inexistente"]);
        }

        throw $e;
    }