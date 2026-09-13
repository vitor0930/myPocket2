<?php
session_start();

require_once "conexao.php";

try {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        throw new Exception("ID inválido.");
    }

    $stmt = $pdo->prepare("SELECT id_recorrencias FROM lancamentos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $lanc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lanc) {
        throw new Exception("Transação não encontrada.");
    }

    $pdo->beginTransaction();

    $stmtDel = $pdo->prepare("DELETE FROM lancamentos WHERE id = :id");
    $stmtDel->execute([':id' => $id]);

    if (!empty($lanc['id_recorrencias'])) {
        $stmtRec = $pdo->prepare("DELETE FROM recorrencias WHERE id = :id");
        $stmtRec->execute([':id' => $lanc['id_recorrencias']]);
    }

    $pdo->commit();

    $_SESSION['mensagem'] = "Transação excluída com sucesso!";

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