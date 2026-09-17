<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>綁定登入方式｜太平洋莊園</title>
<style>
body{font:20px/1.65 system-ui,"Noto Sans TC",sans-serif;background:#F6F1E7;color:#1C3D4C;margin:0}
header{background:#1C3D4C;color:#fff;padding:16px 20px;font-weight:800}
main{max-width:640px;margin:0 auto;padding:20px}
.card{background:#fff;border-radius:18px;padding:22px;margin:14px 0;box-shadow:0 8px 24px #1C3D4C12}
h1{font-size:26px}h2{font-size:22px}
.pill{display:inline-block;background:#eef6f4;border-radius:999px;padding:4px 10px;font-size:15px;margin-right:6px}
label,input,button{font:inherit}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #c5d0cc;border-radius:10px}
button{min-height:48px;border:0;border-radius:10px;background:#2E5E73;color:#fff;padding:10px 16px;margin-top:10px}
.soft{background:#edf5f3;color:#1C3D4C}
#msg{min-height:1.4em}.err{color:#922e26}
a{color:#2E5E73}
.fine{font-size:16px;color:#5a6e78}
</style>
</head>
<body>
<header>太平洋莊園｜綁定登入方式</header>
<main>
<div class="card">
  <h1>同一個會員，多種登入</h1>
  <p>主編號是網站會員編號。Google／Apple 只負責登入；手機是主要聯絡；Email 只是輔助。</p>
  <p id="hello"></p>
  <p id="pills"></p>
  <p id="msg" role="status"></p>
</div>
<div class="card" id="guest">
  <p>請先登入，再綁定其他方式。系統不會在未確認時合併帳號。</p>
  <p><a href="pm_member_portal_v17.php#socialLogin">前往登入</a></p>
</div>
<div class="card" id="member" hidden>
  <h2>綁定 Google</h2>
  <p class="fine">若您已登入，完成 Google 驗證後會再問一次是否綁定。</p>
  <p><a href="pm_google_login.php"><button type="button">前往 Google 綁定</button></a></p>
  <h2>綁定 Apple</h2>
  <p class="fine">可用 Apple 登入；若開啟隱藏信箱，我們會以手機聯絡您。</p>
  <p><a href="pm_apple_login.php"><button type="button">前往 Apple 綁定</button></a></p>
  <h2>綁定手機（主要聯絡）</h2>
  <form id="phoneReq">
    <label for="phone">手機號碼</label>
    <input id="phone" name="phone" type="tel" inputmode="tel" maxlength="20" placeholder="例如 0912-345-678" required>
    <button type="submit">送出簡訊驗證碼</button>
  </form>
  <form id="phoneVer" hidden>
    <label for="code">6 位數驗證碼</label>
    <input id="code" name="code" inputmode="numeric" maxlength="6" required>
    <label class="fine"><input id="confirmBind" type="checkbox"> 是，把這個手機綁到目前的會員</label>
    <button type="submit">驗證並綁定</button>
    <button type="button" class="soft" id="backPhone">更改號碼</button>
  </form>
</div>
<p><a href="pm_member_center_v17.php">回會員中心</a></p>
</main>
<script>
const API='pm_member_identity_api.php';
const msg=document.getElementById('msg');
function say(t,err){msg.textContent=t||'';msg.className=err?'err':'';}
async function call(action,data={}){
  const boot=await fetch(API,{credentials:'same-origin',cache:'no-store'});
  const state=await boot.json();
  const body=new URLSearchParams({action,...data});
  const r=await fetch(API,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded','X-PM-CSRF':state.csrf_token||''},body});
  const j=await r.json();
  if(!r.ok||!j.ok) throw Object.assign(new Error(j.error||'操作沒有完成'),j);
  return j;
}
async function refresh(){
  const j=await fetch(API,{credentials:'same-origin',cache:'no-store'}).then(r=>r.json());
  const m=j.member;
  document.getElementById('guest').hidden=!!j.logged_in;
  document.getElementById('member').hidden=!j.logged_in;
  if(!m){document.getElementById('hello').textContent='尚未登入。';return;}
  document.getElementById('hello').textContent=(m.name||'會員')+'，會員編號 '+m.user_id;
  document.getElementById('pills').innerHTML=
    '<span class="pill">'+(m.phone_bound?'手機已綁':'尚未綁手機')+'</span>'+
    '<span class="pill">'+(m.google_bound?'Google 已綁':'尚未綁 Google')+'</span>'+
    '<span class="pill">'+(m.apple_bound?'Apple 已綁':'尚未綁 Apple')+'</span>'+
    '<span class="pill">主要聯絡：'+(m.contact_priority==='phone'?'手機':m.contact_priority==='email'?'Email':'登入帳號')+'</span>';
}
document.getElementById('phoneReq').onsubmit=async e=>{
  e.preventDefault();
  try{say('正在送出簡訊…');await call('request_phone_login_code',{phone:document.getElementById('phone').value});document.getElementById('phoneReq').hidden=true;document.getElementById('phoneVer').hidden=false;say('簡訊驗證碼已送出。');}
  catch(x){say(x.message,true);}
};
document.getElementById('phoneVer').onsubmit=async e=>{
  e.preventDefault();
  if(!document.getElementById('confirmBind').checked){say('請先確認是否綁定到目前的會員。系統不會自動合併。',true);return;}
  try{
    say('正在驗證…');
    await call('verify_phone_login_code',{phone:document.getElementById('phone').value,code:document.getElementById('code').value,confirm_bind:'1'});
    say('已綁定到同一個會員。');
    await refresh();
  }catch(x){say(x.message,true);}
};
document.getElementById('backPhone').onclick=()=>{document.getElementById('phoneVer').hidden=true;document.getElementById('phoneReq').hidden=false;say('');};
refresh().catch(e=>say(e.message,true));
</script>
</body>
</html>
