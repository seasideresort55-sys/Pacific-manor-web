/* Homepage / experience form: no silent 1960 birth-year default; consistent age copy. */
(function () {
  function emptyBirthYear(select) {
    if (!select || select.getAttribute("data-pm-year-fixed") === "1") return;
    var blank = document.createElement("option");
    blank.value = "";
    blank.textContent = "請選擇出生年";
    select.insertBefore(blank, select.firstChild);
    select.value = "";
    select.required = true;
    select.setAttribute("data-pm-year-fixed", "1");
    var hint = document.createElement("small");
    hint.textContent = "請親自選擇出生年，用於確認年齡資格，請勿略過。";
    hint.style.display = "block";
    hint.style.marginTop = "6px";
    select.insertAdjacentElement("afterend", hint);
  }

  function replaceAgeCopy(root) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    var node;
    while ((node = walker.nextNode())) {
      var text = node.nodeValue;
      if (!text) continue;
      if (text.indexOf("50歲以上") !== -1 && text.indexOf("60") === -1) {
        node.nodeValue = text.replace(
          /50歲以上[，、]?生活可自理[，、]?並有長住評估需求者，歡迎提出申請。/g,
          "了解與體驗申請以 50 歲以上、生活可自理為原則；長住入住原則年滿 60 歲。",
        );
      }
    }
  }

  function run() {
    document.querySelectorAll("select").forEach(function (select) {
      var selected = select.querySelector('option[value="1960"][selected], option[selected][value="1960"]');
      var has1960 = select.querySelector('option[value="1960"]');
      if (has1960 && (selected || select.value === "1960")) emptyBirthYear(select);
    });
    replaceAgeCopy(document.body);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run);
  else run();
})();
