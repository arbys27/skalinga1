// assets/js/profile.js

document.addEventListener('DOMContentLoaded', () => {
  // Photo preview
  const photoInput = document.getElementById('photo-upload');
  const profilePhoto = document.getElementById('profile-photo');
  const miniAvatar = document.getElementById('mini-avatar');

  if (photoInput) {
    photoInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          profilePhoto.src = ev.target.result;
          if (miniAvatar) miniAvatar.src = ev.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Digital ID Modal
  const modal = document.getElementById('digitalIdModal');
  const btn = document.getElementById('viewDigitalIdBtn');
  const close = document.getElementById('closeModal');

  if (btn && modal) {
    btn.onclick = () => {
      modal.style.display = 'flex';
    };

    close.onclick = () => {
      modal.style.display = 'none';
    };

    window.onclick = (event) => {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    };
  }

  // Form submit (demo only — no real save yet)
  const form = document.getElementById('profileForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      alert('Profile updated! (This is a demo — no data is saved yet)');
    });
  }
});