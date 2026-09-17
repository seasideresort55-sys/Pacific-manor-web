<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Google 登入｜太平洋莊園</title>
<style>
body{font:20px/1.65 system-ui,"Noto Sans TC",sans-serif;background:#F6F1E7;color:#1C3D4C;margin:0;padding:24px 14px}
main{max-width:460px;margin:4vh auto;background:#fff;border-radius:20px;padding:28px;box-shadow:0 10px 30px #1C3D4C14}
h1{font-size:28px;margin:8px 0 16px}
.notice{color:#5a6e78;font-size:16px}
#message{min-height:1.5em;margin:16px 0}
.err{color:#922e26}
label{display:block;margin:18px 0;font-size:18px}
button{font:inherit;min-height:48px;padding:12px 16px;border-radius:10px;border:1px solid #c5d0cc;background:#edf5f3;color:#1C3D4C}
#confirmBox{display:none;background:#eef6f4;padding:16px;border-radius:12px;margin:16px 0}
a{color:#2E5E73}
</style>
</head>
<body>
<main>
<p class="notice">PACIFIC MANOR</p>
<h1>用 Google 登入</h1>
<p>系統只用 Google 的帳號識別碼登入，不會用 Email 當會員編號。信箱只作輔助聯絡。</p>
<label><input id="consent" type="checkbox"> 我已閱讀<a href="pm_google_notice.php" target="_blank" rel="noopener">Google 登入及資料使用說明</a>。</label>
<div id="googleButton"></div>
<div id="confirmBox">
  <p id="bindPrompt">您已有會員帳號，是否綁定 Google？</p>
  <label><input id="confirmBind" type="checkbox"> 是，綁定到目前這個會員</label>
</div>
<p id="message" role="status"></p>
<button id="retry" type="button" hidden>重新準備登入</button>
<p><a href="pm_member_portal_v17.php#socialLogin">返回登入</a>　｜　<a href="pm_member_bind.php">管理綁定</a></p>
</main>
<script>
let loginData=null,busy=false,pendingCredential=null;
const msg=document.getElementById('message');
const retry=document.getElementById('retry');
async function request(data){
  const r=await fetch('pm_google_api.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  const j=await r.json();
  if(!r.ok||!j.ok){
    const err=new Error(j.error||'登入服務暫時無法使用');
    err.nextStep=j.next_step;err.payload=j;throw err;
  }
  return j;
}
async function prepare(){
  try{
    retry.hidden=true;msg.textContent='';
    loginData=await request({action:'prepare'});
    if(loginData.logged_in){
      document.getElementById('confirmBox').style.display='block';
      document.getElementById('bindPrompt').textContent=loginData.bind_prompt||'您已有會員帳號，是否綁定 Google？';
    }
    if(!window.google?.accounts?.id)throw new Error('Google 登入元件未載入，請檢查網路。');
    google.accounts.id.initialize({client_id:loginData.client_id,nonce:loginData.nonce,auto_select:false,callback:complete});
    document.getElementById('googleButton').replaceChildren();
    google.accounts.id.renderButton(document.getElementById('googleButton'),{type:'standard',theme:'outline',size:'large',text:'continue_with',locale:'zh_TW',width:Math.min(360,window.innerWidth-84)});
  }catch(e){msg.textContent=e.message;msg.className='err';retry.hidden=false;}
}
async function complete(response){
  if(busy||!loginData)return;
  if(!document.getElementById('consent').checked){msg.textContent='請先勾選同意資料使用說明。';msg.className='err';return;}
  pendingCredential=response.credential;
  if(loginData.logged_in && !document.getElementById('confirmBind').checked){
    msg.textContent='請先確認是否要把 Google 綁到目前的會員。系統不會自動合併。';
    msg.className='err';
    document.getElementById('confirmBox').style.display='block';
    return;
  }
  busy=true;msg.textContent='正在驗證會員身分…';msg.className='';
  try{
    await request({
      action: loginData.logged_in ? 'bind' : 'login',
      csrf: loginData.csrf,
      credential: response.credential,
      confirm_bind: loginData.logged_in && document.getElementById('confirmBind').checked ? 1 : 0
    });
    location.assign('pm_member_center_v17.php');
  }catch(e){
    if(e.nextStep==='confirm_bind'){
      document.getElementById('confirmBox').style.display='block';
      document.getElementById('bindPrompt').textContent=e.message;
      msg.textContent=e.message;msg.className='';
      loginData.logged_in=true;
    }else{
      await prepare();
      msg.textContent=e.message;msg.className='err';
    }
  }finally{busy=false;}
}
document.getElementById('confirmBind').addEventListener('change',()=>{
  if(pendingCredential && document.getElementById('confirmBind').checked) complete({credential:pendingCredential});
});
retry.onclick=prepare;
const script=document.createElement('script');
script.src='https://accounts.google.com/gsi/client';
script.async=true;
script.onload=prepare;
script.onerror=()=>{msg.textContent='Google 元件載入失敗，請檢查網路。';retry.hidden=false;};
document.head.appendChild(script);
</script>
</body>
</html>
