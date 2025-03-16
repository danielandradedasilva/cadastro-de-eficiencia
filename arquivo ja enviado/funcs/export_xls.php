<?php
    // Exportar para Excel
    if (isset($_POST['exportar'])) {
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=eficiencia.xls");
        header("Cache-Control: max-age=0");
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // Corrige problemas de acentuação
        fputcsv($output, ["Nome", "Quantidade de Painéis", "Início", "Fim", "OF", "Eficiência (%)"], "\t");
        
        $dados = $pdo->query("SELECT * FROM eficiencia")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dados as $dado) {
            fputcsv($output, [
                $dado['nome'],
                $dado['qtd_paineis'],
                $dado['inicio'],
                $dado['fim'],
                $dado['of'],
                number_format($dado['eficiencia'], 2)
            ], "\t");
        }
        fclose($output);
        exit;
    }
?>