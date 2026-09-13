<?php

declare (strict_types=1);

require_once __DIR__ . "/Lancamento.php";

class Recorrencia extends Lancamento {
    private DateTime $dataInicio;
    private DateTime $dataFim;
    private bool $ativa = true;

    public function __construct(
        int $id, 
        string $descricao, 
        float $valor, 
        DateTime $dataInicio, 
        DateTime $dataFim,
        bool $ativa = true
    ) {
        parent::__construct($id, $descricao, $valor, $dataInicio, null, null, 'recorrencia');
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        $this->ativa = $ativa;
    }

    public function getDataInicio(): DateTime {
        return $this->dataInicio;
    }

    public function setDataInicio(DateTime $dataInicio): void {
        $this->dataInicio = $dataInicio;
    }

    public function getDataFim(): DateTime {
        return $this->dataFim;
    }

    public function setDataFim(DateTime $dataFim): void {
        $this->dataFim = $dataFim;
    }

    public function isAtiva(): bool {
        return $this->ativa;
    }

    public function setAtiva(bool $ativa): void {
        $this->ativa = $ativa;
    }

    public function getDiasTotais(): int {
        return ((int)$this->dataInicio->diff($this->dataFim)->days) + 1;
    }

    public function getValorTotal(): float {
        return $this->getValor() * $this->getDiasTotais();
    }
}