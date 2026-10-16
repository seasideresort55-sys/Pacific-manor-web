<?php
$report = $report ?? ['direct' => [], 'selling' => [], 'sourced' => [], 'shipping_twd' => 0];
$sum = static function (array $rows, string $key): int {
    $n = 0;
    foreach ($rows as $row) {
        $n += (int) $row[$key];
    }
    return $n;
};
?>
<h1>銷售分潤</h1>
<p class="lede">代賣成交時平台抽成是 0。售價的 70% 給種植的店，30% 給代賣的店。運費歸出貨的原店，不拆。</p>

<h2>本店自己賣出</h2>
<?php if (!$report['direct']): ?>
  <p class="note">還沒有。</p>
<?php else: ?>
  <div class="report"><table class="table">
    <tr><th>商品</th><th>數量</th><th>售價</th><th>本店</th></tr>
    <?php foreach ($report['direct'] as $row): ?>
      <tr>
        <td><?= h($row['name']) ?></td>
        <td><?= (int) $row['qty'] ?></td>
        <td><?= money((int) $row['line_twd']) ?></td>
        <td><?= money((int) $row['origin_share_twd']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="meta">合計 <?= money($sum($report['direct'], 'origin_share_twd')) ?></p>
<?php endif; ?>

<h2>代賣別店的商品</h2>
<?php if (!$report['selling']): ?>
  <p class="note">還沒有。</p>
<?php else: ?>
  <div class="report"><table class="table">
    <tr><th>商品</th><th>原店</th><th>數量</th><th>售價</th><th>本店 30%</th><th>原店 70%</th><th>平台</th></tr>
    <?php foreach ($report['selling'] as $row): ?>
      <tr>
        <td><?= h($row['name']) ?></td>
        <td><?= h($row['origin_name']) ?></td>
        <td><?= (int) $row['qty'] ?></td>
        <td><?= money((int) $row['line_twd']) ?></td>
        <td><?= money((int) $row['seller_share_twd']) ?></td>
        <td><?= money((int) $row['origin_share_twd']) ?></td>
        <td><?= money((int) $row['platform_share_twd']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="meta">本店 <?= money($sum($report['selling'], 'seller_share_twd')) ?>，原店 <?= money($sum($report['selling'], 'origin_share_twd')) ?>，平台 <?= money($sum($report['selling'], 'platform_share_twd')) ?></p>
<?php endif; ?>

<h2>別店代賣本店的商品</h2>
<?php if (!$report['sourced']): ?>
  <p class="note">還沒有。</p>
<?php else: ?>
  <div class="report"><table class="table">
    <tr><th>商品</th><th>代賣店</th><th>數量</th><th>售價</th><th>本店 70%</th><th>代賣店 30%</th><th>平台</th></tr>
    <?php foreach ($report['sourced'] as $row): ?>
      <tr>
        <td><?= h($row['name']) ?></td>
        <td><?= h($row['seller_name']) ?></td>
        <td><?= (int) $row['qty'] ?></td>
        <td><?= money((int) $row['line_twd']) ?></td>
        <td><?= money((int) $row['origin_share_twd']) ?></td>
        <td><?= money((int) $row['seller_share_twd']) ?></td>
        <td><?= money((int) $row['platform_share_twd']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="meta">本店 <?= money($sum($report['sourced'], 'origin_share_twd')) ?>，代賣店 <?= money($sum($report['sourced'], 'seller_share_twd')) ?>，平台 <?= money($sum($report['sourced'], 'platform_share_twd')) ?></p>
<?php endif; ?>

<h2>出貨運費</h2>
<p class="note">本店出貨收取的運費 <?= money((int) $report['shipping_twd']) ?>。運費不拆，平台不抽。</p>
