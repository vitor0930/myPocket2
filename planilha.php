<?php
session_start();
require_once "conexao.php";

$mesAtual = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

$partes = explode('-', $mesAtual);
$ano = (int)$partes[0];
$mes = (int)$partes[1];

$diasNoMes = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));
$primeiroDia = "$ano-" . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "-01";
$ultimoDia   = "$ano-" . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "-" . str_pad((string)$diasNoMes, 2, '0', STR_PAD_LEFT);

$stmtNormais = $pdo->prepare("
    SELECT data, tipo, valor 
    FROM lancamentos 
    WHERE tipo IN ('receita','despesa') 
      AND data BETWEEN :inicio AND :fim
    ORDER BY data
");
$stmtNormais->execute([':inicio' => $primeiroDia, ':fim' => $ultimoDia]);
$lancamentosNormais = $stmtNormais->fetchAll(PDO::FETCH_ASSOC);

$stmtRec = $pdo->prepare("
    SELECT l.valor, r.data_inicio, r.data_final
    FROM lancamentos l
    INNER JOIN recorrencias r ON l.id_recorrencias = r.id
    WHERE l.tipo = 'recorrencia'
      AND r.data_inicio <= :fim
      AND r.data_final >= :inicio
");
$stmtRec->execute([':inicio' => $primeiroDia, ':fim' => $ultimoDia]);
$recorrencias = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

$stmtSaldoAnterior = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN l.tipo = 'receita' THEN l.valor ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN l.tipo = 'despesa' THEN l.valor ELSE 0 END), 0) AS saldo
    FROM lancamentos l
    WHERE l.tipo IN ('receita','despesa')
      AND l.data < :inicio
");
$stmtSaldoAnterior->execute([':inicio' => $primeiroDia]);
$saldoAnterior = (float)($stmtSaldoAnterior->fetch(PDO::FETCH_ASSOC)['saldo'] ?? 0);

