<?php
include_once './conx/conexao.php';
include_once './funcs/func_calc.php';
include_once './funcs/export_xls.php';

// Função para gerar token CSRF
function gerarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para verificar token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Iniciar sessão
session_start();

// Inicializar variáveis de pesquisa
$nomePesquisa = '';
$dataInicioPesquisa = '';
$dataFimPesquisa = '';

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    processarFormularioCadastro();
}

// Exclusão de registros
if (isset($_GET['excluir'])) {
    excluirRegistro();
}

// Pesquisa de registros
$dados = pesquisarRegistros();

// Calcular total de painéis por hora e número de registros
list($totalPaineis, $totalHoras, $numRegistros) = calcularTotais($dados);

// Capacidade total de 3 impressores é 219 painéis por hora
$metaPorHora = 73;
$mediaPaineisPorHora = $totalHoras > 0 ? ($totalPaineis / $totalHoras) : 0;
$mediaClass = $mediaPaineisPorHora >= $metaPorHora ? 'text-success' : 'text-danger';

function processarFormularioCadastro() {
    global $pdo;

    if (!verificarTokenCSRF($_POST['csrf_token'])) {
        redirecionarComMensagem("Token CSRF inválido.");
    }

    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $qtdPaineis = filter_input(INPUT_POST, 'qtd_paineis', FILTER_VALIDATE_INT);
    $inicio = filter_input(INPUT_POST, 'inicio', FILTER_SANITIZE_STRING);
    $fim = filter_input(INPUT_POST, 'fim', FILTER_SANITIZE_STRING);
    $of = filter_input(INPUT_POST, 'of', FILTER_SANITIZE_STRING);

    if ($nome && $qtdPaineis && $inicio && $fim && $of) {
        $eficiencia = calcularEficiencia($qtdPaineis, $inicio, $fim);

        $stmt = $pdo->prepare("INSERT INTO eficiencia (nome, qtd_paineis, inicio, fim, of, eficiencia) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$nome, $qtdPaineis, $inicio, $fim, $of, $eficiencia])) {
            redirecionarComMensagem("Cadastro realizado com sucesso!");
        } else {
            redirecionarComMensagem("Erro ao cadastrar.");
        }
    } else {
        redirecionarComMensagem("Preencha todos os campos.");
    }
}

function excluirRegistro() {
    global $pdo;

    $id = filter_input(INPUT_GET, 'excluir', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM eficiencia WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    }
}

