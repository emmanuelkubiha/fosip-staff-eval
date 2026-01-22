// 🔐 Affiche ou masque un champ mot de passe avec icône Bootstrap
function togglePassword(fieldId, iconSpan) {
  const input = document.getElementById(fieldId);
  const icon = iconSpan.querySelector('i');

  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("bi-eye-fill");
    icon.classList.add("bi-eye-slash-fill");
  } else {
    input.type = "password";
    icon.classList.remove("bi-eye-slash-fill");
    icon.classList.add("bi-eye-fill");
  }
}

// ✅ Vérifie que les deux mots de passe sont identiques avant soumission (toast au lieu d'alert)
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function (e) {
      const pwd = document.getElementById('mot_de_passe');
      const confirmPwd = document.getElementById('mot_de_passe_confirm');
      if (pwd && confirmPwd && pwd.value !== confirmPwd.value) {
        e.preventDefault();
        globalShowToast('danger', 'Les mots de passe ne correspondent pas.');
      }
    });
  }
});

// 🔔 Ferme automatiquement les alertes Bootstrap après 5 secondes
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(alert => {
    try { bootstrap.Alert.getOrCreateInstance(alert).close(); } catch(e) {}
  });
}, 5000);

// 🖼️ Prévisualisation de la photo de profil
document.addEventListener('DOMContentLoaded', function () {
  const photoInput = document.querySelector('input[name="photo"]');
  const preview = document.getElementById('photo-preview');

  if (photoInput && preview) {
    photoInput.addEventListener('change', function () {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }
});


// 🧼 Masque le loader après chargement complet (un seul gestionnaire consolidé)
window.addEventListener('load', () => {
  const loader = document.getElementById('page-loader');
  if (loader) {
    loader.classList.add('fade-out');
    setTimeout(() => { loader.remove(); }, 650);
  }
  // Toast éventuel
  if (sessionStorage.getItem('logoutToast') === '1') {
    showLogoutToast();
    sessionStorage.removeItem('logoutToast');
  }
});

// Fonction pour afficher le message de déconnexion

function showLogoutToast() { globalShowToast('success','Déconnexion réussie'); }

document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('objectifChart');
  if (ctx && typeof objectifData !== 'undefined') {
    const labels = Object.keys(objectifData);
    const complet = labels.map(mois => objectifData[mois].complet);
    const encours = labels.map(mois => objectifData[mois].encours);
    const attente = labels.map(mois => objectifData[mois].attente);

  new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Complet',
            data: complet,
            backgroundColor: '#198754',
            stack: 'Statut'
          },
          {
            label: 'Encours',
            data: encours,
            backgroundColor: '#0d6efd',
            stack: 'Statut'
          },
          {
            label: 'Attente',
            data: attente,
            backgroundColor: '#ffc107',
            stack: 'Statut'
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'top' },
          title: { display: false }
        },
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true }
        }
      }
    });
  }
});

// ==============================
// TOAST GLOBAL UNIFIÉ
// ==============================
window.globalShowToast = function(type='info', message='', delay=4000) {
  var container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = 1080;
    document.body.appendChild(container);
  }
  const id = 'toast-' + Date.now() + '-' + Math.floor(Math.random()*1000);
  const map = { success:'bg-success text-white', danger:'bg-danger text-white', warning:'bg-warning text-dark', info:'bg-primary text-white' };
  const klass = map[type] || map.info;
  const div = document.createElement('div');
  div.className = 'toast ' + klass;
  div.id = id;
  div.setAttribute('role','alert');
  div.setAttribute('aria-live','assertive');
  div.setAttribute('aria-atomic','true');
  div.dataset.bsAutohide = 'true';
  div.dataset.bsDelay = String(delay);
  div.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  container.appendChild(div);
  const bsToast = new bootstrap.Toast(div); bsToast.show();
  div.addEventListener('hidden.bs.toast', ()=> div.remove());
};

