<?php
session_start();

require_once "conexao.php";

$stmt = $pdo->query("
    SELECT l.*, r.data_inicio, r.data_final 
    FROM lancamentos l 
    LEFT JOIN recorrencias r ON l.id_recorrencias = r.id 
    ORDER BY l.data DESC, l.id DESC
");
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$saldo = (float)($saldoStmt->fetch(PDO::FETCH_ASSOC)['saldo'] ?? 0);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyPocket - Controle Financeiro</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h2 mb-0 text-primary">
            <i class="bi bi-wallet2 me-2"></i>MyPocket
        </h1>
        <a href="planilha.php" class="btn btn-outline-success shadow-sm">
            <i class="bi bi-table me-1"></i> Planilha Mensal
        </a>
    </div>

    <div class="alert alert-primary shadow-sm d-flex justify-content-between align-items-center">
        <div>
            <span class="fs-6 text-uppercase text-secondary fw-semibold d-block">Saldo Atual</span>
            <span class="fs-3 fw-bold <?= $saldo >= 0 ? 'text-success' : 'text-danger' ?>">
                R$ <?= number_format($saldo, 2, ',', '.') ?>
            </span>
        </div>
        <i class="bi bi-cash-coin fs-1 text-primary opacity-50"></i>
    </div>

    <?php if(isset($_SESSION['mensagem'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['mensagem']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <!-- CARD COM ABAS PARA OS FORMULÁRIOS -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-bottom-0 pt-3 px-3">
            <ul class="nav nav-tabs card-header-tabs" id="tipoLancamentoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="normal-tab" data-bs-toggle="tab" data-bs-target="#tab-normal" type="button" role="tab" aria-controls="tab-normal" aria-selected="true">
                        <i class="bi bi-receipt me-1 text-primary"></i> Lançamento Normal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="recorrencia-tab" data-bs-toggle="tab" data-bs-target="#tab-recorrencia" type="button" role="tab" aria-controls="tab-recorrencia" aria-selected="false">
                        <i class="bi bi-arrow-repeat me-1 text-danger"></i> Despesa Recorrente (Diária)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4 bg-white rounded-bottom">
            <div class="tab-content" id="tipoLancamentoTabsContent">

                <!-- ABA 1: FORMULÁRIO DE LANÇAMENTO NORMAL -->
                <div class="tab-pane fade show active" id="tab-normal" role="tabpanel" aria-labelledby="normal-tab">
                    <form action="processa.php" method="POST">
                        <input type="hidden" name="form_tipo" value="normal">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="descricao_normal" class="form-label fw-medium">Descrição</label>
                                <input type="text" id="descricao_normal" name="descricao" class="form-control" placeholder="Ex: Salário, Almoço..." required>
                            </div>

                            <div class="col-md-3">
                                <label for="valor_normal" class="form-label fw-medium">Valor (R$)</label>
                                <input type="number" id="valor_normal" step="0.01" min="0.01" name="valor" class="form-control" placeholder="0,00" required>
                            </div>

                            <div class="col-md-3">
                                <label for="data_normal" class="form-label fw-medium">Data</label>
                                <input type="date" id="data_normal" name="data" class="form-control"
                                       value="<?= date('Y-m-d') ?>"
                                       max="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="tipo_normal" class="form-label fw-medium">Tipo de Lançamento</label>
                                <select id="tipo_normal" name="tipo" class="form-select" required>
                                    <option value="receita">Receita (Entrada)</option>
                                    <option value="despesa">Despesa (Saída)</option>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100 py-2">
                                    <i class="bi bi-check2-circle me-1"></i> Salvar Lançamento
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ABA 2: FORMULÁRIO DE RECORRÊNCIA (APENAS PARA DESPESAS DIÁRIAS) -->
                <div class="tab-pane fade" id="tab-recorrencia" role="tabpanel" aria-labelledby="recorrencia-tab">
                    <div class="alert alert-warning py-2 mb-3 d-flex align-items-center border-0 shadow-sm">
                        <i class="bi bi-calendar-event-fill me-2 fs-5 text-warning"></i>
                        <div>
                            <strong>Recorrência Diária:</strong> Exclusiva para <strong>despesas diárias</strong> (ex: transporte, alimentação diária, café). A despesa é lançada diariamente no intervalo entre a data de início e a data de fim.
                        </div>
                    </div>

                    <form action="processaRec.php" method="POST">
                        <input type="hidden" name="form_tipo" value="recorrencia">
                        <input type="hidden" name="tipo" value="despesa">
                        <input type="hidden" name="frequencia" value="diaria">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="descricao_rec" class="form-label fw-medium">Descrição da Despesa Diária</label>
                                <input type="text" id="descricao_rec" name="descricao" class="form-control" placeholder="Ex: Transporte / Passagem, Café da manhã, Almoço..." required>
                            </div>

                            <div class="col-md-3">
                                <label for="valor_rec" class="form-label fw-medium">Valor por Dia (R$)</label>
                                <input type="number" id="valor_rec" step="0.01" min="0.01" name="valor" class="form-control" placeholder="0,00" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-medium">Tipo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-danger-subtle text-danger fw-bold border-danger-subtle w-100">
                                        <i class="bi bi-lock-fill me-1"></i> Despesa (Exclusivo)
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="data_inicio" class="form-label fw-medium">Data de Início</label>
                                <input type="date" id="data_inicio" name="data_inicio" class="form-control"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-md-3">
                                <label for="data_fim" class="form-label fw-medium">Data de Fim</label>
                                <input type="date" id="data_fim" name="data_fim" class="form-control"
                                       value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-medium">Periodicidade</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary fw-semibold border w-100">
                                        <i class="bi bi-clock-history me-1"></i> Diária (Todo dia)
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-danger px-4 py-2">
                                    <i class="bi bi-arrow-repeat me-1"></i> Salvar Despesa Diária
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0 text-secondary">
                <i class="bi bi-list-ul me-2"></i>Extrato de Transações
            </h2>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach($lancamentos as $l): ?>
                    <?php
                    $isReceita = ($l['tipo'] === 'receita');
                    $isRecorrencia = ($l['tipo'] === 'recorrencia');

                    if ($isReceita) {
                        $badgeClass = 'bg-success';
                        $tipoLabel = 'Receita';
                        $icon = 'bi-arrow-up-circle';
                    } elseif ($isRecorrencia) {
                        $badgeClass = 'bg-warning text-dark';
                        $tipoLabel = 'Recorrente (Diária)';
                        $icon = 'bi-arrow-repeat';
                    } else {
                        $badgeClass = 'bg-danger';
                        $tipoLabel = 'Despesa';
                        $icon = 'bi-arrow-down-circle';
                    }

                    $dataFormatada = date('d/m/Y', strtotime($l['data']));
                    ?>

                    <li class="list-group-item p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <span class="badge <?= $badgeClass ?> me-3 p-2">
                                <i class="bi <?= $icon ?>"></i> <?= $tipoLabel ?>
                            </span>
                            <div>
                                <span class="fw-semibold d-block text-dark"><?= htmlspecialchars($l['descricao']) ?></span>
                                <small class="text-muted">
                                    <?php if ($isRecorrencia && !empty($l['data_inicio']) && !empty($l['data_final'])): ?>
                                        <i class="bi bi-calendar-range me-1"></i>
                                        <?= date('d/m/Y', strtotime($l['data_inicio'])) ?> a <?= date('d/m/Y', strtotime($l['data_final'])) ?>
                                    <?php else: ?>
                                        <i class="bi bi-calendar3 me-1"></i><?= $dataFormatada ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold <?= $isReceita ? 'text-success' : ($isRecorrencia ? 'text-warning-emphasis' : 'text-danger') ?>">
                                <?= $isReceita ? '+ ' : '- ' ?>R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                                <?php if ($isRecorrencia): ?>
                                    <small class="text-muted fw-normal">/ dia</small>
                                <?php endif; ?>
                            </span>
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?= $l['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="delete.php?id=<?= $l['id'] ?>" class="btn btn-outline-danger" title="Excluir"
                                   onclick="return confirm('Tem certeza que deseja excluir esta transação?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>

                <?php if (empty($lancamentos)): ?>
                    <li class="list-group-item p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                        Nenhuma transação cadastrada até o momento.
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>