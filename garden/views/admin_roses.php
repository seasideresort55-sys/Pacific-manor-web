<h1>玫瑰庫</h1>
<p class="lede">匯入的品種預設不開放。開放之後，店家才能在「選苗上架」選到。庫存與狀態由平台填。</p>
<form method="post" action="/admin/settings" class="card pad" style="margin-bottom:16px">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <div class="filters">
    <label class="f">廠商價倍數 A（廠商價＝成本×A）
      <input type="text" name="multiplier_a" value="<?= h($settings['multiplier_a']) ?>">
    </label>
    <label class="f">建議售價除數（售價＝成本÷這個數，預設 0.7）
      <input type="text" name="retail_divisor" value="<?= h($settings['retail_divisor']) ?>">
    </label>
    <label class="f">取整
      <input type="text" value="到 10 元" disabled>
    </label>
    <button class="btn" type="submit">儲存倍數</button>
  </div>
</form>
<p class="meta">品種 <?= (int) $counts['varieties'] ?> · 已開放 <?= (int) $counts['listed'] ?> · 在售苗批 <?= (int) $counts['offers'] ?></p>
<form class="filters" method="get" action="/admin/roses">
  <label class="f">搜尋<input type="text" name="q" value="<?= h($q) ?>"></label>
  <label class="f">開放
    <select name="listed">
      <option value="">全部</option>
      <option value="1" <?= $listed === '1' ? 'selected' : '' ?>>已開放</option>
      <option value="0" <?= $listed === '0' ? 'selected' : '' ?>>未開放</option>
    </select>
  </label>
  <button class="btn" type="submit">找出來</button>
</form>
<table class="table">
  <thead>
    <tr><th>品種</th><th>株型</th><th>開放</th><th>苗批</th><th>狀態</th><th>平台庫存</th><th>成本</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= h($row['name_zh']) ?><div class="meta"><?= h($row['seedling']) ?></div></td>
      <td><?= h(category_name((string) $row['class_main'])) ?></td>
      <td><?= (int) $row['listed'] === 1 ? '已開放' : '未開放' ?></td>
      <td><?= h($row['supplier_sku']) ?></td>
      <td><?= h($row['offer_state']) ?></td>
      <td><?= (int) $row['platform_stock'] ?></td>
      <td><?= $row['cost_twd'] === null ? PENDING : money((int) $row['cost_twd']) ?></td>
      <td><a href="/admin/rose/<?= (int) $row['offer_id'] ?>">編輯</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
