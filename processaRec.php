<?php
session_start();

require_once "conexao.php";
require_once "classes/Recorrencia.php";
require_once "classes/Lancamento.php";

try {
    $descricao = trim($_POST["descricao"] ?? '');
    $valor = filter_var($_POST['valor'] ?? null, FILTER_VALIDATE_FLOAT);
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $tipo = 'recorrencia';

    if ($tipo !== 'recorrencia') {
        throw new Exception("Tipo de transação inválido.");
    }
    if ($valor === false || $valor <= 0) {
        throw new Exception("Valor inválido.");
    }
    if ($descricao === '') {
        throw new Exception("Descrição é obrigatória.");
    }

    if ($dataInicio === "" || $dataFim === "") {
        throw new Exception("As datas são obrigatórias.");
    }

    $dataInicioObj = DateTime::createFromFormat('Y-m-d', $dataInicio);
    $dataFimObj = DateTime::createFromFormat('Y-m-d', $dataFim);

    if (!$dataInicioObj || !$dataFimObj || $dataInicioObj > $dataFimObj) {
        throw new Exception("Intervalo de datas inválido.");
    }

    $pdo->beginTransaction();

    $sql = "INSERT INTO recorrencias (data_inicio, data_final) VALUES (:data_inicio, :data_final)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':data_inicio' => $dataInicioObj->format('Y-m-d'),
        ':data_final'  => $dataFimObj->format('Y-m-d')
    ]);
    $recorrenciaId = (int)$pdo->lastInsertId();

    $sql = "INSERT INTO lancamentos (descricao, valor, data, id_recorrencias, tipo) VALUES (:descricao, :valor, :data, :recorrencia_id, :tipo)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':descricao'      => $descricao,
        ':valor'          => $valor,
        ':data'           => $dataInicioObj->format('Y-m-d'),
        ':recorrencia_id' => $recorrenciaId,
        ':tipo'           => $tipo
    ]);
    $lancamentoId = (int)$pdo->lastInsertId();

    $pdo->commit();

    $recorrencia = new Recorrencia($lancamentoId, $descricao, $valor, $dataInicioObj, $dataFimObj);

    $_SESSION['mensagem'] = "Despesa recorrente cadastrada com sucesso!";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['erro'] = $e->getMessage();
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['erro'] = "Erro no banco de dados: " . $e->getMessage();
}

header("Location: index.php");
exit;