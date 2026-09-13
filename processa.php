<?php
session_start();

require_once "conexao.php";

try {

    $tipo = $_POST['tipo'] ?? '';
    $valor = filter_var($_POST['valor'] ?? null, FILTER_VALIDATE_FLOAT);
    $descricao = trim($_POST['descricao'] ?? '');
    $data = $_POST['data'] ?? '';

    if (!in_array($tipo, ['receita', 'despesa'], true)) {
        throw new Exception("Tipo de transação inválido.");
    }
    if ($valor === false || $valor <= 0) {
        throw new Exception("Valor inválido.");
    }
    if ($descricao === '') {
        throw new Exception("Descrição é obrigatória.");
    }

    $dataObj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dataObj || $dataObj->format('Y-m-d') !== $data) {
        throw new Exception("Data inválida.");
    }

    $hoje = new DateTime('today');
    if ($dataObj > $hoje) {
        throw new Exception("A data não pode ser no futuro.");
    }

    if ($tipo === 'despesa') {

        $saldoStmt = $pdo->query("
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
        ");
        $saldoAtual = (float) ($saldoStmt->fetch()['saldo'] ?? 0);

        if ($valor > $saldoAtual) {
            throw new Exception(
                "Saldo insuficiente. Saldo atual: R$ " . number_format($saldoAtual, 2, ',', '.')
            );
        }
    }

    $sql = "INSERT INTO lancamentos (tipo, valor, descricao, data) 
            VALUES (:tipo, :valor, :descricao, :data)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tipo' => $tipo,
        ':valor' => $valor,
        ':descricao' => $descricao,
        ':data' => $data,
    ]);

    $_SESSION['mensagem'] = "Transação cadastrada com sucesso!";

} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
} catch (PDOException $e) {
    $_SESSION['erro'] = "Erro no banco de dados: " . $e->getMessage();
}

header("Location: index.php");
exit;