function pesquisarRegistros() {
    global $pdo;

    $nomePesquisa = filter_input(INPUT_GET, 'nomePesquisa', FILTER_SANITIZE_STRING);
    $dataInicioPesquisa = filter_input(INPUT_GET, 'dataInicioPesquisa', FILTER_SANITIZE_STRING);
    $dataFimPesquisa = filter_input(INPUT_GET, 'dataFimPesquisa', FILTER_SANITIZE_STRING);
    $query = "SELECT * FROM eficiencia";
    $params = [];

    if ($nomePesquisa || $dataInicioPesquisa || $dataFimPesquisa) {
        $query .= " WHERE 1=1";
        if ($nomePesquisa) {
            $query .= " AND nome LIKE ?";
            $params[] = '%' . $nomePesquisa . '%';
        }
        if ($dataInicioPesquisa) {
            $query .= " AND DATE(inicio) >= ?";
            $params[] = $dataInicioPesquisa;
        }
        if ($dataFimPesquisa) {
            $query .= " AND DATE(fim) <= ?";
            $params[] = $dataFimPesquisa;
        }
    }

    $query .= " ORDER BY id DESC LIMIT 80";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calcularTotais($dados) {
    $totalPaineis = 0;
    $totalHoras = 0;
    $numRegistros = count($dados);

    foreach ($dados as $dado) {
        $inicio = new DateTime($dado['inicio']);
        $fim = new DateTime($dado['fim']);
        $intervalo = $inicio->diff($fim);
        $horas = $intervalo->h + ($intervalo->days * 24) + ($intervalo->i / 60);
        if ($horas > 0) {
            $totalPaineis += $dado['qtd_paineis'];
            $totalHoras += $horas;
        }
    }

    return [$totalPaineis, $totalHoras, $numRegistros];
}

function redirecionarComMensagem($mensagem) {
    header("Location: index.php?mensagem=" . urlencode($mensagem));
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Eficiência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .btn-orange { background-color: orange; color: white; }
        .btn-orange:hover { background-color: #FF6400; color: white; }
    </style>
    <script>
        setTimeout(() => {
            let msg = document.getElementById('mensagem');
            if (msg) {
                msg.style.display = 'none';
            }
        }, 5000);

        function confirmarExclusao(event) {
            if (!confirm('Você realmente deseja excluir este registro?')) {
                event.preventDefault();
            }
        }
    </script>
</head>
<body class="container mt-4">
    <h2>Cadastro de Eficiência</h2>
    <?php if (isset($_GET['mensagem'])): ?>
        <div id="mensagem" class="alert alert-info"><?= htmlspecialchars($_GET['mensagem']) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="mb-3">
        <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
        <div class="row">
            <div class="col-md-4">
                <label class="form-label">Colaborador:</label>
                <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantidade de Painéis:</label>
                <input type="number" name="qtd_paineis" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Início da Impressão:</label>
                <input type="datetime-local" name="inicio" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Fim da Impressão:</label>
                <input type="datetime-local" name="fim" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Número da OF:</label>
                <input type="text" name="of" class="form-control" required>
            </div>
        </div>
        <button type="submit" name="cadastrar" class="btn btn-orange mt-2 col-md-4 col-sm-12">Cadastrar</button>
    </form>

    <!-- Formulário de Pesquisa por colaborador -->
    <form method="GET" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <label class="form-label">Pesquisar por Colaborador:</label>
                <input type="text" name="nomePesquisa" class="form-control" value="<?= htmlspecialchars($nomePesquisa) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Início:</label>
                <input type="date" name="dataInicioPesquisa" class="form-control" value="<?= htmlspecialchars($dataInicioPesquisa) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Fim:</label>
                <input type="date" name="dataFimPesquisa" class="form-control" value="<?= htmlspecialchars($dataFimPesquisa) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary form-control">Pesquisar</button>
            </div>
        </div>
    </form>
    <br>
    <hr>
    <h3>Média de Painéis por Hora: <span class="<?= $mediaClass ?>"><?= number_format($mediaPaineisPorHora, 2) ?></span></h3>
    <hr>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Pns</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>OF</th>
                    <th>Pns Hora</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados as $dado): ?>
                    <tr>
                        <td><?= htmlspecialchars($dado['nome']) ?></td>
                        <td><?= htmlspecialchars($dado['qtd_paineis']) ?></td>
                        <td><?= (new DateTime($dado['inicio']))->format('d/m/Y H:i') ?></td>
                        <td><?= (new DateTime($dado['fim']))->format('d/m/Y H:i') ?></td>
                        <td><?= htmlspecialchars($dado['of']) ?></td>
                        <td>
                            <?php
                            $inicio = new DateTime($dado['inicio']);
                            $fim = new DateTime($dado['fim']);
                            $intervalo = $inicio->diff($fim);
                            $horas = $intervalo->h + ($intervalo->days * 24) + ($intervalo->i / 60);
                            if ($horas > 0) {
                                $paineisPorHora = $dado['qtd_paineis'] / $horas;
                                $paineisClass = $paineisPorHora >= 73 ? 'text-success' : 'text-danger';
                                echo "<span class='$paineisClass'>" . number_format($paineisPorHora, 2) . "</span>";
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><a href="?excluir=<?= htmlspecialchars($dado['id']) ?>" class="btn btn-danger btn-sm" onclick="confirmarExclusao(event)">Excluir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>