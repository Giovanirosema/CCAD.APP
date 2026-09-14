/* CCAD — comportements côté client (sans dépendance externe). */

/** Recompose les options d'un <select> commune quand le département change. */
function ccadWireDeptCommune(deptSelId, communeSelId, haitiMap) {
  var deptSel = document.getElementById(deptSelId);
  var communeSel = document.getElementById(communeSelId);
  if (!deptSel || !communeSel) return;
  function fill(keepValue) {
    var communes = haitiMap[deptSel.value] || [];
    var current = keepValue || communeSel.value;
    communeSel.innerHTML = '<option value="">Sélectionner</option>' +
      communes.map(function (c) { return '<option value="' + c.replace(/"/g, '&quot;') + '">' + c + '</option>'; }).join('');
    if (communes.indexOf(current) >= 0) communeSel.value = current;
  }
  deptSel.addEventListener('change', function () { fill(null); });
  fill(communeSel.getAttribute('data-selected'));
}

/** Ajoute/retire des lignes dans un tableau répétable (membres de famille, bénéficiaires…). */
function ccadWireRepeater(tableBodyId, addBtnId, rowTemplateHtml, maxRows) {
  var tbody = document.getElementById(tableBodyId);
  var addBtn = document.getElementById(addBtnId);
  if (!tbody || !addBtn) return;
  function countRows() { return tbody.querySelectorAll('tr').length; }
  function wireRemove(tr) {
    var btn = tr.querySelector('[data-remove-row]');
    if (btn) btn.addEventListener('click', function () {
      if (countRows() > 1) tr.remove();
    });
  }
  Array.prototype.forEach.call(tbody.querySelectorAll('tr'), wireRemove);
  addBtn.addEventListener('click', function () {
    if (maxRows && countRows() >= maxRows) { alert('Nombre maximal de lignes atteint (' + maxRows + ').'); return; }
    var tmp = document.createElement('tbody');
    tmp.innerHTML = rowTemplateHtml.replace(/__I__/g, String(Date.now()));
    var tr = tmp.firstElementChild;
    tbody.appendChild(tr);
    wireRemove(tr);
  });
}

/** Bascule un panneau (onglets simples sans rechargement, ex. Paramètres). */
function ccadWireTabs(tabsSelector, panelPrefix) {
  var tabs = document.querySelectorAll(tabsSelector);
  Array.prototype.forEach.call(tabs, function (tab) {
    tab.addEventListener('click', function (e) {
      e.preventDefault();
      var target = tab.getAttribute('data-tab');
      Array.prototype.forEach.call(tabs, function (t) { t.classList.remove('is-active'); });
      tab.classList.add('is-active');
      Array.prototype.forEach.call(document.querySelectorAll('[data-panel]'), function (p) {
        p.style.display = (p.getAttribute('data-panel') === target) ? '' : 'none';
      });
      var url = new URL(window.location);
      url.searchParams.set(panelPrefix, target);
      window.history.replaceState({}, '', url);
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  var toast = document.getElementById('flashToast');
  if (toast) setTimeout(function () { toast.style.display = 'none'; }, 5000);
});
