<h1><?= h($vendor['name']) ?></h1>
<p class="lede">選一株、設好運費，就可以賣。</p>
<div class="vendor-home">
  <a class="btn big block" href="<?= h(url('/vendor/pick')) ?>">選苗上架</a>
  <a class="btn big block" href="<?= h(url('/vendor/products')) ?>">我的商品</a>
  <a class="btn big block" href="<?= h(url('/vendor/consign')) ?>">可代賣商品</a>
  <a class="btn big block" href="<?= h(url('/vendor/orders')) ?>">收款確認</a>
  <a class="btn big block" href="<?= h(url('/vendor/bank')) ?>">收款帳號</a>
  <a class="btn big block" href="<?= h(url('/vendor/report')) ?>">銷售分潤</a>
  <a class="btn big block" href="<?= h(url('/vendor/shipping')) ?>">運費設定</a>
</div>
<p class="note">代賣別人的商品時，平台不抽成。售價的 70% 給原店，30% 給代賣的店。客人的購物說明不放這段。</p>