$stmtRecAnterior = $pdo->prepare("
    SELECT l.valor, r.data_inicio, r.data_final
    FROM lancamentos l
    INNER JOIN recorrencias r ON l.id_recorrencias = r.id
    WHERE l.tipo = 'recorrencia'
      AND r.data_inicio < :inicio
");
$stmtRecAnterior->execute([':inicio' => $primeiroDia]);
$recAnterior = $stmtRecAnterior->fetchAll(PDO::FETCH_ASSOC);

foreach ($recAnterior as $rec) {
    $recInicio = $rec['data_inicio'];
    $recFim = min($rec['data_final'], date('Y-m-d', strtotime($primeiroDia . ' -1 day')));
    if ($recFim >= $recInicio) {
        $dias = (int)(new DateTime($recInicio))->diff(new DateTime($recFim))->days + 1;
        $saldoAnterior -= (float)$rec['valor'] * $dias;
    }
}

$diasMap = [];
for ($d = 1; $d <= $diasNoMes; $d++) {
    $dataStr = "$ano-" . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . "-" . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    $diasMap[$dataStr] = ['entrada' => 0.0, 'saida' => 0.0];
}

foreach ($lancamentosNormais as $l) {
    if ($l['tipo'] === 'receita') {
        $diasMap[$l['data']]['entrada'] += (float)$l['valor'];
    } else {
        $diasMap[$l['data']]['saida'] += (float)$l['valor'];
    }
}

foreach ($recorrencias as $rec) {
    $recInicio = max($rec['data_inicio'], $primeiroDia);
    $recFim    = min($rec['data_final'], $ultimoDia);
    $cur = new DateTime($recInicio);
    $end = new DateTime($recFim);
    while ($cur <= $end) {
        $key = $cur->format('Y-m-d');
        if (isset($diasMap[$key])) {
            $diasMap[$key]['saida'] += (float)$rec['valor'];
        }
        $cur->modify('+1 day');
    }
}

$stmtMeses = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(data, '%Y-%m') as mes 
    FROM lancamentos 
    ORDER BY mes DESC
");
$mesesDisponiveis = $stmtMeses->fetchAll(PDO::FETCH_COLUMN);

if (!in_array(date('Y-m'), $mesesDisponiveis)) {
    array_unshift($mesesDisponiveis, date('Y-m'));
}

$nomeMeses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyPocket - Planilha Mensal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .planilha-table {
            font-size: 0.9rem;
        }
        .planilha-table th {
            background-color: #1a3a5c;
            color: #ffffff;
            text-align: center;
            padding: 10px 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .planilha-table td {
            text-align: right;
            padding: 7px 12px;
            vertical-align: middle;
            border-color: #dee2e6;
        }
        .planilha-table td:first-child {
            text-align: center;
            font-weight: 500;
            background-color: #f0f4f8;
            color: #1a3a5c;
        }
        .planilha-table tbody tr:hover {
            background-color: #e8f0fe !important;
        }
        .planilha-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .planilha-table .row-total {
            background-color: #1a3a5c !important;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .planilha-table .row-total td {
            background-color: #1a3a5c !important;
            color: #ffffff;
            border-color: #1a3a5c;
        }
        .planilha-table .row-total td:first-child {
            background-color: #1a3a5c !important;
            color: #ffffff;
        }
        .valor-positivo { color: #198754; }
        .valor-negativo { color: #dc3545; }
        .valor-zero { color: #6c757d; }
        .saldo-header {
            font-size: 1.1rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0 text-primary">
            <i class="bi bi-table me-2"></i>Planilha Mensal
        </h1>
        <a href="index.php" class="btn btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <!-- Seletor de Mês -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="planilha.php" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label for="selectMes" class="form-label fw-medium mb-1">
                        <i class="bi bi-calendar-month me-1"></i>Selecionar Mês
                    </label>
                    <select name="mes" id="selectMes" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($mesesDisponiveis as $m): ?>
                            <?php
                            $mParts = explode('-', $m);
                            $mLabel = $nomeMeses[(int)$mParts[1]] . ' / ' . $mParts[0];
                            ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $m === $mesAtual ? 'selected' : '' ?>>
                                <?= $mLabel ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Visualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela Planilha -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0 text-secondary">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i><?= $nomeMeses[$mes] ?> / <?= $ano ?>
            </h2>
            <span class="saldo-header">
                Saldo anterior: 
                <strong class="<?= $saldoAnterior >= 0 ? 'valor-positivo' : 'valor-negativo' ?>">
                    R$ <?= number_format($saldoAnterior, 2, ',', '.') ?>
                </strong>
            </span>
        </div>
        <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
            <table class="table table-bordered planilha-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 15%;">Data</th>
                        <th style="width: 20%;">Entrada</th>
                        <th style="width: 20%;">Saída</th>
                        <th style="width: 20%;">Diário</th>
                        <th style="width: 25%;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $saldoAcum = $saldoAnterior;
                    $totalEntrada = 0.0;
                    $totalSaida   = 0.0;

                    foreach ($diasMap as $dataStr => $valores):
                        $entrada = $valores['entrada'];
                        $saida   = $valores['saida'];
                        $diario  = $entrada - $saida;
                        $saldoAcum += $diario;

                        $totalEntrada += $entrada;
                        $totalSaida   += $saida;

                        $diaNum = (int)date('d', strtotime($dataStr));
                        $diaSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                        $ds = $diaSemana[(int)date('w', strtotime($dataStr))];

                        $classDiario = $diario > 0 ? 'valor-positivo' : ($diario < 0 ? 'valor-negativo' : 'valor-zero');
                        $classSaldo  = $saldoAcum > 0 ? 'valor-positivo' : ($saldoAcum < 0 ? 'valor-negativo' : 'valor-zero');
                    ?>
                    <tr>
                        <td><?= str_pad((string)$diaNum, 2, '0', STR_PAD_LEFT) ?>/<?= str_pad((string)$mes, 2, '0', STR_PAD_LEFT) ?> (<?= $ds ?>)</td>
                        <td class="<?= $entrada > 0 ? 'valor-positivo' : 'valor-zero' ?>">
                            <?= $entrada > 0 ? 'R$ ' . number_format($entrada, 2, ',', '.') : '-' ?>
                        </td>
                        <td class="<?= $saida > 0 ? 'valor-negativo' : 'valor-zero' ?>">
                            <?= $saida > 0 ? 'R$ ' . number_format($saida, 2, ',', '.') : '-' ?>
                        </td>
                        <td class="<?= $classDiario ?>">
                            <?php if ($diario != 0): ?>
                                <?= $diario > 0 ? '+' : '' ?>R$ <?= number_format($diario, 2, ',', '.') ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold <?= $classSaldo ?>">
                            R$ <?= number_format($saldoAcum, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php
                    $totalDiario = $totalEntrada - $totalSaida;
                    ?>
                    <tr class="row-total">
                        <td style="text-align: center;">
                            <i class="bi bi-calculator me-1"></i>TOTAL MENSAL
                        </td>
                        <td>R$ <?= number_format($totalEntrada, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($totalSaida, 2, ',', '.') ?></td>
                        <td><?= $totalDiario >= 0 ? '+' : '' ?>R$ <?= number_format($totalDiario, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($saldoAcum, 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
