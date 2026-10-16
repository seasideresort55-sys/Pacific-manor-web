<?php $prices = offer_prices($offer, $offer); ?>
<h1><?= h($offer['name_zh']) ?></h1>
<p class="lede"><?= h($offer['name_orig']) ?> · <?= h(category_name((string) $offer['class_main'])) ?> · 苗型 <?= h($offer['seedling']) ?></p>
<form method="post" action="/admin/rose/<?= (int) $offer['offer_id'] ?>" class="card pad">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="f"><input type="checkbox" name="listed" value="1" <?= (int) $offer['listed'] === 1 ? 'checked' : '' ?>> 開放給店家選苗</label>
  <div class="filters">
    <label class="f">狀態
      <select name="offer_state">
        <?php foreach (['有貨','扦插預購','完售','停止'] as $st): ?>
          <option <?= $offer['offer_state'] === $st ? 'selected' : '' ?>><?= h($st) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="f">平台庫存（棵，選苗上架用）<input type="number" name="platform_stock" value="<?= (int) $offer['platform_stock'] ?>"></label>
    <label class="f">這品種的代賣可售（空白＝用全站 <?= h(setting('consign_stock_default')) ?>）
      <input type="number" name="consign_stock" min="0" value="<?= h((string) ($offer['consign_stock'] ?? '')) ?>">
    </label>
    <label class="f">成本<input type="number" name="cost_twd" value="<?= h((string) ($offer['cost_twd'] ?? '')) ?>"></label>
    <label class="f">預估出貨日<input type="text" name="eta" value="<?= h($offer['eta']) ?>"></label>
  </div>
  <label class="f"><input type="checkbox" name="auto_preorder" value="1" <?= (int) $offer['auto_preorder'] === 1 ? 'checked' : '' ?>> 庫存 0 時自動改成扦插預購</label>
  <div class="filters">
    <label class="f">這株的 A（空白＝用全站 <?= h(setting('multiplier_a')) ?>）
      <input type="text" name="multiplier_a" value="<?= h((string) ($offer['multiplier_a'] ?? '')) ?>">
    </label>
    <label class="f">這株的售價除數（空白＝用全站 <?= h(setting('retail_divisor')) ?>）
      <input type="text" name="retail_divisor" value="<?= h((string) ($offer['retail_divisor'] ?? '')) ?>">
    </label>
  </div>
  <p class="meta">
    廠商價 <?= $prices['vendor'] === null ? PENDING : money((int) $prices['vendor']) ?>
    · 建議售價 <?= $prices['retail'] === null ? PENDING : money((int) $prices['retail']) ?>
  </p>
  <p class="meta"><?= h($offer['stock_note']) ?></p>
  <p><button class="btn" type="submit">儲存</button></p>
</form>
<h2>欄位</h2>
<table class="specs">
  <?php
  $labels = [
    'breeder' => '育種者', 'breeder_country' => '國家', 'year' => '年份', 'color_group' => '花色',
    'fragrance' => '香味', 'flower_dia_cm' => '花徑公分', 'petals' => '花瓣數', 'form' => '花型',
    'bloom_habit' => '花期型', 'bloom_months' => '花期月份', 'height_cm' => '株高', 'width_cm' => '株寬',
    'heat_tw' => '耐熱分數', 'heat_flag' => '賣家標示耐熱', 'disease_bs' => '黑斑', 'disease_pm' => '白粉',
    'seedling_types' => '苗型',
  ];
  $sources = json_decode((string) ($offer['sources_json'] ?? '{}'), true) ?: [];
  foreach ($labels as $key => $label):
  ?>
    <tr>
      <th><?= h($label) ?></th>
      <td><?= h((string) ($offer[$key] ?? PENDING)) ?>
        <?php if (!empty($sources[$key]['src'])): ?><div class="meta">來源：<?= h($sources[$key]['src']) ?></div><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<p><?= h($offer['description']) ?></p>
