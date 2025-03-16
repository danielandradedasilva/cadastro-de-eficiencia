<?php
// Função para calcular eficiência
    function calcularEficiencia($qtd_paineis, $inicio, $fim) {
        $inicio_time = strtotime($inicio);
        $fim_time = strtotime($fim);
        $horas_trabalhadas = ($fim_time - $inicio_time) / 3600;
        if ($horas_trabalhadas > 0) {
            return ($qtd_paineis / ($horas_trabalhadas * 73)) * 100;
        }
        return 0;
    }
?>