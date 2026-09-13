<?php
session_start();

require_once "conexao.php";

try {

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $tipo = $_POST['tipo'] ?? '';
    $valor = filter_var($_POST['valor'] ?? null, FILTER_VALIDATE_FLOAT);
    $descricao = trim($_POST['descricao'] ?? '');
    $data = $_POST['data'] ?? '';
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';

    if (!$id) {
        throw new Exception("ID inválido.");
    }
    if (!in_array($tipo, ['receita', 'despesa', 'recorrencia'], true)) {
        throw new Exception("Tipo de transação inválido.");
    }
    if ($valor === false || $valor <= 0) {
        throw new Exception("Valor inválido.");
    }
    if ($descricao === '') {
        throw new Exception("Descrição é obrigatória.");
    }

    $stmtCurrent = $pdo->prepare("SELECT * FROM lancamentos WHERE id = :id");
    $stmtCurrent->execute([':id' => $id]);
    $currentLancamento = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    if (!$currentLancamento) {
        throw new Exception("Transação não encontrada.");
    }

    $idRecorrencias = $currentLancamento['id_recorrencias'];

    $pdo->beginTransaction();

    if ($tipo === 'recorrencia') {
        if ($dataInicio === '' || $dataFim === '') {
            throw new Exception("As datas de início e fim são obrigatórias para recorrência.");
        }

        $dataInicioObj = DateTime::createFromFormat('Y-m-d', $dataInicio);
        $dataFimObj = DateTime::createFromFormat('Y-m-d', $dataFim);

        if (!$dataInicioObj || !$dataFimObj || $dataInicioObj > $dataFimObj) {
            throw new Exception("Intervalo de datas inválido.");
        }

        $data = $dataInicioObj->format('Y-m-d');

        if (!empty($idRecorrencias)) {
            $stmtRec = $pdo->prepare("
                UPDATE recorrencias 
                SET data_inicio = :data_inicio, data_final = :data_final 
                WHERE id = :id
            ");
            $stmtRec->execute([
                ':data_inicio' => $dataInicioObj->format('Y-m-d'),
                ':data_final'  => $dataFimObj->format('Y-m-d'),
                ':id'          => $idRecorrencias
            ]);
        } else {
            $stmtRec = $pdo->prepare("
                INSERT INTO recorrencias (data_inicio, data_final) 
                VALUES (:data_inicio, :data_final)
            ");
            $stmtRec->execute([
                ':data_inicio' => $dataInicioObj->format('Y-m-d'),
                ':data_final'  => $dataFimObj->format('Y-m-d')
            ]);
            $idRecorrencias = (int)$pdo->lastInsertId();
        }
    } else {
        $dataObj = DateTime::createFromFormat('Y-m-d', $data);
        if (!$dataObj || $dataObj->format('Y-m-d') !== $data) {
            throw new Exception("Data inválida.");
        }

        $hoje = new DateTime('today');
        if ($dataObj > $hoje) {
            throw new Exception("A data não pode ser no futuro.");
        }

        if ($tipo === 'despesa') {
            $saldoStmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN l.tipo = 'receita' THEN l.valor ELSE 0 END), 0) -
                    (
                        COALESCE(SUM(CASE WHEN l.tipo = 'despesa' THEN l.valor ELSE 0 END), 0) 
                        + 
                        COALESCE(SUM(
                            CASE 
                                WHEN l.tipo = 'recorrencia' AND r.data_inicio <= CURRENT_DATE()
                                THEN l.valor * (DATEDIFF(
                                    LEAST(CURRENT_DATE(), COALESCE(r.data_final, CURRENT_DATE())),
                                    r.data_inicio
                                ) + 1)
                                ELSE 0
                            END
                        ), 0)
                    ) AS saldo
                FROM lancamentos l
                LEFT JOIN recorrencias r ON l.id_recorrencias = r.id
                WHERE l.id != :id
            ");
            $saldoStmt->execute([':id' => $id]);
            $saldoAtual = (float)($saldoStmt->fetch(PDO::FETCH_ASSOC)['saldo'] ?? 0);

            if ($valor > $saldoAtual) {
                throw new Exception(
                    "Saldo insuficiente. Saldo disponível: R$ " . number_format($saldoAtual, 2, ',', '.')
                );
            }
        }

        // Se mudou de recorrência para receita/despesa, remove a recorrência antiga
        if (!empty($idRecorrencias)) {
            $oldRecId = $idRecorrencias;
            $idRecorrencias = null;
            $stmtDelRec = $pdo->prepare("DELETE FROM recorrencias WHERE id = :id");
            $stmtDelRec->execute([':id' => $oldRecId]);
        }
    }

    $sql = "UPDATE lancamentos 
            SET tipo = :tipo, valor = :valor, descricao = :descricao, data = :data, id_recorrencias = :id_recorrencias 
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tipo'            => $tipo,
        ':valor'           => $valor,
        ':descricao'       => $descricao,
        ':data'            => $data,
        ':id_recorrencias' => $idRecorrencias,
        ':id'              => $id,
    ]);

    $pdo->commit();

    $_SESSION['mensagem'] = "Transação atualizada com sucesso!";

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