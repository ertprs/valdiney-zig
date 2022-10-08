<?php

namespace App\Models;

use System\Model\Model;
use App\Repositories\RelatorioVendasPorPeriodoRepository;

class FluxoCaixa extends Model
{
    protected $table = 'fluxo_caixa';
    protected $timestamps = true;
    protected $incluirVendasNoCaixa = true;
    protected $vendas;

    public function __construct()
    {
        $this->vendas = new RelatorioVendasPorPeriodoRepository();
        parent::__construct();
    }

    public function fluxoDeCaixa(array $periodo, $idEmpresa)
    {
        $de = $periodo['de'];
        $ate = $periodo['ate'];

        try {
            $query = $this->queryGetOne(
                "SELECT
                    (SELECT SUM(valor) FROM fluxo_caixa WHERE tipo_movimento = 1
                        AND DATE(created_at) BETWEEN '{$de}' AND '{$ate}') AS entradas,
                    (SELECT SUM(valor) FROM fluxo_caixa WHERE tipo_movimento = 0
                        AND DATE(created_at) BETWEEN '{$de}' AND '{$ate}') AS saidas,
                    (SELECT SUM(valor) FROM fluxo_caixa WHERE tipo_movimento = 1
                        AND DATE(created_at) BETWEEN '{$de}' AND '{$ate}') -
                    (SELECT SUM(valor) FROM fluxo_caixa WHERE tipo_movimento = 0
                AND DATE(created_at) BETWEEN '{$de}' AND '{$ate}') AS restante

                FROM fluxo_caixa WHERE id_empresa = {$idEmpresa}
                AND DATE(created_at) BETWEEN '{$de}' AND '{$ate}'
                GROUP BY DATE(created_at)"
            );

            # Vendas vindas do PDV
            $vendas = $this->vendas->totalDasVendas($periodo, false, $idEmpresa);

            # Tem venda realizada no período
            if ( ! is_null($vendas)) {
                # Nenhum valor no caixa para o perído
                if (isset($query->scalar)) {
                    # Seto novas propriedade para não ficar underfined
                    $query = (object) ['entradas' => 0, 'restante' => 0, 'saidas' => 0];
                }

                $query->entradas += $vendas;
                $query->restante = $query->entradas - $query->saidas;
                $query->entradasVendas = $vendas;
            }

            # Se não tiver nenhuma venda e nenhum caixa no período
            if (is_null($vendas) && isset($query->scalar)) {
                # Seto novas propriedade para não ficar underfined
                $query = (object) ['entradas' => 0, 'restante' => 0, 'saidas' => 0];
            }

        } catch (Exception $e) {
            dd($e->getMessage());
        }

        return $query;
    }

    public function fluxoDeCaixaDetalhadoPorMes(array $periodo, $idEmpresa)
    {
        $de = $periodo['de'];
        $ate = $periodo['ate'];

        $query = $this->query(
            "SELECT * FROM fluxo_caixa WHERE id_empresa = {$idEmpresa}
            AND DATE(created_at) BETWEEN '{$de}' AND '{$ate}'"
        );

        if (count($query) > 0) {
            $vendas = $this->vendas->totalDasVendas($periodo, false, $idEmpresa);
            if ($this->incluirVendasNoCaixa && ! is_null($vendas)) {
                $vendaDoPdv = new \StdClass;
                $vendaDoPdv->id = 0;
                $vendaDoPdv->id_empresa = 1;
                $vendaDoPdv->id_categoria = 1;
                $vendaDoPdv->descricao = 'Total vendas PDV';
                $vendaDoPdv->data = timestamp();
                $vendaDoPdv->valor = $vendas;
                $vendaDoPdv->tipo_movimento = 1;
                $vendaDoPdv->created_at = timestamp();
                $vendaDoPdv->updated_at = null;
                $vendaDoPdv->deleted_at = null;
                $vendaDoPdv->fromPDV = true;

                array_push($query, $vendaDoPdv);
            }
        }

        return $query;
    }
}
