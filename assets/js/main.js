// ============================================================
//  assets/js/main.js — DiemChuan.vn v2
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Auto dismiss alert ──────────────────────────────────
  document.querySelectorAll('.alert[role="alert"]').forEach(el => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el)?.close(), 4000);
  });

  // ── Dark mode ───────────────────────────────────────────
  const darkBtn = document.getElementById('darkBtn');
  const applyTheme = (t) => {
    document.documentElement.setAttribute('data-theme', t);
    if (darkBtn) darkBtn.innerHTML = t === 'dark'
      ? '<i class="bi bi-sun-fill"></i>'
      : '<i class="bi bi-moon"></i>';
  };
  applyTheme(localStorage.getItem('theme') || 'light');
  if (darkBtn) darkBtn.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', next);
    applyTheme(next);
  });

  // ── AJAX Autocomplete ───────────────────────────────────
  const inp  = document.getElementById('navSearch');
  const drop = document.getElementById('navDrop');
  if (inp && drop) {
    const API    = inp.dataset.apiurl;
    const uniUrl = inp.dataset.uniurl;
    const majUrl = inp.dataset.majurl;
    let timer;
    inp.addEventListener('input', () => {
      clearTimeout(timer);
      const q = inp.value.trim();
      if (q.length < 2) { drop.style.display = 'none'; return; }
      timer = setTimeout(() => {
        fetch(API + '?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(d => {
            drop.innerHTML = '';
            if (!d.universities?.length && !d.majors?.length) { drop.style.display = 'none'; return; }
            if (d.universities?.length) {
              drop.innerHTML += `<div class="drop-hd">🏛️ Trường đại học</div>`;
              d.universities.forEach(u => {
                drop.innerHTML += `<a class="drop-item" href="${uniUrl}?id=${u.id}">${u.name}
                  <small class="text-muted ms-1">${u.province}</small></a>`;
              });
            }
            if (d.majors?.length) {
              drop.innerHTML += `<div class="drop-hd mt-1">📚 Ngành học</div>`;
              d.majors.forEach(m => {
                drop.innerHTML += `<a class="drop-item" href="${majUrl}?id=${m.id}">${m.name}</a>`;
              });
            }
            drop.style.display = 'block';
          });
      }, 280);
    });
    document.addEventListener('click', e => { if (!inp.contains(e.target)) drop.style.display = 'none'; });
  }
});

// ── Confirm delete ───────────────────────────────────────────
function confirmDelete(url, name) {
  if (confirm(`Xóa "${name}"?\nKhông thể hoàn tác!`)) location.href = url;
}

// ── Logo preview ─────────────────────────────────────────────
function previewLogo(inp, imgId) {
  if (inp.files?.[0]) {
    const r = new FileReader();
    r.onload = e => { const img=document.getElementById(imgId); if(img){img.src=e.target.result;img.classList.remove('d-none');} };
    r.readAsDataURL(inp.files[0]);
  }
}

// ── Chart: Line ──────────────────────────────────────────────
function chartLine(id, labels, datasets) {
  const ctx = document.getElementById(id);
  if (!ctx) return null;
  return new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true,
      interaction: { mode:'index', intersect:false },
      plugins: { legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } },
      scales: { y:{ suggestedMin:15, suggestedMax:30, title:{ display:true, text:'Điểm chuẩn' } } }
    }
  });
}

// ── Chart: Bar ───────────────────────────────────────────────
function chartBar(id, labels, data, label='Điểm') {
  const ctx = document.getElementById(id);
  if (!ctx) return null;
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets:[{ label, data, backgroundColor:'rgba(26,86,219,.82)', borderRadius:6 }] },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:false,min:15}} }
  });
}

// ── Chart: Doughnut ──────────────────────────────────────────
function chartDoughnut(id, labels, data) {
  const ctx = document.getElementById(id);
  if (!ctx) return null;
  return new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets:[{ data, backgroundColor:['#1a56db','#10b981','#f59e0b','#8b5cf6','#ef4444','#64748b'], borderWidth:0 }] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } } }
  });
}
