<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Apple 登入｜太平洋莊園</title>
<style>
body{font:20px/1.65 system-ui,"Noto Sans TC",sans-serif;background:#F6F1E7;color:#1C3D4C;margin:0;padding:24px 14px}
main{max-width:460px;margin:4vh auto;background:#fff;border-radius:20px;padding:28px;box-shadow:0 10px 30px #1C3D4C14}
h1{font-size:28px}
.notice{color:#5a6e78;font-size:16px}
#message{min-height:1.5em;margin:16px 0}
.err{color:#922e26}
label{display:block;margin:18px 0;font-size:18px}
button.primary{font:inherit;min-height:52px;width:100%;border:0;border-radius:12px;background:#111;color:#fff}
a{color:#2E5E73}
</style>
</head>
<body>
<main>
<p class="notice">PACIFIC MANOR</p>
<h1>用 Apple 登入</h1>
<p>系統只用 Apple 的帳號識別碼（sub）登入。隱藏我的電子郵件可以使用，不會被擋下；信箱只作輔助，我們會優先用手機聯絡您。</p>
<label><input id="consent" type="checkbox"> 我已閱讀<a href="pm_apple_notice.php" target="_blank" rel="noopener">Apple 登入及資料使用說明</a>。</label>
<p id="message" role="status"></p>
<button class="primary" id="start" type="button">繼續使用 Apple</button>
<p><a href="pm_member_portal_v17.php#socialLogin">返回登入</a>　｜　<a href="pm_member_bind.php">管理綁定</a></p>
</main>
<script>
const msg=document.getElementById('message');
document.getElementById('start').onclick=async()=>{
  if(!document.getElementById('consent').checked){msg.textContent='請先勾選同意資料使用說明。';msg.className='err';return;}
  msg.textContent='正在前往 Apple…';msg.className='';
  try{
    const r=await fetch('pm_apple_api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'prepare'})});
    const j=await r.json();
    if(!r.ok||!j.ok)throw new Error(j.error||'Apple 登入尚未啟用');
    const url=new URL(j.url);
    if(url.protocol!=='https:'||url.hostname!=='appleid.apple.com')throw new Error('登入網址不正確');
    location.assign(url.href);
  }catch(e){msg.textContent=e.message;msg.className='err';}
};
</script>
</body>
</html>
