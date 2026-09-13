<?php
declare (strict_types=1);

require_once __DIR__ . "/Categoria.php";

class Lancamento {
    private int $id;
    private string $descricao;
    private float $valor;
    private DateTime $data;
    private ?Categoria $categoria = null;
    private ?Recorrencia $recorrencia = null;
    private string $tipo;

    public function __construct(
        int $id, 
        string $descricao, 
        float $valor, 
        DateTime $data, 
        ?Categoria $categoria = null,
        ?Recorrencia $recorrencia = null,
        string $tipo = 'despesa'
    ) {
        $this->id = $id;
        $this->descricao = $descricao;
        $this->valor = $valor;
        $this->data = $data;
        $this->categoria = $categoria;
        $this->recorrencia = $recorrencia;
        $this->tipo = $tipo;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function getDescricao(): string {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): void {
        $this->descricao = $descricao;
    }

    public function getValor(): float {
        return $this->valor;
    }

    public function setValor(float $valor): void {
        $this->valor = $valor;
    }

    public function getData(): DateTime {
        return $this->data;
    }

    public function setData(DateTime $data): void {
        $this->data = $data;
    }

    public function getCategoria(): ?Categoria {
        return $this->categoria;
    }

    public function setCategoria(?Categoria $categoria): void {
        $this->categoria = $categoria;
    }

    public function getRecorrencia(): ?Recorrencia {
        return $this->recorrencia;
    }

    public function setRecorrencia(?Recorrencia $recorrencia): void {
        $this->recorrencia = $recorrencia;
    }

    public function getTipo(): string {
        return $this->tipo;
    }

    public function setTipo(string $tipo): void {
        $this->tipo = $tipo;
    }
}