<?php
session_start();

require_once "conexao.php";

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['erro'] = "ID inválido.";
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT l.*, r.data_inicio, r.data_final 
    FROM lancamentos l
    LEFT JOIN recorrencias r ON l.id_recorrencias = r.id
    WHERE l.id = :id
");
$stmt->execute([':id' => $id]);
$transacao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transacao) {
    $_SESSION['erro'] = "Transação não encontrada.";
    header("Location: index.php");
    exit;
}

$isRecorrencia = ($transacao['tipo'] === 'recorrencia');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyPocket - Editar Transação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4" style="max-width: 650px;">

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h1 class="h4 mb-0 text-primary">
                <i class="bi bi-pencil-square me-2"></i>Editar Transação
            </h1>
        </div>
        <div class="card-body p-4">

            <form action="update.php" method="POST">

                <input type="hidden" name="id" value="<?= $transacao['id'] ?>">

                <div class="mb-3">
                    <label class="form-label fw-medium">Descrição</label>
                    <input type="text" name="descricao" class="form-control"
                           value="<?= htmlspecialchars($transacao['descricao']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">
                        <?= $isRecorrencia ? 'Valor por Dia (R$)' : 'Valor (R$)' ?>
                    </label>
                    <input type="number" step="0.01" min="0.01" name="valor" class="form-control"
                           value="<?= htmlspecialchars((string)$transacao['valor']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Tipo</label>
                    <select name="tipo" id="tipoSelect" class="form-select" required>
                        <option value="receita" <?= $transacao['tipo'] === 'receita' ? 'selected' : '' ?>>Receita (Entrada)</option>
                        <option value="despesa" <?= $transacao['tipo'] === 'despesa' ? 'selected' : '' ?>>Despesa (Saída)</option>
                        <option value="recorrencia" <?= $isRecorrencia ? 'selected' : '' ?>>Despesa Recorrente (Diária)</option>
                    </select>
                </div>

                <div id="campoDataNormal" class="mb-3" style="<?= $isRecorrencia ? 'display: none;' : '' ?>">
                    <label class="form-label fw-medium">Data</label>
                    <input type="date" name="data" class="form-control"
                           value="<?= htmlspecialchars($transacao['data']) ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div id="camposRecorrencia" class="row g-3 mb-3" style="<?= $isRecorrencia ? '' : 'display: none;' ?>">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Data de Início</label>
                        <input type="date" name="data_inicio" class="form-control"
                               value="<?= htmlspecialchars($transacao['data_inicio'] ?? $transacao['data']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Data de Fim</label>
                        <input type="date" name="data_fim" class="form-control"
                               value="<?= htmlspecialchars($transacao['data_final'] ?? $transacao['data']) ?>">
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end pt-3">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Salvar alterações
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<script>
    const tipoSelect = document.getElementById('tipoSelect');
    const campoDataNormal = document.getElementById('campoDataNormal');
    const camposRecorrencia = document.getElementById('camposRecorrencia');

    tipoSelect.addEventListener('change', function() {
        if (this.value === 'recorrencia') {
            campoDataNormal.style.display = 'none';
            camposRecorrencia.style.display = '';
        } else {
            campoDataNormal.style.display = '';
            camposRecorrencia.style.display = 'none';
        }
    });
</script>

</body>
</html>