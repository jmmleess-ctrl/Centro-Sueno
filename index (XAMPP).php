<?php
$host = 'localhost';
$port = '3306';
$db   = 'centro_sueño_jorge'; 
$user = 'Jorge_admin'; 
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

$stmt_tablas = $pdo->query("SHOW TABLES");
$todas_las_tablas = $stmt_tablas->fetchAll(PDO::FETCH_COLUMN);

$excluir = ['mantiene', 'realiza', 'usa', 'supervisa'];
$tablas_filtradas = array_filter($todas_las_tablas, function($t) use ($excluir) {
    return !in_array(strtolower($t), $excluir);
});

$tabla_actual = $_GET['t'] ?? reset($tablas_filtradas);

$stmt_cols = $pdo->query("DESCRIBE `$tabla_actual`");
$columnas = $stmt_cols->fetchAll();
$pk = ''; 
foreach($columnas as $c) { if($c['Key'] == 'PRI') $pk = $c['Field']; }
if(!$pk) $pk = $columnas[0]['Field'];

if (isset($_POST['accion'])) {
    $campos = [];
    $params = [];
    $post_data = $_POST;
    unset($post_data['accion'], $post_data['pk_val']);

    foreach ($post_data as $key => $val) {
        $campos[] = "`$key`=?";
        $params[] = $val;
    }

    if ($_POST['accion'] == 'insertar') {
        $cols_names = implode(", ", array_map(function($k){ return "`$k`"; }, array_keys($post_data)));
        $placeholders = implode(", ", array_fill(0, count($params), "?"));
        $pdo->prepare("INSERT INTO `$tabla_actual` ($cols_names) VALUES ($placeholders)")->execute($params);
    } 
    elseif ($_POST['accion'] == 'actualizar') {
        $params[] = $_POST['pk_val'];
        $pdo->prepare("UPDATE `$tabla_actual` SET " . implode(", ", $campos) . " WHERE `$pk`=?")->execute($params);
    }
    header("Location: index.php?t=$tabla_actual");
    exit;
}

if (isset($_GET['borrar'])) {
    $pdo->prepare("DELETE FROM `$tabla_actual` WHERE `$pk`=?")->execute([$_GET['borrar']]);
    header("Location: index.php?t=$tabla_actual");
    exit;
}

$editar_datos = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM `$tabla_actual` WHERE `$pk`=?");
    $stmt->execute([$_GET['editar']]);
    $editar_datos = $stmt->fetch();
}

$datos = $pdo->query("SELECT * FROM `$tabla_actual` LIMIT 20")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consola Administrativa</title>
    <style>
        body { font-family: 'Segoe UI', Arial; background: #f4f7f6; margin: 0; padding: 20px; }
        .sidebar { background: #2c3e50; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .sidebar a { color: #fff; text-decoration: none; padding: 5px 10px; border: 1px solid #555; border-radius: 4px; font-size: 13px; background: #34495e; }
        .sidebar a:hover, .sidebar a.active { background: #1a73e8; border-color: #1a73e8; }
        .main-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; margin-top: 0; }
        .form-dinamico { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 20px; }
        .form-dinamico label { display: block; font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #555; }
        .form-dinamico input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn-container { grid-column: 1 / -1; text-align: right; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-add { background: #28a745; color: white; }
        .btn-upd { background: #ffc107; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f1f3f5; color: #333; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-size: 14px; }
        td { padding: 10px; border-bottom: 1px solid #eee; font-size: 13px; }
        .actions a { text-decoration: none; font-size: 12px; margin-right: 10px; padding: 3px 7px; border-radius: 3px; }
        .edit-link { color: #f39c12; border: 1px solid #f39c12; }
        .del-link { color: #d93025; border: 1px solid #d93025; }
    </style>
</head>
<body>

<div class="sidebar">
    <?php foreach($tablas_filtradas as $t): ?>
        <a href="?t=<?= $t ?>" class="<?= $tabla_actual == $t ? 'active' : '' ?>"><?= strtoupper($t) ?></a>
    <?php endforeach; ?>
</div>

<div class="main-card">
    <h2>Tabla: <?= strtoupper($tabla_actual) ?></h2>

    <form method="POST">
        <input type="hidden" name="accion" value="<?= $editar_datos ? 'actualizar' : 'insertar' ?>">
        <input type="hidden" name="pk_val" value="<?= $editar_datos[$pk] ?? '' ?>">
        
        <div class="form-dinamico">
            <?php foreach($columnas as $col): ?>
                <div>
                    <label><?= strtoupper($col['Field']) ?></label>
                    <input type="text" name="<?= $col['Field'] ?>" 
                           value="<?= $editar_datos[$col['Field']] ?? '' ?>"
                           <?= ($col['Key'] == 'PRI' && $editar_datos) ? 'readonly' : '' ?> required>
                </div>
            <?php endforeach; ?>
            <div class="btn-container">
                <button type="submit" class="btn <?= $editar_datos ? 'btn-upd' : 'btn-add' ?>">
                    <?= $editar_datos ? 'UPDATE RECORD' : 'INSERT RECORD' ?>
                </button>
                <?php if($editar_datos): ?>
                    <a href="?t=<?= $tabla_actual ?>" style="font-size:12px; margin-left:10px;">Cancelar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <?php if(count($datos) > 0): ?>
                    <?php foreach(array_keys($datos[0]) as $col): ?> <th><?= $col ?></th> <?php endforeach; ?>
                    <th>ACTIONS</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($datos as $d): ?>
                <tr>
                    <?php foreach($d as $v): ?> <td><?= htmlspecialchars($v) ?></td> <?php endforeach; ?>
                    <td class="actions">
                        <a href="?t=<?= $tabla_actual ?>&editar=<?= $d[$pk] ?>" class="edit-link">EDIT</a>
                        <a href="?t=<?= $tabla_actual ?>&borrar=<?= $d[$pk] ?>" class="del-link" onclick="return confirm('Confirm DELETE?')">DELETE</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
