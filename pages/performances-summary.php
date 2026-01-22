<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: login.php');
  exit;
}

require_once('../includes/db.php');
include('../includes/header.php');

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$q = $_GET['q'] ?? '';
$periode = $_GET['periode'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construction WHERE
$where = "1";
if ($q !== '') {
  $qSafe = mysqli_real_escape_string($conn, $q);
  $where .= " AND (u.nom LIKE '%$qSafe%' OR u.post_nom LIKE '%$qSafe%' OR u.email LIKE '%$qSafe%')";
}
if ($periode !== '') {
  $periodeSafe = mysqli_real_escape_string($conn, $periode);
  $where .= " AND o.periode = '$periodeSafe'";
}

// Total agents
$countSql = "SELECT COUNT(DISTINCT u.id) AS total FROM users u JOIN objectifs o ON o.user_id = u.id WHERE $where";
$countRes = mysqli_query($conn, $countSql);
$total = mysqli_fetch_assoc($countRes)['total'] ?? 0;
$totalPages = ceil($total / $perPage);

// Liste synthétique
$sql = "
  SELECT u.id, u.nom, u.post_nom, u.email, u.fonction,
    COUNT(o.id) AS total_objectifs,
    SUM(IF(o.statut='complet',1,0)) AS complet,
    SUM(IF(o.statut='encours',1,0)) AS encours,
    SUM(IF(o.statut='attente',1,0)) AS attente,
    MAX(o.periode) AS last_periode,
    MAX(o.updated_at) AS last_updated
  FROM users u
  JOIN objectifs o ON o.user_id = u.id
  WHERE $where
  GROUP BY u.id
  ORDER BY complet DESC, u.nom ASC
  LIMIT $offset, $perPage
";
$res = mysqli_query($conn, $sql);

// Périodes disponibles
$periodes = [];
$pq = mysqli_query($conn, "SELECT DISTINCT periode FROM objectifs ORDER BY periode DESC");
while ($row = mysqli_fetch_assoc($pq)) $periodes[] = $row['periode'];
?>

<div class="container mt-4">
  <h4>Synthèse des performances</h4>
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-5">
      <input type="search" name="q" class="form-control" placeholder="Rechercher nom ou email..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
      <select name="periode" class="form-select">
        <option value="">Toutes périodes</option>
        <?php foreach ($periodes as $p): ?>
          <option value="<?= e($p) ?>" <?= $p === $periode ? 'selected' : '' ?>><?= e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary" type="submit">Filtrer</button>
    </div>
    <div class="col-md-2 text-end">
      <a href="admin-fiches.php" class="btn btn-outline-secondary">Réinitialiser</a>
    </div>
  </form>

  <table class="table table-sm table-hover">
    <thead class="table-light">
      <tr>
        <th>Agent</th>
        <th>Période</th>
        <th>Total</th>
        <th>Complet</th>
        <th>En cours</th>
        <th>Attente</th>
        <th>Dernière modif</th>
      </tr>
    </thead>
    <tbody>
      <?php if (mysqli_num_rows($res) === 0): ?>
        <tr><td colspan="7" class="text-center text-muted">Aucun résultat</td></tr>
      <?php else: while ($u = mysqli_fetch_assoc($res)): ?>
        <tr>
          <td>
            <strong><?= e($u['nom'].' '.$u['post_nom']) ?></strong><br>
            <small class="text-muted"><?= e($u['email']) ?> · <?= e($u['fonction']) ?></small>
          </td>
          <td><?= e($u['last_periode'] ?: '—') ?></td>
          <td><?= (int)$u['total_objectifs'] ?></td>
          <td><span class="badge bg-success"><?= (int)$u['complet'] ?></span></td>
          <td><span class="badge bg-warning text-dark"><?= (int)$u['encours'] ?></span></td>
          <td><span class="badge bg-secondary"><?= (int)$u['attente'] ?></span></td>
          <td><?= $u['last_updated'] ? e(date('d/m/Y H:i', strtotime($u['last_updated']))) : '—' ?></td>
        </tr>
      <?php endwhile; endif; ?>
    </tbody>
  </table>

  <nav>
    <ul class="pagination pagination-sm">
      <?php for ($p=1; $p<=$totalPages; $p++): ?>
        <li class="page-item <?= $p==$page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(['q'=>$q,'periode'=>$periode,'page'=>$p]) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<?php include('../includes/footer.php'); ?>
