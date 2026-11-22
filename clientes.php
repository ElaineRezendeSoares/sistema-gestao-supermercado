<?php
session_start();

/*
 * Versão temporária sem banco de dados.
 * Os registros ficam armazenados na sessão do navegador/servidor e podem
 * desaparecer quando a sessão expirar ou for encerrada.
 */

if (!isset($_SESSION['clientes_demo'])) {
    $_SESSION['clientes_demo'] = [];
    $_SESSION['proximo_cliente_id'] = 1;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function formatarCpf($cpf) {
    $cpf = preg_replace('/\D+/', '', (string)$cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' .
           substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

$erro = '';
$sucesso = '';

if (isset($_SESSION['mensagem_sucesso'])) {
    $sucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'salvar') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $nome = trim($_POST['nome'] ?? '');
            $cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $endereco = trim($_POST['endereco'] ?? '');
            $status = ($_POST['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';

            if ($nome === '') {
                $erro = 'Informe o nome do cliente.';
            } elseif ($cpf !== '' && strlen($cpf) !== 11) {
                $erro = 'O CPF deve conter 11 números.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erro = 'Informe um endereço de e-mail válido.';
            } elseif ($id && !isset($_SESSION['clientes_demo'][$id])) {
                $erro = 'O cliente selecionado não foi encontrado.';
            } else {
                // Verifica duplicidade de CPF e e-mail na lista temporária.
                $duplicado = false;
                foreach ($_SESSION['clientes_demo'] as $clienteExistente) {
                    if ((int)$clienteExistente['id'] === (int)$id) continue;
                    if ($cpf !== '' && $clienteExistente['cpf'] === $cpf) $duplicado = true;
                    if ($email !== '' && strcasecmp($clienteExistente['email'], $email) === 0) $duplicado = true;
                }

                if ($duplicado) {
                    $erro = 'Já existe outro cliente com esse CPF ou e-mail.';
                } else {
                    if ($id) {
                        $clienteId = (int)$id;
                        $mensagem = 'Cliente atualizado com sucesso!';
                    } else {
                        $clienteId = $_SESSION['proximo_cliente_id']++;
                        $mensagem = 'Cliente cadastrado com sucesso!';
                    }

                    $_SESSION['clientes_demo'][$clienteId] = [
                        'id' => $clienteId,
                        'nome' => $nome,
                        'cpf' => $cpf,
                        'email' => $email,
                        'telefone' => $telefone,
                        'endereco' => $endereco,
                        'status' => $status
                    ];

                    $_SESSION['mensagem_sucesso'] = $mensagem;
                    header('Location: clientes.php');
                    exit;
                }
            }
        } elseif ($acao === 'excluir') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            if (!$id || !isset($_SESSION['clientes_demo'][$id])) {
                $erro = 'Cliente não encontrado.';
            } else {
                unset($_SESSION['clientes_demo'][$id]);
                $_SESSION['mensagem_sucesso'] = 'Cliente excluído com sucesso!';
                header('Location: clientes.php');
                exit;
            }
        } elseif ($acao === 'limpar_todos') {
            $_SESSION['clientes_demo'] = [];
            $_SESSION['proximo_cliente_id'] = 1;
            $_SESSION['mensagem_sucesso'] = 'Todos os registros temporários foram removidos.';
            header('Location: clientes.php');
            exit;
        }
    }
}

$clienteEdicao = null;
if (isset($_GET['editar'])) {
    $idEdicao = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
    if ($idEdicao && isset($_SESSION['clientes_demo'][$idEdicao])) {
        $clienteEdicao = $_SESSION['clientes_demo'][$idEdicao];
    }
}

$termo = trim($_GET['busca'] ?? '');
$clientes = array_values($_SESSION['clientes_demo']);

if ($termo !== '') {
    $termoBusca = mb_strtolower($termo, 'UTF-8');
    $clientes = array_filter($clientes, static function ($cliente) use ($termoBusca) {
        $campos = [$cliente['nome'], $cliente['cpf'], $cliente['email'], $cliente['telefone']];
        foreach ($campos as $campo) {
            if (mb_stripos((string)$campo, $termoBusca, 0, 'UTF-8') !== false) return true;
        }
        return false;
    });
}

usort($clientes, static function ($a, $b) {
    return strcasecmp($a['nome'], $b['nome']);
});

$totalClientes = count($_SESSION['clientes_demo']);
$totalAtivos = count(array_filter($_SESSION['clientes_demo'], static fn($c) => $c['status'] === 'ativo'));
$totalInativos = count(array_filter($_SESSION['clientes_demo'], static fn($c) => $c['status'] === 'inativo'));
$totalComEmail = count(array_filter($_SESSION['clientes_demo'], static fn($c) => $c['email'] !== ''));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Clientes | Sistema de Supermercado</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body id="page-top">
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="produtos.php">
            <div class="sidebar-brand-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="sidebar-brand-text mx-3">Sistema <sup>Supermercado</sup></div>
        </a>
        <hr class="sidebar-divider my-0">
        <li class="nav-item"><a class="nav-link" href="produtos.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Visão geral</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Gerenciamento</div>
        <li class="nav-item"><a class="nav-link" href="produtos.php"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
        <li class="nav-item active"><a class="nav-link" href="clientes.php"><i class="fas fa-fw fa-users"></i><span>Clientes</span></a></li>
        <hr class="sidebar-divider d-none d-md-block">
        <div class="text-center d-none d-md-inline"><button class="rounded-circle border-0" id="sidebarToggle"></button></div>
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                <h1 class="h6 mb-0 text-gray-800">Sistema de Controle do Supermercado</h1>
                <ul class="navbar-nav ml-auto"><li class="nav-item no-arrow"><span class="nav-link text-gray-600"><i class="fas fa-user-circle fa-fw"></i> Administrador</span></li></ul>
            </nav>

            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">Gerenciamento de Clientes</h1>
                        <p class="mb-0 text-muted">Cadastre, consulte e mantenha os dados dos clientes do supermercado.</p>
                    </div>
                    <span class="badge badge-warning mt-2 mt-sm-0"><i class="fas fa-flask mr-1"></i> Modo de demonstração</span>
                </div>

                <?php if ($erro): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= e($erro) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>
                <?php if ($sucesso): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= e($sucesso) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center">
                            <div class="col mr-2"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Clientes cadastrados</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalClientes ?></div></div>
                            <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                        </div></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center">
                            <div class="col mr-2"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Clientes ativos</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalAtivos ?></div></div>
                            <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                        </div></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center">
                            <div class="col mr-2"><div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Clientes inativos</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalInativos ?></div></div>
                            <div class="col-auto"><i class="fas fa-user-clock fa-2x text-gray-300"></i></div>
                        </div></div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center">
                            <div class="col mr-2"><div class="text-xs font-weight-bold text-info text-uppercase mb-1">Com e-mail cadastrado</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalComEmail ?></div></div>
                            <div class="col-auto"><i class="fas fa-envelope fa-2x text-gray-300"></i></div>
                        </div></div></div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-<?= $clienteEdicao ? 'user-edit' : 'user-plus' ?> mr-1"></i> <?= $clienteEdicao ? 'Editar cliente' : 'Cadastrar novo cliente' ?></h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="clientes.php" autocomplete="off">
                            <input type="hidden" name="acao" value="salvar">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="id" value="<?= e($clienteEdicao['id'] ?? '') ?>">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="nome">Nome completo</label>
                                    <input type="text" class="form-control" id="nome" name="nome" maxlength="150" required placeholder="Nome do cliente" value="<?= e($clienteEdicao['nome'] ?? '') ?>">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="cpf">CPF</label>
                                    <input type="text" class="form-control" id="cpf" name="cpf" maxlength="14" placeholder="000.000.000-00" value="<?= e(formatarCpf($clienteEdicao['cpf'] ?? '')) ?>">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="email">E-mail</label>
                                    <input type="email" class="form-control" id="email" name="email" maxlength="150" placeholder="cliente@email.com" value="<?= e($clienteEdicao['email'] ?? '') ?>">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="telefone">Telefone</label>
                                    <input type="text" class="form-control" id="telefone" name="telefone" maxlength="30" placeholder="(00) 00000-0000" value="<?= e($clienteEdicao['telefone'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-9">
                                    <label for="endereco">Endereço</label>
                                    <input type="text" class="form-control" id="endereco" name="endereco" maxlength="255" placeholder="Rua, número, bairro e cidade" value="<?= e($clienteEdicao['endereco'] ?? '') ?>">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="status">Situação</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="ativo" <?= ($clienteEdicao['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                        <option value="inativo" <?= ($clienteEdicao['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= $clienteEdicao ? 'Salvar alterações' : 'Cadastrar cliente' ?></button>
                            <?php if ($clienteEdicao): ?><a href="clientes.php" class="btn btn-light ml-2">Cancelar edição</a><?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">Clientes cadastrados</h6>
                        <form method="GET" action="clientes.php" class="form-inline">
                            <input type="search" name="busca" value="<?= e($termo) ?>" class="form-control form-control-sm mr-2 mb-2 mb-sm-0" placeholder="Nome, CPF, e-mail ou telefone">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i> Buscar</button>
                            <?php if ($termo !== ''): ?><a href="clientes.php" class="btn btn-light btn-sm ml-2">Limpar</a><?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead class="thead-light"><tr><th>Cliente</th><th>CPF</th><th>Contato</th><th>Situação</th><th class="text-center">Ações</th></tr></thead>
                                <tbody>
                                <?php if (!$clientes): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Nenhum cliente encontrado.</td></tr>
                                <?php else: foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?= e($cliente['nome']) ?></td>
                                        <td><?= $cliente['cpf'] !== '' ? e(formatarCpf($cliente['cpf'])) : '<span class="text-muted">Não informado</span>' ?></td>
                                        <td>
                                            <?php if ($cliente['email'] !== ''): ?><div><i class="fas fa-envelope text-gray-400 mr-1"></i><?= e($cliente['email']) ?></div><?php endif; ?>
                                            <?php if ($cliente['telefone'] !== ''): ?><div><i class="fas fa-phone text-gray-400 mr-1"></i><?= e($cliente['telefone']) ?></div><?php endif; ?>
                                            <?php if ($cliente['email'] === '' && $cliente['telefone'] === ''): ?><span class="text-muted">Não informado</span><?php endif; ?>
                                        </td>
                                        <td><?php if ($cliente['status'] === 'ativo'): ?><span class="badge badge-success">Ativo</span><?php else: ?><span class="badge badge-secondary">Inativo</span><?php endif; ?></td>
                                        <td class="text-center text-nowrap">
                                            <a href="clientes.php?editar=<?= (int)$cliente['id'] ?>" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
                                            <form method="POST" action="clientes.php" class="d-inline" onsubmit="return confirm('Tem certeza de que deseja excluir este cliente?');">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$cliente['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <small class="text-muted"><i class="fas fa-flask mr-1"></i> Dados temporários armazenados na sessão atual.</small>
                            <?php if ($totalClientes > 0): ?>
                            <form method="POST" action="clientes.php" class="mt-2 mt-md-0" onsubmit="return confirm('Deseja remover todos os clientes desta sessão?');">
                                <input type="hidden" name="acao" value="limpar_todos">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt mr-1"></i> Limpar demonstração</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>Sistema de Supermercado &copy; <?= date('Y') ?></span></div></div></footer>
    </div>
</div>
<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>
<script>
(function () {
    const cpf = document.getElementById('cpf');
    if (cpf) {
        cpf.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            this.value = v;
        });
    }
})();
</script>
</body>
</html>