// ==============================
// MODALE DE CONFIRMATION GÉNÉRIQUE
// ==============================
window.confirmDialog = function({title='Confirmation', message='', confirmText='Confirmer', cancelText='Annuler', confirmVariant='primary'} = {}) {
  return new Promise(resolve => {
    let modalEl = document.getElementById('globalConfirmModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'globalConfirmModal';
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">${title}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body"><div class="text-muted">${message}</div></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${cancelText}</button>
              <button type="button" id="globalConfirmBtn" class="btn btn-${confirmVariant}">${confirmText}</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modalEl);
    } else {
      modalEl.querySelector('.modal-title').textContent = title;
      modalEl.querySelector('.modal-body').innerHTML = `<div class="text-muted">${message}</div>`;
      const btn = modalEl.querySelector('#globalConfirmBtn');
      btn.textContent = confirmText;
      btn.className = `btn btn-${confirmVariant}`;
    }
    const btn = modalEl.querySelector('#globalConfirmBtn');
    const bs = bootstrap.Modal.getOrCreateInstance(modalEl);
    const cleanup = () => {
      btn.removeEventListener('click', onConfirm);
      modalEl.removeEventListener('hidden.bs.modal', onCancel);
    };
    const onConfirm = () => { cleanup(); bs.hide(); resolve(true); };
    const onCancel = () => { cleanup(); resolve(false); };
    btn.addEventListener('click', onConfirm);
    modalEl.addEventListener('hidden.bs.modal', onCancel, { once:true });
    bs.show();
  });
};

// ==============================
// CONFIRMATION LOGOUT (header lien logout)
// ==============================
document.addEventListener('DOMContentLoaded', () => {
  const logoutLink = document.querySelector('a[href="logout.php"], a[href="../pages/logout.php"]');
  if (logoutLink) {
    logoutLink.addEventListener('click', function(e){
      // Prévenir redirection immédiate
      e.preventDefault();
      const url = this.getAttribute('href');
      confirmDialog({
        title: 'Confirmation',
        message: "Êtes-vous sûr de vouloir vous déconnecter ?",
        confirmText: 'Se déconnecter',
        confirmVariant: 'danger'
      }).then(ok => {
        if (!ok) return;
        sessionStorage.setItem('logoutToast','1');
        window.location.href = url;
      });
    });
  }
});

// Sécurité supplémentaire : masquer le loader au bout de 4s si nécessaire
setTimeout(() => {
  const loader = document.getElementById('page-loader');
  if (loader) { loader.classList.add('fade-out'); setTimeout(()=> loader.remove(), 600); }
}, 4000);

// Toast pour message de déconnexion (compat)
if (sessionStorage.getItem('logoutToast') === '1') {
  sessionStorage.removeItem('logoutToast');
  setTimeout(() => { if (typeof globalShowToast==='function') globalShowToast('success','Vous avez été déconnecté avec succès',3000); }, 900);
}

// ===== Toggles UI: Dark mode & Sidebar compact =====
(function(){
  function applyPrefs(){
    const theme = localStorage.getItem('theme') || 'light';
    const compact = localStorage.getItem('sidebarCompact') === '1';
    document.body.classList.toggle('theme-dark', theme==='dark');
    document.body.classList.toggle('sidebar-compact', compact);
  }
  function toggleTheme(){
    const isDark = document.body.classList.toggle('theme-dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
  }
  function toggleCompact(){
    const isCompact = document.body.classList.toggle('sidebar-compact');
    localStorage.setItem('sidebarCompact', isCompact ? '1' : '0');
  }
  document.addEventListener('DOMContentLoaded', function(){
    applyPrefs();
    const btnsTheme = [document.getElementById('toggleDarkMode'), document.getElementById('toggleDarkModeDesktop'), document.getElementById('toggle-theme')].filter(Boolean);
    const btnsCompact = [document.getElementById('toggleCompactSidebar'), document.getElementById('toggleCompactSidebarDesktop'), document.getElementById('toggle-compact')].filter(Boolean);
    btnsTheme.forEach(b=> b.addEventListener('click', toggleTheme));
    btnsCompact.forEach(b=> b.addEventListener('click', toggleCompact));
  });
})();
